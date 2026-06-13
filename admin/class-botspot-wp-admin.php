<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/admin
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/admin
 * @author     BotSpot Team
 */
class BotSpot_WP_Admin
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
            __("bot.spot Settings", "botspot-wp"),
            __("bot.spot", "botspot-wp"),
            "manage_options",
            "botspot-wp",
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
        register_setting("botspot_wp_settings", "botspot_wp_api_key", [
            "sanitize_callback" => [$this, "sanitize_secret_field_api_key"],
        ]);

        // Sync settings
        register_setting("botspot_wp_settings", "botspot_wp_auto_sync_enabled", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => true,
        ]);
        register_setting("botspot_wp_settings", "botspot_wp_sync_sensitivity", [
            "sanitize_callback" => [$this, "sanitize_sensitivity"],
            "default" => "medium",
        ]);
        register_setting("botspot_wp_settings", "botspot_wp_sync_post_types", [
            "sanitize_callback" => [$this, "sanitize_post_types"],
            "default" => ["post", "page"],
        ]);

        // Display settings
        register_setting("botspot_wp_settings", "botspot_wp_appendix_enabled", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => true,
        ]);
        register_setting("botspot_wp_settings", "botspot_wp_jsonld_enabled", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => true,
        ]);
        register_setting("botspot_wp_settings", "botspot_wp_jsonld_conflict_mode", [
            "sanitize_callback" => [$this, "sanitize_jsonld_conflict_mode"],
            "default" => "merge",
        ]);
        register_setting("botspot_wp_settings", "botspot_wp_injection_position", [
            "sanitize_callback" => [$this, "sanitize_position"],
            "default" => "bottom_of_content",
        ]);
        register_setting("botspot_wp_settings", "botspot_wp_inject_on_post_types", [
            "sanitize_callback" => [$this, "sanitize_post_types"],
            "default" => ["post", "page"],
        ]);
        // Cache settings
        register_setting("botspot_wp_settings", "botspot_wp_cache_ttl", [
            "sanitize_callback" => "absint",
            "default" => 3600,
        ]);

        // Debug settings
        register_setting("botspot_wp_settings", "botspot_wp_debug_mode", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => false,
        ]);

    }

    /**
     * Display the settings page
     *
     * @since    0.1.0
     */
    public function display_settings_page()
    {
        require_once BOTSPOT_WP_PLUGIN_PATH . "admin/partials/botspot-wp-admin-settings.php";
    }

    /**
     * Enqueue admin-specific assets on the plugin settings page only.
     *
     * @since    2.2.0
     * @param    string    $hook    The current admin page hook.
     */
    public function enqueue_admin_assets($hook)
    {
        // Enqueue sync metabox script on post edit screens
        if (in_array($hook, ["post.php", "post-new.php"], true)) {
            wp_enqueue_script(
                "botspot-sync-metabox",
                BOTSPOT_WP_PLUGIN_URL . "admin/js/botspot-sync-metabox.js",
                ["jquery"],
                $this->version,
                true
            );
            wp_localize_script("botspot-sync-metabox", "botspotMetabox", [
                "nonce" => wp_create_nonce("botspot_wp_manual_sync"),
                "syncing" => __("Syncing...", "botspot-wp"),
                "syncNow" => __("Sync Now", "botspot-wp"),
                "failed" => __("Sync failed", "botspot-wp"),
                "requestFailed" => __("Request failed", "botspot-wp"),
            ]);
        }

        // Settings page assets
        if ($hook !== "toplevel_page_botspot-wp") {
            return;
        }

        wp_enqueue_style(
            "botspot-admin",
            BOTSPOT_WP_PLUGIN_URL . "admin/css/botspot-admin.css",
            [],
            $this->version
        );

        wp_enqueue_script(
            "botspot-admin",
            BOTSPOT_WP_PLUGIN_URL . "admin/js/botspot-admin.js",
            [],
            $this->version,
            true
        );

        // Localized data + nonces for all AJAX handlers the JS will call
        wp_localize_script("botspot-admin", "botspotAdmin", [
            "ajaxurl" => admin_url("admin-ajax.php"),
            "restUrl" => rest_url("botspot-wp/v1/"),
            "siteDomain" => wp_parse_url(home_url(), PHP_URL_HOST),
            "pluginVersion" => $this->version,
            "wpVersion" => get_bloginfo("version"),
            "phpVersion" => phpversion(),
            "apiVersion" => "v1 (stable)",
            "nonces" => [
                "testConnection" => wp_create_nonce("botspot_wp_test_connection"),
                "clearErrors" => wp_create_nonce("botspot_wp_clear_errors"),
                "manualSync" => wp_create_nonce("botspot_wp_manual_sync"),
                "registerConnection" => wp_create_nonce("botspot_wp_register_connection"),
                "getLogs" => wp_create_nonce("botspot_wp_get_logs"),
                "getStatus" => wp_create_nonce("botspot_wp_get_status"),
                "forceResync" => wp_create_nonce("botspot_wp_force_resync"),
                "clearCache" => wp_create_nonce("botspot_wp_clear_cache"),
                "getSyncHealth" => wp_create_nonce("botspot_wp_get_sync_health"),
                "getEnrichmentLifecycle" => wp_create_nonce("botspot_wp_get_enrichment_lifecycle"),
                "getImpressions" => wp_create_nonce("botspot_wp_get_impressions"),
                "forceFlush" => wp_create_nonce("botspot_wp_force_flush"),
                "saveSettings" => wp_create_nonce("botspot_wp_save_settings"),
            ],
            "strings" => [
                "allSaved" => __("All changes saved", "botspot-wp"),
                "unsaved" => __("Unsaved changes", "botspot-wp"),
                "testing" => __("Testing...", "botspot-wp"),
                "testSuccess" => __("Connection successful", "botspot-wp"),
                "testFailed" => __("Connection failed", "botspot-wp"),
                "copied" => __("Copied to clipboard", "botspot-wp"),
            ],
        ]);

        // Settings page footer detection note toggle
        $toggle_script = <<<'JS'
(function() {
    var radios = document.querySelectorAll('input[name="botspot_wp_injection_position"]');
    var note = document.getElementById('bsa-footer-detection-note');
    if (!radios.length || !note) return;
    function toggle() {
        var val = document.querySelector('input[name="botspot_wp_injection_position"]:checked');
        note.style.display = (val && (val.value === 'above_footer' || val.value === 'bottom_of_page')) ? 'block' : 'none';
    }
    radios.forEach(function(r) { r.addEventListener('change', toggle); });
    toggle();
})();
JS;
        wp_add_inline_script("botspot-admin", $toggle_script);
    }

    /**
     * Display admin notices
     *
     * @since    0.1.0
     */
    public function display_admin_notices()
    {
        $screen = get_current_screen();

        if (get_transient("botspot_wp_settings_updated_notice")) {
            if ($screen && $screen->base === "toplevel_page_botspot-wp") {
                delete_transient("botspot_wp_settings_updated_notice");
            } else {
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <strong><?php _e("bot.spot:", "botspot-wp"); ?></strong>
                        <?php _e("Your BotSpot settings were updated remotely.", "botspot-wp"); ?>
                        <a href="<?php echo esc_url(admin_url("admin.php?page=botspot-wp&tab=settings")); ?>">
                            <?php _e("View Settings", "botspot-wp"); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }

        if (!BotSpot_WP_Logger::has_errors()) {
            return;
        }

        if (!$screen || !in_array($screen->base, ["toplevel_page_botspot-wp", "dashboard", "post", "page"])) {
            return;
        }

        $last_error = BotSpot_WP_Logger::get_last_error();
        if (!$last_error) {
            return;
        }

        $time_ago = human_time_diff($last_error["timestamp"], time());
        ?>
        <div class="notice notice-<?php echo esc_attr($last_error["type"]); ?> is-dismissible">
            <p>
                <strong><?php _e("bot.spot WP:", "botspot-wp"); ?></strong>
                <?php echo esc_html($last_error["message"]); ?>
                <em>(<?php echo esc_html(sprintf(__("%s ago", "botspot-wp"), $time_ago)); ?>)</em>
            </p>
            <?php if (BotSpot_WP_Logger::get_error_count() > 1): ?>
                <p>
                    <?php printf(
                        __('There are %d more errors. <a href="%s">View settings</a> for details.', "botspot-wp"),
                        BotSpot_WP_Logger::get_error_count() - 1,
                        admin_url("admin.php?page=botspot-wp"),
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
        $sync_post_types = BotSpot_WP_Options::get("sync_post_types", ["post", "page"]);
        foreach ($sync_post_types as $post_type) {
            add_meta_box(
                "botspot-wp-sync",
                __("bot.spot Sync", "botspot-wp"),
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
        $status = BotSpot_WP_Sync::get_sync_status($post->ID);
        $status_label = $this->get_sync_status_label($status["status"]);
        $status_color = $this->get_sync_status_color($status["status"]);
        ?>
        <div class="botspot-sync-meta-box">
            <p>
                <strong><?php _e("Status:", "botspot-wp"); ?></strong>
                <span style="color: <?php echo esc_attr(
                    $status_color,
                ); ?>;"><?php echo esc_html($status_label); ?></span>
            </p>
            <?php if ($status["last_synced_at"]): ?>
                <p>
                    <strong><?php _e("Last synced:", "botspot-wp"); ?></strong>
                    <?php echo esc_html(human_time_diff(strtotime($status["last_synced_at"]), time())); ?> <?php _e(
     "ago",
     "botspot-wp",
 ); ?>
                </p>
            <?php endif; ?>
            <p>
                <button type="button" class="button botspot-sync-now" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php _e("Sync Now", "botspot-wp"); ?>
                </button>
                <span class="botspot-sync-result"></span>
            </p>
        </div>
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
        $columns["botspot_sync"] = __("bot.spot", "botspot-wp");
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
        if ($column_name !== "botspot_sync") {
            return;
        }

        $sync_post_types = BotSpot_WP_Options::get("sync_post_types", ["post", "page"]);
        if (!in_array(get_post_type($post_id), $sync_post_types)) {
            echo '<span style="color: #999;">&#8212;</span>';
            return;
        }

        // Show enrichment status if available, fall back to sync status
        $enrichment_status = get_post_meta($post_id, "_botspot_enrichment_status", true);
        if ($enrichment_status) {
            $enrichment_colors = [
                "indexed" => "#2271b1",
                "enriching" => "#dba617",
                "enriched" => "#00a32a",
            ];
            $enrichment_labels = [
                "indexed" => __("Indexed", "botspot-wp"),
                "enriching" => __("Enriching", "botspot-wp"),
                "enriched" => __("Enriched", "botspot-wp"),
            ];
            $color = $enrichment_colors[$enrichment_status] ?? "#999";
            $label = $enrichment_labels[$enrichment_status] ?? ucfirst($enrichment_status);
            echo '<span style="color: ' . esc_attr($color) . ';" title="' . esc_attr($label) . '">&#9679;</span>';
            return;
        }

        $status = BotSpot_WP_Sync::get_sync_status($post_id);
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
        $actions["botspot_sync"] = __("Sync with bot.spot", "botspot-wp");
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
        if ($doaction !== "botspot_sync") {
            return $redirect_to;
        }

        $synced = 0;
        foreach ($post_ids as $post_id) {
            if (BotSpot_WP_Sync::manual_sync($post_id)) {
                $synced++;
            }
        }

        return add_query_arg("botspot_synced", $synced, $redirect_to);
    }

    // ---- AJAX Handlers ----

    /**
     * Handle AJAX test connection request
     *
     * @since    1.0.0
     */
    public function handle_test_connection()
    {
        check_ajax_referer("botspot_wp_test_connection", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $this->maybe_save_inline_api_key();

        $result = BotSpot_WP_Content_Fetcher::test_connection();

        if (!empty($result["success"])) {
            // Register webhook for cache invalidation on successful connection
            BotSpot_WP_Webhook_Handler::register_webhook();

            // Fetch platform-owned settings
            BotSpot_WP_Webhook_Handler::fetch_platform_settings();

            wp_send_json_success([
                "message" => isset($result["message"])
                    ? $result["message"]
                    : __("Connected to bot.spot successfully.", "botspot-wp"),
                "details" => ["locus_core" => $result],
            ]);
        } else {
            wp_send_json_error([
                "message" => isset($result["message"])
                    ? $result["message"]
                    : __("Connection failed.", "botspot-wp"),
                "details" => ["locus_core" => $result],
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
        check_ajax_referer("botspot_wp_clear_errors", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        BotSpot_WP_Logger::clear_errors();
        wp_send_json_success();
    }

    /**
     * Handle AJAX manual sync for single post
     *
     * @since    1.0.0
     */
    public function handle_manual_sync()
    {
        check_ajax_referer("botspot_wp_manual_sync", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $post_id = isset($_POST["post_id"]) ? absint($_POST["post_id"]) : 0;

        if (!$post_id) {
            wp_send_json_error(["message" => __("Invalid post ID", "botspot-wp")]);
            return;
        }

        $result = BotSpot_WP_Sync::manual_sync($post_id);

        if ($result) {
            wp_send_json_success(["message" => __("Synced successfully", "botspot-wp")]);
        } else {
            wp_send_json_error(["message" => __("Sync failed. Check error log for details.", "botspot-wp")]);
        }
    }

    /**
     * Persist an inline api_key submitted alongside Connect/Test AJAX calls.
     *
     * Lets the user paste a key and click Connect without first hitting the
     * Settings save button. Only acts when a non-empty `api_key` POST param
     * is present and looks like a WorkOS sk_ key. When `$reset_connection`
     * is true and the key actually changed, also clears the prior webhook
     * registration so the caller registers fresh against the new org.
     *
     * @since 2.6.2
     * @param bool $reset_connection Wipe webhook_id/secret/tenant_id on key change.
     */
    private function maybe_save_inline_api_key($reset_connection = false)
    {
        if (empty($_POST["api_key"])) {
            return;
        }
        $submitted = sanitize_text_field(wp_unslash($_POST["api_key"]));
        if (strpos($submitted, "sk_") !== 0) {
            return;
        }
        $current = BotSpot_WP_Options::get("api_key");
        if ($submitted === $current) {
            return;
        }
        BotSpot_WP_Options::set("api_key", $submitted);
        if ($reset_connection) {
            BotSpot_WP_Options::set("webhook_id", "");
            BotSpot_WP_Options::set("webhook_secret", "");
            BotSpot_WP_Options::set("connection_id", "");
            BotSpot_WP_Options::set("tenant_id", "");
        }
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
            return BotSpot_WP_Options::get($option_name);
        }
        return $value;
    }

    public function sanitize_secret_field_api_key($value)
    {
        $new_value = $this->sanitize_secret_field($value, "api_key");
        $old_value = BotSpot_WP_Options::get("api_key");

        // Auto-disconnect when API key changes and a connection exists
        if (!empty($new_value) && $new_value !== $old_value && !empty(BotSpot_WP_Options::get("connection_id"))) {
            BotSpot_WP_Options::set("connection_id", "");
            BotSpot_WP_Options::set("webhook_secret", "");
            BotSpot_WP_Options::set("webhook_id", "");
            BotSpot_WP_Options::set("tenant_id", "");

            add_settings_error(
                "botspot_wp_api_key",
                "botspot_wp_api_key_changed",
                __("Access key changed. Previous connection has been disconnected. Please re-connect.", "botspot-wp"),
                "warning"
            );
        }

        return $new_value;
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
        return BotSpot_WP_Options::sanitize_option_value("sync_sensitivity", $value);
    }

    /**
     * Sanitize injection position value
     *
     * @since    1.0.0
     */
    public function sanitize_position($value)
    {
        return BotSpot_WP_Options::sanitize_option_value("injection_position", $value);
    }

    /**
     * Sanitize JSON-LD conflict mode value
     *
     * @since    1.2.0
     */
    public function sanitize_jsonld_conflict_mode($value)
    {
        return BotSpot_WP_Options::sanitize_option_value("jsonld_conflict_mode", $value);
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
     * POST directly to core /api/v1/webhooks with API key + site info,
     * store returned webhook_id, webhook_secret, and tenant_id (org_id).
     *
     * @since    1.1.0
     */
    public function handle_register_connection()
    {
        check_ajax_referer("botspot_wp_register_connection", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        // Accept an inline api_key from the Connect button so the user
        // doesn't need to hit "Save settings" first. If the key is new or
        // changed, also clear the old webhook so the registration below
        // creates a fresh one against the new key's organization.
        $this->maybe_save_inline_api_key(true);

        $api_key = BotSpot_WP_Options::get("api_key");
        if (empty($api_key)) {
            wp_send_json_error(["message" => __("Please enter an access key.", "botspot-wp")]);
            return;
        }

        // Register webhook directly with locus-core.
        // The WebhookRead response includes id, secret, and org_id, so we get
        // everything we need in a single call - no locus-connectors dependency.
        $locus_api_url = BotSpot_WP_Options::get_locus_api_url();
        $webhook_url = rest_url("botspot-wp/v1/webhook");

        $response = wp_remote_post(
            rtrim($locus_api_url, "/") . "/api/v1/webhooks",
            [
                "headers" => [
                    "Content-Type" => "application/json",
                    "X-API-Key" => $api_key,
                ],
                "body" => wp_json_encode([
                    "url" => $webhook_url,
                    "events" => ["content.indexed", "content.enriched"],
                    "name" => "WordPress - " . get_bloginfo("name"),
                ]),
                "timeout" => 15,
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error([
                "message" => sprintf(
                    __("Connection failed: %s", "botspot-wp"),
                    $response->get_error_message()
                ),
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
            $error_msg = is_array($body) && isset($body["detail"])
                ? $body["detail"]
                : sprintf("HTTP %d", $status_code);
            // Friendly copy for auth failures — the most common cause of
            // webhook registration failing is a wrong / truncated key.
            $friendly = ($status_code === 401 || $status_code === 403)
                ? __("Connection failed: Invalid Access Key. Please ensure you copied the entire key from the bot.spot dashboard.", "botspot-wp")
                : sprintf(__("Connection failed: %s", "botspot-wp"), $error_msg);
            wp_send_json_error(["message" => $friendly]);
            return;
        }

        // Store everything from the webhook create response
        if (!empty($body["id"])) {
            BotSpot_WP_Options::set("webhook_id", $body["id"]);
            // Also alias as connection_id for backwards compat with existing
            // code that checks connection_id as the "is connected" signal
            BotSpot_WP_Options::set("connection_id", $body["id"]);
        }
        if (!empty($body["secret"])) {
            BotSpot_WP_Options::set("webhook_secret", $body["secret"]);
        }
        if (!empty($body["org_id"])) {
            BotSpot_WP_Options::set("tenant_id", $body["org_id"]);
        }

        // Trigger initial bulk sync to provision site and pages
        BotSpot_WP_Sync::bulk_sync();

        wp_send_json_success([
            "message" => __("Connected successfully.", "botspot-wp"),
            "connection_id" => isset($body["id"]) ? $body["id"] : "",
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
                return __("Synced", "botspot-wp");
            case "pending":
                return __("Pending", "botspot-wp");
            case "error":
                return __("Error", "botspot-wp");
            case "never":
            default:
                return __("Never synced", "botspot-wp");
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

    // ============================================================
    // New AJAX handlers (2.2.0) — Developer tab + status probe
    // ============================================================

    /**
     * Return recent log entries shaped for the Developer tab log viewer.
     *
     * Response:
     *   { success: true, data: { entries: [...], count: int } }
     *
     * @since    2.2.0
     */
    public function handle_get_logs()
    {
        check_ajax_referer("botspot_wp_get_logs", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $level_filter = isset($_POST["level"]) ? sanitize_text_field($_POST["level"]) : "all";
        $entries = BotSpot_WP_Logger::get_logs_for_viewer($level_filter);

        wp_send_json_success([
            "entries" => $entries,
            "count" => count($entries),
        ]);
    }

    /**
     * Return current connection / sync / runtime status for header indicators.
     *
     * Response shape (each status is one of: "ok", "warn", "error", "unknown"):
     *   { success: true, data: {
     *     connection: { status, label, detail },
     *     sync: { status, label, detail },
     *     runtime: { status, label, detail }
     *   }}
     *
     * @since    2.2.0
     */
    public function handle_get_status()
    {
        check_ajax_referer("botspot_wp_get_status", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        wp_send_json_success($this->get_status_snapshot());
    }

    /**
     * Probe connection / sync / runtime state. Called by handle_get_status
     * and cached briefly via transient to avoid repeated HTTP calls on
     * every admin page load.
     *
     * @since    2.2.0
     * @return   array
     */
    protected function get_status_snapshot()
    {
        $cached = get_transient("botspot_wp_status_snapshot");
        if (is_array($cached)) {
            return $cached;
        }

        // ---------- Connection ----------
        $api_key = BotSpot_WP_Options::get("api_key");
        $connection_id = BotSpot_WP_Options::get("connection_id");

        if (empty($api_key)) {
            $connection = [
                "status" => "error",
                "label" => __("No access key", "botspot-wp"),
                "detail" => __("Paste your bot.spot access key on the Connect tab.", "botspot-wp"),
            ];
        } elseif (empty($connection_id)) {
            $connection = [
                "status" => "warn",
                "label" => __("Not registered", "botspot-wp"),
                "detail" => __("Access key present but site is not yet registered with bot.spot.", "botspot-wp"),
            ];
        } else {
            // Quick health probe via content fetcher (has its own short timeout)
            $probe = BotSpot_WP_Content_Fetcher::test_connection();
            $connection = [
                "status" => !empty($probe["success"]) ? "ok" : "error",
                "label" => !empty($probe["success"])
                    ? __("Connected", "botspot-wp")
                    : __("Unreachable", "botspot-wp"),
                "detail" => isset($probe["message"]) ? (string) $probe["message"] : "",
            ];
        }

        // ---------- Sync ----------
        // Look for any post synced in the last 24h without a current error
        $recent_synced = get_posts([
            "post_type" => "any",
            "post_status" => "publish",
            "posts_per_page" => 1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "_botspot_sync_status",
                    "value" => "synced",
                    "compare" => "=",
                ],
                [
                    "key" => "_botspot_last_synced_at",
                    "value" => gmdate("Y-m-d H:i:s", time() - DAY_IN_SECONDS),
                    "compare" => ">=",
                    "type" => "DATETIME",
                ],
            ],
        ]);
        $recent_errors = get_posts([
            "post_type" => "any",
            "post_status" => "publish",
            "posts_per_page" => 1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "_botspot_sync_status",
                    "value" => "error",
                    "compare" => "=",
                ],
            ],
        ]);

        if (!empty($recent_synced)) {
            $sync = [
                "status" => empty($recent_errors) ? "ok" : "warn",
                "label" => empty($recent_errors)
                    ? __("Sync healthy", "botspot-wp")
                    : __("Sync has recent errors", "botspot-wp"),
                "detail" => __("Last successful sync within 24 hours.", "botspot-wp"),
            ];
        } elseif (!empty($recent_errors)) {
            $sync = [
                "status" => "error",
                "label" => __("Sync failing", "botspot-wp"),
                "detail" => __("Recent sync attempts have errored.", "botspot-wp"),
            ];
        } else {
            $sync = [
                "status" => "warn",
                "label" => __("No recent sync", "botspot-wp"),
                "detail" => __("No posts synced in the last 24 hours.", "botspot-wp"),
            ];
        }

        // ---------- Runtime ----------
        // Presence of any fetcher transient indicates appendix was served recently.
        global $wpdb;
        $fetcher_hits = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                "_transient_botspot_wp_appendix_%"
            )
        );
        if ($fetcher_hits > 0) {
            $runtime = [
                "status" => "ok",
                "label" => __("Runtime active", "botspot-wp"),
                "detail" => sprintf(
                    /* translators: %d: cached entries */
                    _n("%d cached appendix entry.", "%d cached appendix entries.", $fetcher_hits, "botspot-wp"),
                    $fetcher_hits
                ),
            ];
        } else {
            $runtime = [
                "status" => "warn",
                "label" => __("Runtime idle", "botspot-wp"),
                "detail" => __("No frontend requests have cached appendix content yet.", "botspot-wp"),
            ];
        }

        $snapshot = [
            "connection" => $connection,
            "sync" => $sync,
            "runtime" => $runtime,
            "checked_at" => gmdate("c"),
        ];

        set_transient("botspot_wp_status_snapshot", $snapshot, 5 * MINUTE_IN_SECONDS);

        return $snapshot;
    }

    /**
     * Force a full re-sync of all tracked posts.
     *
     * @since    2.2.0
     */
    public function handle_force_resync()
    {
        check_ajax_referer("botspot_wp_force_resync", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $post_types = BotSpot_WP_Options::get("sync_post_types", ["post", "page"]);
        $post_ids = get_posts([
            "post_type" => $post_types,
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
        ]);

        if (empty($post_ids)) {
            wp_send_json_success([
                "queued" => 0,
                "message" => __("No posts to sync.", "botspot-wp"),
            ]);
            return;
        }

        BotSpot_WP_Options::set("force_resync_started_at", time());
        BotSpot_WP_Options::set("force_resync_total", count($post_ids));
        delete_transient("botspot_wp_status_snapshot");

        // Run bulk_sync directly - uses batch ingest endpoint which is efficient
        $result = BotSpot_WP_Sync::bulk_sync();

        BotSpot_WP_Options::set("force_resync_finished_at", time());
        if ($result) {
            BotSpot_WP_Options::set("force_resync_succeeded", $result["processed"] ?? 0);
            BotSpot_WP_Options::set("force_resync_failed", $result["failed"] ?? 0);
        }

        if ($result === false) {
            wp_send_json_error([
                "message" => __("Sync failed - check API key configuration.", "botspot-wp"),
            ]);
            return;
        }

        wp_send_json_success([
            "queued" => $result["processed"] ?? 0,
            "total" => $result["total"] ?? count($post_ids),
            "failed" => $result["failed"] ?? 0,
            "message" => sprintf(
                /* translators: 1: number of posts synced */
                __("Resync complete: %d post(s) synced.", "botspot-wp"),
                $result["processed"] ?? 0
            ),
        ]);
    }

    /**
     * wp-cron callback that performs the actual force resync.
     *
     * Registered to the `botspot_wp_force_resync_run` action and triggered
     * once per Force Resync click. Walks every published post of the
     * configured post types and pushes each through the sync pipeline.
     *
     * @since 2.6.3
     */
    public function run_scheduled_force_resync()
    {
        $post_types = BotSpot_WP_Options::get("sync_post_types", ["post", "page"]);
        $post_ids = get_posts([
            "post_type" => $post_types,
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
        ]);

        $queued = 0;
        $errors = 0;
        foreach ($post_ids as $post_id) {
            if (BotSpot_WP_Sync::manual_sync($post_id)) {
                $queued++;
            } else {
                $errors++;
            }
        }

        BotSpot_WP_Options::set("force_resync_finished_at", time());
        BotSpot_WP_Options::set("force_resync_succeeded", $queued);
        BotSpot_WP_Options::set("force_resync_failed", $errors);
        delete_transient("botspot_wp_status_snapshot");
    }

    /**
     * Clear all fetcher transients (cached appendix HTML + JSON-LD).
     *
     * @since    2.2.0
     */
    public function handle_clear_cache()
    {
        check_ajax_referer("botspot_wp_clear_cache", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $deleted = BotSpot_WP_Cache::purge_all();

        wp_send_json_success([
            "cleared" => $deleted,
            "message" => sprintf(
                /* translators: %d: cleared entries */
                _n(
                    "Cleared %d cached entry and purged external page caches.",
                    "Cleared %d cached entries and purged external page caches.",
                    max(0, $deleted),
                    "botspot-wp"
                ),
                max(0, $deleted)
            ),
        ]);
    }

    /**
     * AJAX: Save all plugin settings.
     *
     * Bypasses WordPress options.php to avoid memory issues on shared hosts.
     * Each setting is sanitized and saved individually via BotSpot_WP_Options.
     *
     * @since    2.6.8
     */
    public function handle_save_settings()
    {
        check_ajax_referer("botspot_wp_save_settings", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $settings = isset($_POST["settings"]) ? $_POST["settings"] : [];
        if (!is_array($settings)) {
            wp_send_json_error(["message" => __("Invalid settings data", "botspot-wp")]);
            return;
        }

        // Map of setting keys to their sanitizers
        $sanitizers = [
            "api_key" => [$this, "sanitize_secret_field_api_key"],
            "auto_sync_enabled" => [$this, "sanitize_checkbox"],
            "sync_sensitivity" => [$this, "sanitize_sensitivity"],
            "sync_post_types" => [$this, "sanitize_post_types"],
            "appendix_enabled" => [$this, "sanitize_checkbox"],
            "jsonld_enabled" => [$this, "sanitize_checkbox"],
            "jsonld_conflict_mode" => [$this, "sanitize_jsonld_conflict_mode"],
            "injection_position" => [$this, "sanitize_position"],
            "inject_on_post_types" => [$this, "sanitize_post_types"],
            "cache_ttl" => "absint",
            "debug_mode" => [$this, "sanitize_checkbox"],
        ];

        $saved = 0;
        foreach ($sanitizers as $key => $sanitizer) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $value = $settings[$key];

            // Apply sanitizer
            if (is_callable($sanitizer)) {
                $value = call_user_func($sanitizer, $value);
            }

            BotSpot_WP_Options::set($key, $value);
            $saved++;
        }

        // Clear status snapshot so next load picks up changes
        delete_transient("botspot_wp_status_snapshot");

        wp_send_json_success([
            "saved" => $saved,
            "message" => __("Settings saved.", "botspot-wp"),
        ]);
    }

    /**
     * AJAX: count posts by sync status for the Analytics tab.
     */
    public function handle_get_sync_health()
    {
        check_ajax_referer('botspot_wp_get_sync_health', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden']);
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT meta_value AS status, COUNT(*) AS n
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_botspot_sync_status'
             GROUP BY meta_value",
            ARRAY_A
        );

        $counts = ['synced' => 0, 'pending' => 0, 'error' => 0, 'never' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $counts[$status] = (int) $row['n'];
        }

        wp_send_json_success(['counts' => $counts]);
    }

    /**
     * AJAX: count posts by enrichment tier.
     */
    public function handle_get_enrichment_lifecycle()
    {
        check_ajax_referer('botspot_wp_get_enrichment_lifecycle', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden']);
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT meta_value AS tier, COUNT(*) AS n
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_botspot_enrichment_tier'
             GROUP BY meta_value",
            ARRAY_A
        );

        $counts = ['NONE' => 0, 'TIER0' => 0, 'TIER1' => 0, 'TIER2' => 0, 'FULL' => 0];
        foreach ($rows as $row) {
            $tier = (string) $row['tier'];
            $counts[$tier] = (int) $row['n'];
        }

        wp_send_json_success(['counts' => $counts]);
    }

    /**
     * AJAX: query impressions from locus-core and cache for 10 minutes.
     *
     * Query param: window (24h|7d|30d), default 7d.
     */
    public function handle_get_impressions()
    {
        check_ajax_referer('botspot_wp_get_impressions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden']);
        }

        $window = isset($_POST['window']) ? sanitize_text_field($_POST['window']) : '7d';
        if (!in_array($window, ['24h', '7d', '30d'], true)) {
            $window = '7d';
        }

        $cache_key = "botspot_impressions_$window";
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            wp_send_json_success($cached);
        }

        // Opportunistic flush if stale
        if (BotSpot_WP_Analytics_Flusher::should_opportunistic_flush()) {
            BotSpot_WP_Analytics_Flusher::flush(false);
        }

        $api_url = BotSpot_WP_Options::get('api_url', '');
        $api_key = BotSpot_WP_Options::get('api_key', '');
        if (empty($api_url) || empty($api_key)) {
            wp_send_json_error(['message' => 'API not configured']);
        }

        $url = rtrim($api_url, '/') . '/api/v1/analytics/impressions?since=' . $window . '&limit=10';
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'X-API-Key'    => $api_key,
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($body)) {
            wp_send_json_error(['message' => "HTTP $code"]);
        }

        // Correlate artifact_id → WP post for display
        $decorated = [];
        foreach ((array) ($body['by_artifact'] ?? []) as $row) {
            $artifact_id = (string) ($row['artifact_id'] ?? '');
            $post = $this->find_post_by_artifact_id($artifact_id);
            $row['title'] = $post ? get_the_title($post) : '(unknown)';
            $row['permalink'] = $post ? get_permalink($post) : null;
            $decorated[] = $row;
        }
        $body['by_artifact'] = $decorated;

        set_transient($cache_key, $body, 600);
        wp_send_json_success($body);
    }

    /**
     * AJAX: force a flush (Flush now button).
     */
    public function handle_force_flush()
    {
        check_ajax_referer('botspot_wp_force_flush', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden']);
        }

        $result = BotSpot_WP_Analytics_Flusher::flush(true);

        // Invalidate the impressions cache so the next tab load sees fresh data
        foreach (['24h', '7d', '30d'] as $window) {
            delete_transient("botspot_impressions_$window");
        }

        wp_send_json_success($result);
    }

    /**
     * Look up a WP post by its `_botspot_artifact_id` meta value.
     *
     * @param string $artifact_id
     * @return int|null Post ID or null if not found.
     */
    private function find_post_by_artifact_id($artifact_id)
    {
        if (empty($artifact_id)) {
            return null;
        }
        $posts = get_posts([
            'post_type'      => 'any',
            'posts_per_page' => 1,
            'meta_key'       => '_botspot_artifact_id',
            'meta_value'     => $artifact_id,
            'fields'         => 'ids',
        ]);
        return !empty($posts) ? (int) $posts[0] : null;
    }
}
