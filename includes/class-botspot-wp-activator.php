<?php
/**
 * Fired during plugin activation
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/includes
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
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/includes
 * @author     BotSpot Team
 */
class BotSpot_WP_Activator {

    /**
     * Plugin activation actions.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Migrate from botdot-wp if upgrading from v2.x
        self::migrate_from_botdot();

        // Connection defaults
        if (!BotSpot_WP_Options::exists('api_key')) {
            BotSpot_WP_Options::set('api_key', '');
        }
        // Remove legacy botspot_key (was a redundant copy of api_key)
        BotSpot_WP_Options::migrate_remove_botspot_key();
        if (!BotSpot_WP_Options::exists('webhook_secret')) {
            BotSpot_WP_Options::set('webhook_secret', '');
        }
        if (!BotSpot_WP_Options::exists('connection_id')) {
            BotSpot_WP_Options::set('connection_id', '');
        }

        // Sync defaults
        if (!BotSpot_WP_Options::exists('auto_sync_enabled')) {
            BotSpot_WP_Options::set('auto_sync_enabled', true);
        }
        if (!BotSpot_WP_Options::exists('sync_sensitivity')) {
            BotSpot_WP_Options::set('sync_sensitivity', 'medium');
        }
        if (!BotSpot_WP_Options::exists('sync_post_types')) {
            BotSpot_WP_Options::set('sync_post_types', array('post', 'page'));
        }

        // Display defaults
        if (!BotSpot_WP_Options::exists('appendix_enabled')) {
            BotSpot_WP_Options::set('appendix_enabled', true);
        }
        if (!BotSpot_WP_Options::exists('jsonld_enabled')) {
            BotSpot_WP_Options::set('jsonld_enabled', true);
        }
        if (!BotSpot_WP_Options::exists('jsonld_conflict_mode')) {
            BotSpot_WP_Options::set('jsonld_conflict_mode', 'merge');
        }
        if (!BotSpot_WP_Options::exists('injection_position')) {
            BotSpot_WP_Options::set('injection_position', 'bottom');
        }
        if (!BotSpot_WP_Options::exists('inject_on_post_types')) {
            BotSpot_WP_Options::set('inject_on_post_types', array('post', 'page'));
        }
        // Cache defaults
        if (!BotSpot_WP_Options::exists('cache_ttl')) {
            BotSpot_WP_Options::set('cache_ttl', 3600);
        }

        // Debug defaults
        if (!BotSpot_WP_Options::exists('debug_mode')) {
            BotSpot_WP_Options::set('debug_mode', false);
        }

        // Set activation notice
        set_transient('botspot_wp_activation_notice', true, 60);

        // Schedule hourly analytics flush wp-cron event
        if (!wp_next_scheduled('botspot_flush_analytics')) {
            wp_schedule_event(time() + 3600, 'hourly', 'botspot_flush_analytics');
        }

        // Register webhook for cache invalidation (if API key is configured)
        if (BotSpot_WP_Options::get('api_key')) {
            require_once BOTSPOT_WP_PLUGIN_PATH . 'includes/class-botspot-wp-webhook-handler.php';
            BotSpot_WP_Webhook_Handler::register_webhook();
        }

        if (BotSpot_WP_Options::get('debug_mode')) {
            BotSpot_WP_Logger::log_debug('BotSpot WP v3.0.0 activated.');
        }
    }

    /**
     * Migrate options and post meta from botdot-wp v2.x naming.
     *
     * @since 3.0.0
     */
    private static function migrate_from_botdot() {
        global $wpdb;

        if (get_option('botspot_wp_migrated_from_botdot')) {
            return;
        }

        // 1. Migrate options: botdot_wp_* -> botspot_wp_*
        $old_options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'botdot_wp_%'"
        );
        foreach ($old_options as $opt) {
            $new_name = str_replace('botdot_wp_', 'botspot_wp_', $opt->option_name);
            if (false === get_option($new_name)) {
                update_option($new_name, maybe_unserialize($opt->option_value));
            }
        }

        // 2. Migrate post meta: _botdot_* -> _botspot_*
        $wpdb->query(
            "UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, '_botdot_', '_botspot_') WHERE meta_key LIKE '_botdot_%'"
        );

        update_option('botspot_wp_migrated_from_botdot', time());
    }
}
