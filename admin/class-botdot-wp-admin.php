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
if (!defined("WPINC")) {
    die();
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/admin
 * @author     BotDot Team
 */
class BotDot_WP_Admin
{
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
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the settings page in the admin menu
     *
     * @since    0.1.0
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __("BotSpot Settings", "botdot-wp"),
            __("BotSpot", "botdot-wp"),
            "manage_options",
            "botdot-wp",
            [$this, "display_settings_page"],
            "dashicons-admin-site-alt3",
            80,
        );
    }

    /**
     * Initialize plugin settings
     *
     * @since    1.0.0
     */
    public function init_settings()
    {
        // Connection settings
        register_setting("botdot_wp_settings", "botdot_wp_api_key", [
            "sanitize_callback" => [$this, "sanitize_secret_field_api_key"],
        ]);

        // Sync settings
        register_setting("botdot_wp_settings", "botdot_wp_auto_sync_enabled", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => true,
        ]);
        register_setting("botdot_wp_settings", "botdot_wp_sync_sensitivity", [
            "sanitize_callback" => [$this, "sanitize_sensitivity"],
            "default" => "medium",
        ]);
        register_setting("botdot_wp_settings", "botdot_wp_sync_post_types", [
            "sanitize_callback" => [$this, "sanitize_post_types"],
            "default" => ["post", "page"],
        ]);

        // Display settings
        register_setting("botdot_wp_settings", "botdot_wp_injection_enabled", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => true,
        ]);
        register_setting("botdot_wp_settings", "botdot_wp_injection_position", [
            "sanitize_callback" => [$this, "sanitize_position"],
            "default" => "bottom",
        ]);
        register_setting("botdot_wp_settings", "botdot_wp_inject_on_post_types", [
            "sanitize_callback" => [$this, "sanitize_post_types"],
            "default" => ["post", "page"],
        ]);
        // page_injection_status is now stored as post_meta (_botdot_inject_enabled)

        // Cache settings
        register_setting("botdot_wp_settings", "botdot_wp_cache_ttl", [
            "sanitize_callback" => "absint",
            "default" => 3600,
        ]);

        // Debug settings
        register_setting("botdot_wp_settings", "botdot_wp_debug_mode", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => false,
        ]);

        // page_injection_status_json is no longer needed (migrated to post_meta)
    }

    /**
     * Display the settings page
     *
     * @since    0.1.0
     */
    public function display_settings_page()
    {
        require_once BOTDOT_WP_PLUGIN_PATH . "admin/partials/botdot-wp-admin-settings.php";
    }

    /**
     * Display admin notices
     *
     * @since    0.1.0
     */
    public function display_admin_notices()
    {
        if (!BotDot_WP_Logger::has_errors()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, ["toplevel_page_botdot-wp", "dashboard", "post", "page"])) {
            return;
        }

        $last_error = BotDot_WP_Logger::get_last_error();
        if (!$last_error) {
            return;
        }

        $time_ago = human_time_diff($last_error["timestamp"], time());
        ?>
        <div class="notice notice-<?php echo esc_attr($last_error["type"]); ?> is-dismissible">
            <p>
                <strong><?php _e("BotSpot WP:", "botdot-wp"); ?></strong>
                <?php echo esc_html($last_error["message"]); ?>
                <em>(<?php echo esc_html(sprintf(__("%s ago", "botdot-wp"), $time_ago)); ?>)</em>
            </p>
            <?php if (BotDot_WP_Logger::get_error_count() > 1): ?>
                <p>
                    <?php printf(
                        __('There are %d more errors. <a href="%s">View settings</a> for details.', "botdot-wp"),
                        BotDot_WP_Logger::get_error_count() - 1,
                        admin_url("admin.php?page=botdot-wp"),
                    ); ?>
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
    public function add_sync_meta_box()
    {
        $sync_post_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
        foreach ($sync_post_types as $post_type) {
            add_meta_box(
                "botdot-wp-sync",
                __("BotDot Sync", "botdot-wp"),
                [$this, "render_sync_meta_box"],
                $post_type,
                "side",
                "default",
            );
        }
    }

    /**
     * Render sync meta box content
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object.
     */
    public function render_sync_meta_box($post)
    {
        $status = BotDot_WP_Sync::get_sync_status($post->ID);
        $status_label = $this->get_sync_status_label($status["status"]);
        $status_color = $this->get_sync_status_color($status["status"]);
        ?>
        <div class="botdot-sync-meta-box">
            <p>
                <strong><?php _e("Status:", "botdot-wp"); ?></strong>
                <span style="color: <?php echo esc_attr(
                    $status_color,
                ); ?>;"><?php echo esc_html($status_label); ?></span>
            </p>
            <?php if ($status["last_synced_at"]): ?>
                <p>
                    <strong><?php _e("Last synced:", "botdot-wp"); ?></strong>
                    <?php echo esc_html(human_time_diff(strtotime($status["last_synced_at"]), time())); ?> <?php _e(
     "ago",
     "botdot-wp",
 ); ?>
                </p>
            <?php endif; ?>
            <p>
                <button type="button" class="button botdot-sync-now" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php _e("Sync Now", "botdot-wp"); ?>
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
                btn.prop('disabled', true).text('<?php _e("Syncing...", "botdot-wp"); ?>');
                result.html('');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'botdot_wp_manual_sync',
                        nonce: '<?php echo wp_create_nonce("botdot_wp_manual_sync"); ?>',
                        post_id: btn.data('post-id')
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<span style="color: green;">&#10003; ' + response.data.message + '</span>');
                        } else {
                            result.html('<span style="color: red;">&#10007; ' + (response.data.message || '<?php _e(
                                "Sync failed",
                                "botdot-wp",
                            ); ?>') + '</span>');
                        }
                    },
                    error: function() {
                        result.html('<span style="color: red;">&#10007; <?php _e(
                            "Request failed",
                            "botdot-wp",
                        ); ?></span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('<?php _e("Sync Now", "botdot-wp"); ?>');
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
    public function add_sync_column($columns)
    {
        $columns["botdot_sync"] = __("BotDot", "botdot-wp");
        return $columns;
    }

    /**
     * Render sync status column content
     *
     * @since    1.0.0
     * @param    string    $column_name    The column name.
     * @param    int       $post_id        The post ID.
     */
    public function render_sync_column($column_name, $post_id)
    {
        if ($column_name !== "botdot_sync") {
            return;
        }

        $sync_post_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
        if (!in_array(get_post_type($post_id), $sync_post_types)) {
            echo '<span style="color: #999;">&#8212;</span>';
            return;
        }

        $status = BotDot_WP_Sync::get_sync_status($post_id);
        $color = $this->get_sync_status_color($status["status"]);
        $label = $this->get_sync_status_label($status["status"]);
        $icon = $this->get_sync_status_icon($status["status"]);

        echo '<span style="color: ' . esc_attr($color) . ';" title="' . esc_attr($label) . '">' . $icon . "</span>";
    }

    /**
     * Add bulk sync action to post list
     *
     * @since    1.0.0
     * @param    array    $actions    Existing bulk actions.
     * @return   array
     */
    public function add_bulk_sync_action($actions)
    {
        $actions["botdot_sync"] = __("Sync with BotDot", "botdot-wp");
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
    public function handle_bulk_sync_action($redirect_to, $doaction, $post_ids)
    {
        if ($doaction !== "botdot_sync") {
            return $redirect_to;
        }

        $synced = 0;
        foreach ($post_ids as $post_id) {
            if (BotDot_WP_Sync::manual_sync($post_id)) {
                $synced++;
            }
        }

        return add_query_arg("botdot_synced", $synced, $redirect_to);
    }

    // ---- AJAX Handlers ----

    /**
     * Handle AJAX test connection request
     *
     * @since    1.0.0
     */
    public function handle_test_connection()
    {
        check_ajax_referer("botdot_wp_test_connection", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        $results = [];

        // Test locus-core connection
        $core_result = BotDot_WP_Content_Fetcher::test_connection();
        $results["locus_core"] = $core_result;

        // Test locus-connectors connection
        $connector_url = BotDot_WP_Options::get_connector_url();
        $api_key = BotDot_WP_Options::get("api_key");

        if (!empty($api_key)) {
            $response = wp_remote_get(rtrim($connector_url, "/") . "/health", [
                "headers" => ["X-API-Key" => $api_key],
                "timeout" => 10,
            ]);

            if (is_wp_error($response)) {
                $results["connector"] = [
                    "success" => false,
                    "message" => sprintf(
                        __("Connector connection failed: %s", "botdot-wp"),
                        $response->get_error_message(),
                    ),
                ];
            } else {
                $status = wp_remote_retrieve_response_code($response);
                $results["connector"] = [
                    "success" => $status >= 200 && $status < 300,
                    "message" =>
                        $status >= 200 && $status < 300
                            ? __("Connected to locus-connectors successfully", "botdot-wp")
                            : sprintf(__("Connector returned HTTP %d", "botdot-wp"), $status),
                ];
            }
        } else {
            $results["connector"] = [
                "success" => false,
                "message" => __("API key not configured", "botdot-wp"),
            ];
        }

        $all_success = $results["locus_core"]["success"] && $results["connector"]["success"];

        if ($all_success) {
            wp_send_json_success([
                "message" => __("Both connections successful", "botdot-wp"),
                "details" => $results,
            ]);
        } else {
            wp_send_json_error([
                "message" => __("One or more connections failed", "botdot-wp"),
                "details" => $results,
            ]);
        }
    }

    /**
     * Handle AJAX clear errors request
     *
     * @since    0.1.0
     */
    public function handle_clear_errors()
    {
        check_ajax_referer("botdot_wp_clear_errors", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        BotDot_WP_Logger::clear_errors();
        wp_send_json_success();
    }

    /**
     * Handle AJAX toggle page injection status
     *
     * @since    0.3.0
     */
    public function handle_toggle_page_injection()
    {
        check_ajax_referer("botdot_wp_toggle_page", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        $page_id = isset($_POST["page_id"]) ? absint($_POST["page_id"]) : 0;
        $enabled = isset($_POST["enabled"]) ? (bool) $_POST["enabled"] : false;

        if (!$page_id) {
            wp_send_json_error(["message" => __("Invalid page ID", "botdot-wp")]);
            return;
        }

        update_post_meta($page_id, "_botdot_inject_enabled", $enabled ? "1" : "0");

        wp_send_json_success([
            "page_id" => $page_id,
            "enabled" => $enabled,
        ]);
    }

    /**
     * Handle AJAX bulk update page injection status
     *
     * @since    0.3.0
     */
    public function handle_bulk_update_pages()
    {
        check_ajax_referer("botdot_wp_bulk_pages", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        $page_ids = isset($_POST["page_ids"]) ? array_map("absint", (array) $_POST["page_ids"]) : [];
        $enabled = isset($_POST["enabled"]) ? (bool) $_POST["enabled"] : false;

        if (empty($page_ids)) {
            wp_send_json_error(["message" => __("No pages selected", "botdot-wp")]);
            return;
        }

        foreach ($page_ids as $page_id) {
            if ($page_id > 0) {
                update_post_meta($page_id, "_botdot_inject_enabled", $enabled ? "1" : "0");
            }
        }

        wp_send_json_success([
            "count" => count($page_ids),
            "enabled" => $enabled,
        ]);
    }

    /**
     * Handle AJAX manual sync for single post
     *
     * @since    1.0.0
     */
    public function handle_manual_sync()
    {
        check_ajax_referer("botdot_wp_manual_sync", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        $post_id = isset($_POST["post_id"]) ? absint($_POST["post_id"]) : 0;

        if (!$post_id) {
            wp_send_json_error(["message" => __("Invalid post ID", "botdot-wp")]);
            return;
        }

        $result = BotDot_WP_Sync::manual_sync($post_id);

        if ($result) {
            wp_send_json_success(["message" => __("Synced successfully", "botdot-wp")]);
        } else {
            wp_send_json_error(["message" => __("Sync failed. Check error log for details.", "botdot-wp")]);
        }
    }

    /**
     * Handle AJAX bulk sync
     *
     * @since    1.0.0
     */
    public function handle_bulk_sync()
    {
        check_ajax_referer("botdot_wp_bulk_sync", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        $result = BotDot_WP_Sync::bulk_sync();

        if ($result !== false) {
            wp_send_json_success([
                "message" => __("Bulk sync initiated", "botdot-wp"),
                "status" => $result,
            ]);
        } else {
            wp_send_json_error(["message" => __("Bulk sync failed. Check connection settings.", "botdot-wp")]);
        }
    }

    /**
     * Handle AJAX sync status poll
     *
     * @since    1.0.0
     */
    public function handle_sync_status()
    {
        check_ajax_referer("botdot_wp_sync_status", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        $connector_url = BotDot_WP_Options::get_connector_url();
        $api_key = BotDot_WP_Options::get("api_key");
        $connection_id = BotDot_WP_Options::get("connection_id");

        if (empty($connection_id)) {
            wp_send_json_error(["message" => __("Connection not configured", "botdot-wp")]);
            return;
        }

        $endpoint = rtrim($connector_url, "/") . "/sync/wordpress/" . $connection_id . "/status";

        $response = wp_remote_get($endpoint, [
            "headers" => ["X-API-Key" => $api_key],
            "timeout" => 10,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(["message" => $response->get_error_message()]);
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        wp_send_json_success($body);
    }

    // ---- Sanitization helpers ----

    /**
     * Sanitize a secret field, preserving existing value if submitted empty.
     *
     * @since    1.0.1
     * @param    string    $value          The submitted value.
     * @param    string    $option_name    The option name (without prefix).
     * @return   string
     */
    private function sanitize_secret_field($value, $option_name)
    {
        $value = sanitize_text_field(trim($value));
        if (empty($value)) {
            // Keep existing value when empty submission
            return BotDot_WP_Options::get($option_name);
        }
        return $value;
    }

    public function sanitize_secret_field_api_key($value)
    {
        return $this->sanitize_secret_field($value, "api_key");
    }

    /**
     * Sanitize checkbox value
     *
     * @since    0.1.0
     */
    public function sanitize_checkbox($value)
    {
        return !empty($value);
    }

    /**
     * Sanitize sync sensitivity value
     *
     * @since    1.0.0
     */
    public function sanitize_sensitivity($value)
    {
        return BotDot_WP_Options::sanitize_option_value("sync_sensitivity", $value);
    }

    /**
     * Sanitize injection position value
     *
     * @since    1.0.0
     */
    public function sanitize_position($value)
    {
        return BotDot_WP_Options::sanitize_option_value("injection_position", $value);
    }

    /**
     * Sanitize post types array
     *
     * @since    0.1.0
     */
    public function sanitize_post_types($value)
    {
        if (!is_array($value)) {
            return [];
        }
        return array_map("sanitize_text_field", $value);
    }

    /**
     * Sanitize page injection status array
     *
     * @since    0.3.0
     */
    // sanitize_page_injection_status and sanitize_page_injection_status_json
    // removed: page injection status is now stored as post_meta

    /**
     * Handle AJAX connection registration
     *
     * POST to connectors /api/v1/connections/register with API key + site info,
     * store returned connection_id, webhook_secret, botspot_key.
     *
     * @since    1.1.0
     */
    public function handle_register_connection()
    {
        check_ajax_referer("botdot_wp_register_connection", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        $api_key = BotDot_WP_Options::get("api_key");
        if (empty($api_key)) {
            wp_send_json_error(["message" => __("Please save an API key first.", "botdot-wp")]);
            return;
        }

        $connector_url = BotDot_WP_Options::get_connector_url();
        $endpoint = rtrim($connector_url, "/") . "/api/v1/connections/register";

        $payload = [
            "site_url" => home_url(),
            "site_name" => get_bloginfo("name"),
            "wp_version" => get_bloginfo("version"),
            "plugin_version" => BOTDOT_WP_VERSION,
        ];

        $response = wp_remote_post($endpoint, [
            "headers" => [
                "Content-Type" => "application/json",
                "X-API-Key" => $api_key,
            ],
            "body" => wp_json_encode($payload),
            "timeout" => 15,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                "message" => sprintf(
                    __("Registration failed: %s", "botdot-wp"),
                    $response->get_error_message(),
                ),
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
            $error_msg = is_array($body) && isset($body["error"]) ? $body["error"] : sprintf("HTTP %d", $status_code);
            wp_send_json_error([
                "message" => sprintf(__("Registration failed: %s", "botdot-wp"), $error_msg),
            ]);
            return;
        }

        // Store provisioned credentials
        if (isset($body["connection_id"])) {
            BotDot_WP_Options::set("connection_id", $body["connection_id"]);
        }
        if (isset($body["webhook_secret"])) {
            BotDot_WP_Options::set("webhook_secret", $body["webhook_secret"]);
        }
        if (isset($body["botspot_key"])) {
            BotDot_WP_Options::set("botspot_key", $body["botspot_key"]);
        }

        wp_send_json_success([
            "message" => __("Connection registered successfully.", "botdot-wp"),
            "connection_id" => isset($body["connection_id"]) ? $body["connection_id"] : "",
        ]);
    }

    /**
     * Handle AJAX disconnect
     *
     * Clears connection_id, webhook_secret, botspot_key.
     *
     * @since    1.1.0
     */
    public function handle_disconnect()
    {
        check_ajax_referer("botdot_wp_disconnect", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botdot-wp")]);
            return;
        }

        BotDot_WP_Options::set("connection_id", "");
        BotDot_WP_Options::set("webhook_secret", "");
        BotDot_WP_Options::set("botspot_key", "");

        wp_send_json_success([
            "message" => __("Disconnected successfully.", "botdot-wp"),
        ]);
    }

    // ---- Status helpers ----

    /**
     * Get human-readable sync status label
     *
     * @since    1.0.0
     */
    private function get_sync_status_label($status)
    {
        switch ($status) {
            case "synced":
                return __("Synced", "botdot-wp");
            case "pending":
                return __("Pending", "botdot-wp");
            case "error":
                return __("Error", "botdot-wp");
            case "never":
            default:
                return __("Never synced", "botdot-wp");
        }
    }

    /**
     * Get sync status color
     *
     * @since    1.0.0
     */
    private function get_sync_status_color($status)
    {
        switch ($status) {
            case "synced":
                return "#00a32a";
            case "pending":
                return "#dba617";
            case "error":
                return "#d63638";
            case "never":
            default:
                return "#999";
        }
    }

    /**
     * Get sync status icon
     *
     * @since    1.0.0
     */
    private function get_sync_status_icon($status)
    {
        switch ($status) {
            case "synced":
                return "&#9679;"; // filled circle
            case "pending":
                return "&#9675;"; // empty circle
            case "error":
                return "&#9888;"; // warning
            case "never":
            default:
                return "&#8212;"; // em dash
        }
    }
}
