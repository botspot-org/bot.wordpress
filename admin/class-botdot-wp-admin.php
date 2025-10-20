<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @link       https://botdot.ai
 * @since      0.1.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the admin area.
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/admin
 * @author     BotDot Team
 */
class BotDot_WP_Admin {

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
     * Register the settings page in the admin menu
     *
     * @since    0.1.0
     */
    public function add_admin_menu() {
        add_options_page(
            __('BotDot WP Settings', 'botdot-wp'),
            __('BotDot WP', 'botdot-wp'),
            'manage_options',
            'botdot-wp',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Initialize plugin settings
     *
     * @since    0.1.0
     */
    public function init_settings() {
        // Register settings
        register_setting('botdot_wp_settings', 'botdot_wp_mirror_domain', array(
            'sanitize_callback' => array($this, 'sanitize_mirror_domain'),
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false,
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_fetch_timeout', array(
            'sanitize_callback' => 'absint',
            'default' => 10,
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_inject_on_post_types', array(
            'sanitize_callback' => array($this, 'sanitize_post_types'),
            'default' => array('post', 'page'),
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_exclude_page_ids', array(
            'sanitize_callback' => array($this, 'sanitize_page_ids'),
            'default' => array(),
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_debug_mode', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false,
        ));

        // Add settings sections
        add_settings_section(
            'botdot_wp_general_section',
            __('General Settings', 'botdot-wp'),
            array($this, 'render_general_section'),
            'botdot-wp'
        );

        add_settings_section(
            'botdot_wp_injection_section',
            __('Injection Settings', 'botdot-wp'),
            array($this, 'render_injection_section'),
            'botdot-wp'
        );

        add_settings_section(
            'botdot_wp_advanced_section',
            __('Advanced Settings', 'botdot-wp'),
            array($this, 'render_advanced_section'),
            'botdot-wp'
        );

        // Add settings fields
        add_settings_field(
            'botdot_wp_mirror_domain',
            __('Mirror Domain', 'botdot-wp'),
            array($this, 'render_mirror_domain_field'),
            'botdot-wp',
            'botdot_wp_general_section'
        );

        add_settings_field(
            'botdot_wp_enabled',
            __('Enable Plugin', 'botdot-wp'),
            array($this, 'render_enabled_field'),
            'botdot-wp',
            'botdot_wp_general_section'
        );

        add_settings_field(
            'botdot_wp_inject_on_post_types',
            __('Inject on Post Types', 'botdot-wp'),
            array($this, 'render_post_types_field'),
            'botdot-wp',
            'botdot_wp_injection_section'
        );

        add_settings_field(
            'botdot_wp_exclude_page_ids',
            __('Exclude Page IDs', 'botdot-wp'),
            array($this, 'render_exclude_pages_field'),
            'botdot-wp',
            'botdot_wp_injection_section'
        );

        add_settings_field(
            'botdot_wp_fetch_timeout',
            __('Fetch Timeout (seconds)', 'botdot-wp'),
            array($this, 'render_fetch_timeout_field'),
            'botdot-wp',
            'botdot_wp_advanced_section'
        );

        add_settings_field(
            'botdot_wp_debug_mode',
            __('Debug Mode', 'botdot-wp'),
            array($this, 'render_debug_mode_field'),
            'botdot-wp',
            'botdot_wp_advanced_section'
        );
    }

    /**
     * Render general section description
     *
     * @since    0.1.0
     */
    public function render_general_section() {
        echo '<p>' . __('Configure the basic settings for BotDot WP.', 'botdot-wp') . '</p>';
    }

    /**
     * Render injection section description
     *
     * @since    0.1.0
     */
    public function render_injection_section() {
        echo '<p>' . __('Control which pages and post types receive JSON-LD injection.', 'botdot-wp') . '</p>';
    }

    /**
     * Render advanced section description
     *
     * @since    0.1.0
     */
    public function render_advanced_section() {
        echo '<p>' . __('Advanced configuration options.', 'botdot-wp') . '</p>';
    }

    /**
     * Render mirror domain field
     *
     * @since    0.1.0
     */
    public function render_mirror_domain_field() {
        $value = BotDot_WP_Options::get('mirror_domain', '');
        ?>
        <input type="text" name="botdot_wp_mirror_domain" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="ai.example.com">
        <p class="description">
            <?php _e('The mirror domain to fetch JSON-LD from (without https://). Example: ai.example.com', 'botdot-wp'); ?>
        </p>
        <button type="button" id="botdot-wp-test-connection" class="button">
            <?php _e('Test Connection', 'botdot-wp'); ?>
        </button>
        <span id="botdot-wp-test-result"></span>
        <?php
    }

    /**
     * Render enabled field
     *
     * @since    0.1.0
     */
    public function render_enabled_field() {
        $value = BotDot_WP_Options::get('enabled', false);
        ?>
        <label>
            <input type="checkbox" name="botdot_wp_enabled" value="1" <?php checked($value, true); ?>>
            <?php _e('Enable JSON-LD injection', 'botdot-wp'); ?>
        </label>
        <?php
    }

    /**
     * Render post types field
     *
     * @since    0.1.0
     */
    public function render_post_types_field() {
        $selected = BotDot_WP_Options::get('inject_on_post_types', array('post', 'page'));
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <?php foreach ($post_types as $post_type) : ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="botdot_wp_inject_on_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $selected)); ?>>
                <?php echo esc_html($post_type->label); ?>
            </label>
        <?php endforeach; ?>
        <p class="description">
            <?php _e('Select which post types should have JSON-LD injected.', 'botdot-wp'); ?>
        </p>
        <?php
    }

    /**
     * Render exclude pages field
     *
     * @since    0.1.0
     */
    public function render_exclude_pages_field() {
        $value = BotDot_WP_Options::get('exclude_page_ids', array());
        $value_string = implode(', ', $value);
        ?>
        <input type="text" name="botdot_wp_exclude_page_ids" value="<?php echo esc_attr($value_string); ?>" class="regular-text" placeholder="123, 456, 789">
        <p class="description">
            <?php _e('Comma-separated list of page/post IDs to exclude from injection.', 'botdot-wp'); ?>
        </p>
        <?php
    }

    /**
     * Render fetch timeout field
     *
     * @since    0.1.0
     */
    public function render_fetch_timeout_field() {
        $value = BotDot_WP_Options::get('fetch_timeout', 10);
        ?>
        <input type="number" name="botdot_wp_fetch_timeout" value="<?php echo esc_attr($value); ?>" min="1" max="60" step="1">
        <p class="description">
            <?php _e('Maximum time to wait for JSON-LD fetch (1-60 seconds).', 'botdot-wp'); ?>
        </p>
        <?php
    }

    /**
     * Render debug mode field
     *
     * @since    0.1.0
     */
    public function render_debug_mode_field() {
        $value = BotDot_WP_Options::get('debug_mode', false);
        ?>
        <label>
            <input type="checkbox" name="botdot_wp_debug_mode" value="1" <?php checked($value, true); ?>>
            <?php _e('Enable debug logging', 'botdot-wp'); ?>
        </label>
        <p class="description">
            <?php _e('Logs additional debug information to the WordPress debug log.', 'botdot-wp'); ?>
        </p>
        <?php
    }

    /**
     * Display the settings page
     *
     * @since    0.1.0
     */
    public function display_settings_page() {
        require_once BOTDOT_WP_PLUGIN_PATH . 'admin/partials/botdot-wp-admin-settings.php';
    }

    /**
     * Display admin notices
     *
     * @since    0.1.0
     */
    public function display_admin_notices() {
        // Check if there are recent errors
        if (!BotDot_WP_Logger::has_errors()) {
            return;
        }

        // Only show on relevant admin pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, array('settings_page_botdot-wp', 'dashboard'))) {
            return;
        }

        $last_error = BotDot_WP_Logger::get_last_error();
        if (!$last_error) {
            return;
        }

        $time_ago = human_time_diff($last_error['timestamp'], current_time('timestamp'));
        ?>
        <div class="notice notice-<?php echo esc_attr($last_error['type']); ?> is-dismissible">
            <p>
                <strong><?php _e('BotDot WP:', 'botdot-wp'); ?></strong>
                <?php echo esc_html($last_error['message']); ?>
                <em>(<?php echo esc_html(sprintf(__('%s ago', 'botdot-wp'), $time_ago)); ?>)</em>
            </p>
            <?php if (BotDot_WP_Logger::get_error_count() > 1) : ?>
                <p>
                    <?php
                    printf(
                        __('There are %d more errors. <a href="%s">View settings</a> for details.', 'botdot-wp'),
                        BotDot_WP_Logger::get_error_count() - 1,
                        admin_url('options-general.php?page=botdot-wp')
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle AJAX test connection request
     *
     * @since    0.1.0
     */
    public function handle_test_connection() {
        check_ajax_referer('botdot_wp_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        $result = BotDot_WP_Fetcher::test_connection('/');

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle AJAX clear errors request
     *
     * @since    0.1.0
     */
    public function handle_clear_errors() {
        check_ajax_referer('botdot_wp_clear_errors', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        BotDot_WP_Logger::clear_errors();
        wp_send_json_success();
    }

    /**
     * Sanitize mirror domain
     *
     * @since    0.1.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_mirror_domain($value) {
        return BotDot_WP_Options::sanitize_option_value('mirror_domain', $value);
    }

    /**
     * Sanitize checkbox value
     *
     * @since    0.1.0
     * @param    mixed    $value    The value to sanitize.
     * @return   bool               The sanitized value.
     */
    public function sanitize_checkbox($value) {
        return !empty($value);
    }

    /**
     * Sanitize post types array
     *
     * @since    0.1.0
     * @param    mixed    $value    The value to sanitize.
     * @return   array              The sanitized value.
     */
    public function sanitize_post_types($value) {
        if (!is_array($value)) {
            return array();
        }
        return array_map('sanitize_text_field', $value);
    }

    /**
     * Sanitize page IDs
     *
     * @since    0.1.0
     * @param    mixed    $value    The value to sanitize.
     * @return   array              The sanitized value.
     */
    public function sanitize_page_ids($value) {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return array();
        }
        return array_map('absint', array_filter($value));
    }
}
