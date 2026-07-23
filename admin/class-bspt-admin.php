<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    Bspt
 * @subpackage Bspt/admin
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Bspt
 * @subpackage Bspt/admin
 * @author     BotSpot Team
 */
class Bspt_Admin
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
        register_setting("bspt_settings", "bspt_api_key", [
            "sanitize_callback" => [$this, "sanitize_secret_field_api_key"],
        ]);

        // Sync settings
        register_setting("bspt_settings", "bspt_auto_sync_enabled", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => true,
        ]);
        register_setting("bspt_settings", "bspt_sync_sensitivity", [
            "sanitize_callback" => [$this, "sanitize_sensitivity"],
            "default" => "medium",
        ]);
        register_setting("bspt_settings", "bspt_sync_post_types", [
            "sanitize_callback" => [$this, "sanitize_post_types"],
            "default" => ["post", "page"],
        ]);

        // Display settings
        register_setting("bspt_settings", "bspt_appendix_enabled", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => true,
        ]);
        register_setting("bspt_settings", "bspt_jsonld_enabled", [
            "sanitize_callback" => [$this, "sanitize_checkbox"],
            "default" => true,
        ]);
        register_setting("bspt_settings", "bspt_jsonld_conflict_mode", [
            "sanitize_callback" => [$this, "sanitize_jsonld_conflict_mode"],
            "default" => "merge",
        ]);
        register_setting("bspt_settings", "bspt_injection_position", [
            "sanitize_callback" => [$this, "sanitize_position"],
            "default" => "bottom_of_content",
        ]);
        register_setting("bspt_settings", "bspt_inject_on_post_types", [
            "sanitize_callback" => [$this, "sanitize_post_types"],
            "default" => ["post", "page"],
        ]);
        // Cache settings
        register_setting("bspt_settings", "bspt_cache_ttl", [
            "sanitize_callback" => "absint",
            "default" => 3600,
        ]);

        // Debug settings
        register_setting("bspt_settings", "bspt_debug_mode", [
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
        require_once BSPT_PLUGIN_PATH . "admin/partials/botspot-wp-admin-settings.php";
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
                "bspt-sync-metabox",
                BSPT_PLUGIN_URL . "admin/js/botspot-sync-metabox.js",
                ["jquery"],
                $this->version,
                true
            );
            wp_localize_script("bspt-sync-metabox", "bsptMetabox", [
                "nonce" => wp_create_nonce("bspt_manual_sync"),
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
            "bspt-admin",
            BSPT_PLUGIN_URL . "admin/css/botspot-admin.css",
            [],
            $this->version
        );

        wp_enqueue_script(
            "bspt-admin",
            BSPT_PLUGIN_URL . "admin/js/botspot-admin.js",
            [],
            $this->version,
            true
        );

        // Localized data + nonces for all AJAX handlers the JS will call
        wp_localize_script("bspt-admin", "bsptAdmin", [
            "ajaxurl" => admin_url("admin-ajax.php"),
            "restUrl" => rest_url("botspot-wp/v1/"),
            "siteDomain" => wp_parse_url(home_url(), PHP_URL_HOST),
            "pluginVersion" => $this->version,
            "wpVersion" => get_bloginfo("version"),
            "phpVersion" => phpversion(),
            "apiVersion" => "v1 (stable)",
            "nonces" => [
                "testConnection" => wp_create_nonce("bspt_test_connection"),
                "clearErrors" => wp_create_nonce("bspt_clear_errors"),
                "manualSync" => wp_create_nonce("bspt_manual_sync"),
                "registerConnection" => wp_create_nonce("bspt_register_connection"),
                "getLogs" => wp_create_nonce("bspt_get_logs"),
                "getStatus" => wp_create_nonce("bspt_get_status"),
                "forceResync" => wp_create_nonce("bspt_force_resync"),
                "clearCache" => wp_create_nonce("bspt_clear_cache"),
                "saveSettings" => wp_create_nonce("bspt_save_settings"),
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
    var radios = document.querySelectorAll('input[name="bspt_injection_position"]');
    var note = document.getElementById('bsa-footer-detection-note');
    if (!radios.length || !note) return;
    function toggle() {
        var val = document.querySelector('input[name="bspt_injection_position"]:checked');
        note.style.display = (val && (val.value === 'above_footer' || val.value === 'bottom_of_page')) ? 'block' : 'none';
    }
    radios.forEach(function(r) { r.addEventListener('change', toggle); });
    toggle();
})();
JS;
        wp_add_inline_script("bspt-admin", $toggle_script);
    }

    /**
     * Display admin notices
     *
     * @since    0.1.0
     */
    public function display_admin_notices()
    {
        $screen = get_current_screen();

        // Settings updated notice - only show on plugin page to avoid dashboard hijacking
        if (get_transient("bspt_settings_updated_notice")) {
            if ($screen && $screen->base === "toplevel_page_botspot-wp") {
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <strong><?php esc_html_e("bot.spot:", "botspot-wp"); ?></strong>
                        <?php esc_html_e("Your bot.spot settings were updated remotely.", "botspot-wp"); ?>
                    </p>
                </div>
                <?php
                delete_transient("bspt_settings_updated_notice");
            }
        }

        if (!Bspt_Logger::has_errors()) {
            return;
        }

        if (!$screen || !in_array($screen->base, ["toplevel_page_botspot-wp", "dashboard", "post", "page"], true)) {
            return;
        }

        $last_error = Bspt_Logger::get_last_error();
        if (!$last_error) {
            return;
        }

        $time_ago = human_time_diff($last_error["timestamp"], time());
        ?>
        <div class="notice notice-<?php echo esc_attr($last_error["type"]); ?> is-dismissible">
            <p>
                <strong><?php esc_html_e("bot.spot WP:", "botspot-wp"); ?></strong>
                <?php echo esc_html($last_error["message"]); ?>
                <em>(<?php
                    /* translators: %s: human-readable time ago string */
                    echo esc_html(sprintf(__("%s ago", "botspot-wp"), $time_ago));
                ?>)</em>
            </p>
            <?php if (Bspt_Logger::get_error_count() > 1): ?>
                <p>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: 1: number of additional errors, 2: settings page URL */
                            __('There are %1$d more errors. <a href="%2$s">View settings</a> for details.', "botspot-wp"),
                            Bspt_Logger::get_error_count() - 1,
                            esc_url(admin_url("admin.php?page=botspot-wp"))
                        ),
                        ["a" => ["href" => []]]
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
    public function add_sync_meta_box()
    {
        $sync_post_types = Bspt_Options::get("sync_post_types", ["post", "page"]);
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
        $status = Bspt_Sync::get_sync_status($post->ID);
        $status_label = $this->get_sync_status_label($status["status"]);
        $status_color = $this->get_sync_status_color($status["status"]);
        ?>
        <div class="botspot-sync-meta-box">
            <p>
                <strong><?php esc_html_e("Status:", "botspot-wp"); ?></strong>
                <span style="color: <?php echo esc_attr(
                    $status_color,
                ); ?>;"><?php echo esc_html($status_label); ?></span>
            </p>
            <?php if ($status["last_synced_at"]): ?>
                <p>
                    <strong><?php esc_html_e("Last synced:", "botspot-wp"); ?></strong>
                    <?php echo esc_html(human_time_diff(strtotime($status["last_synced_at"]), time())); ?> <?php esc_html_e(
     "ago",
     "botspot-wp",
 ); ?>
                </p>
            <?php endif; ?>
            <p>
                <button type="button" class="button botspot-sync-now" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php esc_html_e("Sync Now", "botspot-wp"); ?>
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

        $sync_post_types = Bspt_Options::get("sync_post_types", ["post", "page"]);
        if (!in_array(get_post_type($post_id), $sync_post_types, true)) {
            echo '<span style="color: #999;">&#8212;</span>';
            return;
        }

        // Show enrichment status if available, fall back to sync status
        $enrichment_status = get_post_meta($post_id, "_bspt_enrichment_status", true);
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

        $status = Bspt_Sync::get_sync_status($post_id);
        $color = $this->get_sync_status_color($status["status"]);
        $label = $this->get_sync_status_label($status["status"]);
        $icon = $this->get_sync_status_icon($status["status"]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $icon is a fixed HTML entity from get_sync_status_icon()'s internal allow-list, not user input; esc_html() would double-encode the entity.
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
            if (Bspt_Sync::manual_sync($post_id)) {
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
        check_ajax_referer("bspt_test_connection", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $this->maybe_save_inline_api_key();

        $result = Bspt_Content_Fetcher::test_connection();

        if (!empty($result["success"])) {
            // Register webhook for cache invalidation on successful connection
            Bspt_Webhook_Handler::register_webhook();

            // Fetch platform-owned settings
            Bspt_Webhook_Handler::fetch_platform_settings();

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
        check_ajax_referer("bspt_clear_errors", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        Bspt_Logger::clear_errors();
        wp_send_json_success();
    }

    /**
     * Handle AJAX manual sync for single post
     *
     * @since    1.0.0
     */
    public function handle_manual_sync()
    {
        check_ajax_referer("bspt_manual_sync", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $post_id = isset($_POST["post_id"]) ? absint($_POST["post_id"]) : 0;

        if (!$post_id) {
            wp_send_json_error(["message" => __("Invalid post ID", "botspot-wp")]);
            return;
        }

        $result = Bspt_Sync::manual_sync($post_id);

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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_ajax_referer() in the calling AJAX handler (handle_test_connection / handle_register_connection) before this method is invoked.
        if (empty($_POST["api_key"])) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_ajax_referer() in the calling AJAX handler before this method is invoked.
        $submitted = sanitize_text_field(wp_unslash($_POST["api_key"]));
        if (strpos($submitted, "sk_") !== 0) {
            return;
        }
        $current = Bspt_Options::get("api_key");
        if ($submitted === $current) {
            return;
        }
        Bspt_Options::set("api_key", $submitted);
        if ($reset_connection) {
            Bspt_Options::set("webhook_id", "");
            Bspt_Options::set("webhook_secret", "");
            Bspt_Options::set("connection_id", "");
            Bspt_Options::set("tenant_id", "");
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
            return Bspt_Options::get($option_name);
        }
        return $value;
    }

    public function sanitize_secret_field_api_key($value)
    {
        $new_value = $this->sanitize_secret_field($value, "api_key");
        $old_value = Bspt_Options::get("api_key");

        // Auto-disconnect when API key changes and a connection exists
        if (!empty($new_value) && $new_value !== $old_value && !empty(Bspt_Options::get("connection_id"))) {
            Bspt_Options::set("connection_id", "");
            Bspt_Options::set("webhook_secret", "");
            Bspt_Options::set("webhook_id", "");
            Bspt_Options::set("tenant_id", "");

            add_settings_error(
                "bspt_api_key",
                "bspt_api_key_changed",
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
        return Bspt_Options::sanitize_option_value("sync_sensitivity", $value);
    }

    /**
     * Sanitize injection position value
     *
     * @since    1.0.0
     */
    public function sanitize_position($value)
    {
        return Bspt_Options::sanitize_option_value("injection_position", $value);
    }

    /**
     * Sanitize JSON-LD conflict mode value
     *
     * @since    1.2.0
     */
    public function sanitize_jsonld_conflict_mode($value)
    {
        return Bspt_Options::sanitize_option_value("jsonld_conflict_mode", $value);
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
        check_ajax_referer("bspt_register_connection", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        // Accept an inline api_key from the Connect button so the user
        // doesn't need to hit "Save settings" first. If the key is new or
        // changed, also clear the old webhook so the registration below
        // creates a fresh one against the new key's organization.
        $this->maybe_save_inline_api_key(true);

        $api_key = Bspt_Options::get("api_key");
        if (empty($api_key)) {
            wp_send_json_error(["message" => __("Please enter an access key.", "botspot-wp")]);
            return;
        }

        // Register webhook directly with locus-core.
        // The WebhookRead response includes id, secret, and org_id, so we get
        // everything we need in a single call - no locus-connectors dependency.
        $locus_api_url = Bspt_Options::get_locus_api_url();
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
                    /* translators: %s: WP_Error message */
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
                /* translators: %s: error message returned by the connection attempt */
                : sprintf(__("Connection failed: %s", "botspot-wp"), $error_msg);
            wp_send_json_error(["message" => $friendly]);
            return;
        }

        // Store everything from the webhook create response
        if (!empty($body["id"])) {
            Bspt_Options::set("webhook_id", $body["id"]);
            // Also alias as connection_id for backwards compat with existing
            // code that checks connection_id as the "is connected" signal
            Bspt_Options::set("connection_id", $body["id"]);
        }
        if (!empty($body["secret"])) {
            Bspt_Options::set("webhook_secret", $body["secret"]);
        }
        if (!empty($body["org_id"])) {
            Bspt_Options::set("tenant_id", $body["org_id"]);
        }

        // Trigger initial bulk sync to provision site and pages
        Bspt_Sync::bulk_sync();

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
        check_ajax_referer("bspt_get_logs", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified via check_ajax_referer above
        $level_filter = isset($_POST["level"]) ? sanitize_text_field(wp_unslash($_POST["level"])) : "all";
        $entries = Bspt_Logger::get_logs_for_viewer($level_filter);

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
        check_ajax_referer("bspt_get_status", "nonce");

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
        $cached = get_transient("bspt_status_snapshot");
        if (is_array($cached)) {
            return $cached;
        }

        // ---------- Connection ----------
        $api_key = Bspt_Options::get("api_key");
        $connection_id = Bspt_Options::get("connection_id");

        if (empty($api_key)) {
            $connection = [
                "status" => "error",
                "label" => __("No access key", "botspot-wp"),
                "detail" => __("Paste your bot.spot access key on the Connect tab.", "botspot-wp"),
            ];
        } elseif (empty($connection_id)) {
            $connection = [
                "status" => "ok",
                "label" => __("Ready", "botspot-wp"),
                "detail" => __("Access key set. Click Connect to register.", "botspot-wp"),
            ];
        } else {
            // Quick health probe via content fetcher (has its own short timeout)
            $probe = Bspt_Content_Fetcher::test_connection();
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
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, bounded lookup (posts_per_page => 1) used only for an admin status indicator, not a listing query.
            "meta_query" => [
                [
                    "key" => "_bspt_sync_status",
                    "value" => "synced",
                    "compare" => "=",
                ],
                [
                    // _bspt_last_synced_at is always written with current_time("mysql")
                    // (site-local time, see Bspt_Sync), so the comparison threshold
                    // must also be site-local — gmdate() here would silently skew the
                    // "recent" window by the site's UTC offset.
                    "key" => "_bspt_last_synced_at",
                    "value" => gmdate("Y-m-d H:i:s", current_time("timestamp") - DAY_IN_SECONDS),
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
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, bounded lookup (posts_per_page => 1) used only for an admin status indicator, not a listing query.
            "meta_query" => [
                [
                    "key" => "_bspt_sync_status",
                    "value" => "error",
                    "compare" => "=",
                ],
            ],
        ]);

        if (!empty($recent_errors)) {
            $sync = [
                "status" => "error",
                "label" => __("Sync errors", "botspot-wp"),
                "detail" => __("Some posts failed to sync.", "botspot-wp"),
            ];
        } elseif (!empty($recent_synced)) {
            $sync = [
                "status" => "ok",
                "label" => __("Synced", "botspot-wp"),
                "detail" => __("Content synced within 24 hours.", "botspot-wp"),
            ];
        } else {
            $sync = [
                "status" => "ok",
                "label" => __("Ready", "botspot-wp"),
                "detail" => __("No syncs yet. Use Force Re-Sync to start.", "botspot-wp"),
            ];
        }

        // ---------- Runtime ----------
        // Presence of any fetcher transient indicates appendix was served recently.
        // Bspt_Content_Fetcher caches under "bspt_content_*" (rendered appendix
        // HTML) and "bspt_jsonld_*" (JSON-LD payloads) — NOT "bspt_appendix_*".
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned query, prepared; no wp_options API exists for a LIKE scan of transient names, and the result is itself cached via set_transient() below.
        $fetcher_hits = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                "_transient_bspt_content_%",
                "_transient_bspt_jsonld_%"
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
                "status" => "ok",
                "label" => __("Ready", "botspot-wp"),
                "detail" => __("Waiting for first frontend request.", "botspot-wp"),
            ];
        }

        $snapshot = [
            "connection" => $connection,
            "sync" => $sync,
            "runtime" => $runtime,
            "checked_at" => gmdate("c"),
        ];

        set_transient("bspt_status_snapshot", $snapshot, 5 * MINUTE_IN_SECONDS);

        return $snapshot;
    }

    /**
     * Force a full re-sync of all tracked posts.
     *
     * @since    2.2.0
     */
    public function handle_force_resync()
    {
        check_ajax_referer("bspt_force_resync", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $post_types = Bspt_Options::get("sync_post_types", ["post", "page"]);
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

        Bspt_Options::set("force_resync_started_at", time());
        Bspt_Options::set("force_resync_total", count($post_ids));
        delete_transient("bspt_status_snapshot");

        // Run bulk_sync directly - uses batch ingest endpoint which is efficient
        $result = Bspt_Sync::bulk_sync();

        Bspt_Options::set("force_resync_finished_at", time());
        if ($result) {
            Bspt_Options::set("force_resync_succeeded", $result["processed"] ?? 0);
            Bspt_Options::set("force_resync_failed", $result["failed"] ?? 0);
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
     * Registered to the `bspt_force_resync_run` action and triggered
     * once per Force Resync click. Walks every published post of the
     * configured post types and pushes each through the sync pipeline.
     *
     * @since 2.6.3
     */
    public function run_scheduled_force_resync()
    {
        $post_types = Bspt_Options::get("sync_post_types", ["post", "page"]);
        $post_ids = get_posts([
            "post_type" => $post_types,
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
        ]);

        $queued = 0;
        $errors = 0;
        foreach ($post_ids as $post_id) {
            if (Bspt_Sync::manual_sync($post_id)) {
                $queued++;
            } else {
                $errors++;
            }
        }

        Bspt_Options::set("force_resync_finished_at", time());
        Bspt_Options::set("force_resync_succeeded", $queued);
        Bspt_Options::set("force_resync_failed", $errors);
        delete_transient("bspt_status_snapshot");
    }

    /**
     * Clear all fetcher transients (cached appendix HTML + JSON-LD).
     *
     * @since    2.2.0
     */
    public function handle_clear_cache()
    {
        check_ajax_referer("bspt_clear_cache", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        $deleted = Bspt_Cache::purge_all();

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
     * Each setting is sanitized and saved individually via Bspt_Options.
     *
     * @since    2.6.8
     */
    public function handle_save_settings()
    {
        check_ajax_referer("bspt_save_settings", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("Permission denied", "botspot-wp")]);
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized per-key below via the $sanitizers dispatch table before being persisted.
        $settings = isset($_POST["settings"]) ? wp_unslash($_POST["settings"]) : [];
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

            Bspt_Options::set($key, $value);
            $saved++;
        }

        // Clear status snapshot so next load picks up changes
        delete_transient("bspt_status_snapshot");

        wp_send_json_success([
            "saved" => $saved,
            "message" => __("Settings saved.", "botspot-wp"),
        ]);
    }
}
