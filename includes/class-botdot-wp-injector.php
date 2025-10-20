<?php
/**
 * JSON-LD injector class for the BotDot WP plugin
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
 * JSON-LD injector class for the BotDot WP plugin.
 *
 * This class handles injecting JSON-LD into page headers.
 *
 * @since      0.1.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Injector {

    /**
     * The plugin name.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $plugin_name    The plugin name.
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $version    The plugin version.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    string    $plugin_name    The name of this plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Inject JSON-LD into page head
     *
     * This is the main injection method called by wp_head hook
     *
     * @since    0.1.0
     */
    public function inject_json_ld() {
        // Check if injection should happen
        if (!$this->should_inject()) {
            return;
        }

        // Get current URL path
        $url_path = $this->get_current_url_path();

        if (empty($url_path)) {
            BotDot_WP_Logger::log_debug('Could not determine URL path for injection');
            return;
        }

        // Apply filter to allow modification of URL path
        $url_path = apply_filters('botdot_wp_url_path', $url_path);

        // Fetch JSON-LD from mirror domain
        $json_ld = BotDot_WP_Fetcher::fetch_json_ld($url_path);

        // Handle fetch errors
        if (is_wp_error($json_ld)) {
            BotDot_WP_Logger::log_error(sprintf(
                'Failed to fetch JSON-LD for path %s: %s',
                $url_path,
                $json_ld->get_error_message()
            ));
            return;
        }

        // Inject the JSON-LD
        $this->output_json_ld($json_ld);

        // Log success if debug mode is enabled
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug(sprintf(
                'Successfully injected JSON-LD for path: %s',
                $url_path
            ));
        }
    }

    /**
     * Check if JSON-LD should be injected on current page
     *
     * @since    0.1.0
     * @access   private
     * @return   bool    True if should inject, false otherwise.
     */
    private function should_inject() {
        // Check if plugin is enabled
        if (!BotDot_WP_Options::get('enabled')) {
            return false;
        }

        // Don't inject on admin pages
        if (is_admin()) {
            return false;
        }

        // Don't inject on 404 pages
        if (is_404()) {
            return false;
        }

        // Don't inject on search results
        if (is_search()) {
            return false;
        }

        // Don't inject on archive pages (unless specifically allowed)
        if (is_archive() && !apply_filters('botdot_wp_inject_on_archives', false)) {
            return false;
        }

        // Check if current post type is allowed
        if (is_singular()) {
            $post_type = get_post_type();
            $allowed_post_types = BotDot_WP_Options::get('inject_on_post_types', array());

            if (!in_array($post_type, $allowed_post_types)) {
                return false;
            }

            // Check if current page is excluded
            $excluded_ids = BotDot_WP_Options::get('exclude_page_ids', array());
            $current_id = get_the_ID();

            if (in_array($current_id, $excluded_ids)) {
                return false;
            }
        }

        // Apply filter to allow custom injection logic
        return apply_filters('botdot_wp_should_inject', true);
    }

    /**
     * Get current URL path
     *
     * @since    0.1.0
     * @access   private
     * @return   string    The current URL path.
     */
    private function get_current_url_path() {
        global $wp;

        // Get the current request URI
        $current_url = home_url(add_query_arg(array(), $wp->request));

        // Parse URL to get path
        $parsed = parse_url($current_url);
        $path = isset($parsed['path']) ? $parsed['path'] : '/';

        // Remove home path if WordPress is in a subdirectory
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path && $home_path !== '/') {
            $path = str_replace($home_path, '', $path);
        }

        // Ensure path starts with /
        if (!empty($path) && $path[0] !== '/') {
            $path = '/' . $path;
        }

        // Default to / if empty
        if (empty($path)) {
            $path = '/';
        }

        return $path;
    }

    /**
     * Output JSON-LD script tag
     *
     * @since    0.1.0
     * @access   private
     * @param    array    $json_ld    The JSON-LD data.
     */
    private function output_json_ld($json_ld) {
        if (empty($json_ld)) {
            return;
        }

        // Encode JSON-LD
        $json_encoded = wp_json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json_encoded === false) {
            BotDot_WP_Logger::log_error('Failed to encode JSON-LD for output');
            return;
        }

        // Output script tag
        echo "\n<!-- BotDot WP JSON-LD -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo $json_encoded . "\n";
        echo '</script>' . "\n";
        echo "<!-- /BotDot WP JSON-LD -->\n";
    }
}
