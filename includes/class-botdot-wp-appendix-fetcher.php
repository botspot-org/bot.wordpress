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
     * Log debug message if debug mode is enabled
     *
     * @since    0.6.6
     * @access   private
     * @param    string    $message    The message to log.
     */
    private static function log_debug($message) {
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('[AppendixFetcher] ' . $message);
        }
    }

    /**
     * Log error message (always logged)
     *
     * @since    0.6.6
     * @access   private
     * @param    string    $message    The message to log.
     */
    private static function log_error($message) {
        BotDot_WP_Logger::log_error('[AppendixFetcher] ' . $message);
    }

    /**
     * Clean the domain by removing protocol and trailing slashes
     *
     * @since    0.4.0
     * @access   private
     * @param    string    $domain    The domain to clean.
     * @return   string               The cleaned domain.
     */
    private static function clean_domain($domain) {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim($domain, '/');
        return $domain;
    }

    /**
     * Determine the protocol (http or https) based on the domain
     *
     * @since    0.2.0
     * @access   private
     * @param    string    $domain    The domain to check.
     * @return   string               'http' or 'https'.
     */
    private static function get_protocol($domain) {
        // Clean domain first to ensure accurate matching
        $domain = self::clean_domain($domain);
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
     * @return   string|WP_Error        The appendix HTML content, or WP_Error on failure.
     */
    public static function fetch_appendix($url_path) {
        self::log_debug(sprintf('fetch_appendix() called with path: "%s"', $url_path));

        // Get mirror domain from options
        $mirror_domain = BotDot_WP_Options::get('mirror_domain');

        if (empty($mirror_domain)) {
            self::log_error('Mirror domain not configured - cannot fetch appendix');
            return new WP_Error(
                'no_mirror_domain',
                __('Mirror domain not configured', 'botdot-wp')
            );
        }

        self::log_debug(sprintf('Raw mirror domain from options: "%s"', $mirror_domain));

        // Clean the domain (removes https://, trailing slashes, etc.)
        $original_domain = $mirror_domain;
        $mirror_domain = self::clean_domain($mirror_domain);
        if ($mirror_domain !== $original_domain) {
            self::log_debug(sprintf('Cleaned mirror domain: "%s" -> "%s"', $original_domain, $mirror_domain));
        }

        // Build the full URL (fetches HTML, not JSON)
        $original_path = $url_path;
        $url_path = ltrim($url_path, '/');
        if ($url_path !== $original_path) {
            self::log_debug(sprintf('Trimmed path: "%s" -> "%s"', $original_path, $url_path));
        }

        $protocol = self::get_protocol($mirror_domain);
        $url = $protocol . '://' . $mirror_domain . '/' . $url_path;

        self::log_debug(sprintf('Constructed URL: %s (protocol: %s)', $url, $protocol));

        // Get timeout from options
        $timeout = BotDot_WP_Options::get('fetch_timeout', 10);
        self::log_debug(sprintf('Request timeout: %d seconds', $timeout));

        // Make HTTP request for HTML
        self::log_debug('Making HTTP GET request...');
        $start_time = microtime(true);

        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'headers' => array(
                'Accept' => 'text/html',
                'User-Agent' => 'BotDot-WP/' . BOTDOT_WP_VERSION . ' (WordPress)',
            ),
        ));

        $elapsed = round((microtime(true) - $start_time) * 1000, 2);

        // Check for HTTP errors
        if (is_wp_error($response)) {
            self::log_error(sprintf(
                'HTTP request FAILED for %s after %sms: %s (code: %s)',
                $url,
                $elapsed,
                $response->get_error_message(),
                $response->get_error_code()
            ));
            return $response;
        }

        // Get response details
        $status_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $content_length = wp_remote_retrieve_header($response, 'content-length');

        self::log_debug(sprintf(
            'Response received in %sms: HTTP %d, Content-Type: %s, Content-Length: %s',
            $elapsed,
            $status_code,
            $content_type ?: '(not set)',
            $content_length ?: '(not set)'
        ));

        // Check HTTP status code
        if ($status_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $body_preview = strlen($response_body) > 200
                ? substr($response_body, 0, 200) . '...'
                : $response_body;

            self::log_error(sprintf(
                'HTTP %d error for %s. Response body preview: %s',
                $status_code,
                $url,
                $body_preview
            ));

            return new WP_Error(
                'http_error',
                sprintf(
                    __('HTTP request returned status code %d', 'botdot-wp'),
                    $status_code
                )
            );
        }

        // Get response body (HTML)
        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            self::log_error(sprintf('Empty response body from %s', $url));
            return new WP_Error(
                'empty_response',
                __('Empty response received', 'botdot-wp')
            );
        }

        // Log success with body preview
        $body_preview = strlen($body) > 100
            ? substr($body, 0, 100) . '...'
            : $body;

        self::log_debug(sprintf(
            'Successfully fetched %d bytes from %s. Preview: %s',
            strlen($body),
            $url,
            preg_replace('/\s+/', ' ', $body_preview)
        ));

        // Return HTML as-is
        return $body;
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
        self::log_debug(sprintf('test_connection() called with path: "%s"', $test_path));

        $mirror_domain = BotDot_WP_Options::get('mirror_domain');

        if (empty($mirror_domain)) {
            self::log_error('test_connection: Mirror domain not configured');
            return array(
                'success' => false,
                'message' => __('Mirror domain not configured', 'botdot-wp'),
            );
        }

        // Clean the domain (removes https://, trailing slashes, etc.)
        $mirror_domain = self::clean_domain($mirror_domain);

        $protocol = self::get_protocol($mirror_domain);
        $url = $protocol . '://' . $mirror_domain . $test_path;
        $timeout = BotDot_WP_Options::get('fetch_timeout', 10);

        self::log_debug(sprintf('Testing connection to: %s (timeout: %ds)', $url, $timeout));

        $start_time = microtime(true);
        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'BotDot-WP/' . BOTDOT_WP_VERSION . ' (WordPress)',
            ),
        ));
        $elapsed = round((microtime(true) - $start_time) * 1000, 2);

        if (is_wp_error($response)) {
            self::log_error(sprintf(
                'test_connection FAILED for %s after %sms: %s',
                $url,
                $elapsed,
                $response->get_error_message()
            ));
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Connection failed: %s', 'botdot-wp'),
                    $response->get_error_message()
                ),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $body_length = strlen(wp_remote_retrieve_body($response));

        self::log_debug(sprintf(
            'test_connection completed in %sms: HTTP %d, Content-Type: %s, Body: %d bytes',
            $elapsed,
            $status_code,
            $content_type ?: '(not set)',
            $body_length
        ));

        $success = ($status_code >= 200 && $status_code < 400);

        return array(
            'success' => $success,
            'message' => sprintf(
                __('Appendix connection %s. HTTP status: %d, Response time: %sms', 'botdot-wp'),
                $success ? 'successful' : 'failed',
                $status_code,
                $elapsed
            ),
            'status_code' => $status_code,
            'response_time_ms' => $elapsed,
            'content_type' => $content_type,
            'body_length' => $body_length,
        );
    }
}
