<?php
/**
 * Webhook handler for BotSpot cache invalidation
 *
 * Receives webhook events from locus-core and invalidates local transient cache.
 *
 * @link       https://bot.spot
 * @since      2.8.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

if (!defined("WPINC")) {
    die();
}

/**
 * Handles incoming webhooks from locus-core.
 *
 * @since      2.8.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */
class BotDot_WP_Webhook_Handler
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
        $secret = get_option("botdot_wp_webhook_secret", "");

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

            do_action("botdot_wp_cache_invalidated", $path, $lang, $content_hash);

            return new WP_REST_Response([
                "status" => "ok",
                "path" => $path,
                "lang" => $lang,
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
     * Invalidate cached appendix content for a page.
     *
     * Clears transients matching the cache key format used by BotDot_WP_Content_Fetcher.
     *
     * @since    2.8.0
     * @param    string       $path    Page path (e.g., "/pricing").
     * @param    string|null  $lang    Language code or null to clear all variants.
     */
    private function invalidate_page_cache($path, $lang = null)
    {
        if ($lang) {
            $cache_key = "botdot_content_" . md5($path . "_" . $lang);
            delete_transient($cache_key);

            $jsonld_key = "botdot_jsonld_" . md5($path . "_" . $lang);
            delete_transient($jsonld_key);
        } else {
            global $wpdb;

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    "_transient_botdot_content_" . $wpdb->esc_like(md5($path)) . "%",
                    "_transient_timeout_botdot_content_" . $wpdb->esc_like(md5($path)) . "%"
                )
            );

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    "_transient_botdot_jsonld_" . $wpdb->esc_like(md5($path)) . "%",
                    "_transient_timeout_botdot_jsonld_" . $wpdb->esc_like(md5($path)) . "%"
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
        $api_url = BotDot_WP_Options::get_locus_api_url();
        $api_key = BotDot_WP_Options::get("api_key");

        if (empty($api_key)) {
            return false;
        }

        $existing_id = get_option("botdot_wp_webhook_id", "");
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
                "events" => ["appendix.updated"],
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

        update_option("botdot_wp_webhook_id", $body["id"]);
        update_option("botdot_wp_webhook_secret", $body["secret"]);

        return true;
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
        $api_url = BotDot_WP_Options::get_locus_api_url();
        $api_key = BotDot_WP_Options::get("api_key");
        $webhook_id = get_option("botdot_wp_webhook_id", "");

        if (!empty($webhook_id) && !empty($api_key)) {
            wp_remote_request(rtrim($api_url, "/") . "/api/v1/webhooks/" . $webhook_id, [
                "method" => "DELETE",
                "headers" => [
                    "X-API-Key" => $api_key,
                ],
                "timeout" => 10,
            ]);
        }

        delete_option("botdot_wp_webhook_id");
        delete_option("botdot_wp_webhook_secret");
    }
}
