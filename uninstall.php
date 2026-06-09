<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    BotSpot_WP
 */

// If uninstall not called from WordPress, then exit.
if (!defined("WP_UNINSTALL_PLUGIN")) {
    exit();
}

/**
 * Delete all plugin options
 */
$botspot_options = [
    // Connection
    "botspot_wp_api_key",
    "botspot_wp_botspot_key", // legacy, removed in 1.3.0 but clean up if present
    "botspot_wp_webhook_secret",
    "botspot_wp_webhook_id",
    "botspot_wp_connection_id",
    "botspot_wp_tenant_id",
    "botspot_wp_migrated_from_botdot",
    // Sync
    "botspot_wp_auto_sync_enabled",
    "botspot_wp_sync_sensitivity",
    "botspot_wp_sync_post_types",
    "botspot_wp_force_resync_started_at",
    "botspot_wp_force_resync_finished_at",
    "botspot_wp_force_resync_total",
    "botspot_wp_force_resync_succeeded",
    "botspot_wp_force_resync_failed",
    // Display
    "botspot_wp_injection_enabled", // legacy
    "botspot_wp_appendix_enabled",
    "botspot_wp_jsonld_enabled",
    "botspot_wp_jsonld_conflict_mode",
    "botspot_wp_injection_position",
    "botspot_wp_inject_on_post_types",
    "botspot_wp_page_injection_status", // legacy
    // Cache
    "botspot_wp_cache_ttl",
    // Debug
    "botspot_wp_debug_mode",
    "botspot_wp_fatal_errors",
    "botspot_wp_platform_settings",
    "botspot_wp_local_settings_backup",
    "botspot_wp_last_flush_at",
    "botspot_wp_last_flush_id",
];

foreach ($botspot_options as $botspot_option) {
    delete_option($botspot_option);
}

/**
 * Clear all transients
 */
delete_transient("botspot_wp_recent_errors");
delete_transient("botspot_wp_activation_notice");
delete_transient("botspot_wp_status_snapshot");
delete_transient("botspot_flush_lock");

// Clear all plugin-owned transients.
global $wpdb;
$botspot_transient_prefixes = [
    "botspot_content_",
    "botspot_jsonld_",
    "botspot_impressions_",
    "botspot_wp_status_snapshot",
    "botspot_flush_lock",
];

foreach ($botspot_transient_prefixes as $botspot_transient_prefix) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall removes only plugin-owned transient rows by fixed prefixes.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like("_transient_" . $botspot_transient_prefix) . "%",
            $wpdb->esc_like("_transient_timeout_" . $botspot_transient_prefix) . "%"
        )
    );
}

if (function_exists("wp_clear_scheduled_hook")) {
    wp_clear_scheduled_hook("botspot_flush_analytics");
    wp_clear_scheduled_hook("botspot_wp_force_resync_run");
}

/**
 * Delete all post meta
 */
$botspot_post_meta_keys = [
    "_botspot_sync_hash",
    "_botspot_last_synced_at",
    "_botspot_sync_status",
    "_botspot_sync_word_count",
    "_botspot_inject_enabled",
    "_botspot_artifact_id",
    "_botspot_enrichment_tier",
    "_botspot_enrichment_status",
    "_botspot_pre_enrich_jsonld",
    "_botspot_impressions_pending",
    "_botspot_impressions_inflight",
    "_botspot_impressions_inflight_batch",
    "_botspot_impressions_inflight_at",
    // Legacy v2.x names, only plugin-owned data.
    "_botdot_sync_hash",
    "_botdot_last_synced_at",
    "_botdot_sync_status",
    "_botdot_sync_word_count",
    "_botdot_inject_enabled",
    "_botdot_artifact_id",
    "_botdot_enrichment_tier",
    "_botdot_enrichment_status",
    "_botdot_pre_enrich_jsonld",
];

foreach ($botspot_post_meta_keys as $botspot_meta_key) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall removes only plugin-owned post meta keys.
    $wpdb->delete($wpdb->postmeta, ["meta_key" => $botspot_meta_key]);
}
