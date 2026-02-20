<?php
/**
 * Unified content injector for the BotDot WP plugin
 *
 * Injects both JSON-LD and appendix HTML from a single fetch.
 *
 * @link       https://bot.spot
 * @since      1.0.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Unified content injector for JSON-LD and appendix HTML.
 *
 * Replaces the old BotDot_WP_Injector and appendix injection logic
 * from BotDot_WP_Public with a single class that handles both.
 *
 * @since      1.0.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Content_Injector {

    /**
     * The plugin name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $version;

    /**
     * Whether appendix has already been injected on current request.
     *
     * @since    1.0.0
     * @access   private
     * @var      bool
     */
    private $appendix_injected = false;

    /**
     * Whether shortcode was used on current page.
     *
     * @since    1.0.0
     * @access   private
     * @var      bool
     */
    private $shortcode_used = false;

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    The plugin name.
     * @param    string    $version        The plugin version.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Inject JSON-LD into wp_head
     *
     * Hook: wp_head (priority 1)
     *
     * @since    1.0.0
     */
    public function inject_jsonld() {
        if (!$this->should_inject()) {
            return;
        }

        $path = $this->get_current_url_path();
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data['jsonld'] === null) {
            return;
        }

        $jsonld = $data['jsonld'];

        // Apply filter
        $jsonld = apply_filters('botdot_wp_appendix_jsonld', $jsonld);

        if (empty($jsonld)) {
            return;
        }

        // Output as JSON-LD script tag
        $json_string = is_string($jsonld) ? $jsonld : wp_json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        echo "\n<!-- BotSpot JSON-LD -->\n";
        echo '<script type="application/ld+json">' . $json_string . '</script>';
        echo "\n<!-- /BotSpot JSON-LD -->\n";

        $this->log_debug('JSON-LD injected into wp_head');
    }

    /**
     * Inject appendix content via the_content filter
     *
     * Hook: the_content (priority 20)
     *
     * @since    1.0.0
     * @param    string    $content    The post content.
     * @return   string                Modified content with appendix.
     */
    public function inject_appendix_content($content) {
        // Don't add if already injected
        if ($this->appendix_injected) {
            return $content;
        }

        if (!$this->should_inject()) {
            return $content;
        }

        $position = BotDot_WP_Options::get('injection_position', 'bottom');

        // Only inject via content filter for 'bottom' position
        if ($position !== 'bottom') {
            return $content;
        }

        // Check for manual placement
        if ($this->has_manual_placement($content)) {
            return $content;
        }

        // Don't add on feeds
        if (is_feed()) {
            return $content;
        }

        $path = $this->get_current_url_path();
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data['html'] === null) {
            return $content;
        }

        $html = $data['html'];

        // Apply filter
        $html = apply_filters('botdot_wp_appendix_html', $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            $content .= $html;
            $this->log_debug(sprintf('Appendix injected via content filter (%d bytes)', strlen($html)));
        }

        return $content;
    }

    /**
     * Inject appendix above footer
     *
     * Hook: wp_footer (priority 5)
     *
     * @since    1.0.0
     */
    public function inject_above_footer() {
        if ($this->appendix_injected) {
            return;
        }

        if (!$this->should_inject()) {
            return;
        }

        $position = BotDot_WP_Options::get('injection_position', 'bottom');

        // Only inject via footer for 'above_footer' position
        if ($position !== 'above_footer') {
            return;
        }

        // Check for manual placement
        global $post;
        if ($post && $this->has_manual_placement($post->post_content)) {
            return;
        }

        // Don't add on feeds
        if (is_feed()) {
            return;
        }

        $path = $this->get_current_url_path();
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data['html'] === null) {
            return;
        }

        $html = $data['html'];

        // Apply filter
        $html = apply_filters('botdot_wp_appendix_html', $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            echo $html;
            $this->log_debug(sprintf('Appendix injected via footer hook (%d bytes)', strlen($html)));
        }
    }

    /**
     * Render shortcode
     *
     * @since    1.0.0
     * @param    array     $atts    Shortcode attributes.
     * @return   string             Rendered appendix HTML.
     */
    public function render_shortcode($atts) {
        $this->shortcode_used = true;

        if (!$this->should_inject()) {
            return '';
        }

        $path = $this->get_current_url_path();
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data['html'] === null) {
            return '';
        }

        $html = $data['html'];

        // Apply filter
        $html = apply_filters('botdot_wp_appendix_html', $html);

        $this->appendix_injected = true;

        return $html;
    }

    /**
     * Check if injection should happen on the current page
     *
     * @since    1.0.0
     * @return   bool    True if should inject, false otherwise.
     */
    private function should_inject() {
        // Check global injection toggle
        if (!BotDot_WP_Options::get('injection_enabled')) {
            return false;
        }

        // Don't inject in admin
        if (is_admin()) {
            return false;
        }

        // Don't inject on 404 or search
        if (is_404() || is_search()) {
            return false;
        }

        // Check valid page type
        if (!$this->is_valid_page_type()) {
            return false;
        }

        // Check post type
        $post_type = get_post_type();
        if ($post_type) {
            $allowed_types = BotDot_WP_Options::get('inject_on_post_types', array('post', 'page'));
            if (!in_array($post_type, $allowed_types)) {
                // Allow front page even if post type doesn't match
                if (!is_front_page()) {
                    return false;
                }
            }
        }

        // Check per-page override
        $current_id = get_the_ID();
        if ($current_id) {
            $injection_status = BotDot_WP_Options::get('page_injection_status', array());
            if (isset($injection_status[$current_id])) {
                return (bool) $injection_status[$current_id];
            }
        }

        // Apply filter
        return apply_filters('botdot_wp_should_inject', true);
    }

    /**
     * Check if current page is a valid page type for injection
     *
     * @since    1.0.0
     * @access   private
     * @return   bool
     */
    private function is_valid_page_type() {
        if (is_front_page() || is_home() || is_singular()) {
            return true;
        }

        return false;
    }

    /**
     * Check if manual placement (block or shortcode) is used
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $content    The post content.
     * @return   bool
     */
    private function has_manual_placement($content) {
        if (function_exists('has_block') && has_block('botdot-wp/appendix', $content)) {
            return true;
        }

        if (has_shortcode($content, 'botdot_appendix')) {
            return true;
        }

        if ($this->shortcode_used) {
            return true;
        }

        return false;
    }

    /**
     * Get the current URL path relative to home
     *
     * @since    1.0.0
     * @access   private
     * @return   string    The URL path.
     */
    private function get_current_url_path() {
        global $wp;
        $current_url = home_url(add_query_arg(array(), $wp->request));
        $parsed = parse_url($current_url);
        $path = isset($parsed['path']) ? $parsed['path'] : '/';

        // Remove home path if WordPress is in a subdirectory
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path && $home_path !== '/') {
            $path = str_replace($home_path, '', $path);
        }

        if (!empty($path) && $path[0] !== '/') {
            $path = '/' . $path;
        }

        if (empty($path)) {
            $path = '/';
        }

        // Apply filter
        $path = apply_filters('botdot_wp_url_path', $path);

        return $path;
    }

    /**
     * Log debug message
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    private function log_debug($message) {
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('[ContentInjector] ' . $message);
        }
    }
}
