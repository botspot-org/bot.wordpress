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
