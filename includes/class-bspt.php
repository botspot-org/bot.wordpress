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
 * @package    Bspt
 * @subpackage Bspt/includes
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
 * @package    Bspt
 * @subpackage Bspt/includes
 * @author     BotSpot Team
 */
class Bspt
{
    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    0.1.0
     * @access   protected
     * @var      Bspt_Loader    $loader
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
        if (defined("BSPT_VERSION")) {
            $this->version = BSPT_VERSION;
        } else {
            $this->version = "1.0.1";
        }
        $this->plugin_name = "botspot-wp";

        $this->load_dependencies();
        if (is_admin()) {
            $this->define_admin_hooks();
        }
        $this->define_public_hooks();
        $this->define_sync_hooks();
        $this->register_legacy_hook_aliases();
    }

    /**
     * Register legacy hook aliases for backwards compatibility with botdot-wp v2.x.
     *
     * Third-party code using old hook names (botdot_wp_*) will continue to work.
     *
     * @since 3.0.0
     */
    private function register_legacy_hook_aliases()
    {
        $aliases = [
            'botdot_wp_should_inject'   => 'bspt_should_inject',
            'botdot_wp_should_sync'     => 'bspt_should_sync',
            'botdot_wp_appendix_html'   => 'bspt_appendix_html',
            'botdot_wp_appendix_jsonld' => 'bspt_appendix_jsonld',
            'botdot_wp_source_jsonld'   => 'bspt_source_jsonld',
        ];
        foreach ($aliases as $old_hook => $new_hook) {
            add_filter($old_hook, function ($value, ...$args) use ($new_hook) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is limited to the plugin's legacy-to-current hook alias map above.
                return apply_filters($new_hook, $value, ...$args);
            }, 10, 99);
        }
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
         * Strauss-prefixed vendor autoloader (jaybizzle/crawler-detect etc.)
         */
        if (file_exists(BSPT_PLUGIN_PATH . "vendor/botspot-prefixed/autoload.php")) {
            require_once BSPT_PLUGIN_PATH . "vendor/botspot-prefixed/autoload.php";
        }

        /**
         * Analytics: bot classifier + flusher (state machine + wp-cron handler).
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-bot-classifier.php";
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-analytics-flusher.php";

        /**
         * The class responsible for orchestrating the actions and filters.
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-loader.php";

        /**
         * The class responsible for options management.
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-options.php";

        /**
         * The class responsible for logging.
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-logger.php";

        /**
         * The class responsible for multilingual plugin detection.
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-language.php";

        /**
         * The class responsible for page builder content extraction.
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-page-builder.php";

        /**
         * The class responsible for content sync (write path).
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-sync.php";

        /**
         * Cache invalidation helpers (plugin transients + external page caches).
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-cache.php";

        /**
         * The class responsible for content fetching (read path).
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-content-fetcher.php";

        /**
         * The class responsible for content injection (JSON-LD + appendix).
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-content-injector.php";

        /**
         * The class responsible for handling webhooks from locus-core.
         */
        require_once BSPT_PLUGIN_PATH . "includes/class-bspt-webhook-handler.php";

        /**
         * The class responsible for defining all actions in the admin area.
         */
        require_once BSPT_PLUGIN_PATH . "admin/class-bspt-admin.php";

        /**
         * The class responsible for public-facing functionality.
         */
        require_once BSPT_PLUGIN_PATH . "public/class-bspt-public.php";

        $this->loader = new Bspt_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Bspt_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action("admin_menu", $plugin_admin, "add_admin_menu");
        $this->loader->add_action("admin_init", $plugin_admin, "init_settings");
        $this->loader->add_action("admin_enqueue_scripts", $plugin_admin, "enqueue_admin_assets");

        // AJAX handlers
        $this->loader->add_action("wp_ajax_bspt_test_connection", $plugin_admin, "handle_test_connection");
        $this->loader->add_action("wp_ajax_bspt_clear_errors", $plugin_admin, "handle_clear_errors");
        $this->loader->add_action("wp_ajax_bspt_manual_sync", $plugin_admin, "handle_manual_sync");
        $this->loader->add_action("wp_ajax_bspt_register_connection", $plugin_admin, "handle_register_connection");
        $this->loader->add_action("wp_ajax_bspt_get_logs", $plugin_admin, "handle_get_logs");
        $this->loader->add_action("wp_ajax_bspt_get_status", $plugin_admin, "handle_get_status");
        $this->loader->add_action("wp_ajax_bspt_force_resync", $plugin_admin, "handle_force_resync");
        $this->loader->add_action("wp_ajax_bspt_clear_cache", $plugin_admin, "handle_clear_cache");
        $this->loader->add_action("wp_ajax_bspt_save_settings", $plugin_admin, "handle_save_settings");

        // Analytics AJAX handlers
        $this->loader->add_action("wp_ajax_bspt_get_sync_health", $plugin_admin, "handle_get_sync_health");
        $this->loader->add_action("wp_ajax_bspt_get_enrichment_lifecycle", $plugin_admin, "handle_get_enrichment_lifecycle");
        $this->loader->add_action("wp_ajax_bspt_get_impressions", $plugin_admin, "handle_get_impressions");
        $this->loader->add_action("wp_ajax_bspt_force_flush", $plugin_admin, "handle_force_flush");

        // wp-cron handler for the hourly flush (both names for migration compatibility)
        $this->loader->add_action("bspt_flush_analytics", "Bspt_Analytics_Flusher", "flush");
        $this->loader->add_action("botspot_flush_analytics", "Bspt_Analytics_Flusher", "flush");

        // wp-cron handler for background Force Resync (one-off, scheduled by handle_force_resync)
        $this->loader->add_action("bspt_force_resync_run", $plugin_admin, "run_scheduled_force_resync");

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
        new Bspt_Webhook_Handler();

        $content_injector = new Bspt_Content_Injector($this->get_plugin_name(), $this->get_version());
        $public = new Bspt_Public($this->get_plugin_name(), $this->get_version(), $content_injector);

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

        // Placement script: emit early so the <script> tag is in the DOM before
        // theme scripts; the script itself runs on DOMContentLoaded.
        $this->loader->add_action("wp_footer", $content_injector, "inject_placement_script", 1);

        // Page-builder fallback: only fires when a page builder bypassed the_content.
        $this->loader->add_action("wp_footer", $content_injector, "inject_appendix_footer_fallback", 5);

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
        $this->loader->add_action("save_post", "Bspt_Sync", "on_save_post", 10, 3);
        $this->loader->add_action("transition_post_status", "Bspt_Sync", "on_status_change", 10, 3);
        $this->loader->add_action("before_delete_post", "Bspt_Sync", "on_delete_post");
        $this->loader->add_action("bspt_retry_sync", "Bspt_Sync", "retry_sync");

        // REST API webhook endpoint for enrichment status updates
        $this->loader->add_action("rest_api_init", "Bspt_Sync", "register_webhook_route");
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
     * @return    Bspt_Loader
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
