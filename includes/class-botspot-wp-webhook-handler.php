<?php
/**
 * Webhook handler for BotSpot cache invalidation
 *
 * Receives webhook events from locus-core and invalidates local transient cache.
 *
 * @link       https://bot.spot
 * @since      2.8.0
 *
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/includes
 */

if (!defined("WPINC")) {
    die();
}

/**
 * Handles incoming webhooks from locus-core.
 *
 * @since      2.8.0
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/includes
 */
class BotSpot_WP_Webhook_Handler
{
    /**
     * Initialize the webhook handler.
     *
     * @since    2.8.0
     */
    public function __construct()
    {
        add_action("rest_api_init", [$this, "register_routes"]);
    }

    /**
     * Register REST API routes for webhook handling.
     *
     * @since    2.8.0
     */
    public function register_routes()
    {
        register_rest_route("botspot/v1", "/webhook", [
            "methods" => "POST",
            "callback" => [$this, "handle_webhook"],
            "permission_callback" => "__return_true",
        ]);
    }

    /**
     * Handle incoming webhook request.
     *
     * @since    2.8.0
     * @param    WP_REST_Request    $request    The request object.
     * @return   WP_REST_Response               Response indicating success or failure.
     */
    public function handle_webhook(WP_REST_Request $request)
    {
        $signature = $request->get_header("X-Webhook-Signature");
        $payload = $request->get_body();
        $secret = get_option("botspot_wp_webhook_secret", "");

        if (empty($secret)) {
            return new WP_REST_Response(["error" => "Webhook not configured"], 400);
        }

        if (!$this->verify_signature($payload, $signature, $secret)) {
            return new WP_REST_Response(["error" => "Invalid signature"], 401);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return new WP_REST_Response(["error" => "Invalid payload"], 400);
        }

        $event = isset($data["event"]) ? $data["event"] : "";

        if ($event === "appendix.updated") {
            $path = isset($data["data"]["path"]) ? $data["data"]["path"] : "";
            $lang = isset($data["data"]["lang"]) ? $data["data"]["lang"] : null;
            $content_hash = isset($data["data"]["content_hash"]) ? $data["data"]["content_hash"] : "";

            $this->invalidate_page_cache($path, $lang);

            do_action("botspot_wp_cache_invalidated", $path, $lang, $content_hash);

            return new WP_REST_Response([
                "status" => "ok",
                "path" => $path,
                "lang" => $lang,
            ], 200);
        }

        if ($event === "settings.updated") {
            $settings = isset($data["data"]["settings"]) ? $data["data"]["settings"] : [];

            $this->handle_settings_updated($settings);

            do_action("botspot_wp_settings_updated", $settings);

            return new WP_REST_Response([
                "status" => "ok",
                "event" => "settings.updated",
            ], 200);
        }

        return new WP_REST_Response(["status" => "ignored", "event" => $event], 200);
    }

