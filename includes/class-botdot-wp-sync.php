<?php
/**
 * Content sync class for push-based ingestion
 *
 * Handles pushing content to locus-connectors on publish/update/delete.
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
 * to push content changes to locus-connectors via webhook.
 *
 * @since      1.0.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Sync
{
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
    }

    /**
     * Send webhook to locus-connectors
     *
     * @since    1.0.0
     * @param    WP_Post   $post           The post object.
     * @param    string    $event          The event type.
     * @param    array     $change_meta    Optional change metadata.
     * @return   bool                      True on success, false on failure.
     */
    public static function send_webhook($post, $event, $change_meta = null)
    {
        $connector_url = BotDot_WP_Options::get("connector_url");
        $api_key = BotDot_WP_Options::get("api_key");
        $webhook_secret = BotDot_WP_Options::get("webhook_secret");
        $connection_id = BotDot_WP_Options::get("connection_id");

        if (empty($connector_url) || empty($api_key) || empty($connection_id)) {
            self::log_error("Cannot send webhook: connector URL, API key, or connection ID not configured");
            return false;
        }

        // Build content payload
        $url_path = self::get_post_url_path($post);
        $categories = wp_get_post_categories($post->ID, ["fields" => "names"]);
        $tags = wp_get_post_tags($post->ID, ["fields" => "names"]);
        $featured_image = get_the_post_thumbnail_url($post->ID, "full");
        $author = get_the_author_meta("display_name", $post->post_author);

        $content = [
            "post_id" => $post->ID,
            "url" => $url_path,
            "title" => $post->post_title,
            "body" => $post->post_content,
            "excerpt" => $post->post_excerpt ?: null,
            "post_type" => $post->post_type,
            "status" => $post->post_status,
            "author" => $author ?: null,
            "published_at" => $post->post_date_gmt !== "0000-00-00 00:00:00" ? $post->post_date_gmt : null,
            "modified_at" => $post->post_modified_gmt !== "0000-00-00 00:00:00" ? $post->post_modified_gmt : null,
            "categories" => is_array($categories) ? $categories : [],
            "tags" => is_array($tags) ? $tags : [],
            "featured_image" => $featured_image ?: null,
            "meta" => [],
        ];

        $payload = [
            "event" => $event,
            "site_url" => home_url(),
            "content" => $content,
        ];

        if ($change_meta) {
            $payload["change_meta"] = $change_meta;
        }

        // Apply filter to allow payload modification
        $payload = apply_filters("botdot_wp_sync_payload", $payload, $post, $event);

        $json_body = wp_json_encode($payload);

        // Build headers
        $headers = [
            "Content-Type" => "application/json",
            "X-API-Key" => $api_key,
        ];

        // Compute HMAC signature if secret is configured
        if (!empty($webhook_secret)) {
            $signature = hash_hmac("sha256", $json_body, $webhook_secret);
            $headers["X-WP-Signature"] = $signature;
        }

        $endpoint = rtrim($connector_url, "/") . "/webhooks/wordpress/" . $connection_id;

        self::log_debug(sprintf("Sending %s webhook for post %d to %s", $event, $post->ID, $endpoint));

        $response = wp_remote_post($endpoint, [
            "headers" => $headers,
            "body" => $json_body,
            "timeout" => 30,
            "data_format" => "body",
        ]);

        if (is_wp_error($response)) {
            self::log_error(sprintf("Webhook failed for post %d: %s", $post->ID, $response->get_error_message()));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 200 && $status_code < 300) {
            self::log_debug(sprintf("Webhook sent successfully for post %d (HTTP %d)", $post->ID, $status_code));
            return true;
        }

        self::log_error(
            sprintf(
                "Webhook returned HTTP %d for post %d: %s",
                $status_code,
                $post->ID,
                substr(wp_remote_retrieve_body($response), 0, 500),
            ),
        );
        return false;
    }

    /**
     * Bulk sync all published posts
     *
     * @since    1.0.0
     * @param    string|null    $post_type    Optional post type filter.
     * @return   array|false                  Job status or false on failure.
     */
    public static function bulk_sync($post_type = null)
    {
        $connector_url = BotDot_WP_Options::get("connector_url");
        $api_key = BotDot_WP_Options::get("api_key");
        $webhook_secret = BotDot_WP_Options::get("webhook_secret");
        $connection_id = BotDot_WP_Options::get("connection_id");

        if (empty($connector_url) || empty($api_key) || empty($connection_id)) {
            return false;
        }

        $sync_post_types = $post_type ? [$post_type] : BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
        $endpoint = rtrim($connector_url, "/") . "/webhooks/wordpress/" . $connection_id;
        $batch_size = 100;
        $offset = 0;
        $total_processed = 0;
        $total_success = 0;
        $total_failed = 0;

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

            $content_items = [];
            $post_hashes = [];
            foreach ($posts as $post) {
                $url_path = self::get_post_url_path($post);
                $categories = wp_get_post_categories($post->ID, ["fields" => "names"]);
                $tags = wp_get_post_tags($post->ID, ["fields" => "names"]);
                $featured_image = get_the_post_thumbnail_url($post->ID, "full");
                $author = get_the_author_meta("display_name", $post->post_author);

                $content_items[] = [
                    "post_id" => $post->ID,
                    "url" => $url_path,
                    "title" => $post->post_title,
                    "body" => $post->post_content,
                    "excerpt" => $post->post_excerpt ?: null,
                    "post_type" => $post->post_type,
                    "status" => $post->post_status,
                    "author" => $author ?: null,
                    "published_at" => $post->post_date_gmt !== "0000-00-00 00:00:00" ? $post->post_date_gmt : null,
                    "modified_at" =>
                        $post->post_modified_gmt !== "0000-00-00 00:00:00" ? $post->post_modified_gmt : null,
                    "categories" => is_array($categories) ? $categories : [],
                    "tags" => is_array($tags) ? $tags : [],
                    "featured_image" => $featured_image ?: null,
                    "meta" => [],
                ];

                $post_hashes[$post->ID] = [
                    "hash" => self::compute_content_hash($post),
                    "word_count" => str_word_count(
                        strip_tags($post->post_title . " " . $post->post_content . " " . $post->post_excerpt),
                    ),
                ];
            }

            $payload = [
                "event" => "content.bulk_sync",
                "site_url" => home_url(),
                "content" => $content_items,
            ];

            $json_body = wp_json_encode($payload);

            $headers = [
                "Content-Type" => "application/json",
                "X-API-Key" => $api_key,
            ];

            if (!empty($webhook_secret)) {
                $headers["X-WP-Signature"] = hash_hmac("sha256", $json_body, $webhook_secret);
            }

            $response = wp_remote_post($endpoint, [
                "headers" => $headers,
                "body" => $json_body,
                "timeout" => 60,
                "data_format" => "body",
            ]);

            $chunk_count = count($content_items);
            $total_processed += $chunk_count;

            if (is_wp_error($response)) {
                self::log_error(
                    sprintf("Bulk sync chunk failed (offset %d): %s", $offset, $response->get_error_message()),
                );
                $total_failed += $chunk_count;
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code >= 200 && $status_code < 300) {
                    $total_success += $chunk_count;
                    foreach ($posts as $post) {
                        $meta = $post_hashes[$post->ID];
                        update_post_meta($post->ID, "_botdot_sync_hash", $meta["hash"]);
                        update_post_meta($post->ID, "_botdot_last_synced_at", current_time("mysql"));
                        update_post_meta($post->ID, "_botdot_sync_status", "synced");
                        update_post_meta($post->ID, "_botdot_sync_word_count", $meta["word_count"]);
                    }
                } else {
                    self::log_error(sprintf("Bulk sync chunk returned HTTP %d (offset %d)", $status_code, $offset));
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
