<?php
/**
 * Cache invalidation helpers.
 *
 * Centralises transient wipes (plugin-owned) and page-cache purges (third-party
 * cache plugins) so the same logic is used from both the manual "Clear cache"
 * button and the webhook that fires when locus-core finishes enriching a post.
 *
 * @package BotSpot_WP
 * @subpackage BotSpot_WP/includes
 * @since 2.6.4
 */

if (!defined("WPINC")) {
    die();
}

class BotSpot_WP_Cache
{
    /**
     * Delete all plugin-owned transients for every post (content + jsonld).
     *
     * @return int Rows deleted.
     */
    public static function purge_plugin_transients_all()
    {
        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_botspot_content_%'
                OR option_name LIKE '_transient_timeout_botspot_content_%'
                OR option_name LIKE '_transient_botspot_jsonld_%'
                OR option_name LIKE '_transient_timeout_botspot_jsonld_%'"
        );
        return (int) $deleted;
    }

    /**
     * Delete plugin-owned transients for a single URL path across all languages.
     *
     * Transient keys incorporate an md5 of "{path}_{lang}", so we wipe by
     * deriving the hash for every language the site has.
     *
     * @param string $url_path URL path, e.g. "/my-post".
     */
    public static function purge_plugin_transients_for_path($url_path)
    {
        if (!$url_path) {
            return;
        }
        $langs = [];
        if (class_exists("BotSpot_WP_Language")) {
            $langs[] = BotSpot_WP_Language::get_current_language();
        }
        $langs[] = substr(get_locale(), 0, 2);
        $langs = array_unique(array_filter($langs));
        foreach ($langs as $lang) {
            $hash = md5($url_path . "_" . $lang);
            delete_transient("botspot_content_" . $hash);
            delete_transient("botspot_jsonld_" . $hash);
        }
    }

    /**
     * Fire page-cache purge hooks for a single post across popular cache plugins.
     *
     * All calls are guarded so plugins that aren't installed are silently skipped.
     *
     * @param int $post_id
     */
    public static function purge_page_caches_for_post($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        clean_post_cache($post_id);

        // LiteSpeed Cache
        do_action("litespeed_purge_post", $post_id);

        // W3 Total Cache
        do_action("w3tc_flush_post", $post_id);

        // WP Rocket
        do_action("rocket_clean_post", $post_id);

        // WP Super Cache
        if (function_exists("wp_cache_post_change")) {
            wp_cache_post_change($post_id);
        }

        // SiteGround Optimizer
        if (function_exists("sg_cachepress_purge_cache")) {
            $permalink = get_permalink($post_id);
            if ($permalink) {
                sg_cachepress_purge_cache($permalink);
            }
        }

        // Cache Enabler
        if (function_exists("cache_enabler_clear_page_cache_by_post")) {
            cache_enabler_clear_page_cache_by_post($post_id);
        }

        /**
         * Fires after BotSpot has requested a per-post page-cache purge.
         * Third-party integrations can hook this to add more caches.
         *
         * @param int $post_id
         */
        do_action("botspot_wp_after_purge_post", $post_id);
    }

    /**
     * Fire site-wide page-cache purge hooks across popular cache plugins.
     */
    public static function purge_page_caches_all()
    {
        // LiteSpeed Cache
        do_action("litespeed_purge_all");

        // W3 Total Cache
        do_action("w3tc_flush_all");

        // WP Rocket
        if (function_exists("rocket_clean_domain")) {
            rocket_clean_domain();
        }

        // WP Super Cache
        if (function_exists("wp_cache_clear_cache")) {
            wp_cache_clear_cache();
        }

        // SiteGround Optimizer
        if (function_exists("sg_cachepress_purge_everything")) {
            sg_cachepress_purge_everything();
        }

        // Cache Enabler
        if (function_exists("cache_enabler_clear_complete_cache")) {
            cache_enabler_clear_complete_cache();
        }

        do_action("botspot_wp_after_purge_all");
    }

    /**
     * Purge everything: plugin transients + every external page cache.
     *
     * @return int Plugin transient rows deleted.
     */
    public static function purge_all()
    {
        $deleted = self::purge_plugin_transients_all();
        self::purge_page_caches_all();
        delete_transient("botspot_wp_status_snapshot");
        return $deleted;
    }

    /**
     * Purge a single post: plugin transients for its URL path + all external
     * page caches for that post.
     *
     * @param int $post_id
     */
    public static function purge_post($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        $permalink = get_permalink($post_id);
        if ($permalink) {
            $path = wp_parse_url($permalink, PHP_URL_PATH) ?: "/";
            self::purge_plugin_transients_for_path($path);
        }

        self::purge_page_caches_for_post($post_id);
    }
}
