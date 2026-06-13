<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    Bspt
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
    "bspt_api_key",
    "bspt_botspot_key", // legacy, removed in 1.3.0 but clean up if present
    "bspt_webhook_secret",
    "bspt_webhook_id",
    "bspt_connection_id",
    "bspt_tenant_id",
    // Sync
    "bspt_auto_sync_enabled",
    "bspt_sync_sensitivity",
    "bspt_sync_post_types",
    // Display
    "bspt_injection_enabled", // legacy
    "bspt_appendix_enabled",
    "bspt_jsonld_enabled",
    "bspt_jsonld_conflict_mode",
    "bspt_injection_position",
    "bspt_inject_on_post_types",
    "bspt_page_injection_status", // legacy
    // Cache
    "bspt_cache_ttl",
    // Debug
    "bspt_debug_mode",
];

foreach ($options as $option) {
    delete_option($option);
}

/**
 * Clear all transients
 */
delete_transient("bspt_recent_errors");
delete_transient("bspt_activation_notice");

// Clear all botspot_content_ transients
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like("_transient_botspot_content_") . "%",
        $wpdb->esc_like("_transient_timeout_botspot_content_") . "%",
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
