<?php
/**
 * Unified content fetcher for the BotDot WP plugin
 *
 * Fetches rendered appendix HTML and JSON-LD from locus-core's
 * /appendix/render endpoint with transient caching.
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
 * Unified content fetcher for locus-core appendix rendering.
 *
 * Replaces the old BotDot_WP_Fetcher and BotDot_WP_Appendix_Fetcher
 * with a single fetcher that returns both HTML and JSON-LD.
 *
 * @since      1.0.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Content_Fetcher
{
    /**
     * Per-request cache to avoid duplicate HTTP calls within a single page load.
     *
     * @since    1.0.1
     * @access   private
     * @var      array
     */
    private static $request_cache = [];

    /**
     * Fetch appendix content for a given URL path
     *
     * Returns cached data if fresh, otherwise fetches from locus-core.
     *
     * @since    1.0.0
     * @param    string    $url_path    The URL path to fetch content for.
     * @return   array|null             Array with 'html', 'jsonld', 'content_hash' keys, or null on failure.
     */
    public static function fetch($url_path)
    {
        // Check per-request cache first
        if (isset(self::$request_cache[$url_path])) {
            return self::$request_cache[$url_path];
        }

        $locus_api_url = BotDot_WP_Options::get_locus_api_url();
        $api_key = BotDot_WP_Options::get("api_key");

        if (empty($api_key)) {
            self::log_debug("Cannot fetch: API key not configured");
            return null;
        }

        $lang = substr(get_locale(), 0, 2);
        $cache_key = "botdot_content_" . md5($url_path . "_" . $lang);

        // Check transient cache
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            self::log_debug(sprintf("Cache hit for path: %s", $url_path));

            // Validate cache freshness via /appendix/check
            $check_result = self::check_freshness($url_path, $cached);
            if ($check_result === true) {
                self::log_debug("Cache is fresh, returning cached data");
                self::$request_cache[$url_path] = $cached;
                return $cached;
            }

            self::log_debug("Cache is stale, fetching fresh content");
        }

        // Fetch from locus-core
        $endpoint = rtrim($locus_api_url, "/") . "/api/v1/appendix/render";
        $endpoint = add_query_arg("path", $url_path, $endpoint);
        $endpoint = add_query_arg("lang", $lang, $endpoint);

        self::log_debug(sprintf("Fetching from: %s", $endpoint));

        $response = wp_remote_get($endpoint, [
            "headers" => [
                "X-API-Key" => $api_key,
                "Accept" => "application/json",
            ],
            "timeout" => 15,
        ]);

        if (is_wp_error($response)) {
            self::log_error(sprintf("Fetch failed for path %s: %s", $url_path, $response->get_error_message()));
            // Return stale cache if available
            if ($cached !== false && is_array($cached)) {
                self::log_debug("Returning stale cached data after fetch failure");
                self::$request_cache[$url_path] = $cached;
                return $cached;
            }
            self::$request_cache[$url_path] = null;
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            self::log_error(sprintf("Fetch returned HTTP %d for path %s", $status_code, $url_path));
            if ($cached !== false && is_array($cached)) {
                self::$request_cache[$url_path] = $cached;
                return $cached;
            }
            self::$request_cache[$url_path] = null;
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body)) {
            self::log_error("Fetch returned invalid JSON");
            self::$request_cache[$url_path] = null;
            return null;
        }

        $data = [
            "html" => isset($body["html"]) ? $body["html"] : null,
            "jsonld" => isset($body["jsonld"]) ? $body["jsonld"] : null,
            "content_hash" => isset($body["content_hash"]) ? $body["content_hash"] : null,
            "status" => isset($body["status"]) ? $body["status"] : null,
            "reason" => isset($body["reason"]) ? $body["reason"] : null,
        ];

        // Cache with configured TTL
        $ttl = isset($body["cache_ttl"]) ? (int) $body["cache_ttl"] : BotDot_WP_Options::get("cache_ttl", 3600);
        set_transient($cache_key, $data, $ttl);

        self::log_debug(
            sprintf(
                "Fetched and cached content for path %s (TTL: %ds, html: %s, jsonld: %s)",
                $url_path,
                $ttl,
                $data["html"] !== null ? strlen($data["html"]) . " bytes" : "null",
                $data["jsonld"] !== null ? "present" : "null",
            ),
        );

        self::$request_cache[$url_path] = $data;
        return $data;
    }

    /**
     * Fetch JSON-LD only from the dedicated /appendix/jsonld endpoint
     *
     * Used when appendix is disabled but jsonld is enabled, so we do not
     * need to fetch the full rendered HTML.
     *
     * @since    1.2.0
     * @param    string    $url_path    The URL path to fetch JSON-LD for.
     * @return   array|null             Array with 'jsonld' and 'content_hash' keys, or null on failure.
     */
    public static function fetch_jsonld($url_path)
    {
        $lang = substr(get_locale(), 0, 2);
        $cache_key_jsonld = "botdot_jsonld_" . md5($url_path . "_" . $lang);

        // Check per-request cache first
        if (isset(self::$request_cache[$cache_key_jsonld])) {
            return self::$request_cache[$cache_key_jsonld];
        }

        $locus_api_url = BotDot_WP_Options::get_locus_api_url();
        $api_key = BotDot_WP_Options::get("api_key");

        if (empty($api_key)) {
            self::log_debug("Cannot fetch JSON-LD: API key not configured");
            return null;
        }

        // Check transient cache
        $cached = get_transient($cache_key_jsonld);
        if ($cached !== false && is_array($cached)) {
            self::log_debug(sprintf("JSON-LD cache hit for path: %s", $url_path));
            self::$request_cache[$cache_key_jsonld] = $cached;
            return $cached;
        }

        // Fetch from locus-core /appendix/jsonld
        $endpoint = rtrim($locus_api_url, "/") . "/api/v1/appendix/jsonld";
        $endpoint = add_query_arg("path", $url_path, $endpoint);
        $endpoint = add_query_arg("lang", $lang, $endpoint);

        self::log_debug(sprintf("Fetching JSON-LD from: %s", $endpoint));

        $response = wp_remote_get($endpoint, [
            "headers" => [
                "X-API-Key" => $api_key,
                "Accept" => "application/json",
            ],
            "timeout" => 15,
        ]);

        if (is_wp_error($response)) {
            self::log_error(sprintf("JSON-LD fetch failed for path %s: %s", $url_path, $response->get_error_message()));
            self::$request_cache[$cache_key_jsonld] = null;
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            self::log_error(sprintf("JSON-LD fetch returned HTTP %d for path %s", $status_code, $url_path));
            self::$request_cache[$cache_key_jsonld] = null;
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body)) {
            self::log_error("JSON-LD fetch returned invalid JSON");
            self::$request_cache[$cache_key_jsonld] = null;
            return null;
        }

        $data = [
            "jsonld" => isset($body["jsonld"]) ? $body["jsonld"] : null,
            "content_hash" => isset($body["content_hash"]) ? $body["content_hash"] : null,
        ];

        // Cache with configured TTL
        $ttl = isset($body["cache_ttl"]) ? (int) $body["cache_ttl"] : BotDot_WP_Options::get("cache_ttl", 3600);
        set_transient($cache_key_jsonld, $data, $ttl);

        self::log_debug(
            sprintf(
                "Fetched and cached JSON-LD for path %s (TTL: %ds, jsonld: %s)",
                $url_path,
                $ttl,
                $data["jsonld"] !== null ? "present" : "null",
            ),
        );

        self::$request_cache[$cache_key_jsonld] = $data;
        return $data;
    }

    /**
     * Check cache freshness via /appendix/check endpoint
     *
     * @since    1.0.0
     * @param    string    $url_path    The URL path.
     * @param    array     $cached      The cached data with content_hash.
     * @return   bool                   True if cache is fresh, false if stale.
     */
    private static function check_freshness($url_path, $cached)
    {
        if (empty($cached["content_hash"])) {
            return false;
        }

        $locus_api_url = BotDot_WP_Options::get_locus_api_url();
        $api_key = BotDot_WP_Options::get("api_key");

        $endpoint = rtrim($locus_api_url, "/") . "/api/v1/appendix/check";
        $endpoint = add_query_arg("path", $url_path, $endpoint);

        $lang = substr(get_locale(), 0, 2);
        if (!empty($lang)) {
            $endpoint = add_query_arg("lang", $lang, $endpoint);
        }

        $response = wp_remote_get($endpoint, [
            "headers" => [
                "X-API-Key" => $api_key,
                "Accept" => "application/json",
            ],
            "timeout" => 5,
        ]);

        if (is_wp_error($response)) {
            self::log_debug("Freshness check failed, treating cache as fresh");
            return true;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return true;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || !isset($body["content_hash"])) {
            return true;
        }

        return $body["content_hash"] === $cached["content_hash"];
    }

    /**
     * Test connection to locus-core
     *
     * @since    1.0.0
     * @return   array    Result with 'success' and 'message' keys.
     */
    public static function test_connection()
    {
        $locus_api_url = BotDot_WP_Options::get_locus_api_url();
        $api_key = BotDot_WP_Options::get("api_key");

        if (empty($api_key)) {
            return [
                "success" => false,
                "message" => __("API key is not configured", "botdot-wp"),
            ];
        }

        $endpoint = rtrim($locus_api_url, "/") . "/api/v1/appendix/config";

        $response = wp_remote_get($endpoint, [
            "headers" => [
                "X-API-Key" => $api_key,
                "Accept" => "application/json",
            ],
            "timeout" => 10,
        ]);

        if (is_wp_error($response)) {
            return [
                "success" => false,
                "message" => sprintf(__("Connection failed: %s", "botdot-wp"), $response->get_error_message()),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            return [
                "success" => true,
                "message" => __("Connected to locus-core successfully", "botdot-wp"),
            ];
        }

        if ($status_code === 401 || $status_code === 403) {
            return [
                "success" => false,
                "message" => __("Authentication failed. Check your API key.", "botdot-wp"),
            ];
        }

        return [
            "success" => false,
            "message" => sprintf(__("Connection returned HTTP %d", "botdot-wp"), $status_code),
        ];
    }

    /**
     * Clear cached content
     *
     * @since    1.0.0
     * @param    string|null    $path    Optional specific path to clear. Null clears all.
     */
    public static function clear_cache($path = null)
    {
        if ($path !== null) {
            delete_transient("botdot_content_" . md5($path));
            self::log_debug(sprintf("Cleared cache for path: %s", $path));
            return;
        }

        // Clear all botdot_content_ transients
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like("_transient_botdot_content_") . "%",
                $wpdb->esc_like("_transient_timeout_botdot_content_") . "%",
            ),
        );
        self::log_debug("Cleared all content caches");
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
            BotDot_WP_Logger::log_debug("[ContentFetcher] " . $message);
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
        BotDot_WP_Logger::log_error("[ContentFetcher] " . $message);
    }
}
