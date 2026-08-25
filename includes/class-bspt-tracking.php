<?php
/**
 * Server-side page view tracking for bot analytics.
 *
 * Fires on every frontend page view and sends a non-blocking request to the
 * BotSpot API. Server-side because LLM and SEO crawlers do not execute
 * JavaScript, so a browser pixel misses the traffic this exists to measure.
 *
 * @package Bspt
 * @since 3.7.5
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tracking class for server-side analytics.
 */
class Bspt_Tracking {

    /**
     * API endpoint for tracking, relative to the Locus API base URL.
     *
     * @var string
     */
    private $endpoint = '/api/v1/t';

    /**
     * Initialize tracking hooks.
     */
    public function __construct() {
        add_action('template_redirect', [$this, 'track_page_view'], 1);
    }

    /**
     * Track a frontend page view.
     */
    public function track_page_view() {
        if (
            is_admin() ||
            wp_doing_ajax() ||
            wp_doing_cron() ||
            defined('REST_REQUEST') ||
            (defined('WP_CLI') && WP_CLI)
        ) {
            return;
        }

        $api_key = Bspt_Options::get('api_key');

        if (empty($api_key)) {
            return;
        }

        $api_url = rtrim(Bspt_Options::get_locus_api_url(), '/');

        $data = [
            'path'       => $this->get_request_path(),
            'user_agent' => $this->get_user_agent(),
            'referrer'   => $this->get_referrer(),
        ];

        // blocking => false with a near-zero timeout: the socket is opened and
        // abandoned, so a slow or down API never delays the page.
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
     * Get the request path without its query string.
     *
     * @return string
     */
    private function get_request_path() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recording -- read-only page view, no state change
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $path = strtok($uri, '?');
        return false === $path ? '/' : $path;
    }

    /**
     * Get the user agent string.
     *
     * @return string
     */
    private function get_user_agent() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return '';
        }
        $ua = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        return substr($ua, 0, 512);
    }

    /**
     * Get the referrer URL.
     *
     * @return string
     */
    private function get_referrer() {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return '';
        }
        return esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
    }
}
