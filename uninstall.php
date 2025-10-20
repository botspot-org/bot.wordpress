<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://botdot.ai
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
    'botdot_wp_mirror_domain',
    'botdot_wp_enabled',
    'botdot_wp_fetch_timeout',
    'botdot_wp_inject_on_post_types',
    'botdot_wp_exclude_page_ids',
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
