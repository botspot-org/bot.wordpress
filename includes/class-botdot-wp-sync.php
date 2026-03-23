<?php
/**
 * Content sync class for push-based ingestion
 *
 * Handles pushing content to locus-core ingest endpoint on publish/update/delete.
 *
 * @link       https://bot.spot
 * @since      1.0.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * Content sync class for push-based ingestion.
 *
 * Hooks into save_post, transition_post_status, and before_delete_post
 * to push content to locus-core via the IngestPayload API.
 *
 * @since      1.0.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Sync
{
    /**
     * Register REST API webhook endpoint
     *
     * @since    2.1.0
     */
    public static function register_webhook_route()
    {
        register_rest_route("botdot-wp/v1", "/webhook", [
            "methods" => "POST",
            "callback" => [__CLASS__, "handle_webhook"],
            "permission_callback" => "__return_true", // Auth via HMAC
        ]);
    }

    /**
     * Handle incoming webhook from locus-core
     *
     * Verifies HMAC signature, resolves WordPress post, and updates enrichment meta.
     *
     * @since    2.1.0
     * @param    WP_REST_Request    $request    The REST request object.
     * @return   WP_REST_Response
     */
    public static function handle_webhook($request)
    {
        $signature = $request->get_header("X-Webhook-Signature");
        $raw_body = $request->get_body();
        $secret = BotDot_WP_Options::get("webhook_secret");

        if (!$secret || !$signature) {
            return new WP_REST_Response(["error" => "Unauthorized"], 401);
        }

        $expected = "sha256=" . hash_hmac("sha256", $raw_body, $secret);
        if (!hash_equals($expected, $signature)) {
            return new WP_REST_Response(["error" => "Invalid signature"], 401);
        }

        $payload = json_decode($raw_body, true);
        $event = $payload["event"] ?? "";
        $data = $payload["data"] ?? [];
        $content_id = $payload["content_id"] ?? "";

        // Resolve WordPress post from source_id (URL)
        $source_id = $data["source_id"] ?? $data["url"] ?? "";
        $post_id = $source_id ? url_to_postid($source_id) : 0;

        // Fallback: lookup by artifact_id stored in post_meta
        if (!$post_id && $content_id) {
            $posts = get_posts([
                "meta_key" => "_botdot_artifact_id",
                "meta_value" => $content_id,
                "post_type" => "any",
                "numberposts" => 1,
                "fields" => "ids",
            ]);
            $post_id = $posts[0] ?? 0;
        }

        if (!$post_id) {
            return new WP_REST_Response(["status" => "ok", "matched" => false], 200);
        }

        // Update enrichment meta (idempotent, no downgrades)
        $tier_order = ["NONE" => 0, "TIER0" => 1, "TIER1" => 2, "TIER2" => 3];
        $new_tier = $data["enrichment_tier"] ?? "TIER0";
        $current_tier = get_post_meta($post_id, "_botdot_enrichment_tier", true) ?: "NONE";

        if (($tier_order[$new_tier] ?? 0) >= ($tier_order[$current_tier] ?? 0)) {
            update_post_meta($post_id, "_botdot_enrichment_tier", $new_tier);
            update_post_meta($post_id, "_botdot_artifact_id", $content_id);

            // Derive human-readable status
            $status_map = ["TIER0" => "indexed", "TIER1" => "enriching", "TIER2" => "enriched"];
            update_post_meta($post_id, "_botdot_enrichment_status", $status_map[$new_tier] ?? "unknown");
        }

        return new WP_REST_Response(["status" => "ok", "post_id" => $post_id], 200);
    }

    /**
     * Handle save_post hook
     *
     * @since    1.0.0
     * @param    int       $post_id    The post ID.
     * @param    WP_Post   $post       The post object.
     * @param    bool      $update     Whether this is an update.
     */
    public static function on_save_post($post_id, $post, $update)
    {
        // Skip autosaves
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }

        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Skip if auto-sync is disabled
        if (!BotDot_WP_Options::get("auto_sync_enabled")) {
            return;
        }

        // Skip non-synced post types
        $sync_post_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
        if (!in_array($post->post_type, $sync_post_types)) {
            return;
        }

        // Only sync published posts
        if ($post->post_status !== "publish") {
            return;
        }

        // Apply filter to allow skipping specific posts
        if (!apply_filters("botdot_wp_should_sync", true, $post_id, $post)) {
            return;
        }

        // Compute content hash
        $current_hash = self::compute_content_hash($post);
        $previous_hash = get_post_meta($post_id, "_botdot_sync_hash", true);

        // Determine if sync is needed based on change threshold
        if ($previous_hash && $previous_hash === $current_hash) {
            self::log_debug(sprintf("Post %d: hash unchanged, skipping sync", $post_id));
            return;
        }

        if ($previous_hash) {
            // Check change threshold
            $change_pct = self::compute_change_percentage($post, $post_id);
            $threshold = self::get_threshold();

            if ($change_pct < $threshold) {
                // Store hash but don't sync (minor change)
                update_post_meta($post_id, "_botdot_sync_hash", $current_hash);
                update_post_meta($post_id, "_botdot_sync_status", "pending");
                self::log_debug(
                    sprintf(
                        "Post %d: change %.1f%% below threshold %.1f%%, storing hash but not syncing",
                        $post_id,
                        $change_pct,
                        $threshold,
                    ),
                );
                return;
            }

            $event = "content.updated";
            $change_meta = [
                "previous_hash" => $previous_hash,
                "current_hash" => $current_hash,
                "change_pct" => $change_pct,
                "is_manual" => false,
            ];
        } else {
            $event = $update ? "content.updated" : "content.created";
            $change_meta = [
                "previous_hash" => null,
                "current_hash" => $current_hash,
                "change_pct" => 100.0,
                "is_manual" => false,
            ];
        }

        $result = self::send_webhook($post, $event, $change_meta);

        if ($result) {
            update_post_meta($post_id, "_botdot_sync_hash", $current_hash);
            update_post_meta($post_id, "_botdot_last_synced_at", current_time("mysql"));
            update_post_meta($post_id, "_botdot_sync_status", "synced");
            update_post_meta(
                $post_id,
                "_botdot_sync_word_count",
                str_word_count(strip_tags($post->post_title . " " . $post->post_content . " " . $post->post_excerpt)),
            );
        } else {
            update_post_meta($post_id, "_botdot_sync_status", "error");
            // Schedule a single retry in 5 minutes
            if (!wp_next_scheduled("botdot_wp_retry_sync", [$post_id])) {
                wp_schedule_single_event(time() + 300, "botdot_wp_retry_sync", [$post_id]);
            }
        }
    }

    /**
     * Retry sync for a single post (called via cron)
     *
     * @since    1.0.1
     * @param    int    $post_id    The post ID.
     */
    public static function retry_sync($post_id)
    {
        self::log_debug(sprintf("Retrying sync for post %d", $post_id));
        self::manual_sync($post_id);
    }

    /**
     * Handle post status transitions
     *
     * @since    1.0.0
     * @param    string    $new_status    New post status.
     * @param    string    $old_status    Old post status.
     * @param    WP_Post   $post          The post object.
     */
    public static function on_status_change($new_status, $old_status, $post)
    {
        if ($new_status === $old_status) {
            return;
        }

        $sync_post_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
        if (!in_array($post->post_type, $sync_post_types)) {
            return;
        }

        if ($old_status !== "publish" && $new_status === "publish") {
            // Newly published — on_save_post handles this
            return;
        }

        if ($old_status === "publish" && in_array($new_status, ["draft", "trash", "pending"])) {
            // Unpublished or trashed
            $event = $new_status === "trash" ? "content.deleted" : "content.status_changed";
            self::send_webhook($post, $event);
            update_post_meta($post->ID, "_botdot_sync_status", "synced");
        }
    }

    /**
     * Handle post deletion
     *
     * @since    1.0.0
     * @param    int    $post_id    The post ID.
     */
    public static function on_delete_post($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $sync_post_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
        if (!in_array($post->post_type, $sync_post_types)) {
            return;
        }

        self::send_webhook($post, "content.deleted");

        // Clean up post meta
        delete_post_meta($post_id, "_botdot_sync_hash");
        delete_post_meta($post_id, "_botdot_last_synced_at");
        delete_post_meta($post_id, "_botdot_sync_status");
        delete_post_meta($post_id, "_botspot_pre_enrich_jsonld");
    }

    /**
     * Send content to locus-core ingest endpoint
     *
     * @since    2.0.0
     * @param    WP_Post   $post           The post object.
     * @param    string    $event          The event type.
     * @param    array     $change_meta    Optional change metadata.
     * @return   bool                      True on success, false on failure.
     */
    public static function send_webhook($post, $event, $change_meta = null)
    {
        $api_key = BotDot_WP_Options::get("api_key");

        if (empty($api_key)) {
            self::log_error("Cannot send content: API key not configured");
            return false;
        }

        // Skip delete/unpublish events (no delete endpoint in locus-core yet)
        if (in_array($event, ["content.deleted", "content.status_changed"])) {
            self::log_debug(sprintf("Post %d: skipping %s event (no delete endpoint)", $post->ID, $event));
            return true;
        }

        $payload = self::build_ingest_payload($post);

        // Snapshot source JSON-LD before enrichment (schema versioning)
        $source_jsonld = $payload["structured_data"]["data"]["source_jsonld"] ?? null;
        if ($source_jsonld !== null) {
            update_post_meta($post->ID, "_botspot_pre_enrich_jsonld", wp_json_encode($source_jsonld));
        } elseif (!get_post_meta($post->ID, "_botspot_pre_enrich_jsonld", true)) {
            update_post_meta($post->ID, "_botspot_pre_enrich_jsonld", "");
        }

        // Apply filter to allow payload modification
        $payload = apply_filters("botdot_wp_sync_payload", $payload, $post, $event);

        $json_body = wp_json_encode($payload);

        $headers = [
            "Content-Type" => "application/json",
            "X-API-Key" => $api_key,
            "X-Source-Type" => "wordpress",
        ];

        $locus_api_url = BotDot_WP_Options::get_locus_api_url();
        $endpoint = rtrim($locus_api_url, "/") . "/api/v1/connector/ingest";

        self::log_debug(sprintf("Sending %s for post %d to %s", $event, $post->ID, $endpoint));

        $response = wp_remote_post($endpoint, [
            "headers" => $headers,
            "body" => $json_body,
            "timeout" => 30,
            "data_format" => "body",
        ]);

        if (is_wp_error($response)) {
            self::log_error(sprintf("Ingest failed for post %d: %s", $post->ID, $response->get_error_message()));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 200 && $status_code < 300) {
            // Store artifact_id from response for webhook mapping
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body["artifact_id"])) {
                update_post_meta($post->ID, "_botdot_artifact_id", $body["artifact_id"]);
            }
            self::log_debug(sprintf("Ingest successful for post %d (HTTP %d)", $post->ID, $status_code));
            return true;
        }

        self::log_error(
            sprintf(
                "Ingest returned HTTP %d for post %d: %s",
                $status_code,
                $post->ID,
                substr(wp_remote_retrieve_body($response), 0, 500),
            ),
        );
        return false;
    }

    /**
     * Build IngestPayload for a post
     *
     * @since    2.0.0
     * @param    WP_Post   $post    The post object.
     * @return   array              IngestPayload-compatible array.
     */
    private static function build_ingest_payload($post)
    {
        $permalink = get_permalink($post->ID);
        $categories = wp_get_post_categories($post->ID, ["fields" => "names"]);
        $tags = wp_get_post_tags($post->ID, ["fields" => "names"]);
        $featured_image = get_the_post_thumbnail_url($post->ID, "full");
        $author = get_the_author_meta("display_name", $post->post_author);

        $published_at =
            $post->post_date_gmt !== "0000-00-00 00:00:00" ? get_post_time("c", true, $post) : null;
        $updated_at =
            $post->post_modified_gmt !== "0000-00-00 00:00:00" ? get_post_modified_time("c", true, $post) : null;

        // Build media array from featured image
        $media = [];
        if ($featured_image) {
            $media[] = [
                "url" => $featured_image,
                "media_type" => self::infer_media_type($featured_image),
            ];
        }

        $tenant_id = BotDot_WP_Options::get("tenant_id");

        // Truncate fields to match DB column limits (safety net)
        $title = mb_substr($post->post_title, 0, 500);
        $author = $author ? mb_substr($author, 0, 255) : null;
        $excerpt = $post->post_excerpt ? mb_substr($post->post_excerpt, 0, 2000) : null;
        $url = mb_substr($permalink, 0, 2048);

        // Extract existing page JSON-LD from SEO plugins (source layer for merge)
        $source_jsonld = self::extract_page_jsonld($post->ID);

        $payload = [
            "source_type" => "wordpress",
            "identifier" => [
                "source_id" => $url,
                "source_url" => $url,
            ],
            "metadata" => [
                "title" => $title,
                "author" => $author,
                "language" => substr(get_locale(), 0, 2),
                "published_at" => $published_at,
                "updated_at" => $updated_at,
                "tags" => is_array($tags) ? array_values($tags) : [],
                "categories" => is_array($categories) ? array_values($categories) : [],
            ],
            "body" => [
                "format" => "html",
                "content" => $post->post_content,
                "excerpt" => $excerpt,
            ],
            "structured_data" => [
                "schema_type" => "wordpress_" . $post->post_type,
                "data" => [
                    "post_id" => $post->ID,
                    "post_type" => $post->post_type,
                    "status" => $post->post_status,
                    "featured_image" => $featured_image ?: null,
                    "source_jsonld" => $source_jsonld,
                ],
            ],
            "media" => $media,
        ];

        if (!empty($tenant_id)) {
            $payload["tenant_id"] = $tenant_id;
        }

        return $payload;
    }

    /**
     * Extract existing JSON-LD from SEO plugins for a post
     *
     * Checks Yoast SEO and RankMath for schema output. Filters out
     * locus-generated nodes (containing #locus- in @id) to prevent
     * circular re-ingestion.
     *
     * @since    2.2.0
     * @param    int    $post_id    The post ID.
     * @return   array|null         JSON-LD data or null if none found.
     */
    private static function extract_page_jsonld($post_id)
    {
        $jsonld = null;

        // Try Yoast SEO first
        if (defined("WPSEO_VERSION")) {
            $permalink = get_permalink($post_id);
            if ($permalink) {
                $response = wp_remote_get(
                    rest_url("yoast/v1/get_head") . "?url=" . urlencode($permalink),
                    ["timeout" => 5],
                );
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body["json"]) && is_array($body["json"])) {
                        $jsonld = $body["json"];
                    }
                }
            }
        }

        // Try RankMath if no Yoast data
        if ($jsonld === null && class_exists("RankMath\\Schema\\DB")) {
            $schemas = \RankMath\Schema\DB::get_schemas($post_id);
            if (!empty($schemas) && is_array($schemas)) {
                $jsonld = array_values($schemas);
                if (count($jsonld) === 1) {
                    $jsonld = $jsonld[0];
                }
            }
        }

        // Fallback: check Yoast post meta
        if ($jsonld === null) {
            $schema_meta = get_post_meta($post_id, "_yoast_wpseo_schema_page_type", true);
            if (!empty($schema_meta)) {
                // Only a type hint, not full JSON-LD - skip
                $jsonld = null;
            }
        }

        if ($jsonld === null) {
            return null;
        }

        // Filter out locus-generated nodes to prevent circular re-ingestion
        $jsonld = self::filter_locus_nodes($jsonld);

        /**
         * Filter extracted source JSON-LD before including in ingest payload.
         *
         * @since 2.2.0
         * @param array|null $jsonld    The extracted JSON-LD data.
         * @param int        $post_id   The post ID.
         */
        return apply_filters("botdot_wp_source_jsonld", $jsonld, $post_id);
    }

    /**
     * Filter out locus-generated nodes from JSON-LD
     *
     * Removes any nodes with @id containing #locus- to prevent
     * re-ingesting our own enrichment output.
     *
     * @since    2.2.0
     * @param    array    $jsonld    JSON-LD data (single node, array, or @graph wrapper).
     * @return   array|null          Filtered JSON-LD or null if empty after filtering.
     */
    private static function filter_locus_nodes($jsonld)
    {
        if (!is_array($jsonld)) {
            return $jsonld;
        }

        // Handle @graph wrapper
        if (isset($jsonld["@graph"]) && is_array($jsonld["@graph"])) {
            $filtered = array_values(
                array_filter($jsonld["@graph"], function ($node) {
                    if (!is_array($node)) {
                        return true;
                    }
                    $id = isset($node["@id"]) ? (string) $node["@id"] : "";
                    return strpos($id, "#locus-") === false && strpos($id, "#botspot-") === false;
                }),
            );
            if (empty($filtered)) {
                return null;
            }
            $jsonld["@graph"] = $filtered;
            return $jsonld;
        }

        // Handle flat array of nodes
        if (isset($jsonld[0])) {
            $filtered = array_values(
                array_filter($jsonld, function ($node) {
                    if (!is_array($node)) {
                        return true;
                    }
                    $id = isset($node["@id"]) ? (string) $node["@id"] : "";
                    return strpos($id, "#locus-") === false && strpos($id, "#botspot-") === false;
                }),
            );
            return empty($filtered) ? null : $filtered;
        }

        // Single node
        $id = isset($jsonld["@id"]) ? (string) $jsonld["@id"] : "";
        if (strpos($id, "#locus-") !== false) {
            return null;
        }

        return $jsonld;
    }

    /**
     * Infer MIME type from image URL extension
     *
     * @since    2.0.0
     * @param    string    $url    The image URL.
     * @return   string            MIME type.
     */
    private static function infer_media_type($url)
    {
        $extension = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        $mime_types = [
            "jpg" => "image/jpeg",
            "jpeg" => "image/jpeg",
            "png" => "image/png",
            "gif" => "image/gif",
            "webp" => "image/webp",
            "svg" => "image/svg+xml",
            "avif" => "image/avif",
        ];

        return isset($mime_types[$extension]) ? $mime_types[$extension] : "image/jpeg";
    }

    /**
     * Bulk sync all published posts via batch ingest endpoint
     *
     * @since    2.0.0
     * @param    string|null    $post_type    Optional post type filter.
     * @return   array|false                  Job status or false on failure.
     */
    public static function bulk_sync($post_type = null)
    {
        $api_key = BotDot_WP_Options::get("api_key");

        if (empty($api_key)) {
            return false;
        }

        $sync_post_types = $post_type ? [$post_type] : BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
        $locus_api_url = BotDot_WP_Options::get_locus_api_url();
        $endpoint = rtrim($locus_api_url, "/") . "/api/v1/connector/ingest/batch";
        $batch_size = 100;
        $offset = 0;
        $total_processed = 0;
        $total_success = 0;
        $total_failed = 0;

        $headers = [
            "Content-Type" => "application/json",
            "X-API-Key" => $api_key,
            "X-Source-Type" => "wordpress",
        ];

        while (true) {
            $posts = get_posts([
                "post_type" => $sync_post_types,
                "post_status" => "publish",
                "posts_per_page" => $batch_size,
                "offset" => $offset,
                "orderby" => "ID",
                "order" => "ASC",
            ]);

            if (empty($posts)) {
                break;
            }

            $items = [];
            $post_hashes = [];
            foreach ($posts as $post) {
                $payload = self::build_ingest_payload($post);
                $items[] = $payload;

                // Snapshot source JSON-LD before enrichment (schema versioning)
                $source_jsonld = $payload["structured_data"]["data"]["source_jsonld"] ?? null;
                if ($source_jsonld !== null) {
                    update_post_meta($post->ID, "_botspot_pre_enrich_jsonld", wp_json_encode($source_jsonld));
                } elseif (!get_post_meta($post->ID, "_botspot_pre_enrich_jsonld", true)) {
                    update_post_meta($post->ID, "_botspot_pre_enrich_jsonld", "");
                }

                $post_hashes[$post->ID] = [
                    "hash" => self::compute_content_hash($post),
                    "word_count" => str_word_count(
                        strip_tags($post->post_title . " " . $post->post_content . " " . $post->post_excerpt),
                    ),
                ];
            }

            $payload = ["items" => $items];
            $json_body = wp_json_encode($payload);

            $response = wp_remote_post($endpoint, [
                "headers" => $headers,
                "body" => $json_body,
                "timeout" => 60,
                "data_format" => "body",
            ]);

            $chunk_count = count($items);
            $total_processed += $chunk_count;

            if (is_wp_error($response)) {
                self::log_error(
                    sprintf("Bulk sync batch failed (offset %d): %s", $offset, $response->get_error_message()),
                );
                $total_failed += $chunk_count;
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if ($status_code >= 200 && $status_code < 300 && is_array($body)) {
                    $batch_accepted = isset($body["accepted"]) ? (int) $body["accepted"] : 0;
                    $batch_rejected = isset($body["rejected"]) ? (int) $body["rejected"] : 0;
                    $total_success += $batch_accepted;
                    $total_failed += $batch_rejected;

                    // Store artifact_ids from batch response (matched by index)
                    $artifact_ids = isset($body["artifact_ids"]) ? (array) $body["artifact_ids"] : [];

                    // Update meta for all posts in accepted batch
                    foreach ($posts as $idx => $post) {
                        $meta = $post_hashes[$post->ID];
                        update_post_meta($post->ID, "_botdot_sync_hash", $meta["hash"]);
                        update_post_meta($post->ID, "_botdot_last_synced_at", current_time("mysql"));
                        update_post_meta($post->ID, "_botdot_sync_status", "synced");
                        update_post_meta($post->ID, "_botdot_sync_word_count", $meta["word_count"]);
                        if (!empty($artifact_ids[$idx])) {
                            update_post_meta($post->ID, "_botdot_artifact_id", $artifact_ids[$idx]);
                        }
                    }

                    if (!empty($body["errors"])) {
                        foreach ($body["errors"] as $error) {
                            $detail = is_array($error)
                                ? sprintf("index=%s code=%s detail=%s",
                                    isset($error["index"]) ? $error["index"] : "?",
                                    isset($error["error_code"]) ? $error["error_code"] : "?",
                                    isset($error["detail"]) ? $error["detail"] : "?")
                                : (string) $error;
                            self::log_error(sprintf("Batch item error: %s", $detail));
                        }
                    }
                } else {
                    self::log_error(sprintf("Bulk sync batch returned HTTP %d (offset %d)", $status_code, $offset));
                    $total_failed += $chunk_count;
                }
            }

            $offset += $batch_size;

            if (count($posts) < $batch_size) {
                break;
            }
        }

        if ($total_processed === 0) {
            return ["status" => "completed", "total" => 0, "processed" => 0];
        }

        return [
            "status" => $total_failed === 0 ? "completed" : "partial",
            "total" => $total_processed,
            "processed" => $total_success,
            "failed" => $total_failed,
        ];
    }

    /**
     * Manual sync for a single post (bypasses threshold)
     *
     * @since    1.0.0
     * @param    int    $post_id    The post ID.
     * @return   bool               True on success, false on failure.
     */
    public static function manual_sync($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $current_hash = self::compute_content_hash($post);
        $previous_hash = get_post_meta($post_id, "_botdot_sync_hash", true);

        $change_meta = [
            "previous_hash" => $previous_hash ?: null,
            "current_hash" => $current_hash,
            "change_pct" => 100.0,
            "is_manual" => true,
        ];

        $event = $previous_hash ? "content.updated" : "content.created";

        $result = self::send_webhook($post, $event, $change_meta);

        if ($result) {
            update_post_meta($post_id, "_botdot_sync_hash", $current_hash);
            update_post_meta($post_id, "_botdot_last_synced_at", current_time("mysql"));
            update_post_meta($post_id, "_botdot_sync_status", "synced");
            update_post_meta(
                $post_id,
                "_botdot_sync_word_count",
                str_word_count(strip_tags($post->post_title . " " . $post->post_content . " " . $post->post_excerpt)),
            );
        } else {
            update_post_meta($post_id, "_botdot_sync_status", "error");
        }

        return $result;
    }

    /**
     * Compute SHA256 hash of post content
     *
     * @since    1.0.0
     * @param    WP_Post   $post    The post object.
     * @return   string             SHA256 hash.
     */
    private static function compute_content_hash($post)
    {
        $data = $post->post_title . $post->post_content . $post->post_excerpt;
        return hash("sha256", $data);
    }

    /**
     * Compute change percentage between current and previous content
     *
     * @since    1.0.0
     * @param    WP_Post   $post       The current post object.
     * @param    int       $post_id    The post ID.
     * @return   float                 Change percentage (0.0 - 100.0).
     */
    private static function compute_change_percentage($post, $post_id)
    {
        $current_words = str_word_count(
            strip_tags($post->post_title . " " . $post->post_content . " " . $post->post_excerpt),
        );

        // Get cached word count from previous sync
        $previous_word_count = get_post_meta($post_id, "_botdot_sync_word_count", true);

        if (!$previous_word_count || $previous_word_count <= 0) {
            return 100.0;
        }

        $change = abs($current_words - (int) $previous_word_count);
        $pct = ($change / (int) $previous_word_count) * 100;

        // Update stored word count
        update_post_meta($post_id, "_botdot_sync_word_count", $current_words);

        return round($pct, 1);
    }

    /**
     * Get change threshold based on sync sensitivity setting
     *
     * @since    1.0.0
     * @return   float    Threshold percentage.
     */
    private static function get_threshold()
    {
        $sensitivity = BotDot_WP_Options::get("sync_sensitivity", "medium");

        switch ($sensitivity) {
            case "high":
                return 0.0;
            case "low":
                return 25.0;
            case "medium":
            default:
                return 10.0;
        }
    }

    /**
     * Get the relative URL path for a post
     *
     * @since    1.0.0
     * @param    WP_Post   $post    The post object.
     * @return   string             Relative URL path.
     */
    private static function get_post_url_path($post)
    {
        $permalink = get_permalink($post->ID);
        $home_url = home_url();

        $path = str_replace($home_url, "", $permalink);

        if (empty($path) || $path[0] !== "/") {
            $path = "/" . $path;
        }

        return $path;
    }

    /**
     * Get sync status for a post
     *
     * @since    1.0.0
     * @param    int    $post_id    The post ID.
     * @return   array              Sync status info.
     */
    public static function get_sync_status($post_id)
    {
        return [
            "status" => get_post_meta($post_id, "_botdot_sync_status", true) ?: "never",
            "last_synced_at" => get_post_meta($post_id, "_botdot_last_synced_at", true) ?: null,
            "sync_hash" => get_post_meta($post_id, "_botdot_sync_hash", true) ?: null,
        ];
    }

    /**
     * Log debug message
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    private static function log_debug($message)
    {
        if (BotDot_WP_Options::get("debug_mode")) {
            BotDot_WP_Logger::log_debug("[Sync] " . $message);
        }
    }

    /**
     * Log error message
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    private static function log_error($message)
    {
        BotDot_WP_Logger::log_error("[Sync] " . $message);
    }
}