    /**
     * Verify HMAC signature of webhook payload.
     *
     * @since    2.8.0
     * @param    string    $payload      Raw request body.
     * @param    string    $signature    Signature from X-Webhook-Signature header.
     * @param    string    $secret       Shared secret.
     * @return   bool                    True if signature is valid.
     */
    private function verify_signature($payload, $signature, $secret)
    {
        if (empty($signature)) {
            return false;
        }

        $expected = "sha256=" . hash_hmac("sha256", $payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Normalize API settings to WP option names with validation.
     *
     * @since    2.11.0
     * @param    array    $settings    Settings from API.
     * @return   array                 Normalized settings for wp_options.
     */
    private static function normalize_platform_settings($settings)
    {
        $allowed_placements = ["auto", "footer", "manual", "bottom_of_content"];
        $placement = isset($settings["placement_mode"]) ? $settings["placement_mode"] : null;

        $normalized = [
            "sync_post_types" => isset($settings["sync_post_types"]) ? (array) $settings["sync_post_types"] : null,
            "inject_on_post_types" => isset($settings["output_post_types"]) ? (array) $settings["output_post_types"] : null,
            "appendix_enabled" => isset($settings["appendix_enabled"]) ? (bool) $settings["appendix_enabled"] : null,
            "jsonld_enabled" => isset($settings["jsonld_enabled"]) ? (bool) $settings["jsonld_enabled"] : null,
            "injection_position" => ($placement && in_array($placement, $allowed_placements, true)) ? $placement : null,
        ];

        return array_filter($normalized, function ($v) {
            return $v !== null;
        });
    }

    /**
     * Handle settings updated webhook event.
     *
     * Updates platform-owned settings in wp_options and sets admin notice.
     *
     * @since    2.11.0
     * @param    array    $settings    Settings data from platform.
     */
    private function handle_settings_updated($settings)
    {
        $platform_settings = self::normalize_platform_settings($settings);

        if (empty($platform_settings)) {
            return;
        }

        $current = get_option("botspot_wp_platform_settings", []);
        $updated = array_merge($current, $platform_settings);
        $updated["fetched_at"] = gmdate("c");

        update_option("botspot_wp_platform_settings", $updated);

        foreach ($platform_settings as $key => $value) {
            BotSpot_WP_Options::set($key, $value);
        }

        set_transient("botspot_wp_settings_updated_notice", true, 60 * 60 * 24);
    }

    /**
     * Invalidate cached appendix content for a page.
     *
     * Clears transients matching the cache key format used by BotSpot_WP_Content_Fetcher.
     *
     * @since    2.8.0
     * @param    string       $path    Page path (e.g., "/pricing").
     * @param    string|null  $lang    Language code or null to clear all variants.
     */
    private function invalidate_page_cache($path, $lang = null)
    {
        if ($lang) {
            $cache_key = "botspot_content_" . md5($path . "_" . $lang);
            delete_transient($cache_key);

            $jsonld_key = "botspot_jsonld_" . md5($path . "_" . $lang);
            delete_transient($jsonld_key);
        } else {
            global $wpdb;

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    "_transient_botspot_content_" . $wpdb->esc_like(md5($path)) . "%",
                    "_transient_timeout_botspot_content_" . $wpdb->esc_like(md5($path)) . "%"
                )
            );

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    "_transient_botspot_jsonld_" . $wpdb->esc_like(md5($path)) . "%",
                    "_transient_timeout_botspot_jsonld_" . $wpdb->esc_like(md5($path)) . "%"
                )
            );
        }
    }

    /**
     * Register webhook with locus-core.
     *
     * Called during plugin activation or when API key is first configured.
     *
     * @since    2.8.0
     * @return   bool    True if registration succeeded.
     */
    public static function register_webhook()
    {
        $api_url = BotSpot_WP_Options::get_locus_api_url();
        $api_key = BotSpot_WP_Options::get("api_key");

        if (empty($api_key)) {
            return false;
        }

        $existing_id = get_option("botspot_wp_webhook_id", "");
        if (!empty($existing_id)) {
            return true;
        }

        $response = wp_remote_post(rtrim($api_url, "/") . "/api/v1/webhooks", [
            "headers" => [
                "X-API-Key" => $api_key,
                "Content-Type" => "application/json",
            ],
            "body" => json_encode([
                "url" => rest_url("botspot/v1/webhook"),
                "events" => ["appendix.updated", "settings.updated"],
                "name" => "WordPress: " . home_url(),
            ]),
            "timeout" => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 201) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body["id"]) || empty($body["secret"])) {
            return false;
        }

        update_option("botspot_wp_webhook_id", $body["id"]);
        update_option("botspot_wp_webhook_secret", $body["secret"]);

        return true;
    }

    /**
     * Fetch platform settings from locus-core.
     *
     * Called during connection to sync platform-owned settings.
     *
     * @since    2.11.0
     * @return   array|false    Settings array on success, false on failure.
     */
    public static function fetch_platform_settings()
    {
        $api_url = BotSpot_WP_Options::get_locus_api_url();
        $api_key = BotSpot_WP_Options::get("api_key");

        if (empty($api_key)) {
            return false;
        }

        $response = wp_remote_get(rtrim($api_url, "/") . "/api/v1/wp/settings", [
            "headers" => [
                "X-API-Key" => $api_key,
                "X-Site-URL" => home_url(),
            ],
            "timeout" => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            if ($status_code === 404) {
                return self::bootstrap_platform_settings();
            }
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || !isset($body["settings"])) {
            return false;
        }

        $settings = $body["settings"];
        $platform_settings = self::normalize_platform_settings($settings);
        $platform_settings["fetched_at"] = gmdate("c");
        update_option("botspot_wp_platform_settings", $platform_settings);

        foreach ($platform_settings as $key => $value) {
            if ($key !== "fetched_at") {
                BotSpot_WP_Options::set($key, $value);
            }
        }

        return $platform_settings;
    }

    /**
     * Bootstrap platform settings from current local settings.
     *
     * Called when platform has no settings for this site (first-time migration).
     * Idempotent: if settings already exist on platform, returns existing without overwrite.
     *
     * @since    2.11.0
     * @return   array|false    Settings array on success, false on failure.
     */
    private static function bootstrap_platform_settings()
    {
        $api_url = BotSpot_WP_Options::get_locus_api_url();
        $api_key = BotSpot_WP_Options::get("api_key");

        $local_settings = [
            "sync_post_types" => BotSpot_WP_Options::get("sync_post_types"),
            "output_post_types" => BotSpot_WP_Options::get("inject_on_post_types"),
            "appendix_enabled" => BotSpot_WP_Options::get("appendix_enabled"),
            "jsonld_enabled" => BotSpot_WP_Options::get("jsonld_enabled"),
            "placement_mode" => BotSpot_WP_Options::get("injection_position"),
        ];

        $response = wp_remote_post(rtrim($api_url, "/") . "/api/v1/wp/settings/bootstrap", [
            "headers" => [
                "X-API-Key" => $api_key,
                "X-Site-URL" => home_url(),
                "Content-Type" => "application/json",
            ],
            "body" => json_encode(["settings" => $local_settings]),
            "timeout" => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200 && $status_code !== 201) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $api_settings = isset($body["settings"]) ? $body["settings"] : [];

        $local_normalized = [
            "sync_post_types" => $local_settings["sync_post_types"],
            "inject_on_post_types" => $local_settings["output_post_types"],
            "appendix_enabled" => $local_settings["appendix_enabled"],
            "jsonld_enabled" => $local_settings["jsonld_enabled"],
            "injection_position" => $local_settings["placement_mode"],
        ];

        $platform_settings = array_merge($local_normalized, self::normalize_platform_settings($api_settings));
        $platform_settings["fetched_at"] = gmdate("c");

        update_option("botspot_wp_platform_settings", $platform_settings);
        update_option("botspot_wp_local_settings_backup", $local_settings);

        return $platform_settings;
    }

    /**
     * Deregister webhook from locus-core.
     *
     * Called during plugin deactivation.
     *
     * @since    2.8.0
     */
    public static function deregister_webhook()
    {
        $api_url = BotSpot_WP_Options::get_locus_api_url();
        $api_key = BotSpot_WP_Options::get("api_key");
        $webhook_id = get_option("botspot_wp_webhook_id", "");

        if (!empty($webhook_id) && !empty($api_key)) {
            wp_remote_request(rtrim($api_url, "/") . "/api/v1/webhooks/" . $webhook_id, [
                "method" => "DELETE",
                "headers" => [
                    "X-API-Key" => $api_key,
                ],
                "timeout" => 10,
            ]);
        }

        delete_option("botspot_wp_webhook_id");
        delete_option("botspot_wp_webhook_secret");
    }
}
