<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://botdot.ai
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
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.1.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Deactivator {

    /**
     * Plugin deactivation actions.
     *
     * Clears transients and any temporary data.
     *
     * @since    0.1.0
     */
    public static function deactivate() {
        // Clear error transients
        BotDot_WP_Logger::clear_errors();

        // Clear activation notice transient
        delete_transient('botdot_wp_activation_notice');

        // Unschedule cache polling cron job
        BotDot_WP_Cache_Clearer::unschedule_polling();

        // Unschedule any pending LiteSpeed Cache re-enable event
        $litespeed_timestamp = wp_next_scheduled('botdot_wp_reenable_litespeed');
        if ($litespeed_timestamp) {
            wp_unschedule_event($litespeed_timestamp, 'botdot_wp_reenable_litespeed');

            // If LiteSpeed was disabled, re-enable it immediately on deactivation
            BotDot_WP_Cache_Clearer::reenable_litespeed_cache();
        }

        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('BotSpot WP deactivated: Recache polling unscheduled, LiteSpeed Cache re-enabled if it was disabled.');
        }
    }
}
