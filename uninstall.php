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
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete all plugin options
 */
$options = array(
    // Connection
    'botdot_wp_locus_api_url',
    'botdot_wp_connector_url',
    'botdot_wp_api_key',
    'botdot_wp_botspot_key',
    'botdot_wp_webhook_secret',
    'botdot_wp_connection_id',
    // Sync
    'botdot_wp_auto_sync_enabled',
    'botdot_wp_sync_sensitivity',
    'botdot_wp_sync_post_types',
    // Display
    'botdot_wp_injection_enabled',
    'botdot_wp_injection_position',
    'botdot_wp_inject_on_post_types',
    'botdot_wp_page_injection_status',
    // Cache
    'botdot_wp_cache_ttl',
    // Debug
    'botdot_wp_debug_mode',
);

foreach ($options as $option) {
    delete_option($option);
}

/**
 * Clear all transients
 */
delete_transient('botdot_wp_recent_errors');
delete_transient('botdot_wp_activation_notice');

// Clear all botdot_content_ transients
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_botdot_content_%' OR option_name LIKE '_transient_timeout_botdot_content_%'"
);

/**
 * Delete all post meta
 */
$wpdb->delete($wpdb->postmeta, array('meta_key' => '_botdot_sync_hash'));
$wpdb->delete($wpdb->postmeta, array('meta_key' => '_botdot_last_synced_at'));
$wpdb->delete($wpdb->postmeta, array('meta_key' => '_botdot_sync_status'));
$wpdb->delete($wpdb->postmeta, array('meta_key' => '_botdot_sync_word_count'));
