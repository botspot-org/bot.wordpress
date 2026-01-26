<?php
/**
 * The public-facing functionality of the plugin
 *
 * @link       https://botdot.ai
 * @since      0.2.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/public
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The public-facing functionality of the plugin.
 *
 * Handles appendix injection and shortcodes.
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/public
 * @author     BotDot Team
 */
class BotDot_WP_Public {

    /**
     * The plugin name.
     *
     * @since    0.2.0
     * @access   private
     * @var      string    $plugin_name    The plugin name.
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    0.2.0
     * @access   private
     * @var      string    $version    The plugin version.
     */
    private $version;

    /**
     * Whether shortcode was used on current page.
     *
     * @since    0.2.0
     * @access   private
     * @var      bool    $shortcode_used    Shortcode usage flag.
     */
    private $shortcode_used = false;

    /**
     * Whether appendix has already been injected on current request.
     *
     * @since    0.6.6
     * @access   private
     * @var      bool    $appendix_injected    Injection flag.
     */
    private $appendix_injected = false;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.2.0
     * @param    string    $plugin_name    The name of this plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Log debug message if debug mode is enabled
     *
     * @since    0.6.6
     * @access   private
     * @param    string    $message    The message to log.
     */
    private function log_debug($message) {
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('[Appendix] ' . $message);
        }
    }

    /**
     * Log error message (always logged)
     *
     * @since    0.6.6
     * @access   private
     * @param    string    $message    The message to log.
     */
    private function log_error($message) {
        BotDot_WP_Logger::log_error('[Appendix] ' . $message);
    }

    /**
     * Register shortcode for manual appendix placement
     *
     * @since    0.2.0
     */
    public function register_shortcode() {
        add_shortcode('botdot_appendix', array($this, 'render_appendix_shortcode'));
    }

    /**
     * Register WPBakery (Visual Composer) element
     *
     * @since    0.2.0
     */
    public function register_wpbakery_element() {
        // Check if WPBakery is active
        if (!function_exists('vc_map')) {
            return;
        }

        vc_map(array(
            'name' => __('BotSpot Appendix', 'botdot-wp'),
            'base' => 'botdot_appendix',
            'description' => __('Insert AI-discoverable appendix content', 'botdot-wp'),
            'category' => __('Content', 'botdot-wp'),
            'icon' => 'icon-wpb-botdot',
            'params' => array(
                array(
                    'type' => 'textfield',
                    'heading' => __('Appendix Title', 'botdot-wp'),
                    'param_name' => 'title',
                    'description' => __('Title shown in the appendix section', 'botdot-wp'),
                    'value' => BotDot_WP_Options::get('appendix_title', 'AI Appendix'),
                ),
                array(
                    'type' => 'dropdown',
                    'heading' => __('Open by Default', 'botdot-wp'),
                    'param_name' => 'open',
                    'description' => __('Whether the appendix should be expanded by default', 'botdot-wp'),
                    'value' => array(
                        __('No', 'botdot-wp') => 'false',
                        __('Yes', 'botdot-wp') => 'true',
                    ),
                    'std' => BotDot_WP_Options::get('appendix_open_default', false) ? 'true' : 'false',
                ),
            ),
        ));
    }

    /**
     * Add TinyMCE button for Classic Editor
     *
     * @since    0.2.0
     */
    public function add_tinymce_button() {
        // Check user permissions
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
            return;
        }

        // Check if rich editing is enabled
        if (get_user_option('rich_editing') !== 'true') {
            return;
        }

        // Add button to editor
        add_filter('mce_buttons', array($this, 'register_tinymce_button'));
        add_filter('mce_external_plugins', array($this, 'register_tinymce_plugin'));
    }

    /**
     * Register TinyMCE button
     *
     * @since    0.2.0
     * @param    array    $buttons    Existing buttons.
     * @return   array                Modified buttons array.
     */
    public function register_tinymce_button($buttons) {
        array_push($buttons, 'botdot_appendix');
        return $buttons;
    }

    /**
     * Register TinyMCE plugin
     *
     * @since    0.2.0
     * @param    array    $plugins    Existing plugins.
     * @return   array                Modified plugins array.
     */
    public function register_tinymce_plugin($plugins) {
        $plugins['botdot_appendix'] = BOTDOT_WP_PLUGIN_URL . 'public/js/botdot-wp-tinymce.js';
        return $plugins;
    }

    /**
     * Register Gutenberg block
     *
     * @since    0.2.0
     */
    public function register_gutenberg_block() {
        // Check if Gutenberg is active
        if (!function_exists('register_block_type')) {
            return;
        }

        // Register the block server-side
        register_block_type('botdot-wp/appendix', array(
            'render_callback' => array($this, 'render_appendix_shortcode'),
            'attributes' => array(
                'title' => array(
                    'type' => 'string',
                    'default' => BotDot_WP_Options::get('appendix_title', 'AI Appendix'),
                ),
                'open' => array(
                    'type' => 'boolean',
                    'default' => BotDot_WP_Options::get('appendix_open_default', false),
                ),
            ),
        ));
    }

    /**
     * Enqueue Gutenberg block assets
     *
     * @since    0.2.0
     */
    public function enqueue_gutenberg_assets() {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . '-gutenberg',
            BOTDOT_WP_PLUGIN_URL . 'public/js/botdot-wp-gutenberg.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
            $this->version,
            true
        );

        // Pass plugin data to JavaScript
        wp_localize_script($this->plugin_name . '-gutenberg', 'botdotWP', array(
            'defaultTitle' => BotDot_WP_Options::get('appendix_title', 'AI Appendix'),
            'defaultOpen' => BotDot_WP_Options::get('appendix_open_default', false),
        ));
    }

    /**
     * Render appendix via shortcode
     *
     * @since    0.2.0
     * @param    array     $atts    Shortcode attributes.
     * @return   string             Rendered appendix HTML.
     */
    public function render_appendix_shortcode($atts) {
        $this->log_debug('Shortcode [botdot_appendix] invoked');

        // Mark that shortcode was used
        $this->shortcode_used = true;

        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'title' => BotDot_WP_Options::get('appendix_title', 'AI Appendix'),
            'open' => BotDot_WP_Options::get('appendix_open_default', false),
        ), $atts, 'botdot_appendix');

        // Convert open attribute to boolean
        $atts['open'] = filter_var($atts['open'], FILTER_VALIDATE_BOOLEAN);

        // Render the appendix
        $result = $this->render_appendix($atts);

        if (empty($result)) {
            $this->log_debug('Shortcode returned empty result');
        } else {
            $this->log_debug(sprintf('Shortcode rendered successfully (%d bytes)', strlen($result)));
        }

        return $result;
    }

    /**
     * Check if current page is a valid page for appendix injection
     *
     * Handles homepage, front page, and singular pages.
     *
     * @since    0.6.6
     * @access   private
     * @return   bool    True if valid page type, false otherwise.
     */
    private function is_valid_page_type() {
        // Allow front page (static or blog)
        if (is_front_page()) {
            $this->log_debug('Page type check: is_front_page() = true');
            return true;
        }

        // Allow home page (blog posts page)
        if (is_home()) {
            $this->log_debug('Page type check: is_home() = true');
            return true;
        }

        // Allow singular pages (posts, pages, custom post types)
        if (is_singular()) {
            $this->log_debug('Page type check: is_singular() = true');
            return true;
        }

        $this->log_debug(sprintf(
            'Page type check FAILED: is_front_page=%s, is_home=%s, is_singular=%s',
            is_front_page() ? 'true' : 'false',
            is_home() ? 'true' : 'false',
            is_singular() ? 'true' : 'false'
        ));

        return false;
    }

    /**
     * Filter content to add appendix at bottom
     *
     * @since    0.2.0
     * @param    string    $content    The post content.
     * @return   string                Modified content with appendix.
     */
    public function filter_content($content) {
        $this->log_debug('filter_content() called');

        // Don't add if already injected
        if ($this->appendix_injected) {
            $this->log_debug('SKIP: Appendix already injected this request');
            return $content;
        }

        // Don't add if not enabled
        if (!BotDot_WP_Options::get('appendix_enabled')) {
            $this->log_debug('SKIP: appendix_enabled is false');
            return $content;
        }

        // Don't add if position is shortcode-only
        $position = BotDot_WP_Options::get('appendix_position');
        if ($position === 'shortcode') {
            $this->log_debug('SKIP: appendix_position is "shortcode" (manual placement only)');
            return $content;
        }

        // Don't add if auto placement is set to footer
        $auto_placement = BotDot_WP_Options::get('appendix_auto_placement');
        if ($auto_placement !== 'bottom') {
            $this->log_debug(sprintf('SKIP: appendix_auto_placement is "%s" (not "bottom")', $auto_placement));
            return $content;
        }

        // Check if manual placement is used (block or shortcode)
        if ($this->has_manual_placement($content)) {
            $this->log_debug('SKIP: Manual placement detected (shortcode or block)');
            return $content;
        }

        // Don't add on feeds
        if (is_feed()) {
            $this->log_debug('SKIP: is_feed() = true');
            return $content;
        }

        // Check page type (singular, front page, or home)
        if (!$this->is_valid_page_type()) {
            $this->log_debug('SKIP: Not a valid page type for appendix');
            return $content;
        }

        // Check if injection should happen on this page
        if (!$this->should_inject_on_current_page()) {
            $this->log_debug('SKIP: should_inject_on_current_page() returned false');
            return $content;
        }

        $this->log_debug('All checks passed, rendering appendix for content filter');

        // Render and append
        $appendix_html = $this->render_appendix();

        if (!empty($appendix_html)) {
            $this->appendix_injected = true;
            $content .= $appendix_html;
            $this->log_debug(sprintf('Appendix injected via content filter (%d bytes)', strlen($appendix_html)));
        } else {
            $this->log_debug('Appendix render returned empty');
        }

        return $content;
    }

    /**
     * Inject appendix above footer
     *
     * @since    0.3.0
     */
    public function inject_above_footer() {
        $this->log_debug('inject_above_footer() called');

        // Don't add if already injected
        if ($this->appendix_injected) {
            $this->log_debug('SKIP: Appendix already injected this request');
            return;
        }

        // Don't add if not enabled
        if (!BotDot_WP_Options::get('appendix_enabled')) {
            $this->log_debug('SKIP: appendix_enabled is false');
            return;
        }

        // Don't add if position is shortcode-only
        $position = BotDot_WP_Options::get('appendix_position');
        if ($position === 'shortcode') {
            $this->log_debug('SKIP: appendix_position is "shortcode" (manual placement only)');
            return;
        }

        // Only inject if auto placement is set to footer
        $auto_placement = BotDot_WP_Options::get('appendix_auto_placement');
        if ($auto_placement !== 'above_footer') {
            $this->log_debug(sprintf('SKIP: appendix_auto_placement is "%s" (not "above_footer")', $auto_placement));
            return;
        }

        // Don't add on feeds
        if (is_feed()) {
            $this->log_debug('SKIP: is_feed() = true');
            return;
        }

        // Check page type (singular, front page, or home)
        if (!$this->is_valid_page_type()) {
            $this->log_debug('SKIP: Not a valid page type for appendix');
            return;
        }

        // Check if manual placement is used
        global $post;
        if ($post && $this->has_manual_placement($post->post_content)) {
            $this->log_debug('SKIP: Manual placement detected (shortcode or block)');
            return;
        }

        // Check if injection should happen on this page
        if (!$this->should_inject_on_current_page()) {
            $this->log_debug('SKIP: should_inject_on_current_page() returned false');
            return;
        }

        $this->log_debug('All checks passed, rendering appendix for footer injection');

        // Render and output
        $appendix_html = $this->render_appendix();

        if (!empty($appendix_html)) {
            $this->appendix_injected = true;
            echo $appendix_html;
            $this->log_debug(sprintf('Appendix injected via footer hook (%d bytes)', strlen($appendix_html)));
        } else {
            $this->log_debug('Appendix render returned empty');
        }
    }

    /**
     * Check if manual placement (block or shortcode) is used
     *
     * @since    0.3.0
     * @access   private
     * @param    string    $content    The post content.
     * @return   bool                  True if manual placement detected.
     */
    private function has_manual_placement($content) {
        // Check for Gutenberg block
        if (function_exists('has_block') && has_block('botdot-wp/appendix', $content)) {
            $this->log_debug('Manual placement: Gutenberg block detected');
            return true;
        }

        // Check for shortcode
        if (has_shortcode($content, 'botdot_appendix')) {
            $this->log_debug('Manual placement: Shortcode detected in content');
            return true;
        }

        // Check if shortcode was already rendered
        if ($this->shortcode_used) {
            $this->log_debug('Manual placement: Shortcode was already rendered');
            return true;
        }

        return false;
    }

    /**
     * Check if injection should happen on the current page
     *
     * @since    0.3.0
     * @access   private
     * @return   bool    True if should inject, false otherwise.
     */
    private function should_inject_on_current_page() {
        $current_id = get_the_ID();

        // For homepage without a static page, there's no post ID
        if (!$current_id) {
            // Allow injection on front page even without post ID
            if (is_front_page() || is_home()) {
                $this->log_debug('should_inject: No post ID but is front_page/home, allowing injection');
                return true;
            }
            $this->log_debug('should_inject: No post ID and not front_page/home, denying injection');
            return false;
        }

        $this->log_debug(sprintf('should_inject: Checking page ID %d', $current_id));

        // Check page injection status table
        $injection_status = BotDot_WP_Options::get('page_injection_status', array());

        // If the page has an explicit status set, use that
        if (isset($injection_status[$current_id])) {
            $enabled = (bool) $injection_status[$current_id];
            $this->log_debug(sprintf(
                'should_inject: Page %d has explicit status: %s',
                $current_id,
                $enabled ? 'ENABLED' : 'DISABLED'
            ));
            return $enabled;
        }

        // Otherwise, check if current post type is allowed (default behavior)
        $post_type = get_post_type();
        $allowed_types = BotDot_WP_Options::get('appendix_on_post_types', array('post', 'page'));

        $this->log_debug(sprintf(
            'should_inject: Post type "%s", allowed types: [%s]',
            $post_type,
            implode(', ', $allowed_types)
        ));

        if (!in_array($post_type, $allowed_types)) {
            $this->log_debug(sprintf('should_inject: Post type "%s" not in allowed list', $post_type));
            return false;
        }

        // Check legacy exclude list for backwards compatibility
        $excluded_ids = BotDot_WP_Options::get('exclude_page_ids', array());
        if (in_array($current_id, $excluded_ids)) {
            $this->log_debug(sprintf('should_inject: Page %d is in legacy exclude list', $current_id));
            return false;
        }

        $this->log_debug(sprintf('should_inject: Page %d passed all checks', $current_id));
        return true;
    }

    /**
     * Render the appendix
     *
     * @since    0.2.0
     * @access   private
     * @param    array     $args    Optional. Rendering arguments.
     * @return   string             Rendered appendix HTML.
     */
    private function render_appendix($args = array()) {
        $this->log_debug('render_appendix() called');

        // Get current URL path
        global $wp;
        $current_url = home_url(add_query_arg(array(), $wp->request));
        $parsed = parse_url($current_url);
        $path = isset($parsed['path']) ? $parsed['path'] : '/';

        $this->log_debug(sprintf('URL parsing: current_url=%s, parsed_path=%s', $current_url, $path));

        // Remove home path if WordPress is in a subdirectory
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path && $home_path !== '/') {
            $original_path = $path;
            $path = str_replace($home_path, '', $path);
            $this->log_debug(sprintf('Subdirectory adjustment: home_path=%s, %s -> %s', $home_path, $original_path, $path));
        }

        // Ensure path starts with /
        if (!empty($path) && $path[0] !== '/') {
            $path = '/' . $path;
        }

        // Default to / if empty
        if (empty($path)) {
            $path = '/';
        }

        // Apply filter to allow modification of URL path
        $original_path = $path;
        $path = apply_filters('botdot_wp_appendix_path', $path);
        if ($path !== $original_path) {
            $this->log_debug(sprintf('Path modified by filter: %s -> %s', $original_path, $path));
        }

        $this->log_debug(sprintf('Final path for appendix fetch: %s', $path));

        // Check mirror domain configuration
        $mirror_domain = BotDot_WP_Options::get('mirror_domain');
        if (empty($mirror_domain)) {
            $this->log_error('Cannot fetch appendix: mirror_domain is not configured');
            return '';
        }

        $this->log_debug(sprintf('Fetching appendix from mirror domain: %s', $mirror_domain));

        // Fetch appendix data
        $appendix_data = BotDot_WP_Appendix_Fetcher::fetch_appendix($path);

        // Handle fetch errors
        if (is_wp_error($appendix_data)) {
            $this->log_error(sprintf(
                'Failed to fetch appendix for path %s: %s (code: %s)',
                $path,
                $appendix_data->get_error_message(),
                $appendix_data->get_error_code()
            ));
            return '';
        }

        // Check for empty response
        if (empty($appendix_data)) {
            $this->log_error(sprintf('Appendix fetch returned empty data for path: %s', $path));
            return '';
        }

        $this->log_debug(sprintf(
            'Appendix fetched successfully: %d bytes, type: %s',
            strlen($appendix_data),
            gettype($appendix_data)
        ));

        // Render the appendix
        $rendered = BotDot_WP_Appendix_Renderer::render($appendix_data, $args);

        if (empty($rendered)) {
            $this->log_error('Appendix renderer returned empty output');
            return '';
        }

        $this->log_debug(sprintf('Appendix rendered: %d bytes', strlen($rendered)));

        return $rendered;
    }

    /**
     * Enqueue public styles
     *
     * @since    0.2.0
     */
    public function enqueue_styles() {
        // Only enqueue if appendix is enabled
        if (!BotDot_WP_Options::get('appendix_enabled')) {
            return;
        }

        // Get dynamic CSS version (cache buster timestamp or fall back to plugin version)
        $cache_buster = BotDot_WP_Options::get('css_cache_buster', 0);
        $css_version = $cache_buster > 0 ? $this->version . '.' . $cache_buster : $this->version;

        wp_enqueue_style(
            $this->plugin_name . '-appendix',
            BOTDOT_WP_PLUGIN_URL . 'public/css/botdot-wp-appendix.css',
            array(),
            $css_version,
            'all'
        );
    }
}
