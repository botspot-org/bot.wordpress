<?php
/**
 * Fired during plugin activation
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    Bspt
 * @subpackage Bspt/includes
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
 * @package    Bspt
 * @subpackage Bspt/includes
 * @author     BotSpot Team
 */
class Bspt_Activator {

    /**
     * Plugin activation actions.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Migrate from botdot-wp if upgrading from v2.x
        self::migrate_from_botdot();

        // Migrate from botspot_wp_ prefix to bspt_ (WordPress.org compliance)
        self::migrate_prefix();

        // Connection defaults
        if (!Bspt_Options::exists('api_key')) {
            Bspt_Options::set('api_key', '');
        }
        // Remove legacy botspot_key (was a redundant copy of api_key)
        Bspt_Options::migrate_remove_botspot_key();
        if (!Bspt_Options::exists('webhook_secret')) {
            Bspt_Options::set('webhook_secret', '');
        }
        if (!Bspt_Options::exists('connection_id')) {
            Bspt_Options::set('connection_id', '');
        }

        // Sync defaults
        if (!Bspt_Options::exists('auto_sync_enabled')) {
            Bspt_Options::set('auto_sync_enabled', true);
        }
        if (!Bspt_Options::exists('sync_sensitivity')) {
            Bspt_Options::set('sync_sensitivity', 'medium');
        }
        if (!Bspt_Options::exists('sync_post_types')) {
            Bspt_Options::set('sync_post_types', array('post', 'page'));
        }

        // Display defaults
        if (!Bspt_Options::exists('appendix_enabled')) {
            Bspt_Options::set('appendix_enabled', true);
        }
        if (!Bspt_Options::exists('jsonld_enabled')) {
            Bspt_Options::set('jsonld_enabled', true);
        }
        if (!Bspt_Options::exists('jsonld_conflict_mode')) {
            Bspt_Options::set('jsonld_conflict_mode', 'merge');
        }
        if (!Bspt_Options::exists('injection_position')) {
            Bspt_Options::set('injection_position', 'bottom');
        }
        if (!Bspt_Options::exists('inject_on_post_types')) {
            Bspt_Options::set('inject_on_post_types', array('post', 'page'));
        }
        // Cache defaults
        if (!Bspt_Options::exists('cache_ttl')) {
            Bspt_Options::set('cache_ttl', 3600);
        }

        // Debug defaults
        if (!Bspt_Options::exists('debug_mode')) {
            Bspt_Options::set('debug_mode', false);
        }

        // Set activation notice
        set_transient('bspt_activation_notice', true, 60);

        // Schedule hourly analytics flush wp-cron event
        if (!wp_next_scheduled('botspot_flush_analytics')) {
            wp_schedule_event(time() + 3600, 'hourly', 'botspot_flush_analytics');
        }

        // Register webhook for cache invalidation (if API key is configured)
        if (Bspt_Options::get('api_key')) {
            require_once BSPT_PLUGIN_PATH . 'includes/class-bspt-webhook-handler.php';
            Bspt_Webhook_Handler::register_webhook();
        }

        if (Bspt_Options::get('debug_mode')) {
            Bspt_Logger::log_debug('BotSpot WP v3.0.0 activated.');
        }
    }

    /**
     * Migrate options and post meta from botdot-wp v2.x naming.
     *
     * @since 3.0.0
     */
    private static function migrate_from_botdot() {
        global $wpdb;

        if (get_option('bspt_migrated_from_botdot')) {
            return;
        }

        // 1. Migrate options: botdot_wp_* -> bspt_*
        $old_options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'botdot_wp_%'"
        );
        foreach ($old_options as $opt) {
            $new_name = str_replace('botdot_wp_', 'bspt_', $opt->option_name);
            if (false === get_option($new_name)) {
                update_option($new_name, maybe_unserialize($opt->option_value));
            }
        }

        // 2. Migrate post meta: _botdot_* -> _botspot_*
        $wpdb->query(
            "UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, '_botdot_', '_botspot_') WHERE meta_key LIKE '_botdot_%'"
        );

        update_option('bspt_migrated_from_botdot', time());
    }

    /**
     * Migrate options from botspot_wp_ prefix to bspt_ (WordPress.org compliance).
     *
     * @since 3.2.0
     */
    private static function migrate_prefix() {
        global $wpdb;

        if (get_option('bspt_migrated_from_botspot_wp')) {
            return;
        }

        // Migrate options: botspot_wp_* -> bspt_*
        $old_options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'botspot_wp_%'"
        );
        foreach ($old_options as $opt) {
            $new_name = str_replace('botspot_wp_', 'bspt_', $opt->option_name);
            if (false === get_option($new_name)) {
                update_option($new_name, maybe_unserialize($opt->option_value));
            }
            delete_option($opt->option_name);
        }

        // Migrate transients
        $old_transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_botspot_wp_%' OR option_name LIKE '_transient_timeout_botspot_wp_%'"
        );
        foreach ($old_transients as $trans) {
            delete_option($trans->option_name);
        }

        update_option('bspt_migrated_from_botspot_wp', time());
    }
}
