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
            'name' => __('BotDot Appendix', 'botdot-wp'),
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
        return $this->render_appendix($atts);
    }

    /**
     * Filter content to add appendix at bottom
     *
     * @since    0.2.0
     * @param    string    $content    The post content.
     * @return   string                Modified content with appendix.
     */
    public function filter_content($content) {
        // Don't add if not enabled
        if (!BotDot_WP_Options::get('appendix_enabled')) {
            return $content;
        }

        // Don't add if position is shortcode-only
        if (BotDot_WP_Options::get('appendix_position') === 'shortcode') {
            return $content;
        }

        // Don't add if auto placement is set to footer
        if (BotDot_WP_Options::get('appendix_auto_placement') !== 'bottom') {
            return $content;
        }

        // Check if manual placement is used (block or shortcode)
        if ($this->has_manual_placement($content)) {
            return $content;
        }

        // Don't add on excerpts or feeds
        if (!is_singular() || is_feed()) {
            return $content;
        }

        // Check if injection should happen on this page
        if (!$this->should_inject_on_current_page()) {
            return $content;
        }

        // Render and append
        $appendix_html = $this->render_appendix();

        if (!empty($appendix_html)) {
            $content .= $appendix_html;
        }

        return $content;
    }

    /**
     * Inject appendix above footer
     *
     * @since    0.3.0
     */
    public function inject_above_footer() {
        // Don't add if not enabled
        if (!BotDot_WP_Options::get('appendix_enabled')) {
            return;
        }

        // Don't add if position is shortcode-only
        if (BotDot_WP_Options::get('appendix_position') === 'shortcode') {
            return;
        }

        // Only inject if auto placement is set to footer
        if (BotDot_WP_Options::get('appendix_auto_placement') !== 'above_footer') {
            return;
        }

        // Don't add on excerpts or feeds
        if (!is_singular() || is_feed()) {
            return;
        }

        // Check if manual placement is used
        global $post;
        if ($post && $this->has_manual_placement($post->post_content)) {
            return;
        }

        // Check if injection should happen on this page
        if (!$this->should_inject_on_current_page()) {
            return;
        }

        // Render and output
        echo $this->render_appendix();
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
            return true;
        }

        // Check for shortcode
        if (has_shortcode($content, 'botdot_appendix')) {
            return true;
        }

        // Check if shortcode was already rendered
        if ($this->shortcode_used) {
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

        if (!$current_id) {
            return false;
        }

        // Check page injection status table
        $injection_status = BotDot_WP_Options::get('page_injection_status', array());

        // If the page has an explicit status set, use that
        if (isset($injection_status[$current_id])) {
            return (bool) $injection_status[$current_id];
        }

        // Otherwise, check if current post type is allowed (default behavior)
        $post_type = get_post_type();
        $allowed_types = BotDot_WP_Options::get('appendix_on_post_types', array('post', 'page'));

        if (!in_array($post_type, $allowed_types)) {
            return false;
        }

        // Check legacy exclude list for backwards compatibility
        $excluded_ids = BotDot_WP_Options::get('exclude_page_ids', array());
        if (in_array($current_id, $excluded_ids)) {
            return false;
        }

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
        // Get current URL path
        global $wp;
        $current_url = home_url(add_query_arg(array(), $wp->request));
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

        // Apply filter to allow modification of URL path
        $path = apply_filters('botdot_wp_appendix_path', $path);

        // Fetch appendix data
        $appendix_data = BotDot_WP_Appendix_Fetcher::fetch_appendix($path);

        // Handle fetch errors
        if (is_wp_error($appendix_data)) {
            BotDot_WP_Logger::log_error(sprintf(
                'Failed to fetch appendix for path %s: %s',
                $path,
                $appendix_data->get_error_message()
            ));
            return '';
        }

        // Render the appendix
        return BotDot_WP_Appendix_Renderer::render($appendix_data, $args);
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

        wp_enqueue_style(
            $this->plugin_name . '-appendix',
            BOTDOT_WP_PLUGIN_URL . 'public/css/botdot-wp-appendix.css',
            array(),
            $this->version,
            'all'
        );
    }
}
