<?php
/**
 * Admin settings view with 3-tab interface
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

// Migrate legacy page_injection_status option to post_meta on first load
$legacy_injection_status = get_option("botdot_wp_page_injection_status");
if (is_array($legacy_injection_status) && !empty($legacy_injection_status)) {
    foreach ($legacy_injection_status as $pid => $enabled) {
        $pid = absint($pid);
        if ($pid > 0) {
            update_post_meta($pid, "_botdot_inject_enabled", $enabled ? "1" : "0");
        }
    }
    delete_option("botdot_wp_page_injection_status");
}

// Pagination for display tab
$per_page = 20;
$current_page = isset($_GET["paged"]) ? max(1, intval($_GET["paged"])) : 1;
$search = isset($_GET["s"]) ? sanitize_text_field($_GET["s"]) : "";
$query = null;
$total_pages = 0;

if ($active_tab === "display") {
    $selected_post_types = BotDot_WP_Options::get("inject_on_post_types", ["post", "page"]);
    $args = [
        "post_type" => !empty($selected_post_types) ? $selected_post_types : "post",
        "posts_per_page" => $per_page,
        "paged" => $current_page,
        "post_status" => ["publish", "draft", "pending", "future"],
        "orderby" => "modified",
        "order" => "DESC",
    ];
    if (!empty($search)) {
        $args["s"] = $search;
    }
    $query = new WP_Query($args);
    $total_pages = $query->max_num_pages;
}

// Sync stats for sync tab
$sync_stats = null;
if ($active_tab === "sync") {
    $sync_post_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
    global $wpdb;

    // Single consolidated query for all sync status counts
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT pm.meta_value AS status, COUNT(DISTINCT pm.post_id) AS cnt
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_botdot_sync_status'
         AND pm.meta_value IN (%s, %s, %s)
         AND p.post_status = 'publish'
         GROUP BY pm.meta_value",
            "synced",
            "pending",
            "error",
        ),
    );

    $sync_stats = ["synced" => 0, "pending" => 0, "error" => 0];
    if (is_array($results)) {
        foreach ($results as $row) {
            $sync_stats[$row->status] = (int) $row->cnt;
        }
    }

    $total_published = 0;
    foreach ($sync_post_types as $pt) {
        $total_published += (int) wp_count_posts($pt)->publish;
    }
    $sync_stats["never"] = max(
        0,
        $total_published - $sync_stats["synced"] - $sync_stats["pending"] - $sync_stats["error"],
    );
    $sync_stats["total"] = $total_published;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <?php if (BotDot_WP_Logger::has_errors()): ?>
        <div class="notice notice-warning">
            <h3><?php _e("Recent Errors", "botdot-wp"); ?></h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach (BotDot_WP_Logger::get_recent_errors(5) as $error): ?>
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
            <?php _e("Content Sync", "botdot-wp"); ?>
        </a>
        <a href="?page=botdot-wp&tab=display" class="nav-tab <?php echo $active_tab === "display"
            ? "nav-tab-active"
            : ""; ?>">
            <?php _e("Display & Injection", "botdot-wp"); ?>
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
            ],
            "display" => [
                "botdot_wp_injection_enabled" => BotDot_WP_Options::get("injection_enabled") ? "1" : "0",
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
        }
        if ($active_tab !== "display") {
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

        <!-- Tab 2: Content Sync -->
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

            <?php if ($sync_stats): ?>
            <hr>
            <div class="botdot-section">
                <h2><?php _e("Sync Status", "botdot-wp"); ?></h2>
                <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                    <div style="padding: 15px; background: #edfaef; border-radius: 4px; min-width: 100px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #00a32a;"><?php echo esc_html(
                            $sync_stats["synced"],
                        ); ?></div>
                        <div style="font-size: 12px; color: #555;"><?php _e("Synced", "botdot-wp"); ?></div>
                    </div>
                    <div style="padding: 15px; background: #fcf9e8; border-radius: 4px; min-width: 100px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #dba617;"><?php echo esc_html(
                            $sync_stats["pending"],
                        ); ?></div>
                        <div style="font-size: 12px; color: #555;"><?php _e("Pending", "botdot-wp"); ?></div>
                    </div>
                    <div style="padding: 15px; background: #fcf0f1; border-radius: 4px; min-width: 100px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #d63638;"><?php echo esc_html(
                            $sync_stats["error"],
                        ); ?></div>
                        <div style="font-size: 12px; color: #555;"><?php _e("Errors", "botdot-wp"); ?></div>
                    </div>
                    <div style="padding: 15px; background: #f0f0f1; border-radius: 4px; min-width: 100px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #999;"><?php echo esc_html(
                            $sync_stats["never"],
                        ); ?></div>
                        <div style="font-size: 12px; color: #555;"><?php _e("Never Synced", "botdot-wp"); ?></div>
                    </div>
                </div>

                <p>
                    <button type="button" id="botdot-wp-bulk-sync" class="button button-primary">
                        <?php _e("Full Resync", "botdot-wp"); ?>
                    </button>
                    <span id="botdot-wp-bulk-sync-result" style="margin-left: 10px;"></span>
                </p>
                <p class="description"><?php _e(
                    "Re-sync all published content to locus-connectors. This may take a few minutes for large sites.",
                    "botdot-wp",
                ); ?></p>
            </div>
            <?php endif; ?>

            <?php submit_button(); ?>
        </div>
        <?php endif; ?>

        <!-- Tab 3: Display & Injection -->
        <?php if ($active_tab === "display"): ?>
        <div class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e("Enable Injection", "botdot-wp"); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="botdot_wp_injection_enabled" value="1" <?php checked(
                                BotDot_WP_Options::get("injection_enabled"),
                                true,
                            ); ?>>
                            <?php _e("Enable JSON-LD + appendix injection", "botdot-wp"); ?>
                        </label>
                        <p class="description"><?php _e(
                            "Controls both JSON-LD in &lt;head&gt; and appendix HTML on the page.",
                            "botdot-wp",
                        ); ?></p>
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
                                "Use [botdot_appendix] shortcode or Gutenberg block",
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

            <hr>

            <!-- Per-Page Injection Table -->
            <div class="botdot-section">
                <h2><?php _e("Per-Page Injection", "botdot-wp"); ?></h2>
                <p class="description"><?php _e("Enable or disable injection for specific pages.", "botdot-wp"); ?></p>

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk-action" id="bulk-action-selector-top">
                            <option value="-1"><?php _e("Bulk Actions", "botdot-wp"); ?></option>
                            <option value="enable"><?php _e("Enable Injection", "botdot-wp"); ?></option>
                            <option value="disable"><?php _e("Disable Injection", "botdot-wp"); ?></option>
                        </select>
                        <button type="button" id="doaction" class="button action"><?php _e(
                            "Apply",
                            "botdot-wp",
                        ); ?></button>
                    </div>

                    <div class="alignleft actions" style="margin-left: 10px;">
                        <form method="get" style="display: inline-block; margin: 0;">
                            <input type="hidden" name="page" value="botdot-wp">
                            <input type="hidden" name="tab" value="display">
                            <input type="search" name="s" value="<?php echo esc_attr(
                                $search,
                            ); ?>" placeholder="<?php esc_attr_e("Search pages...", "botdot-wp"); ?>">
                            <button type="submit" class="button"><?php _e("Search", "botdot-wp"); ?></button>
                        </form>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(
                                _n("%s item", "%s items", $query->found_posts, "botdot-wp"),
                                number_format_i18n($query->found_posts),
                            ); ?></span>
                            <?php
                            $page_links = paginate_links([
                                "base" => add_query_arg(["paged" => "%#%", "tab" => "display"]),
                                "format" => "",
                                "prev_text" => "&laquo;",
                                "next_text" => "&raquo;",
                                "total" => $total_pages,
                                "current" => $current_page,
                            ]);
                            echo wp_kses_post($page_links);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <table class="wp-list-table widefat fixed striped" id="botdot-pages-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th scope="col" class="manage-column column-title column-primary"><?php _e(
                                "Title",
                                "botdot-wp",
                            ); ?></th>
                            <th scope="col" class="manage-column"><?php _e("Type", "botdot-wp"); ?></th>
                            <th scope="col" class="manage-column"><?php _e("Status", "botdot-wp"); ?></th>
                            <th scope="col" class="manage-column"><?php _e("Last Modified", "botdot-wp"); ?></th>
                            <th scope="col" class="manage-column"><?php _e("Injection", "botdot-wp"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($query && $query->have_posts()): ?>
                            <?php while ($query->have_posts()):
                                $query->the_post(); ?>
                                <?php
                                $post_id = get_the_ID();
                                $inject_meta = get_post_meta($post_id, "_botdot_inject_enabled", true);
                                $is_enabled = $inject_meta !== "" ? (bool) $inject_meta : true;
                                $post_type_obj = get_post_type_object(get_post_type());
                                ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="page_ids[]" value="<?php echo esc_attr(
                                            $post_id,
                                        ); ?>" class="page-checkbox">
                                    </th>
                                    <td class="title column-title column-primary">
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">
                                                <?php echo esc_html(get_the_title()); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="view">
                                                <a href="<?php echo esc_url(
                                                    get_permalink($post_id),
                                                ); ?>" target="_blank"><?php _e("View", "botdot-wp"); ?></a>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($post_type_obj->labels->singular_name); ?></td>
                                    <td><?php echo esc_html(get_post_status()); ?></td>
                                    <td><?php echo esc_html(get_the_modified_date()); ?></td>
                                    <td>
                                        <label class="botdot-toggle-switch">
                                            <input type="checkbox" class="botdot-page-toggle" data-page-id="<?php echo esc_attr(
                                                $post_id,
                                            ); ?>" <?php checked($is_enabled, true); ?>>
                                            <span class="botdot-toggle-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php
                            endwhile; ?>
                            <?php wp_reset_postdata(); ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6"><?php _e("No pages found.", "botdot-wp"); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages"><?php echo wp_kses_post($page_links); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <?php submit_button(); ?>
        </div>
        <?php endif; ?>
    </form>
</div>

<style>
.tab-content { padding-top: 20px; }
.botdot-toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.botdot-toggle-switch input { opacity: 0; width: 0; height: 0; }
.botdot-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
.botdot-toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
.botdot-toggle-switch input:checked + .botdot-toggle-slider { background-color: #2271b1; }
.botdot-toggle-switch input:checked + .botdot-toggle-slider:before { transform: translateX(20px); }
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

        // Bulk sync
        $('#botdot-wp-bulk-sync').on('click', function(e) {
            e.preventDefault();
            if (!confirm('<?php _e("This will re-sync all published content. Continue?", "botdot-wp"); ?>')) return;
            var button = $(this);
            var result = $('#botdot-wp-bulk-sync-result');
            button.prop('disabled', true).text('<?php _e("Syncing...", "botdot-wp"); ?>');
            result.html('');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'botdot_wp_bulk_sync',
                    nonce: '<?php echo wp_create_nonce("botdot_wp_bulk_sync"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        result.html('<span style="color: #00a32a;">&#10003; ' + response.data.message + '</span>');
                    } else {
                        result.html('<span style="color: #d63638;">&#10007; ' + (response.data.message || '<?php _e(
                            "Sync failed",
                            "botdot-wp",
                        ); ?>') + '</span>');
                    }
                },
                error: function() {
                    result.html('<span style="color: #d63638;">&#10007; <?php _e(
                        "Request failed",
                        "botdot-wp",
                    ); ?></span>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e("Full Resync", "botdot-wp"); ?>');
                }
            });
        });

        // Page toggle switches
        $('.botdot-page-toggle').on('change', function() {
            var checkbox = $(this);
            var pageId = checkbox.data('page-id');
            var enabled = checkbox.is(':checked');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'botdot_wp_toggle_page_injection',
                    nonce: '<?php echo wp_create_nonce("botdot_wp_toggle_page"); ?>',
                    page_id: pageId,
                    enabled: enabled ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {

                    } else {
                        checkbox.prop('checked', !enabled);
                        alert(response.data.message || '<?php _e("Failed to update", "botdot-wp"); ?>');
                    }
                },
                error: function() {
                    checkbox.prop('checked', !enabled);
                    alert('<?php _e("Request failed", "botdot-wp"); ?>');
                }
            });
        });

        // Select all checkbox
        $('#cb-select-all-1').on('change', function() {
            $('.page-checkbox').prop('checked', $(this).is(':checked'));
        });

        // Bulk actions
        $('#doaction').on('click', function(e) {
            e.preventDefault();
            var action = $('#bulk-action-selector-top').val();
            if (action === '-1') {
                alert('<?php _e("Please select an action", "botdot-wp"); ?>');
                return;
            }
            var selectedPages = [];
            $('.page-checkbox:checked').each(function() { selectedPages.push($(this).val()); });
            if (selectedPages.length === 0) {
                alert('<?php _e("Please select at least one page", "botdot-wp"); ?>');
                return;
            }
            var enabled = action === 'enable';
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'botdot_wp_bulk_update_pages',
                    nonce: '<?php echo wp_create_nonce("botdot_wp_bulk_pages"); ?>',
                    page_ids: selectedPages,
                    enabled: enabled ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e("Bulk update failed", "botdot-wp"); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e("Request failed", "botdot-wp"); ?>');
                }
            });
        });
    });
})(jQuery);
</script>
