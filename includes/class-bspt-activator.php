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

        // Auto-add WooCommerce product type to existing installs
        self::migrate_woocommerce_post_types();

        // Clear any cached header status snapshot left over from before
        // (re)activation — without this, a reactivated (or freshly reinstalled)
        // site can read up to 5 minutes of stale "connected" state computed
        // before any API key existed. See Bspt_Admin::get_status_snapshot().
        delete_transient('bspt_status_snapshot');

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
            Bspt_Options::set('sync_post_types', self::get_default_post_types());
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
            Bspt_Options::set('inject_on_post_types', self::get_default_post_types());
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

        // Migrate post meta from _botspot_* to _bspt_* (WordPress.org compliance)
        self::migrate_post_meta_prefix();

        // Register webhook for cache invalidation (if API key is configured)
        if (Bspt_Options::get('api_key')) {
            require_once BSPT_PLUGIN_PATH . 'includes/class-bspt-webhook-handler.php';
            Bspt_Webhook_Handler::register_webhook();
        }

        // Sync webhook secret to connectors (backfill for existing installs)
        self::sync_webhook_secret();

        if (Bspt_Options::get('debug_mode')) {
            Bspt_Logger::log_debug('BotSpot WP v' . BSPT_VERSION . ' activated.');
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

        // phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time v2.x->v3.x option/postmeta prefix migration run once per site on activation; queries use hardcoded LIKE patterns with no user input, and caching is not applicable to a single-run migration.
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
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

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

        // phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time botspot_wp_->bspt_ option/transient prefix migration run once per site on activation; queries use hardcoded LIKE patterns with no user input, and caching is not applicable to a single-run migration.
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
        // phpcs:enable WordPress.DB.DirectDatabaseQuery
        foreach ($old_transients as $trans) {
            delete_option($trans->option_name);
        }

        update_option('bspt_migrated_from_botspot_wp', time());
    }

    /**
     * Migrate post meta from _botspot_* prefix to _bspt_* (WordPress.org compliance).
     *
     * @since 3.3.0
     */
    private static function migrate_post_meta_prefix() {
        global $wpdb;

        if (get_option('bspt_migrated_post_meta_prefix')) {
            return;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time _botspot_->_bspt_ postmeta/option prefix migration run once per site on activation; the postmeta query uses a hardcoded LIKE pattern and the option queries are passed through $wpdb->prepare(); caching is not applicable to a single-run migration.
        $wpdb->query(
            "UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, '_botspot_', '_bspt_') WHERE meta_key LIKE '_botspot_%'"
        );

        // Also migrate transients
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, 'botspot_content_', 'bspt_content_') WHERE option_name LIKE %s",
                '%botspot_content_%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, 'botspot_jsonld_', 'bspt_jsonld_') WHERE option_name LIKE %s",
                '%botspot_jsonld_%'
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery
        update_option('bspt_migrated_post_meta_prefix', time());
    }

    /**
     * Get default post types based on active plugins.
     *
     * @since 3.4.0
     * @return array
     */
    private static function get_default_post_types() {
        $types = array('post', 'page');

        if (self::is_woocommerce_active()) {
            $types[] = 'product';
        }

        return $types;
    }

    /**
     * Check if WooCommerce is active.
     *
     * @since 3.4.0
     * @return bool
     */
    private static function is_woocommerce_active() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- applying WordPress core 'active_plugins' filter to detect WooCommerce
        return class_exists('WooCommerce') || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins', array())), true);
    }

    /**
     * Migrate existing installs to include WooCommerce product type.
     *
     * @since 3.4.0
     */
    private static function migrate_woocommerce_post_types() {
        if (get_option('bspt_migrated_woocommerce_types')) {
            return;
        }

        if (!self::is_woocommerce_active()) {
            return;
        }

        foreach (array('sync_post_types', 'inject_on_post_types') as $option) {
            $types = get_option('bspt_' . $option);
            if (is_array($types) && !in_array('product', $types, true)) {
                $types[] = 'product';
                update_option('bspt_' . $option, $types);
            }
        }

        update_option('bspt_migrated_woocommerce_types', time());
    }

    /**
     * Sync webhook secret to connectors for backfill.
     *
     * Existing installs have the secret stored locally but connectors
     * doesn't have it in DB. This pushes it back so force-recache works.
     *
     * @since 3.5.0
     */
    private static function sync_webhook_secret() {
        $api_key = Bspt_Options::get('api_key');
        $connection_id = Bspt_Options::get('connection_id');
        $webhook_secret = Bspt_Options::get('webhook_secret');

        if (empty($api_key) || empty($connection_id) || empty($webhook_secret)) {
            return;
        }

        $connectors_url = Bspt_Options::get_connectors_api_url();
        $endpoint = rtrim($connectors_url, '/') . '/api/v1/connections/' . $connection_id . '/sync-secret';

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ],
            'body' => wp_json_encode([
                'webhook_secret' => $webhook_secret,
            ]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            if (Bspt_Options::get('debug_mode')) {
                Bspt_Logger::log_debug('Webhook secret sync failed: ' . $response->get_error_message());
            }
            return;
        }

        if (Bspt_Options::get('debug_mode')) {
            Bspt_Logger::log_debug('Webhook secret synced to connectors');
        }
    }
}
