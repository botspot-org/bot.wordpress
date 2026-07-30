<?php
/**
 * PHPUnit bootstrap for botspot-wp unit tests.
 *
 * Tests that only exercise pure PHP logic (no WP functions) can be run
 * without a WordPress installation. Tests that require WP stubs define
 * their own minimal stubs before including plugin classes.
 */

define('WPINC', 'wp-includes');
define('ABSPATH', '/tmp/');

// Minimal WP stubs used by the injector / fetcher under test.
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) { return $value; }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

// has_shortcode()/has_block() stubs mirroring core's matching rules closely
// enough for the manual-placement detection tests: has_shortcode() matches the
// exact tag name (not a longer name sharing the prefix), has_block() matches
// the block delimiter comment.
if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag) {
        if (!is_string($content) || $content === '') {
            return false;
        }
        return (bool) preg_match('/\[' . preg_quote($tag, '/') . '(?![\w-])/', $content);
    }
}
if (!function_exists('has_block')) {
    function has_block($block_name, $content) {
        if (!is_string($content) || $content === '') {
            return false;
        }
        return strpos($content, '<!-- wp:' . $block_name . ' ') !== false
            || strpos($content, '<!-- wp:' . $block_name . "\n") !== false;
    }
}

// Post-meta and post lookups, backed by test-controlled globals so a test can
// stand up a fake Elementor/Divi/Bricks post without a WordPress install.
$GLOBALS['bspt_test_post_meta'] = [];
$GLOBALS['bspt_test_posts'] = [];

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        $store = isset($GLOBALS['bspt_test_post_meta']) ? $GLOBALS['bspt_test_post_meta'] : [];
        $value = isset($store["{$post_id}:{$key}"]) ? $store["{$post_id}:{$key}"] : '';
        return $single ? $value : ($value === '' ? [] : [$value]);
    }
}
if (!function_exists('get_post')) {
    function get_post($post_id = null) {
        $store = isset($GLOBALS['bspt_test_posts']) ? $GLOBALS['bspt_test_posts'] : [];
        return isset($store[$post_id]) ? $store[$post_id] : null;
    }
}
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text, $remove_breaks = false) {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
        $text = strip_tags($text);
        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
        }
        return trim($text);
    }
}
if (!function_exists('do_shortcode')) {
    // No shortcode handlers are registered in unit tests, so core would return
    // the content unchanged. Mirror that.
    function do_shortcode($content, $ignore_html = false) { return $content; }
}
