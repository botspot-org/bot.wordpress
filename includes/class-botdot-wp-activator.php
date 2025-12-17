<?php
/**
 * Fired during plugin activation
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
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
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
     * Sets up default options and creates activation notice.
     *
     * @since    0.1.0
     */
    public static function activate() {
        // Set default options if they don't exist
        if (!BotDot_WP_Options::exists('mirror_domain')) {
            BotDot_WP_Options::set('mirror_domain', '');
        }

        if (!BotDot_WP_Options::exists('enabled')) {
            BotDot_WP_Options::set('enabled', false);
        }

        if (!BotDot_WP_Options::exists('fetch_timeout')) {
            BotDot_WP_Options::set('fetch_timeout', 10);
        }

        if (!BotDot_WP_Options::exists('inject_on_post_types')) {
            BotDot_WP_Options::set('inject_on_post_types', array('post', 'page'));
        }

        if (!BotDot_WP_Options::exists('exclude_page_ids')) {
            BotDot_WP_Options::set('exclude_page_ids', array());
        }

        if (!BotDot_WP_Options::exists('debug_mode')) {
            BotDot_WP_Options::set('debug_mode', false);
        }

        // Appendix options
        if (!BotDot_WP_Options::exists('appendix_enabled')) {
            BotDot_WP_Options::set('appendix_enabled', false);
        }

        if (!BotDot_WP_Options::exists('appendix_title')) {
            BotDot_WP_Options::set('appendix_title', 'AI Appendix');
        }

        if (!BotDot_WP_Options::exists('appendix_position')) {
            BotDot_WP_Options::set('appendix_position', 'bottom');
        }

        if (!BotDot_WP_Options::exists('appendix_open_default')) {
            BotDot_WP_Options::set('appendix_open_default', false);
        }

        if (!BotDot_WP_Options::exists('appendix_on_post_types')) {
            BotDot_WP_Options::set('appendix_on_post_types', array('post', 'page'));
        }

        // Theme & Styling defaults
        if (!BotDot_WP_Options::exists('theme_classes_enabled')) {
            BotDot_WP_Options::set('theme_classes_enabled', true);
        }

        if (!BotDot_WP_Options::exists('custom_theme_classes')) {
            BotDot_WP_Options::set(
                'custom_theme_classes',
                BotDot_WP_Options::get_default('custom_theme_classes')
            );
        }

        // Set activation notice
        set_transient('botdot_wp_activation_notice', true, 60);

        // Schedule cache polling cron job
        BotDot_WP_Cache_Clearer::schedule_polling();

        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('BotSpot WP activated: Recache polling scheduled.');
        }
    }
}
