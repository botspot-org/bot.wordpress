<?php
/**
 * Admin settings view with 2-tab interface
 *
 * @link       https://bot.spot
 * @since      1.0.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/admin/partials
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

// Get current tab
$active_tab = isset($_GET["tab"]) ? sanitize_text_field($_GET["tab"]) : "connection";

// Common data
$post_types = get_post_types(["public" => true], "objects");

// Migrate legacy injection_enabled to split appendix_enabled + jsonld_enabled
BotDot_WP_Options::migrate_injection_toggles();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <?php
    $all_entries = BotDot_WP_Logger::get_recent_errors();
    $errors = array_filter($all_entries, function ($e) { return $e["type"] !== "debug"; });
    $debug_entries = array_filter($all_entries, function ($e) { return $e["type"] === "debug"; });
    ?>

    <?php if (!empty($errors)): ?>
        <div class="notice notice-warning">
            <h3><?php _e("Recent Errors", "botdot-wp"); ?></h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach (array_slice($errors, 0, 5) as $error): ?>
                    <li>
                        <strong><?php echo esc_html(ucfirst($error["type"])); ?>:</strong>
                        <?php echo esc_html($error["message"]); ?>
                        <em>(<?php echo esc_html(human_time_diff($error["timestamp"], time())); ?> ago)</em>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <button type="button" id="botdot-wp-clear-errors" class="button">
                    <?php _e("Clear Errors", "botdot-wp"); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>

    <?php if (BotDot_WP_Options::get("debug_mode") && !empty($debug_entries)): ?>
        <div class="notice notice-info">
            <h3><?php _e("Debug Log", "botdot-wp"); ?></h3>
            <ul style="list-style: none; margin-left: 0; font-family: monospace; font-size: 12px;">
                <?php foreach (array_slice($debug_entries, 0, 20) as $entry): ?>
                    <li style="padding: 2px 0; border-bottom: 1px solid #eee;">
                        <span style="color: #999;"><?php echo esc_html(date("H:i:s", $entry["timestamp"])); ?></span>
                        <?php echo esc_html($entry["message"]); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <button type="button" id="botdot-wp-clear-errors" class="button">
                    <?php _e("Clear Log", "botdot-wp"); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=botdot-wp&tab=connection" class="nav-tab <?php echo $active_tab === "connection"
            ? "nav-tab-active"
            : ""; ?>">
            <?php _e("Connection", "botdot-wp"); ?>
        </a>
        <a href="?page=botdot-wp&tab=sync" class="nav-tab <?php echo $active_tab === "sync"
            ? "nav-tab-active"
            : ""; ?>">
            <?php _e("Sync & Injection", "botdot-wp"); ?>
        </a>
    </h2>

    <form method="post" action="options.php" id="botdot-wp-settings-form">
        <?php settings_fields("botdot_wp_settings"); ?>

        <?php
        // Preserve settings from other tabs via hidden fields
        $all_settings = [
            "connection" => [
                "botdot_wp_api_key" => "", // Never expose secret values in hidden fields
            ],
            "sync" => [
                "botdot_wp_auto_sync_enabled" => BotDot_WP_Options::get("auto_sync_enabled") ? "1" : "0",
                "botdot_wp_sync_sensitivity" => BotDot_WP_Options::get("sync_sensitivity", "medium"),
                "botdot_wp_appendix_enabled" => BotDot_WP_Options::get("appendix_enabled") ? "1" : "0",
                "botdot_wp_jsonld_enabled" => BotDot_WP_Options::get("jsonld_enabled") ? "1" : "0",
                "botdot_wp_jsonld_conflict_mode" => BotDot_WP_Options::get("jsonld_conflict_mode", "merge"),
                "botdot_wp_injection_position" => BotDot_WP_Options::get("injection_position", "bottom"),
                "botdot_wp_cache_ttl" => BotDot_WP_Options::get("cache_ttl", 3600),
                "botdot_wp_debug_mode" => BotDot_WP_Options::get("debug_mode") ? "1" : "0",
            ],
        ];

        // Output hidden fields for tabs that aren't active
        foreach ($all_settings as $tab => $fields) {
            if ($tab === $active_tab) {
                continue;
            }
            foreach ($fields as $name => $value) {
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
            }
        }

        // Array settings need special handling
        if ($active_tab !== "sync") {
            $sync_post_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
            foreach ($sync_post_types as $pt) {
                echo '<input type="hidden" name="botdot_wp_sync_post_types[]" value="' . esc_attr($pt) . '">';
            }
            $inject_post_types = BotDot_WP_Options::get("inject_on_post_types", ["post", "page"]);
            foreach ($inject_post_types as $pt) {
                echo '<input type="hidden" name="botdot_wp_inject_on_post_types[]" value="' . esc_attr($pt) . '">';
            }
        }
        ?>

        <!-- Tab 1: Connection -->
        <?php if ($active_tab === "connection"): ?>
        <?php
            $has_api_key = !empty(BotDot_WP_Options::get("api_key"));
            $connection_id = BotDot_WP_Options::get("connection_id");
            $tenant_id = BotDot_WP_Options::get("tenant_id");
            $is_connected = !empty($connection_id);
        ?>
        <div class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e("API Key", "botdot-wp"); ?></th>
                    <td>
                        <input type="password" name="botdot_wp_api_key" value="" class="regular-text" placeholder="<?php echo $has_api_key
                            ? "••••••••"
                            : "lc_wp_xxx"; ?>" autocomplete="off" data-has-value="<?php echo $has_api_key
    ? "1"
    : "0"; ?>">
                        <p class="description"><?php
                        _e("API key for authentication.", "botdot-wp");
                        if ($has_api_key): ?> <?php _e("Leave empty to keep current value.", "botdot-wp");endif;
                        ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("Connection Status", "botdot-wp"); ?></th>
                    <td>
                        <?php if ($is_connected): ?>
                            <span style="color: #00a32a; font-weight: 500;">&#9679; <?php _e("Connected", "botdot-wp"); ?></span>
                            <span style="margin-left: 8px; color: #666;">(<?php echo esc_html($connection_id); ?>)</span>
                            <div style="margin-top: 10px;">
                                <button type="button" id="botdot-wp-test-connection" class="button">
                                    <?php _e("Test Connection", "botdot-wp"); ?>
                                </button>
                                <button type="button" id="botdot-wp-disconnect" class="button" style="margin-left: 5px; color: #d63638;">
                                    <?php _e("Disconnect", "botdot-wp"); ?>
                                </button>
                                <span id="botdot-wp-test-result" style="margin-left: 10px; font-weight: 500;"></span>
                            </div>
                            <div id="botdot-wp-test-details" style="margin-top: 10px; display: none;"></div>
                        <?php else: ?>
                            <span style="color: #999;">&#9675; <?php _e("Not connected", "botdot-wp"); ?></span>
                            <div style="margin-top: 10px;">
                                <button type="button" id="botdot-wp-connect" class="button button-primary" <?php echo !$has_api_key ? 'disabled' : ''; ?>>
                                    <?php _e("Connect", "botdot-wp"); ?>
                                </button>
                                <span id="botdot-wp-connect-result" style="margin-left: 10px; font-weight: 500;"></span>
                            </div>
                            <?php if (!$has_api_key): ?>
                                <p class="description"><?php _e("Save an API key first, then click Connect.", "botdot-wp"); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($tenant_id)): ?>
                <tr>
                    <th scope="row"><?php _e("Tenant ID", "botdot-wp"); ?></th>
                    <td>
                        <code><?php echo esc_html($tenant_id); ?></code>
                        <p class="description"><?php _e("Auto-populated from registration. Used to scope content to your organization.", "botdot-wp"); ?></p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php submit_button(); ?>
        </div>
        <?php endif; ?>

        <!-- Tab 2: Sync & Injection -->
        <?php if ($active_tab === "sync"): ?>
        <div class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e("Auto-Sync on Publish", "botdot-wp"); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="botdot_wp_auto_sync_enabled" value="1" <?php checked(
                                BotDot_WP_Options::get("auto_sync_enabled"),
                                true,
                            ); ?>>
                            <?php _e("Automatically sync content when posts are published or updated", "botdot-wp"); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("Sync Sensitivity", "botdot-wp"); ?></th>
                    <td>
                        <?php $sensitivity = BotDot_WP_Options::get("sync_sensitivity", "medium"); ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="botdot_wp_sync_sensitivity" value="high" <?php checked(
                                $sensitivity,
                                "high",
                            ); ?>>
                            <?php _e("High", "botdot-wp"); ?> &mdash; <span class="description"><?php _e(
     "Every save triggers sync",
     "botdot-wp",
 ); ?></span>
                        </label>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="botdot_wp_sync_sensitivity" value="medium" <?php checked(
                                $sensitivity,
                                "medium",
                            ); ?>>
                            <?php _e("Medium", "botdot-wp"); ?> &mdash; <span class="description"><?php _e(
     "10% word count change threshold",
     "botdot-wp",
 ); ?></span>
                        </label>
                        <label style="display: block;">
                            <input type="radio" name="botdot_wp_sync_sensitivity" value="low" <?php checked(
                                $sensitivity,
                                "low",
                            ); ?>>
                            <?php _e("Low", "botdot-wp"); ?> &mdash; <span class="description"><?php _e(
     "25% word count change threshold",
     "botdot-wp",
 ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("Post Types to Sync", "botdot-wp"); ?></th>
                    <td>
                        <?php $sync_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]); ?>
                        <?php foreach ($post_types as $post_type): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="botdot_wp_sync_post_types[]" value="<?php echo esc_attr(
                                    $post_type->name,
                                ); ?>" <?php checked(in_array($post_type->name, $sync_types)); ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>

            <hr>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e("HTML Appendix", "botdot-wp"); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="botdot_wp_appendix_enabled" value="1" <?php checked(
                                BotDot_WP_Options::get("appendix_enabled"),
                                true,
                            ); ?>>
                            <?php _e("Enable HTML appendix injection", "botdot-wp"); ?>
                        </label>
                        <p class="description"><?php _e(
                            "Injects the rendered appendix HTML into your page content.",
                            "botdot-wp",
                        ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("JSON-LD Structured Data", "botdot-wp"); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="botdot_wp_jsonld_enabled" value="1" <?php checked(
                                BotDot_WP_Options::get("jsonld_enabled"),
                                true,
                            ); ?>>
                            <?php _e("Enable JSON-LD structured data injection", "botdot-wp"); ?>
                        </label>
                        <p class="description"><?php _e(
                            "Injects JSON-LD structured data into &lt;head&gt; for search engine optimization.",
                            "botdot-wp",
                        ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("JSON-LD Conflict Mode", "botdot-wp"); ?></th>
                    <td>
                        <?php $conflict_mode = BotDot_WP_Options::get("jsonld_conflict_mode", "merge"); ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="botdot_wp_jsonld_conflict_mode" value="merge" <?php checked(
                                $conflict_mode,
                                "merge",
                            ); ?>>
                            <?php _e("Merge", "botdot-wp"); ?> &mdash; <span class="description"><?php _e(
     "Suppress our JSON-LD @types that are already emitted by other SEO plugins (Yoast, RankMath)",
     "botdot-wp",
 ); ?></span>
                        </label>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="botdot_wp_jsonld_conflict_mode" value="replace" <?php checked(
                                $conflict_mode,
                                "replace",
                            ); ?>>
                            <?php _e("Replace", "botdot-wp"); ?> &mdash; <span class="description"><?php _e(
     "Always inject our JSON-LD regardless of other plugins",
     "botdot-wp",
 ); ?></span>
                        </label>
                        <label style="display: block;">
                            <input type="radio" name="botdot_wp_jsonld_conflict_mode" value="off" <?php checked(
                                $conflict_mode,
                                "off",
                            ); ?>>
                            <?php _e("Off", "botdot-wp"); ?> &mdash; <span class="description"><?php _e(
     "Disable conflict detection entirely",
     "botdot-wp",
 ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("Injection Position", "botdot-wp"); ?></th>
                    <td>
                        <?php $position = BotDot_WP_Options::get("injection_position", "bottom"); ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="botdot_wp_injection_position" value="bottom" <?php checked(
                                $position,
                                "bottom",
                            ); ?>>
                            <?php _e("Bottom of Content", "botdot-wp"); ?>
                        </label>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="botdot_wp_injection_position" value="above_footer" <?php checked(
                                $position,
                                "above_footer",
                            ); ?>>
                            <?php _e("Above Footer", "botdot-wp"); ?>
                        </label>
                        <label style="display: block;">
                            <input type="radio" name="botdot_wp_injection_position" value="shortcode" <?php checked(
                                $position,
                                "shortcode",
                            ); ?>>
                            <?php _e("Manual Placement Only", "botdot-wp"); ?>
                            <span class="description">&mdash; <?php _e(
                                "Use [botdot_appendix] or [botspot_appendix] shortcode or Gutenberg block",
                                "botdot-wp",
                            ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("Post Types for Injection", "botdot-wp"); ?></th>
                    <td>
                        <?php $inject_types = BotDot_WP_Options::get("inject_on_post_types", ["post", "page"]); ?>
                        <?php foreach ($post_types as $post_type): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="botdot_wp_inject_on_post_types[]" value="<?php echo esc_attr(
                                    $post_type->name,
                                ); ?>" <?php checked(in_array($post_type->name, $inject_types)); ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("Cache TTL", "botdot-wp"); ?></th>
                    <td>
                        <input type="number" name="botdot_wp_cache_ttl" value="<?php echo esc_attr(
                            BotDot_WP_Options::get("cache_ttl", 3600),
                        ); ?>" min="60" max="86400" step="60"> <?php _e("seconds", "botdot-wp"); ?>
                        <p class="description"><?php _e(
                            "Default cache duration for fetched content (60-86400 seconds). May be overridden by server response.",
                            "botdot-wp",
                        ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e("Debug Mode", "botdot-wp"); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="botdot_wp_debug_mode" value="1" <?php checked(
                                BotDot_WP_Options::get("debug_mode"),
                                true,
                            ); ?>>
                            <?php _e("Enable debug logging", "botdot-wp"); ?>
                        </label>
                        <p class="description"><?php _e(
                            "Logs additional debug information to the WordPress debug log.",
                            "botdot-wp",
                        ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </div>
        <?php endif; ?>
    </form>
</div>

<style>
.tab-content { padding-top: 20px; }
.botdot-section { margin-bottom: 30px; }
.botdot-section h2 { margin-top: 0; }
</style>

<script type="text/javascript">
(function($) {
    'use strict';

    $(document).ready(function() {
        var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo admin_url("admin-ajax.php"); ?>';

        // Test connection
        $('#botdot-wp-test-connection').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var result = $('#botdot-wp-test-result');
            var details = $('#botdot-wp-test-details');

            button.prop('disabled', true).text('<?php _e("Testing...", "botdot-wp"); ?>');
            result.html('<span style="color: #2271b1;">&#8987; <?php _e("Connecting...", "botdot-wp"); ?></span>');
            details.hide();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'botdot_wp_test_connection',
                    nonce: '<?php echo wp_create_nonce("botdot_wp_test_connection"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        result.html('<span style="color: #00a32a;">&#10003; ' + response.data.message + '</span>');
                    } else {
                        result.html('<span style="color: #d63638;">&#10007; ' + (response.data && response.data.message ? response.data.message : '<?php _e(
                            "Connection failed",
                            "botdot-wp",
                        ); ?>') + '</span>');
                        if (response.data && response.data.details) {
                            var html = '<ul style="list-style: disc; margin-left: 20px;">';
                            if (response.data.details.locus_core) {
                                var lc = response.data.details.locus_core;
                                html += '<li><strong>Locus Core:</strong> ' + (lc.success ? '&#10003; ' : '&#10007; ') + lc.message + '</li>';
                            }
                            if (response.data.details.connector) {
                                var cn = response.data.details.connector;
                                html += '<li><strong>Connector:</strong> ' + (cn.success ? '&#10003; ' : '&#10007; ') + cn.message + '</li>';
                            }
                            html += '</ul>';
                            details.html(html).show();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    result.html('<span style="color: #d63638;">&#10007; <?php _e(
                        "Request failed",
                        "botdot-wp",
                    ); ?>: ' + (error || status) + '</span>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e("Test Connection", "botdot-wp"); ?>');
                }
            });
        });

        // Connect (register connection)
        $('#botdot-wp-connect').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var result = $('#botdot-wp-connect-result');

            button.prop('disabled', true).text('<?php _e("Connecting...", "botdot-wp"); ?>');
            result.html('');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'botdot_wp_register_connection',
                    nonce: '<?php echo wp_create_nonce("botdot_wp_register_connection"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        result.html('<span style="color: #00a32a;">&#10003; ' + response.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        result.html('<span style="color: #d63638;">&#10007; ' + (response.data && response.data.message ? response.data.message : '<?php _e(
                            "Registration failed",
                            "botdot-wp",
                        ); ?>') + '</span>');
                        button.prop('disabled', false).text('<?php _e("Connect", "botdot-wp"); ?>');
                    }
                },
                error: function(xhr, status, error) {
                    result.html('<span style="color: #d63638;">&#10007; <?php _e(
                        "Request failed",
                        "botdot-wp",
                    ); ?>: ' + (error || status) + '</span>');
                    button.prop('disabled', false).text('<?php _e("Connect", "botdot-wp"); ?>');
                }
            });
        });

        // Disconnect
        $('#botdot-wp-disconnect').on('click', function(e) {
            e.preventDefault();
            if (!confirm('<?php _e("Are you sure you want to disconnect?", "botdot-wp"); ?>')) return;
            var button = $(this);
            button.prop('disabled', true);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'botdot_wp_disconnect',
                    nonce: '<?php echo wp_create_nonce("botdot_wp_disconnect"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data && response.data.message ? response.data.message : '<?php _e("Disconnect failed", "botdot-wp"); ?>');
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('<?php _e("Request failed", "botdot-wp"); ?>');
                    button.prop('disabled', false);
                }
            });
        });

        // Clear errors
        $('#botdot-wp-clear-errors').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            button.prop('disabled', true);
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'botdot_wp_clear_errors',
                    nonce: '<?php echo wp_create_nonce("botdot_wp_clear_errors"); ?>'
                },
                success: function() { location.reload(); },
                error: function() {
                    alert('<?php _e("Failed to clear errors", "botdot-wp"); ?>');
                    button.prop('disabled', false);
                }
            });
        });
    });
})(jQuery);
</script>
