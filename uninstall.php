<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    BotDot_WP
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
    "botdot_wp_api_key",
    "botdot_wp_botspot_key", // legacy, removed in 1.3.0 but clean up if present
    "botdot_wp_webhook_secret",
    "botdot_wp_webhook_id",
    "botdot_wp_connection_id",
    "botdot_wp_tenant_id",
    // Sync
    "botdot_wp_auto_sync_enabled",
    "botdot_wp_sync_sensitivity",
    "botdot_wp_sync_post_types",
    // Display
    "botdot_wp_injection_enabled", // legacy
    "botdot_wp_appendix_enabled",
    "botdot_wp_jsonld_enabled",
    "botdot_wp_jsonld_conflict_mode",
    "botdot_wp_injection_position",
    "botdot_wp_inject_on_post_types",
    "botdot_wp_page_injection_status", // legacy
    // Cache
    "botdot_wp_cache_ttl",
    // Debug
    "botdot_wp_debug_mode",
];

foreach ($options as $option) {
    delete_option($option);
}

/**
 * Clear all transients
 */
delete_transient("botdot_wp_recent_errors");
delete_transient("botdot_wp_activation_notice");

// Clear all botdot_content_ transients
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like("_transient_botdot_content_") . "%",
        $wpdb->esc_like("_transient_timeout_botdot_content_") . "%",
    ),
);

/**
 * Delete all post meta
 */
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botdot_sync_hash"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botdot_last_synced_at"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botdot_sync_status"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botdot_sync_word_count"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botdot_inject_enabled"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botdot_artifact_id"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botdot_enrichment_tier"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botdot_enrichment_status"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_botspot_pre_enrich_jsonld"]);
