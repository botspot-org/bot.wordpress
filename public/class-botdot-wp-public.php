<?php
/**
 * The public-facing functionality of the plugin
 *
 * @link       https://bot.spot
 * @since      0.2.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/public
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * The public-facing functionality of the plugin.
 *
 * Handles shortcode registration, Gutenberg block, WPBakery, TinyMCE,
 * and style enqueuing. Content injection is delegated to BotDot_WP_Content_Injector.
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/public
 * @author     BotDot Team
 */
class BotDot_WP_Public
{
    /**
     * The plugin name.
     *
     * @since    0.2.0
     * @access   private
     * @var      string
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    0.2.0
     * @access   private
     * @var      string
     */
    private $version;

    /**
     * Content injector instance for shortcode delegation.
     *
     * @since    1.0.0
     * @access   private
     * @var      BotDot_WP_Content_Injector|null
     */
    private $content_injector = null;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.2.0
     * @param    string                          $plugin_name        The name of this plugin.
     * @param    string                          $version            The version of this plugin.
     * @param    BotDot_WP_Content_Injector|null $content_injector   Optional shared content injector instance.
     */
    public function __construct($plugin_name, $version, $content_injector = null)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->content_injector = $content_injector;
    }

    /**
     * Get the content injector instance
     *
     * @since    1.0.0
     * @return   BotDot_WP_Content_Injector
     */
    private function get_content_injector()
    {
        if ($this->content_injector === null) {
            $this->content_injector = new BotDot_WP_Content_Injector($this->plugin_name, $this->version);
        }
        return $this->content_injector;
    }

    /**
     * Register shortcode for manual appendix placement
     *
     * @since    0.2.0
     */
    public function register_shortcode()
    {
        add_shortcode("botdot_appendix", [$this, "render_appendix_shortcode"]);
    }

    /**
     * Register WPBakery (Visual Composer) element
     *
     * @since    0.2.0
     */
    public function register_wpbakery_element()
    {
        if (!function_exists("vc_map")) {
            return;
        }

        vc_map([
            "name" => __("BotSpot Appendix", "botdot-wp"),
            "base" => "botdot_appendix",
            "description" => __("Insert AI-discoverable appendix content", "botdot-wp"),
            "category" => __("Content", "botdot-wp"),
            "icon" => "icon-wpb-botdot",
            "params" => [],
        ]);
    }

    /**
     * Add TinyMCE button for Classic Editor
     *
     * @since    0.2.0
     */
    public function add_tinymce_button()
    {
        if (!current_user_can("edit_posts") && !current_user_can("edit_pages")) {
            return;
        }

        if (get_user_option("rich_editing") !== "true") {
            return;
        }

        add_filter("mce_buttons", [$this, "register_tinymce_button"]);
        add_filter("mce_external_plugins", [$this, "register_tinymce_plugin"]);
    }

    /**
     * Register TinyMCE button
     *
     * @since    0.2.0
     * @param    array    $buttons    Existing buttons.
     * @return   array
     */
    public function register_tinymce_button($buttons)
    {
        array_push($buttons, "botdot_appendix");
        return $buttons;
    }

    /**
     * Register TinyMCE plugin
     *
     * @since    0.2.0
     * @param    array    $plugins    Existing plugins.
     * @return   array
     */
    public function register_tinymce_plugin($plugins)
    {
        $plugins["botdot_appendix"] = BOTDOT_WP_PLUGIN_URL . "public/js/botdot-wp-tinymce.js";
        return $plugins;
    }

    /**
     * Register Gutenberg block
     *
     * @since    0.2.0
     */
    public function register_gutenberg_block()
    {
        if (!function_exists("register_block_type")) {
            return;
        }

        register_block_type("botdot-wp/appendix", [
            "render_callback" => [$this, "render_appendix_shortcode"],
            "attributes" => [],
        ]);
    }

    /**
     * Enqueue Gutenberg block assets
     *
     * @since    0.2.0
     */
    public function enqueue_gutenberg_assets()
    {
        if (!function_exists("register_block_type")) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . "-gutenberg",
            BOTDOT_WP_PLUGIN_URL . "public/js/botdot-wp-gutenberg.js",
            ["wp-blocks", "wp-element", "wp-editor", "wp-components"],
            $this->version,
            true,
        );

        wp_localize_script($this->plugin_name . "-gutenberg", "botdotWP", [
            "pluginName" => "BotSpot Appendix",
        ]);
    }

    /**
     * Render appendix via shortcode
     *
     * Delegates to the content injector.
     *
     * @since    1.0.0
     * @param    array     $atts    Shortcode attributes.
     * @return   string             Rendered appendix HTML.
     */
    public function render_appendix_shortcode($atts)
    {
        return $this->get_content_injector()->render_shortcode($atts);
    }

    /**
     * Enqueue public styles
     *
     * @since    0.2.0
     */
    public function enqueue_styles()
    {
        if (!BotDot_WP_Options::get("injection_enabled")) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . "-appendix",
            BOTDOT_WP_PLUGIN_URL . "public/css/botdot-wp-appendix.css",
            [],
            $this->version,
            "all",
        );
    }
}
