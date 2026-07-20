<?php
/**
 * Fired during plugin deactivation
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
 * Fired during plugin deactivation.
 *
 * Clears transients and temporary data.
 *
 * @since      0.1.0
 * @package    Bspt
 * @subpackage Bspt/includes
 * @author     BotSpot Team
 */
class Bspt_Deactivator {

    /**
     * Plugin deactivation actions.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear error transients
        Bspt_Logger::clear_errors();

        // Clear activation notice transient
        delete_transient('bspt_activation_notice');

        // Clear cached header status snapshot (connection/sync/runtime pills) so
        // a subsequent reactivation with a different (or no) API key doesn't
        // read up to 5 minutes of stale "connected" state from before
        // deactivation. See Bspt_Admin::get_status_snapshot().
        delete_transient('bspt_status_snapshot');

        // Deregister webhook from locus-core
        require_once BSPT_PLUGIN_PATH . 'includes/class-bspt-webhook-handler.php';
        Bspt_Webhook_Handler::deregister_webhook();

        if (Bspt_Options::get('debug_mode')) {
            Bspt_Logger::log_debug('BotSpot WP deactivated.');
        }
    }
}
