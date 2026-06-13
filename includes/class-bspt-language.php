<?php
/**
 * Per-post and current-request language detection helpers.
 *
 * Resolution order is Polylang → WPML → WordPress locale. Each step is
 * feature-detected at call time so no plugin dependency is required;
 * sites without a multilingual plugin fall back to WP's site-wide locale,
 * which matches the plugin's pre-2.5.0 behavior.
 *
 * All returns are the BCP-47 language subtag (e.g. "en", "fi", "sv") —
 * the first two chars of the locale. Region subtags (e.g. "en-US") are
 * intentionally dropped to match the core API's language parameter.
 *
 * @since      2.5.0
 * @package    Bspt
 */

if (!defined("WPINC")) {
    die();
}

class Bspt_Language
{
    /**
     * Resolve the language for a given post.
     *
     * Polylang is preferred over WPML when both are installed (Polylang's
     * API is cheaper and returns a slug directly). Falls back to
     * {@see get_current_language()} when no $post_id is provided or when
     * neither plugin can resolve the post.
     *
     * @since    2.5.0
     * @param    int|null    $post_id    Post ID or null for current request.
     * @return   string                  Two-letter language code.
     */
    public static function get_post_language($post_id = null)
    {
        if ($post_id !== null && $post_id > 0) {
            // Polylang: returns slug like "fi" directly.
            if (function_exists("pll_get_post_language")) {
                $lang = pll_get_post_language((int) $post_id, "slug");
                if (is_string($lang) && $lang !== "") {
                    return substr($lang, 0, 2);
                }
            }

            // WPML: apply_filters returns ['language_code' => 'fi', ...]
            if (has_filter("wpml_post_language_details")) {
                $details = apply_filters(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML integration hook.
                    "wpml_post_language_details",
                    null,
                    (int) $post_id,
                );
                if (
                    is_array($details) &&
                    isset($details["language_code"]) &&
                    is_string($details["language_code"]) &&
                    $details["language_code"] !== ""
                ) {
                    return substr($details["language_code"], 0, 2);
                }
            }
        }

        return self::get_current_language();
    }

    /**
     * Resolve the language for the current request.
     *
     * Used when no post context is available (e.g. injection on a page that
     * may not map cleanly to a single post, or cache-key derivation on the
     * frontend). Polylang and WPML both filter WP's locale hook, so
     * get_locale() already returns the per-page language on properly
     * configured multilingual sites.
     *
     * @since    2.5.0
     * @return   string    Two-letter language code.
     */
    public static function get_current_language()
    {
        // Polylang: explicit slug query is cheap and unambiguous.
        if (function_exists("pll_current_language")) {
            $lang = pll_current_language("slug");
            if (is_string($lang) && $lang !== "") {
                return substr($lang, 0, 2);
            }
        }

        // WPML: current language via filter.
        if (has_filter("wpml_current_language")) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML integration hook.
            $lang = apply_filters("wpml_current_language", null);
            if (is_string($lang) && $lang !== "") {
                return substr($lang, 0, 2);
            }
        }

        return substr(get_locale(), 0, 2);
    }
}
