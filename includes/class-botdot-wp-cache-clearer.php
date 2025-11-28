<?php
/**
 * Cache clearing class for the BotDot WP plugin
 *
 * @link       https://botdot.ai
 * @since      0.3.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Cache clearing class for the BotDot WP plugin.
 *
 * This class handles polling the recache trigger endpoint and clearing
 * WordPress site caches from various caching plugins.
 *
 * @since      0.3.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Cache_Clearer {

    /**
     * Poll the recache trigger endpoint and clear cache if needed
     *
     * This method is called by WP-Cron every 2.5 minutes.
     * If the recache bit is 1, it clears all WordPress caches.
     *
     * @since    0.3.0
     * @return   void
     */
    public static function poll_recache_trigger() {
        $mirror_domain = BotDot_WP_Options::get('mirror_domain');

        if (empty($mirror_domain)) {
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Skipping recache poll: no mirror domain configured');
            }
            return;
        }

        // Determine protocol
        $protocol = self::get_protocol($mirror_domain);
        $url = $protocol . '://' . $mirror_domain . '/.force-recache-trigger';

        // Fetch the recache bit
        $response = wp_remote_get($url, array(
            'timeout' => 5,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug(sprintf(
                    'Recache poll failed: %s',
                    $response->get_error_message()
                ));
            }
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug(sprintf(
                    'Recache poll returned HTTP %d',
                    $status_code
                ));
            }
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['recache'])) {
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Recache poll response missing "recache" field');
            }
            return;
        }

        if ($data['recache'] !== 1) {
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Recache bit is 0, no action needed');
            }
            return;
        }

        // Recache bit is 1, clear all caches
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('Recache bit is 1, clearing all caches');
        }

        self::clear_site_cache();
    }

    /**
     * Clear all WordPress caches
     *
     * Detects and clears caches from popular WordPress caching plugins.
     *
     * @since    0.3.0
     * @return   bool    True if any cache was cleared.
     */
    public static function clear_site_cache() {
        $cleared = false;

        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared WP Super Cache');
            }
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared W3 Total Cache');
            }
        }

        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared WP Rocket cache');
            }
        }

        // WP Fastest Cache
        if (isset($GLOBALS['wp_fastest_cache']) && method_exists($GLOBALS['wp_fastest_cache'], 'deleteCache')) {
            $GLOBALS['wp_fastest_cache']->deleteCache(true);
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared WP Fastest Cache');
            }
        }

        // LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
            LiteSpeed_Cache_API::purge_all();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared LiteSpeed Cache');
            }
        }

        // Autoptimize
        if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
            autoptimizeCache::clearall();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared Autoptimize cache');
            }
        }

        // Comet Cache
        if (isset($GLOBALS['comet_cache']) && method_exists($GLOBALS['comet_cache'], 'wipe_cache')) {
            $GLOBALS['comet_cache']->wipe_cache();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared Comet Cache');
            }
        }

        // WP-Optimize
        if (function_exists('WP_Optimize') && method_exists(WP_Optimize(), 'get_page_cache')) {
            WP_Optimize()->get_page_cache()->purge();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared WP-Optimize cache');
            }
        }

        // Hummingbird
        if (class_exists('\\Hummingbird\\WP_Hummingbird') && method_exists('\\Hummingbird\\WP_Hummingbird', 'flush_cache')) {
            \Hummingbird\WP_Hummingbird::flush_cache();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared Hummingbird cache');
            }
        }

        // SG Optimizer (SiteGround)
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared SG Optimizer cache');
            }
        }

        // Cachify
        if (class_exists('Cachify') && method_exists('Cachify', 'flush_total_cache')) {
            Cachify::flush_total_cache();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared Cachify cache');
            }
        }

        // Swift Performance
        if (class_exists('Swift_Performance_Cache') && method_exists('Swift_Performance_Cache', 'clear_all_cache')) {
            Swift_Performance_Cache::clear_all_cache();
            $cleared = true;
            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Cleared Swift Performance cache');
            }
        }

        if (!$cleared && BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('No caching plugins detected');
        }

        return $cleared;
    }

    /**
     * Determine the protocol (http or https) based on the domain
     *
     * @since    0.3.0
     * @access   private
     * @param    string    $domain    The domain to check.
     * @return   string               'http' or 'https'.
     */
    private static function get_protocol($domain) {
        // Use HTTP for localhost and 127.0.0.1 (development)
        if (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $domain)) {
            return 'http';
        }
        // Use HTTPS for all other domains (production)
        return 'https';
    }

    /**
     * Schedule the recache polling cron job
     *
     * @since    0.3.0
     * @return   void
     */
    public static function schedule_polling() {
        if (!wp_next_scheduled('botdot_wp_poll_recache')) {
            wp_schedule_event(time(), 'botdot_wp_every_2_5_minutes', 'botdot_wp_poll_recache');

            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Scheduled recache poll cron job (every 2.5 minutes)');
            }
        }
    }

    /**
     * Unschedule the recache polling cron job
     *
     * @since    0.3.0
     * @return   void
     */
    public static function unschedule_polling() {
        $timestamp = wp_next_scheduled('botdot_wp_poll_recache');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'botdot_wp_poll_recache');

            if (BotDot_WP_Options::get('debug_mode')) {
                BotDot_WP_Logger::log_debug('Unscheduled recache poll cron job');
            }
        }
    }

    /**
     * Add custom cron schedule for every 2.5 minutes
     *
     * @since    0.3.0
     * @param    array    $schedules    Existing schedules.
     * @return   array                  Modified schedules.
     */
    public static function add_cron_schedule($schedules) {
        $schedules['botdot_wp_every_2_5_minutes'] = array(
            'interval' => 150, // 2.5 minutes in seconds
            'display'  => __('Every 2.5 Minutes', 'botdot-wp'),
        );
        return $schedules;
    }
}
