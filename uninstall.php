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
$options = [
    // Connection
    "botspot_wp_api_key",
    "botspot_wp_botspot_key", // legacy, removed in 1.3.0 but clean up if present
    "botspot_wp_webhook_secret",
    "botspot_wp_webhook_id",
    "botspot_wp_connection_id",
    "botspot_wp_tenant_id",
    "botspot_wp_platform_settings",
    "botspot_wp_local_settings_backup",
    "botspot_wp_migrated_from_botdot",
    // Sync
    "botspot_wp_auto_sync_enabled",
    "botspot_wp_sync_sensitivity",
    "botspot_wp_sync_post_types",
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
    // Analytics
    "botspot_wp_last_flush_at",
    "botspot_wp_last_flush_id",
];

foreach ($options as $option) {
    delete_option($option);
}

/**
 * Clear all transients
 */
delete_transient("botspot_wp_recent_errors");
delete_transient("botspot_wp_activation_notice");
delete_transient("botspot_wp_settings_updated_notice");
delete_transient("botspot_wp_status_snapshot");
delete_transient("botspot_flush_lock");
delete_transient("botspot_impressions_24h");
delete_transient("botspot_impressions_7d");
delete_transient("botspot_impressions_30d");

// Clear all BotSpot-owned transients.
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s",
        $wpdb->esc_like("_transient_botspot_content_") . "%",
        $wpdb->esc_like("_transient_timeout_botspot_content_") . "%",
        $wpdb->esc_like("_transient_botspot_jsonld_") . "%",
        $wpdb->esc_like("_transient_timeout_botspot_jsonld_") . "%",
        $wpdb->esc_like("_transient_botspot_impressions_") . "%",
        $wpdb->esc_like("_transient_timeout_botspot_impressions_") . "%",
        $wpdb->esc_like("_transient_botspot_wp_") . "%",
        $wpdb->esc_like("_transient_timeout_botspot_wp_") . "%",
    ),
);

/**
 * Delete all post meta
 */
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_sync_hash"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_last_synced_at"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_sync_status"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_sync_word_count"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_inject_enabled"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_artifact_id"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_enrichment_tier"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_enrichment_status"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_pre_enrich_jsonld"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_impressions_pending"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_impressions_inflight"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_impressions_inflight_batch"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_impressions_inflight_at"]);
