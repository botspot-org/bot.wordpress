<?php
/**
 * Fired during plugin activation
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fired during plugin activation.
 *
 * Sets up default options and creates activation notice.
 *
 * @since      0.1.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Activator {

    /**
     * Plugin activation actions.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Connection defaults
        if (!BotDot_WP_Options::exists('api_key')) {
            BotDot_WP_Options::set('api_key', '');
        }
        // Remove legacy botspot_key (was a redundant copy of api_key)
        BotDot_WP_Options::migrate_remove_botspot_key();
        if (!BotDot_WP_Options::exists('webhook_secret')) {
            BotDot_WP_Options::set('webhook_secret', '');
        }
        if (!BotDot_WP_Options::exists('connection_id')) {
            BotDot_WP_Options::set('connection_id', '');
        }

        // Sync defaults
        if (!BotDot_WP_Options::exists('auto_sync_enabled')) {
            BotDot_WP_Options::set('auto_sync_enabled', true);
        }
        if (!BotDot_WP_Options::exists('sync_sensitivity')) {
            BotDot_WP_Options::set('sync_sensitivity', 'medium');
        }
        if (!BotDot_WP_Options::exists('sync_post_types')) {
            BotDot_WP_Options::set('sync_post_types', array('post', 'page'));
        }

        // Display defaults
        if (!BotDot_WP_Options::exists('appendix_enabled')) {
            BotDot_WP_Options::set('appendix_enabled', true);
        }
        if (!BotDot_WP_Options::exists('jsonld_enabled')) {
            BotDot_WP_Options::set('jsonld_enabled', true);
        }
        if (!BotDot_WP_Options::exists('jsonld_conflict_mode')) {
            BotDot_WP_Options::set('jsonld_conflict_mode', 'merge');
        }
        if (!BotDot_WP_Options::exists('injection_position')) {
            BotDot_WP_Options::set('injection_position', 'bottom');
        }
        if (!BotDot_WP_Options::exists('inject_on_post_types')) {
            BotDot_WP_Options::set('inject_on_post_types', array('post', 'page'));
        }
        if (!BotDot_WP_Options::exists('page_injection_status')) {
            BotDot_WP_Options::set('page_injection_status', array());
        }

        // Cache defaults
        if (!BotDot_WP_Options::exists('cache_ttl')) {
            BotDot_WP_Options::set('cache_ttl', 3600);
        }

        // Debug defaults
        if (!BotDot_WP_Options::exists('debug_mode')) {
            BotDot_WP_Options::set('debug_mode', false);
        }

        // Set activation notice
        set_transient('botdot_wp_activation_notice', true, 60);

        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('BotSpot WP v1.0.1 activated.');
        }
    }
}
