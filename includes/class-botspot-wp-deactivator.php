<?php
/**
 * Fired during plugin deactivation
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
 * Fired during plugin deactivation.
 *
 * Clears transients and temporary data.
 *
 * @since      0.1.0
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/includes
 * @author     BotSpot Team
 */
class BotSpot_WP_Deactivator {

    /**
     * Plugin deactivation actions.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear error transients
        BotSpot_WP_Logger::clear_errors();

        // Clear activation notice transient
        delete_transient('botspot_wp_activation_notice');

        // Unschedule analytics flush wp-cron event
        $timestamp = wp_next_scheduled('botspot_flush_analytics');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'botspot_flush_analytics');
        }

        // Deregister webhook from locus-core
        require_once BOTSPOT_WP_PLUGIN_PATH . 'includes/class-botspot-wp-webhook-handler.php';
        BotSpot_WP_Webhook_Handler::deregister_webhook();

        if (BotSpot_WP_Options::get('debug_mode')) {
            BotSpot_WP_Logger::log_debug('BotSpot WP deactivated.');
        }
    }
}
