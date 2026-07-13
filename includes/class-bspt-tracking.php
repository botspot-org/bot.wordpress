<?php
/**
 * Server-side page view tracking for bot analytics.
 *
 * Fires on every page view (before output) and sends a non-blocking
 * request to the BotSpot API. This catches ALL traffic including bots
 * that don't execute JavaScript.
 *
 * @package Bspt
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tracking class for server-side analytics.
 */
class BSPT_Tracking {

    /**
     * API endpoint for tracking.
     *
     * @var string
     */
    private $endpoint = '/v1/t';

    /**
     * Initialize tracking hooks.
     */
    public function __construct() {
        add_action('template_redirect', [$this, 'track_page_view'], 1);
    }

    /**
     * Track page view - fires on every frontend request.
     *
     * Non-blocking request, doesn't affect page load time.
     */
    public function track_page_view() {
        // Skip admin, ajax, cron, REST API, and CLI
        if (
            is_admin() ||
            wp_doing_ajax() ||
            wp_doing_cron() ||
            defined('REST_REQUEST') ||
            (defined('WP_CLI') && WP_CLI)
        ) {
            return;
        }

        $options = get_option('bspt_options', []);
        $api_key = $options['api_key'] ?? '';

        if (empty($api_key)) {
            return;
        }

        // Get API URL from options or use default
        $api_url = $options['api_url'] ?? 'https://locus-api.bot.spot';
        $api_url = rtrim($api_url, '/');

        // Collect tracking data
        $data = [
            'path'       => $this->get_request_path(),
            'user_agent' => $this->get_user_agent(),
            'referrer'   => $this->get_referrer(),
        ];

        // Fire-and-forget - non-blocking request
        wp_remote_post($api_url . $this->endpoint, [
            'blocking'    => false,
            'timeout'     => 0.01,
            'data_format' => 'body',
            'body'        => wp_json_encode($data),
            'headers'     => [
                'Content-Type' => 'application/json',
                'X-API-Key'    => $api_key,
            ],
        ]);
    }

    /**
     * Get sanitized request path.
     *
     * @return string
     */
    private function get_request_path() {
        $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        // Remove query string for cleaner grouping
        $path = strtok($path, '?');
        return sanitize_text_field($path);
    }

    /**
     * Get user agent string.
     *
     * @return string
     */
    private function get_user_agent() {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        // Truncate to prevent abuse
        return substr(sanitize_text_field($ua), 0, 512);
    }

    /**
     * Get referrer URL.
     *
     * @return string
     */
    private function get_referrer() {
        $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        return esc_url_raw($ref);
    }
}
