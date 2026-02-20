<?php
/**
 * Fired during plugin deactivation
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
 * Fired during plugin deactivation.
 *
 * Clears transients and temporary data.
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
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear error transients
        BotDot_WP_Logger::clear_errors();

        // Clear activation notice transient
        delete_transient('botdot_wp_activation_notice');

        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('BotSpot WP deactivated.');
        }
    }
}
