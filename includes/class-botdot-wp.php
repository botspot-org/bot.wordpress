<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
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
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.1.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      BotDot_WP_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    0.1.0
     */
    public function __construct() {
        if (defined('BOTDOT_WP_VERSION')) {
            $this->version = BOTDOT_WP_VERSION;
        } else {
            $this->version = '0.1.0';
        }
        $this->plugin_name = 'botdot-wp';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - BotDot_WP_Loader. Orchestrates the hooks of the plugin.
     * - BotDot_WP_Options. Defines options management.
     * - BotDot_WP_Fetcher. Handles HTTP requests to mirror domain.
     * - BotDot_WP_Injector. Handles JSON-LD injection.
     * - BotDot_WP_Logger. Handles error logging.
     * - BotDot_WP_Admin. Defines all hooks for the admin area.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    0.1.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-loader.php';

        /**
         * The class responsible for options management
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-options.php';

        /**
         * The class responsible for HTTP fetching from mirror domain
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-fetcher.php';

        /**
         * The class responsible for JSON-LD injection
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-injector.php';

        /**
         * The class responsible for logging
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-logger.php';

        /**
         * The class responsible for appendix fetching
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-appendix-fetcher.php';

        /**
         * The class responsible for appendix rendering
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-appendix-renderer.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'admin/class-botdot-wp-admin.php';

        /**
         * The class responsible for public-facing functionality
         */
        require_once BOTDOT_WP_PLUGIN_PATH . 'public/class-botdot-wp-public.php';

        $this->loader = new BotDot_WP_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    0.1.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new BotDot_WP_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'init_settings');

        // AJAX handlers
        $this->loader->add_action('wp_ajax_botdot_wp_test_connection', $plugin_admin, 'handle_test_connection');
        $this->loader->add_action('wp_ajax_botdot_wp_clear_errors', $plugin_admin, 'handle_clear_errors');
        $this->loader->add_action('wp_ajax_botdot_wp_detect_theme_classes', $plugin_admin, 'handle_detect_theme_classes');

        // Admin notices for errors
        $this->loader->add_action('admin_notices', $plugin_admin, 'display_admin_notices');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    0.1.0
     * @access   private
     */
    private function define_public_hooks() {
        $injector = new BotDot_WP_Injector($this->get_plugin_name(), $this->get_version());
        $public = new BotDot_WP_Public($this->get_plugin_name(), $this->get_version());

        // Hook into wp_head at priority 1 for early JSON-LD injection
        $this->loader->add_action('wp_head', $injector, 'inject_json_ld', 1);

        // Register appendix shortcode
        $this->loader->add_action('init', $public, 'register_shortcode');

        // Register WPBakery element (late priority to ensure WPBakery is loaded)
        $this->loader->add_action('vc_before_init', $public, 'register_wpbakery_element');

        // Register Gutenberg block
        $this->loader->add_action('init', $public, 'register_gutenberg_block');

        // Enqueue Gutenberg block assets
        $this->loader->add_action('enqueue_block_editor_assets', $public, 'enqueue_gutenberg_assets');

        // Add TinyMCE button for Classic Editor
        $this->loader->add_action('admin_init', $public, 'add_tinymce_button');

        // Filter content to add appendix
        $this->loader->add_filter('the_content', $public, 'filter_content', 20);

        // Enqueue appendix styles
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_styles');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    0.1.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     0.1.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     0.1.0
     * @return    BotDot_WP_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     0.1.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
