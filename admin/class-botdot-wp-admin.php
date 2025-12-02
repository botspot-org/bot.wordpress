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
        // Add single top-level menu page with tabbed interface
        add_menu_page(
            __('BotDot Settings', 'botdot-wp'),  // Page title
            __('BotDot', 'botdot-wp'),           // Menu title
            'manage_options',                     // Capability
            'botdot-wp',                         // Menu slug
            array($this, 'display_settings_page'), // Callback
            'dashicons-admin-site-alt3',         // Icon
            80                                    // Position (after Settings)
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

        // Appendix settings
        register_setting('botdot_wp_settings', 'botdot_wp_appendix_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false,
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_appendix_title', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'AI Appendix',
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_appendix_position', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'bottom',
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_appendix_open_default', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false,
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_appendix_on_post_types', array(
            'sanitize_callback' => array($this, 'sanitize_post_types'),
            'default' => array('post', 'page'),
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_appendix_auto_placement', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'above_footer',
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_page_injection_status', array(
            'sanitize_callback' => array($this, 'sanitize_page_injection_status'),
            'default' => array(),
        ));

        // Hidden JSON field to preserve page injection status from AJAX updates
        register_setting('botdot_wp_settings', 'botdot_wp_page_injection_status_json', array(
            'sanitize_callback' => array($this, 'sanitize_page_injection_status_json'),
            'default' => '',
        ));

        // Theme & styling settings
        register_setting('botdot_wp_settings', 'botdot_wp_theme_classes_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true,
        ));

        register_setting('botdot_wp_settings', 'botdot_wp_custom_theme_classes', array(
            'sanitize_callback' => array($this, 'sanitize_custom_theme_classes'),
            'default' => BotDot_WP_Options::get_default('custom_theme_classes'),
        ));

        // Add settings sections
        add_settings_section(
            'botdot_wp_general_section',
            __('General Settings', 'botdot-wp'),
            array($this, 'render_general_section'),
            'botdot-wp'
        );

        add_settings_section(
            'botdot_wp_theme_section',
            __('Theme & Styling', 'botdot-wp'),
            array($this, 'render_theme_section'),
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
            'botdot_wp_theme_classes_enabled',
            __('Auto-detect Theme Classes', 'botdot-wp'),
            array($this, 'render_theme_classes_enabled_field'),
            'botdot-wp',
            'botdot_wp_theme_section'
        );

        add_settings_field(
            'botdot_wp_custom_theme_classes',
            __('Custom Theme Classes', 'botdot-wp'),
            array($this, 'render_custom_theme_classes_field'),
            'botdot-wp',
            'botdot_wp_theme_section'
        );

        add_settings_field(
            'botdot_wp_detected_theme_info',
            __('Preview & Detection', 'botdot-wp'),
            array($this, 'render_detected_theme_info'),
            'botdot-wp',
            'botdot_wp_theme_section'
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
     * Render advanced section description
     *
     * @since    0.1.0
     */
    public function render_advanced_section() {
        echo '<p>' . __('Advanced configuration options.', 'botdot-wp') . '</p>';
    }

    /**
     * Render theme section description
     *
     * @since    0.3.0
     */
    public function render_theme_section() {
        $summary = BotDot_WP_Appendix_Renderer::get_detection_summary();
        $theme_name = !empty($summary['theme_name']) ? $summary['theme_name'] : __('Unknown Theme', 'botdot-wp');
        $theme_template = !empty($summary['theme_template']) ? $summary['theme_template'] : __('N/A', 'botdot-wp');
        $accordion_plugin = !empty($summary['accordion_plugin']) ? $summary['accordion_plugin'] : '';

        $builder_label = '';
        if ($accordion_plugin) {
            $builder_label = ucwords(str_replace(array('-', '_'), ' ', $accordion_plugin));
        }

        echo '<div id="botdot-theme-section-intro" data-summary="' . esc_attr__('Theme & Styling (advanced)', 'botdot-wp') . '">';
        echo '<p>' . __('Control how the appendix inherits styling from your theme or page builder.', 'botdot-wp') . '</p>';
        echo '<p class="description">';
        printf(
            /* translators: 1: Theme name, 2: theme slug */
            esc_html__('Active theme: %1$s (%2$s)', 'botdot-wp'),
            esc_html($theme_name),
            esc_html($theme_template)
        );
        echo '</p>';

        if ($builder_label) {
            echo '<p class="description">';
            printf(
                esc_html__('Detected builder: %s', 'botdot-wp'),
                esc_html($builder_label)
            );
            echo '</p>';
        }

        echo '</div>';
    }

    /**
     * Render the auto-detect toggle field
     *
     * @since    0.3.0
     */
    public function render_theme_classes_enabled_field() {
        $value = BotDot_WP_Options::get('theme_classes_enabled', true);
        ?>
        <label>
            <input type="checkbox" id="botdot-wp-theme-classes-enabled" name="botdot_wp_theme_classes_enabled" value="1" <?php checked($value, true); ?>>
            <?php _e('Automatically detect FAQ/accordion classes from the active theme.', 'botdot-wp'); ?>
        </label>
        <p class="description">
            <?php _e('When disabled, the appendix uses only the default BotDot classes or any manual overrides you provide below.', 'botdot-wp'); ?>
        </p>
        <?php
    }

    /**
     * Render manual theme class overrides
     *
     * @since    0.3.0
     */
    public function render_custom_theme_classes_field() {
        $custom_classes = BotDot_WP_Options::get('custom_theme_classes', BotDot_WP_Options::get_default('custom_theme_classes'));
        $fields = array(
            'wrapper' => __('Wrapper', 'botdot-wp'),
            'details' => __('Accordion Item', 'botdot-wp'),
            'summary' => __('Toggle Summary', 'botdot-wp'),
            'title' => __('Title Text', 'botdot-wp'),
            'content' => __('Content Area', 'botdot-wp'),
        );

        $placeholders = array(
            'wrapper' => 'faq-list',
            'details' => 'faq-item',
            'summary' => 'faq-heading',
            'title' => 'faq-title',
            'content' => 'faq-content',
        );
        ?>
        <div class="botdot-theme-class-fields" id="botdot-theme-class-fields">
            <?php foreach ($fields as $key => $label) : ?>
                <label class="botdot-theme-class-field" for="botdot-wp-custom-theme-<?php echo esc_attr($key); ?>">
                    <span class="botdot-theme-class-field__label"><?php echo esc_html($label); ?></span>
                    <input
                        type="text"
                        class="regular-text"
                        id="botdot-wp-custom-theme-<?php echo esc_attr($key); ?>"
                        name="botdot_wp_custom_theme_classes[<?php echo esc_attr($key); ?>]"
                        value="<?php echo esc_attr(isset($custom_classes[$key]) ? $custom_classes[$key] : ''); ?>"
                        placeholder="<?php echo esc_attr($placeholders[$key]); ?>"
                        data-class-key="<?php echo esc_attr($key); ?>"
                    >
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description">
            <?php _e('Leave a field blank to use the auto-detected or default BotDot class for that element.', 'botdot-wp'); ?>
        </p>
        <?php
    }

    /**
     * Render detected theme information and preview
     *
     * @since    0.3.0
     */
    public function render_detected_theme_info() {
        $summary = BotDot_WP_Appendix_Renderer::get_detection_summary();
        $default_classes = BotDot_WP_Appendix_Renderer::get_default_theme_classes();
        $effective_classes = isset($summary['effective_classes']) && is_array($summary['effective_classes'])
            ? wp_parse_args($summary['effective_classes'], $default_classes)
            : $default_classes;

        $auto_classes = isset($summary['auto_classes']) && is_array($summary['auto_classes'])
            ? wp_parse_args($summary['auto_classes'], $default_classes)
            : $default_classes;

        $preview_classes = array();
        foreach ($effective_classes as $key => $value) {
            $values = array($value);
            if (isset($default_classes[$key])) {
                $values[] = $default_classes[$key];
            }
            $values = array_filter(array_unique(array_map('trim', $values)));
            $preview_classes[$key] = implode(' ', $values);
        }

        $auto_status_text = !empty($summary['auto_detection_enabled'])
            ? __('Auto-detection is currently enabled.', 'botdot-wp')
            : __('Auto-detection is currently disabled.', 'botdot-wp');

        $custom_status_text = !empty($summary['has_custom_classes'])
            ? __('Custom classes are defined and will override matching elements.', 'botdot-wp')
            : __('No custom classes are defined yet.', 'botdot-wp');

        $nonce = wp_create_nonce('botdot_wp_detect_theme');
        ?>
        <div
            class="botdot-theme-detection"
            id="botdot-theme-detection"
            data-detect-nonce="<?php echo esc_attr($nonce); ?>"
            data-status-auto-on="<?php echo esc_attr__('Auto-detection is currently enabled.', 'botdot-wp'); ?>"
            data-status-auto-off="<?php echo esc_attr__('Auto-detection is currently disabled.', 'botdot-wp'); ?>"
            data-status-custom-on="<?php echo esc_attr__('Custom classes are defined and will override matching elements.', 'botdot-wp'); ?>"
            data-status-custom-off="<?php echo esc_attr__('No custom classes are defined yet.', 'botdot-wp'); ?>"
            data-detect-success="<?php echo esc_attr__('Detected classes applied. Review and save your changes.', 'botdot-wp'); ?>"
            data-detect-failure="<?php echo esc_attr__('Unable to detect classes for the current theme.', 'botdot-wp'); ?>"
            data-detect-error="<?php echo esc_attr__('Detection request failed. Please try again.', 'botdot-wp'); ?>"
        >
            <div class="botdot-theme-detection__status">
                <p data-status-role="auto"><?php echo esc_html($auto_status_text); ?></p>
                <p data-status-role="custom"><?php echo esc_html($custom_status_text); ?></p>
            </div>

            <table class="botdot-theme-detection__table">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('Element', 'botdot-wp'); ?></th>
                        <th scope="col"><?php _e('Current class', 'botdot-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($effective_classes as $key => $value) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                            <td>
                                <code class="botdot-theme-class-value" data-class-key="<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($value); ?>
                                </code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description">
                <?php _e('Use the button below to detect classes from the active theme and auto-fill the manual fields above.', 'botdot-wp'); ?>
            </p>

            <p>
                <button type="button" class="button" id="botdot-wp-detect-theme" data-detecting-text="<?php echo esc_attr__('Detecting…', 'botdot-wp'); ?>">
                    <?php _e('Detect Current Theme', 'botdot-wp'); ?>
                </button>
                <span id="botdot-wp-detect-theme-result" class="botdot-inline-status" aria-live="polite"></span>
            </p>

            <div class="botdot-theme-preview" id="botdot-theme-preview" data-default-classes="<?php echo esc_attr(wp_json_encode($default_classes)); ?>">
                <h4><?php _e('Preview', 'botdot-wp'); ?></h4>
                <aside
                    class="<?php echo esc_attr($preview_classes['wrapper']); ?> botdot-preview-section"
                    data-class-key="wrapper"
                    data-preview-base="botdot-preview-section"
                >
                    <details
                        class="<?php echo esc_attr($preview_classes['details']); ?> botdot-preview-details"
                        data-class-key="details"
                        data-preview-base="botdot-preview-details"
                        open
                    >
                        <summary
                            class="<?php echo esc_attr($preview_classes['summary']); ?> botdot-preview-summary"
                            data-class-key="summary"
                            data-preview-base="botdot-preview-summary"
                        >
                            <span
                                class="<?php echo esc_attr($preview_classes['title']); ?> botdot-preview-title"
                                data-class-key="title"
                                data-preview-base="botdot-preview-title"
                            >
                                <?php _e('Example Question', 'botdot-wp'); ?>
                            </span>
                        </summary>
                        <div
                            class="<?php echo esc_attr($preview_classes['content']); ?> botdot-preview-content"
                            data-class-key="content"
                            data-preview-base="botdot-preview-content"
                        >
                            <?php _e('Example answer content to demonstrate how the appendix will appear with your current settings.', 'botdot-wp'); ?>
                        </div>
                    </details>
                </aside>
            </div>

            <input type="hidden" id="botdot-wp-auto-classes" value="<?php echo esc_attr(wp_json_encode($auto_classes)); ?>">
        </div>
        <?php
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
            <?php _e('The mirror domain to fetch JSON-LD from (without https://). Examples: ai.example.com, localhost:5000', 'botdot-wp'); ?>
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
        <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
            <div style="margin-top: 15px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h4 style="margin-top: 0;"><?php _e('Debug Tools', 'botdot-wp'); ?></h4>
                <p>
                    <button type="button" id="botdot-wp-manual-poll" class="button">
                        <?php _e('Trigger Cache Poll Manually', 'botdot-wp'); ?>
                    </button>
                    <span id="botdot-wp-manual-poll-result"></span>
                </p>
                <p class="description">
                    <?php _e('Manually trigger the cache poll to test the connection to .force-recache-trigger endpoint.', 'botdot-wp'); ?>
                </p>
                <p style="margin-top: 10px;">
                    <button type="button" id="botdot-wp-manual-clear" class="button button-primary">
                        <?php _e('Clear Site Cache Now', 'botdot-wp'); ?>
                    </button>
                    <span id="botdot-wp-manual-clear-result"></span>
                </p>
                <p class="description">
                    <?php _e('Immediately clear all WordPress caches and detected caching plugin caches.', 'botdot-wp'); ?>
                </p>
                <p class="description" style="margin-top: 10px;">
                    <em><?php _e('These debug tools are only visible when WP_DEBUG is enabled.', 'botdot-wp'); ?></em>
                </p>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Display the settings page with tabbed interface
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
     * Handle AJAX request to detect theme classes
     *
     * @since    0.3.0
     */
    public function handle_detect_theme_classes() {
        check_ajax_referer('botdot_wp_detect_theme', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        $auto_classes = BotDot_WP_Appendix_Renderer::get_auto_detected_theme_classes();
        $summary = BotDot_WP_Appendix_Renderer::get_detection_summary();

        wp_send_json_success(array(
            'theme' => array(
                'name' => isset($summary['theme_name']) ? $summary['theme_name'] : '',
                'template' => isset($summary['theme_template']) ? $summary['theme_template'] : '',
            ),
            'classes' => $auto_classes,
            'accordion_plugin' => isset($summary['accordion_plugin']) ? $summary['accordion_plugin'] : '',
        ));
    }

    /**
     * Handle AJAX request to toggle page injection status
     *
     * @since    0.3.0
     */
    public function handle_toggle_page_injection() {
        check_ajax_referer('botdot_wp_toggle_page', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        $page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;

        if (!$page_id) {
            wp_send_json_error(array('message' => __('Invalid page ID', 'botdot-wp')));
        }

        // Get current status
        $injection_status = BotDot_WP_Options::get('page_injection_status', array());

        // Update status
        $injection_status[$page_id] = $enabled;

        // Save
        BotDot_WP_Options::set('page_injection_status', $injection_status);

        wp_send_json_success(array(
            'page_id' => $page_id,
            'enabled' => $enabled,
        ));
    }

    /**
     * Handle AJAX request to bulk update page injection status
     *
     * @since    0.3.0
     */
    public function handle_bulk_update_pages() {
        check_ajax_referer('botdot_wp_bulk_pages', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        $page_ids = isset($_POST['page_ids']) ? array_map('absint', (array) $_POST['page_ids']) : array();
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;

        if (empty($page_ids)) {
            wp_send_json_error(array('message' => __('No pages selected', 'botdot-wp')));
        }

        // Get current status
        $injection_status = BotDot_WP_Options::get('page_injection_status', array());

        // Update all pages
        foreach ($page_ids as $page_id) {
            if ($page_id > 0) {
                $injection_status[$page_id] = $enabled;
            }
        }

        // Save
        BotDot_WP_Options::set('page_injection_status', $injection_status);

        wp_send_json_success(array(
            'count' => count($page_ids),
            'enabled' => $enabled,
        ));
    }

    /**
     * Handle AJAX request to manually trigger cache poll
     *
     * @since    0.3.0
     */
    public function handle_manual_cache_poll() {
        check_ajax_referer('botdot_wp_manual_poll', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        // Check if mirror domain is configured
        $mirror_domain = BotDot_WP_Options::get('mirror_domain');
        if (empty($mirror_domain)) {
            wp_send_json_error(array('message' => __('Mirror domain not configured', 'botdot-wp')));
        }

        // Manually trigger the polling function
        BotDot_WP_Cache_Clearer::poll_recache_trigger();

        // Check debug log for results (if debug mode is on)
        $debug_mode = BotDot_WP_Options::get('debug_mode', false);

        if ($debug_mode) {
            wp_send_json_success(array(
                'message' => __('Cache poll triggered successfully. Check debug log for details.', 'botdot-wp')
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('Cache poll triggered successfully.', 'botdot-wp')
            ));
        }
    }

    /**
     * Handle AJAX request to manually clear site cache
     *
     * @since    0.4.0
     */
    public function handle_manual_cache_clear() {
        check_ajax_referer('botdot_wp_manual_clear', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        // Manually trigger cache clearing
        $cleared = BotDot_WP_Cache_Clearer::clear_site_cache();

        $debug_mode = BotDot_WP_Options::get('debug_mode', false);

        // Check if LiteSpeed re-enable is scheduled
        $litespeed_scheduled = wp_next_scheduled('botdot_wp_reenable_litespeed');
        $litespeed_message = '';

        if ($litespeed_scheduled) {
            $time_remaining = $litespeed_scheduled - time();
            $minutes = ceil($time_remaining / 60);
            $litespeed_message = sprintf(
                __(' LiteSpeed Cache will re-enable in ~%d minutes.', 'botdot-wp'),
                $minutes
            );
        }

        if ($cleared) {
            if ($debug_mode) {
                wp_send_json_success(array(
                    'message' => __('Site cache cleared successfully.', 'botdot-wp') . $litespeed_message . __(' Check debug log for details.', 'botdot-wp')
                ));
            } else {
                wp_send_json_success(array(
                    'message' => __('Site cache cleared successfully.', 'botdot-wp') . $litespeed_message
                ));
            }
        } else {
            wp_send_json_success(array(
                'message' => __('Cache clearing attempted (no caching plugins detected, WordPress built-in cache cleared).', 'botdot-wp') . $litespeed_message
            ));
        }
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

    /**
     * Sanitize custom theme classes array
     *
     * @since    0.3.0
     * @param    mixed    $value    Raw input values.
     * @return   array              Sanitized class mapping.
     */
    public function sanitize_custom_theme_classes($value) {
        $defaults = BotDot_WP_Options::get_default('custom_theme_classes');

        if (!is_array($value)) {
            return $defaults;
        }

        $sanitized = array();
        foreach ($defaults as $key => $default) {
            $class_value = isset($value[$key]) ? sanitize_text_field($value[$key]) : '';
            $sanitized[$key] = trim($class_value);
        }

        return $sanitized;
    }

    /**
     * Sanitize page injection status array
     *
     * @since    0.3.0
     * @param    mixed    $value    Raw input values.
     * @return   array              Sanitized page status mapping.
     */
    public function sanitize_page_injection_status($value) {
        $sanitized = BotDot_WP_Options::sanitize_option_value('page_injection_status', $value);

        // Merge with JSON field data if it exists (from AJAX updates)
        if (isset($_POST['botdot_wp_page_injection_status_json'])) {
            $json_data = $_POST['botdot_wp_page_injection_status_json'];
            if (!empty($json_data)) {
                $json_decoded = json_decode(stripslashes($json_data), true);
                if (is_array($json_decoded)) {
                    // Merge: JSON data (from AJAX) takes precedence
                    $sanitized = array_merge($sanitized, $json_decoded);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize page injection status JSON field
     *
     * @since    0.3.0
     * @param    string   $value    JSON-encoded page status.
     * @return   string             Empty string (we merge into main array).
     */
    public function sanitize_page_injection_status_json($value) {
        // This field is just a temporary holder to preserve AJAX updates
        // The data is merged into page_injection_status by its sanitize callback
        // We return empty string to avoid storing duplicate data
        return '';
    }
}
