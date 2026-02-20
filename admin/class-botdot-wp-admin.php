<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @link       https://bot.spot
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
     * @var      string
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    0.1.0
     * @access   private
     * @var      string
     */
    private $version;

    /**
     * Initialize the class.
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
        add_menu_page(
            __('BotSpot Settings', 'botdot-wp'),
            __('BotSpot', 'botdot-wp'),
            'manage_options',
            'botdot-wp',
            array($this, 'display_settings_page'),
            'dashicons-admin-site-alt3',
            80
        );
    }

    /**
     * Initialize plugin settings
     *
     * @since    1.0.0
     */
    public function init_settings() {
        // Connection settings
        register_setting('botdot_wp_settings', 'botdot_wp_locus_api_url', array(
            'sanitize_callback' => array($this, 'sanitize_url'),
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_connector_url', array(
            'sanitize_callback' => array($this, 'sanitize_url'),
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_botspot_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_webhook_secret', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_connection_id', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Sync settings
        register_setting('botdot_wp_settings', 'botdot_wp_auto_sync_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true,
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_sync_sensitivity', array(
            'sanitize_callback' => array($this, 'sanitize_sensitivity'),
            'default' => 'medium',
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_sync_post_types', array(
            'sanitize_callback' => array($this, 'sanitize_post_types'),
            'default' => array('post', 'page'),
        ));

        // Display settings
        register_setting('botdot_wp_settings', 'botdot_wp_injection_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true,
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_injection_position', array(
            'sanitize_callback' => array($this, 'sanitize_position'),
            'default' => 'bottom',
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_inject_on_post_types', array(
            'sanitize_callback' => array($this, 'sanitize_post_types'),
            'default' => array('post', 'page'),
        ));
        register_setting('botdot_wp_settings', 'botdot_wp_page_injection_status', array(
            'sanitize_callback' => array($this, 'sanitize_page_injection_status'),
            'default' => array(),
        ));

        // Cache settings
        register_setting('botdot_wp_settings', 'botdot_wp_cache_ttl', array(
            'sanitize_callback' => 'absint',
            'default' => 3600,
        ));

        // Debug settings
        register_setting('botdot_wp_settings', 'botdot_wp_debug_mode', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false,
        ));

        // Hidden JSON field for page injection status
        register_setting('botdot_wp_settings', 'botdot_wp_page_injection_status_json', array(
            'sanitize_callback' => array($this, 'sanitize_page_injection_status_json'),
            'default' => '',
        ));
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
        if (!BotDot_WP_Logger::has_errors()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, array('toplevel_page_botdot-wp', 'dashboard'))) {
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
                <strong><?php _e('BotSpot WP:', 'botdot-wp'); ?></strong>
                <?php echo esc_html($last_error['message']); ?>
                <em>(<?php echo esc_html(sprintf(__('%s ago', 'botdot-wp'), $time_ago)); ?>)</em>
            </p>
            <?php if (BotDot_WP_Logger::get_error_count() > 1) : ?>
                <p>
                    <?php
                    printf(
                        __('There are %d more errors. <a href="%s">View settings</a> for details.', 'botdot-wp'),
                        BotDot_WP_Logger::get_error_count() - 1,
                        admin_url('admin.php?page=botdot-wp')
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add sync meta box to post editor
     *
     * @since    1.0.0
     */
    public function add_sync_meta_box() {
        $sync_post_types = BotDot_WP_Options::get('sync_post_types', array('post', 'page'));
        foreach ($sync_post_types as $post_type) {
            add_meta_box(
                'botdot-wp-sync',
                __('BotDot Sync', 'botdot-wp'),
                array($this, 'render_sync_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render sync meta box content
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object.
     */
    public function render_sync_meta_box($post) {
        $status = BotDot_WP_Sync::get_sync_status($post->ID);
        $status_label = $this->get_sync_status_label($status['status']);
        $status_color = $this->get_sync_status_color($status['status']);
        ?>
        <div class="botdot-sync-meta-box">
            <p>
                <strong><?php _e('Status:', 'botdot-wp'); ?></strong>
                <span style="color: <?php echo esc_attr($status_color); ?>;"><?php echo esc_html($status_label); ?></span>
            </p>
            <?php if ($status['last_synced_at']) : ?>
                <p>
                    <strong><?php _e('Last synced:', 'botdot-wp'); ?></strong>
                    <?php echo esc_html(human_time_diff(strtotime($status['last_synced_at']), current_time('timestamp'))); ?> <?php _e('ago', 'botdot-wp'); ?>
                </p>
            <?php endif; ?>
            <p>
                <button type="button" class="button botdot-sync-now" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php _e('Sync Now', 'botdot-wp'); ?>
                </button>
                <span class="botdot-sync-result"></span>
            </p>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.botdot-sync-now').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var result = btn.siblings('.botdot-sync-result');
                btn.prop('disabled', true).text('<?php _e('Syncing...', 'botdot-wp'); ?>');
                result.html('');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'botdot_wp_manual_sync',
                        nonce: '<?php echo wp_create_nonce('botdot_wp_manual_sync'); ?>',
                        post_id: btn.data('post-id')
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<span style="color: green;">&#10003; ' + response.data.message + '</span>');
                        } else {
                            result.html('<span style="color: red;">&#10007; ' + (response.data.message || '<?php _e('Sync failed', 'botdot-wp'); ?>') + '</span>');
                        }
                    },
                    error: function() {
                        result.html('<span style="color: red;">&#10007; <?php _e('Request failed', 'botdot-wp'); ?></span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('<?php _e('Sync Now', 'botdot-wp'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Add sync status column to post list
     *
     * @since    1.0.0
     * @param    array    $columns    Existing columns.
     * @return   array
     */
    public function add_sync_column($columns) {
        $columns['botdot_sync'] = __('BotDot', 'botdot-wp');
        return $columns;
    }

    /**
     * Render sync status column content
     *
     * @since    1.0.0
     * @param    string    $column_name    The column name.
     * @param    int       $post_id        The post ID.
     */
    public function render_sync_column($column_name, $post_id) {
        if ($column_name !== 'botdot_sync') {
            return;
        }

        $sync_post_types = BotDot_WP_Options::get('sync_post_types', array('post', 'page'));
        if (!in_array(get_post_type($post_id), $sync_post_types)) {
            echo '<span style="color: #999;">&#8212;</span>';
            return;
        }

        $status = BotDot_WP_Sync::get_sync_status($post_id);
        $color = $this->get_sync_status_color($status['status']);
        $label = $this->get_sync_status_label($status['status']);
        $icon = $this->get_sync_status_icon($status['status']);

        echo '<span style="color: ' . esc_attr($color) . ';" title="' . esc_attr($label) . '">' . $icon . '</span>';
    }

    /**
     * Add bulk sync action to post list
     *
     * @since    1.0.0
     * @param    array    $actions    Existing bulk actions.
     * @return   array
     */
    public function add_bulk_sync_action($actions) {
        $actions['botdot_sync'] = __('Sync with BotDot', 'botdot-wp');
        return $actions;
    }

    /**
     * Handle bulk sync action
     *
     * @since    1.0.0
     * @param    string    $redirect_to    The redirect URL.
     * @param    string    $doaction       The action being taken.
     * @param    array     $post_ids       The selected post IDs.
     * @return   string
     */
    public function handle_bulk_sync_action($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'botdot_sync') {
            return $redirect_to;
        }

        $synced = 0;
        foreach ($post_ids as $post_id) {
            if (BotDot_WP_Sync::manual_sync($post_id)) {
                $synced++;
            }
        }

        return add_query_arg('botdot_synced', $synced, $redirect_to);
    }

    // ---- AJAX Handlers ----

    /**
     * Handle AJAX test connection request
     *
     * @since    1.0.0
     */
    public function handle_test_connection() {
        check_ajax_referer('botdot_wp_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        $results = array();

        // Test locus-core connection
        $core_result = BotDot_WP_Content_Fetcher::test_connection();
        $results['locus_core'] = $core_result;

        // Test locus-connectors connection
        $connector_url = BotDot_WP_Options::get('connector_url');
        $api_key = BotDot_WP_Options::get('api_key');

        if (!empty($connector_url) && !empty($api_key)) {
            $response = wp_remote_get(rtrim($connector_url, '/') . '/health', array(
                'headers' => array('X-API-Key' => $api_key),
                'timeout' => 10,
            ));

            if (is_wp_error($response)) {
                $results['connector'] = array(
                    'success' => false,
                    'message' => sprintf(__('Connector connection failed: %s', 'botdot-wp'), $response->get_error_message()),
                );
            } else {
                $status = wp_remote_retrieve_response_code($response);
                $results['connector'] = array(
                    'success' => $status >= 200 && $status < 300,
                    'message' => $status >= 200 && $status < 300
                        ? __('Connected to locus-connectors successfully', 'botdot-wp')
                        : sprintf(__('Connector returned HTTP %d', 'botdot-wp'), $status),
                );
            }
        } else {
            $results['connector'] = array(
                'success' => false,
                'message' => __('Connector URL or API key not configured', 'botdot-wp'),
            );
        }

        $all_success = $results['locus_core']['success'] && $results['connector']['success'];

        if ($all_success) {
            wp_send_json_success(array(
                'message' => __('Both connections successful', 'botdot-wp'),
                'details' => $results,
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('One or more connections failed', 'botdot-wp'),
                'details' => $results,
            ));
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
     * Handle AJAX toggle page injection status
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

        $injection_status = BotDot_WP_Options::get('page_injection_status', array());
        $injection_status[$page_id] = $enabled;
        BotDot_WP_Options::set('page_injection_status', $injection_status);

        wp_send_json_success(array(
            'page_id' => $page_id,
            'enabled' => $enabled,
        ));
    }

    /**
     * Handle AJAX bulk update page injection status
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

        $injection_status = BotDot_WP_Options::get('page_injection_status', array());

        foreach ($page_ids as $page_id) {
            if ($page_id > 0) {
                $injection_status[$page_id] = $enabled;
            }
        }

        BotDot_WP_Options::set('page_injection_status', $injection_status);

        wp_send_json_success(array(
            'count' => count($page_ids),
            'enabled' => $enabled,
        ));
    }

    /**
     * Handle AJAX manual sync for single post
     *
     * @since    1.0.0
     */
    public function handle_manual_sync() {
        check_ajax_referer('botdot_wp_manual_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'botdot-wp')));
        }

        $result = BotDot_WP_Sync::manual_sync($post_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Synced successfully', 'botdot-wp')));
        } else {
            wp_send_json_error(array('message' => __('Sync failed. Check error log for details.', 'botdot-wp')));
        }
    }

    /**
     * Handle AJAX bulk sync
     *
     * @since    1.0.0
     */
    public function handle_bulk_sync() {
        check_ajax_referer('botdot_wp_bulk_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        $result = BotDot_WP_Sync::bulk_sync();

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Bulk sync initiated', 'botdot-wp'),
                'status' => $result,
            ));
        } else {
            wp_send_json_error(array('message' => __('Bulk sync failed. Check connection settings.', 'botdot-wp')));
        }
    }

    /**
     * Handle AJAX sync status poll
     *
     * @since    1.0.0
     */
    public function handle_sync_status() {
        check_ajax_referer('botdot_wp_sync_status', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'botdot-wp')));
        }

        $connector_url = BotDot_WP_Options::get('connector_url');
        $api_key = BotDot_WP_Options::get('api_key');
        $connection_id = BotDot_WP_Options::get('connection_id');

        if (empty($connector_url) || empty($connection_id)) {
            wp_send_json_error(array('message' => __('Connection not configured', 'botdot-wp')));
        }

        $endpoint = rtrim($connector_url, '/') . '/sync/wordpress/' . $connection_id . '/status';

        $response = wp_remote_get($endpoint, array(
            'headers' => array('X-API-Key' => $api_key),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        wp_send_json_success($body);
    }

    // ---- Sanitization helpers ----

    /**
     * Sanitize URL value
     *
     * @since    1.0.0
     */
    public function sanitize_url($value) {
        return BotDot_WP_Options::sanitize_option_value('locus_api_url', $value);
    }

    /**
     * Sanitize checkbox value
     *
     * @since    0.1.0
     */
    public function sanitize_checkbox($value) {
        return !empty($value);
    }

    /**
     * Sanitize sync sensitivity value
     *
     * @since    1.0.0
     */
    public function sanitize_sensitivity($value) {
        return BotDot_WP_Options::sanitize_option_value('sync_sensitivity', $value);
    }

    /**
     * Sanitize injection position value
     *
     * @since    1.0.0
     */
    public function sanitize_position($value) {
        return BotDot_WP_Options::sanitize_option_value('injection_position', $value);
    }

    /**
     * Sanitize post types array
     *
     * @since    0.1.0
     */
    public function sanitize_post_types($value) {
        if (!is_array($value)) {
            return array();
        }
        return array_map('sanitize_text_field', $value);
    }

    /**
     * Sanitize page injection status array
     *
     * @since    0.3.0
     */
    public function sanitize_page_injection_status($value) {
        $sanitized = BotDot_WP_Options::sanitize_option_value('page_injection_status', $value);

        if (isset($_POST['botdot_wp_page_injection_status_json'])) {
            $json_data = $_POST['botdot_wp_page_injection_status_json'];
            if (!empty($json_data)) {
                $json_decoded = json_decode(stripslashes($json_data), true);
                if (is_array($json_decoded)) {
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
     */
    public function sanitize_page_injection_status_json($value) {
        return '';
    }

    // ---- Status helpers ----

    /**
     * Get human-readable sync status label
     *
     * @since    1.0.0
     */
    private function get_sync_status_label($status) {
        switch ($status) {
            case 'synced': return __('Synced', 'botdot-wp');
            case 'pending': return __('Pending', 'botdot-wp');
            case 'error': return __('Error', 'botdot-wp');
            case 'never':
            default: return __('Never synced', 'botdot-wp');
        }
    }

    /**
     * Get sync status color
     *
     * @since    1.0.0
     */
    private function get_sync_status_color($status) {
        switch ($status) {
            case 'synced': return '#00a32a';
            case 'pending': return '#dba617';
            case 'error': return '#d63638';
            case 'never':
            default: return '#999';
        }
    }

    /**
     * Get sync status icon
     *
     * @since    1.0.0
     */
    private function get_sync_status_icon($status) {
        switch ($status) {
            case 'synced': return '&#9679;';   // filled circle
            case 'pending': return '&#9675;';  // empty circle
            case 'error': return '&#9888;';    // warning
            case 'never':
            default: return '&#8212;';          // em dash
        }
    }
}
