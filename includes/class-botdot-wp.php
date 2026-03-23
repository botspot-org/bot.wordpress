<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      0.1.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP
{
    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    0.1.0
     * @access   protected
     * @var      BotDot_WP_Loader    $loader
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $plugin_name
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $version
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    0.1.0
     */
    public function __construct()
    {
        if (defined("BOTDOT_WP_VERSION")) {
            $this->version = BOTDOT_WP_VERSION;
        } else {
            $this->version = "1.0.1";
        }
        $this->plugin_name = "botdot-wp";

        $this->load_dependencies();
        if (is_admin()) {
            $this->define_admin_hooks();
        }
        $this->define_public_hooks();
        $this->define_sync_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters.
         */
        require_once BOTDOT_WP_PLUGIN_PATH . "includes/class-botdot-wp-loader.php";

        /**
         * The class responsible for options management.
         */
        require_once BOTDOT_WP_PLUGIN_PATH . "includes/class-botdot-wp-options.php";

        /**
         * The class responsible for logging.
         */
        require_once BOTDOT_WP_PLUGIN_PATH . "includes/class-botdot-wp-logger.php";

        /**
         * The class responsible for content sync (write path).
         */
        require_once BOTDOT_WP_PLUGIN_PATH . "includes/class-botdot-wp-sync.php";

        /**
         * The class responsible for content fetching (read path).
         */
        require_once BOTDOT_WP_PLUGIN_PATH . "includes/class-botdot-wp-content-fetcher.php";

        /**
         * The class responsible for content injection (JSON-LD + appendix).
         */
        require_once BOTDOT_WP_PLUGIN_PATH . "includes/class-botdot-wp-content-injector.php";

        /**
         * The class responsible for defining all actions in the admin area.
         */
        require_once BOTDOT_WP_PLUGIN_PATH . "admin/class-botdot-wp-admin.php";

        /**
         * The class responsible for public-facing functionality.
         */
        require_once BOTDOT_WP_PLUGIN_PATH . "public/class-botdot-wp-public.php";

        $this->loader = new BotDot_WP_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new BotDot_WP_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action("admin_menu", $plugin_admin, "add_admin_menu");
        $this->loader->add_action("admin_init", $plugin_admin, "init_settings");

        // AJAX handlers
        $this->loader->add_action("wp_ajax_botdot_wp_test_connection", $plugin_admin, "handle_test_connection");
        $this->loader->add_action("wp_ajax_botdot_wp_clear_errors", $plugin_admin, "handle_clear_errors");
        $this->loader->add_action("wp_ajax_botdot_wp_manual_sync", $plugin_admin, "handle_manual_sync");
        $this->loader->add_action("wp_ajax_botdot_wp_register_connection", $plugin_admin, "handle_register_connection");
        $this->loader->add_action("wp_ajax_botdot_wp_disconnect", $plugin_admin, "handle_disconnect");

        // Admin notices for errors
        $this->loader->add_action("admin_notices", $plugin_admin, "display_admin_notices");

        // Post editor meta box
        $this->loader->add_action("add_meta_boxes", $plugin_admin, "add_sync_meta_box");

        // Post list columns
        $this->loader->add_filter("manage_posts_columns", $plugin_admin, "add_sync_column");
        $this->loader->add_action("manage_posts_custom_column", $plugin_admin, "render_sync_column", 10, 2);
        $this->loader->add_filter("manage_pages_columns", $plugin_admin, "add_sync_column");
        $this->loader->add_action("manage_pages_custom_column", $plugin_admin, "render_sync_column", 10, 2);

        // Bulk actions
        $this->loader->add_filter("bulk_actions-edit-post", $plugin_admin, "add_bulk_sync_action");
        $this->loader->add_filter("bulk_actions-edit-page", $plugin_admin, "add_bulk_sync_action");
        $this->loader->add_filter("handle_bulk_actions-edit-post", $plugin_admin, "handle_bulk_sync_action", 10, 3);
        $this->loader->add_filter("handle_bulk_actions-edit-page", $plugin_admin, "handle_bulk_sync_action", 10, 3);
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
        $content_injector = new BotDot_WP_Content_Injector($this->get_plugin_name(), $this->get_version());
        $public = new BotDot_WP_Public($this->get_plugin_name(), $this->get_version(), $content_injector);

        // Merge locus JSON-LD into SEO plugin output (priority 99: run after they build their graph)
        // wpseo_schema_graph: modern Yoast (14.0+) — receives @graph array directly
        // wpseo_json_ld_output: legacy Yoast fallback — receives full JSON-LD object
        $this->loader->add_filter("wpseo_schema_graph", $content_injector, "merge_into_yoast_graph", 99);
        $this->loader->add_filter("wpseo_json_ld_output", $content_injector, "merge_into_yoast_jsonld", 99);
        $this->loader->add_filter("rank_math/json_ld", $content_injector, "merge_into_rankmath_jsonld", 99);

        // JSON-LD injection via wp_head (priority 99, after other SEO plugins)
        $this->loader->add_action("wp_head", $content_injector, "inject_jsonld", 99);

        // Appendix injection via the_content (priority 20)
        $this->loader->add_filter("the_content", $content_injector, "inject_appendix_content", 20);

        // Above-footer placement (priority 5)
        $this->loader->add_action("wp_footer", $content_injector, "inject_above_footer", 5);

        // Register shortcode
        $this->loader->add_action("init", $public, "register_shortcode");

        // Register WPBakery element
        $this->loader->add_action("vc_before_init", $public, "register_wpbakery_element");

        // Register Gutenberg block
        $this->loader->add_action("init", $public, "register_gutenberg_block");

        // Enqueue Gutenberg block assets
        $this->loader->add_action("enqueue_block_editor_assets", $public, "enqueue_gutenberg_assets");

        // TinyMCE button for Classic Editor
        $this->loader->add_action("admin_init", $public, "add_tinymce_button");

        // Enqueue appendix styles
        $this->loader->add_action("wp_enqueue_scripts", $public, "enqueue_styles");
    }

    /**
     * Register all of the hooks related to content sync functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_sync_hooks()
    {
        $this->loader->add_action("save_post", "BotDot_WP_Sync", "on_save_post", 10, 3);
        $this->loader->add_action("transition_post_status", "BotDot_WP_Sync", "on_status_change", 10, 3);
        $this->loader->add_action("before_delete_post", "BotDot_WP_Sync", "on_delete_post");
        $this->loader->add_action("botdot_wp_retry_sync", "BotDot_WP_Sync", "retry_sync");

        // REST API webhook endpoint for enrichment status updates
        $this->loader->add_action("rest_api_init", "BotDot_WP_Sync", "register_webhook_route");
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    0.1.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin.
     *
     * @since     0.1.0
     * @return    string
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the loader.
     *
     * @since     0.1.0
     * @return    BotDot_WP_Loader
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     0.1.0
     * @return    string
     */
    public function get_version()
    {
        return $this->version;
    }
}
