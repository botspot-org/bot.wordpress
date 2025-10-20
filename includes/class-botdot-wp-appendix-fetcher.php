<?php
/**
 * Appendix fetcher class for the BotDot WP plugin
 *
 * @link       https://botdot.ai
 * @since      0.2.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Appendix fetcher class for the BotDot WP plugin.
 *
 * This class handles fetching appendix content from the mirror domain.
 *
 * @since      0.2.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Appendix_Fetcher {

    /**
     * Determine the protocol (http or https) based on the domain
     *
     * @since    0.2.0
     * @access   private
     * @param    string    $domain    The domain to check.
     * @return   string               'http' or 'https'.
     */
    private static function get_protocol($domain) {
        // Use HTTP for localhost and 127.0.0.1 (development)
        if (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $domain)) {
            return 'http';
        }
        // Use HTTPS for all other domains (production)
        return 'https';
    }

    /**
     * Fetch appendix from mirror domain
     *
     * @since    0.2.0
     * @param    string    $url_path    The URL path to fetch (e.g., /blog/my-post).
     * @return   array|WP_Error         The appendix data as array, or WP_Error on failure.
     */
    public static function fetch_appendix($url_path) {
        // Get mirror domain from options
        $mirror_domain = BotDot_WP_Options::get('mirror_domain');

        if (empty($mirror_domain)) {
            return new WP_Error(
                'no_mirror_domain',
                __('Mirror domain not configured', 'botdot-wp')
            );
        }

        // Build the full URL (without .json extension)
        $url_path = ltrim($url_path, '/');
        $protocol = self::get_protocol($mirror_domain);
        $url = $protocol . '://' . $mirror_domain . '/' . $url_path;

        // Get timeout from options
        $timeout = BotDot_WP_Options::get('fetch_timeout', 10);

        // Log debug info if debug mode is enabled
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug(sprintf(
                'Fetching appendix from: %s',
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
                'HTTP request failed for appendix %s: %s',
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
                'HTTP %d error for appendix %s',
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
                'Empty response from appendix %s',
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
                'Invalid JSON from appendix %s: %s',
                $url,
                json_last_error_msg()
            ));
            return $error;
        }

        // Log success if debug mode is enabled
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug(sprintf(
                'Successfully fetched appendix from: %s',
                $url
            ));
        }

        return $json_data;
    }

    /**
     * Test connection to mirror domain for appendix
     *
     * Tests if the mirror domain is accessible for appendix content
     *
     * @since    0.2.0
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

        $protocol = self::get_protocol($mirror_domain);
        $url = $protocol . '://' . $mirror_domain . $test_path;
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
                __('Appendix connection successful. HTTP status: %d', 'botdot-wp'),
                $status_code
            ),
            'status_code' => $status_code,
        );
    }
}
