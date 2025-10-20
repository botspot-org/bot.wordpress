<?php
/**
 * HTTP fetcher class for the BotDot WP plugin
 *
 * @link       https://botdot.ai
 * @since      0.1.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * HTTP fetcher class for the BotDot WP plugin.
 *
 * This class handles fetching JSON-LD from the mirror domain using
 * the WordPress HTTP API.
 *
 * @since      0.1.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Fetcher {

    /**
     * Fetch JSON-LD from mirror domain
     *
     * @since    0.1.0
     * @param    string    $url_path    The URL path to fetch (e.g., /blog/my-post).
     * @return   array|WP_Error         The JSON-LD data as array, or WP_Error on failure.
     */
    public static function fetch_json_ld($url_path) {
        // Get mirror domain from options
        $mirror_domain = BotDot_WP_Options::get('mirror_domain');

        if (empty($mirror_domain)) {
            return new WP_Error(
                'no_mirror_domain',
                __('Mirror domain not configured', 'botdot-wp')
            );
        }

        // Build the full URL
        $url_path = ltrim($url_path, '/');
        $url = 'https://' . $mirror_domain . '/' . $url_path . '.json';

        // Get timeout from options
        $timeout = BotDot_WP_Options::get('fetch_timeout', 10);

        // Log debug info if debug mode is enabled
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug(sprintf(
                'Fetching JSON-LD from: %s',
                $url
            ));
        }

        // Make HTTP request
        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        // Check for HTTP errors
        if (is_wp_error($response)) {
            BotDot_WP_Logger::log_error(sprintf(
                'HTTP request failed for %s: %s',
                $url,
                $response->get_error_message()
            ));
            return $response;
        }

        // Check HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $error = new WP_Error(
                'http_error',
                sprintf(
                    __('HTTP request returned status code %d', 'botdot-wp'),
                    $status_code
                )
            );
            BotDot_WP_Logger::log_error(sprintf(
                'HTTP %d error for %s',
                $status_code,
                $url
            ));
            return $error;
        }

        // Get response body
        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            $error = new WP_Error(
                'empty_response',
                __('Empty response received', 'botdot-wp')
            );
            BotDot_WP_Logger::log_error(sprintf(
                'Empty response from %s',
                $url
            ));
            return $error;
        }

        // Decode JSON
        $json_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = new WP_Error(
                'invalid_json',
                sprintf(
                    __('Invalid JSON response: %s', 'botdot-wp'),
                    json_last_error_msg()
                )
            );
            BotDot_WP_Logger::log_error(sprintf(
                'Invalid JSON from %s: %s',
                $url,
                json_last_error_msg()
            ));
            return $error;
        }

        // Validate JSON-LD structure (basic check)
        if (!self::validate_json_ld($json_data)) {
            $error = new WP_Error(
                'invalid_json_ld',
                __('Response does not appear to be valid JSON-LD', 'botdot-wp')
            );
            BotDot_WP_Logger::log_error(sprintf(
                'Invalid JSON-LD structure from %s',
                $url
            ));
            return $error;
        }

        // Log success if debug mode is enabled
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug(sprintf(
                'Successfully fetched JSON-LD from: %s',
                $url
            ));
        }

        return $json_data;
    }

    /**
     * Validate JSON-LD structure
     *
     * Performs basic validation to ensure the data looks like JSON-LD
     *
     * @since    0.1.0
     * @access   private
     * @param    mixed    $data    The data to validate.
     * @return   bool              True if valid, false otherwise.
     */
    private static function validate_json_ld($data) {
        // Must be an array or object
        if (!is_array($data)) {
            return false;
        }

        // Should have @context or be an array of objects with @context
        if (isset($data['@context'])) {
            return true;
        }

        // Check if it's an array of JSON-LD objects
        if (is_array($data) && !empty($data)) {
            foreach ($data as $item) {
                if (is_array($item) && isset($item['@context'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Test connection to mirror domain
     *
     * Tests if the mirror domain is accessible
     *
     * @since    0.1.0
     * @param    string    $test_path    Optional. Test path to use. Default '/'.
     * @return   array                   Result array with 'success' and 'message' keys.
     */
    public static function test_connection($test_path = '/') {
        $mirror_domain = BotDot_WP_Options::get('mirror_domain');

        if (empty($mirror_domain)) {
            return array(
                'success' => false,
                'message' => __('Mirror domain not configured', 'botdot-wp'),
            );
        }

        $url = 'https://' . $mirror_domain . $test_path;
        $timeout = BotDot_WP_Options::get('fetch_timeout', 10);

        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Connection failed: %s', 'botdot-wp'),
                    $response->get_error_message()
                ),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        return array(
            'success' => ($status_code >= 200 && $status_code < 400),
            'message' => sprintf(
                __('Connection successful. HTTP status: %d', 'botdot-wp'),
                $status_code
            ),
            'status_code' => $status_code,
        );
    }
}
