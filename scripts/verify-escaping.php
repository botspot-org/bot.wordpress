<?php
/**
 * Escaping-pipeline verification against real WordPress.
 *
 * Run inside WordPress Playground so wp_kses(), wp_kses_allowed_html() and
 * wp_json_encode() are the genuine core implementations rather than stubs.
 *
 * Proves three things about the 3.5.14 escaping changes:
 *
 *  1. With no filter attached, the new pipeline (strip -> filter -> wp_kses)
 *     produces output byte-identical to the old one (strip -> wp_kses -> filter).
 *  2. A realistic appendix payload survives the allowlist intact: the inline
 *     <style> block with its CSS, the SVG icons, the inline style attributes and
 *     the details/summary/dl structure.
 *  3. Output added by a bspt_appendix_html filter is now escaped, which is the
 *     behaviour the WordPress.org review asked for.
 *
 * Usage (from the plugin root):
 *   npx @wp-playground/cli@latest php \
 *     --blueprint=scripts/plugin-check-blueprint.json --quiet \
 *     --mount=.:/wordpress/wp-content/plugins/botspot \
 *     -- /wordpress/wp-content/plugins/botspot/scripts/verify-escaping.php
 */

// Playground's `php` runner executes the file standalone, so load WordPress
// ourselves when it is not already bootstrapped.
if (!function_exists('wp_kses')) {
    foreach (['/wordpress/wp-load.php', __DIR__ . '/../../../../wp-load.php'] as $bspt_wp_load) {
        if (file_exists($bspt_wp_load)) {
            define('WP_USE_THEMES', false);
            require_once $bspt_wp_load;
            break;
        }
    }
}

if (!function_exists('wp_kses')) {
    fwrite(STDERR, "This script must run inside WordPress.\n");
    exit(1);
}

$failures = 0;
$checks = 0;

/**
 * @param string $name
 * @param bool   $passed
 * @param string $detail
 */
function check($name, $passed, $detail = '')
{
    global $failures, $checks;
    ++$checks;
    if ($passed) {
        echo "  PASS  {$name}\n";
        return;
    }
    ++$failures;
    echo "  FAIL  {$name}\n";
    if ($detail !== '') {
        echo "        {$detail}\n";
    }
}

// ---------------------------------------------------------------------------
// The allowlist, mirroring Bspt_Content_Injector::allowed_appendix_html().
// ---------------------------------------------------------------------------
function bspt_verify_allowed_html()
{
    $allowed = wp_kses_allowed_html('post');
    $allowed['section'] = ['id' => true, 'class' => true, 'style' => true, 'role' => true, 'aria-label' => true];
    $allowed['details'] = ['class' => true, 'open' => true, 'id' => true, 'data-type' => true];
    $allowed['summary'] = ['class' => true, 'id' => true];
    $allowed['dl'] = ['class' => true, 'id' => true];
    $allowed['dt'] = ['class' => true, 'id' => true];
    $allowed['dd'] = ['class' => true, 'id' => true];
    $allowed['svg'] = ['width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'xmlns' => true, 'class' => true];
    $allowed['path'] = ['d' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill' => true];
    $allowed['style'] = ['id' => true, 'type' => true];
    $allowed['span']['style'] = true;
    $allowed['div']['style'] = true;

    return $allowed;
}

function bspt_verify_strip_config($html)
{
    return preg_replace('/<script[^>]*id="locus-injection-config"[^>]*>.*?<\/script>/s', '', $html);
}

// A payload shaped like real service output: scoped CSS, SVG icon, CSS custom
// property wrapper, disclosure widget, definition list, and the config script
// that must be stripped.
$payload = <<<'HTML'
<style id="ba-style" type="text/css">.ba-root{contain:style;isolation:isolate;--ba-fg:#111}.ba-root > .ba-q{font-weight:600}.ba-root a:hover{text-decoration:underline}@media (max-width:600px){.ba-root{padding:8px}}</style>
<section class="ba-root" id="ba-appendix" role="complementary" aria-label="AI appendix" style="--ba-accent:#0a7">
<div style="--ba-gap:12px"><span style="color:var(--ba-fg)">Overview</span></div>
<svg width="16" height="16" viewbox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="ba-icon"><path d="M2 8L6 12L14 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
<details class="ba-faq" open data-type="faq"><summary class="ba-q">What is this?</summary>
<dl class="ba-dl"><dt class="ba-dt">Term &amp; more</dt><dd class="ba-dd">Definition with <a href="https://example.com/a?x=1&amp;y=2">a link</a> and <strong>bold</strong>.</dd></dl>
</details>
<p>Body text with an em dash &#8212; and an entity &amp;.</p>
</section>
<script type="application/json" id="locus-injection-config">{"position":"bottom_of_content"}</script>
HTML;

echo "\n=== 1. Old vs new pipeline order, no filter attached ===\n";

$stripped = bspt_verify_strip_config($payload);
$allowed = bspt_verify_allowed_html();

// Old: strip -> wp_kses -> apply_filters
$old = apply_filters('bspt_appendix_html_oldorder', wp_kses($stripped, $allowed));
// New: strip -> apply_filters -> wp_kses
$new = wp_kses(apply_filters('bspt_appendix_html', $stripped), $allowed);

check(
    'rendered appendix HTML is byte-identical to the previous pipeline',
    $old === $new,
    'lengths: old=' . strlen($old) . ' new=' . strlen($new)
);

echo "\n=== 2. The appendix payload survives the allowlist ===\n";

check('inline <style> block is preserved', strpos($new, '<style id="ba-style"') !== false);
check('scoped CSS rules survive', strpos($new, 'contain:style;isolation:isolate') !== false);
check('CSS media query survives', strpos($new, '@media (max-width:600px)') !== false);
check('SVG element is preserved', strpos($new, '<svg') !== false);
check('SVG path data is preserved', strpos($new, 'd="M2 8L6 12L14 4"') !== false);
check('section style attribute survives', strpos($new, '--ba-accent:#0a7') !== false);
check('div CSS custom property survives', strpos($new, '--ba-gap:12px') !== false);
check('span style attribute survives', strpos($new, 'color:var(--ba-fg)') !== false);
check('details/summary survive', strpos($new, '<details') !== false && strpos($new, '<summary') !== false);
check('dl/dt/dd survive', strpos($new, '<dl') !== false && strpos($new, '<dt') !== false && strpos($new, '<dd') !== false);
check('links survive', strpos($new, 'https://example.com/a?x=1') !== false);
check('injection config script is stripped', strpos($new, 'locus-injection-config') === false);

echo "\n=== 2b. Pre-existing core behaviour: '>' inside <style> ===\n";

// core wp_kses() entity-encodes ">" in the text content of an allowed element,
// including <style>. Because <style> is a raw-text element, browsers do NOT
// decode character references inside it, so a CSS child selector written as
// ".a > .b" reaches the browser as ".a &gt; .b" and that rule is dropped.
//
// This is NOT a 3.5.14 regression: section 1 above proves the old and new
// pipelines produce identical bytes. It is recorded here so the behaviour is
// visible rather than silently accepted, and so a future change to how the
// appendix CSS is delivered can assert against it.
$child_selector_intact = strpos($new, '.ba-root > .ba-q') !== false;
$child_selector_encoded = strpos($new, '.ba-root &gt; .ba-q') !== false;

check(
    'documented: wp_kses encodes ">" in CSS child selectors (pre-existing)',
    $child_selector_encoded && !$child_selector_intact,
    'if this now fails, the appendix CSS delivery changed - re-evaluate'
);

echo "\n=== 3. Filter output is escaped (the review fix) ===\n";

add_filter('bspt_appendix_html', function ($html) {
    return $html . '<script>alert(1)</script><img src=x onerror=alert(2)>';
});

$filtered_new = wp_kses(apply_filters('bspt_appendix_html', $stripped), $allowed);

check('filter-injected <script> is removed', strpos($filtered_new, '<script>alert(1)') === false);
check('filter-injected onerror handler is removed', stripos($filtered_new, 'onerror') === false);

remove_all_filters('bspt_appendix_html');

echo "\n=== 4. JSON-LD cannot break out of the script element ===\n";

$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$json = wp_json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => '</script><script>alert(1)</script>',
    'url' => 'https://example.com/a/b?x=1&y=2',
], $flags);

check('no literal </script> in encoded JSON-LD', strpos($json, '</script>') === false);
check('no literal < or > in encoded JSON-LD', strpos($json, '<') === false && strpos($json, '>') === false);
check('URLs stay human-readable', strpos($json, 'https://example.com/a/b') !== false);

$decoded = json_decode($json, true);
check(
    'JSON-LD round-trips to the original values',
    is_array($decoded)
        && $decoded['headline'] === '</script><script>alert(1)</script>'
        && $decoded['url'] === 'https://example.com/a/b?x=1&y=2'
);

echo "\n=== 5. Diagnostic comment cannot terminate early ===\n";

$debug = str_replace('--', '-_-', wp_json_encode(['where' => 'the_content', 'path' => '--> x'], $flags));
$comment = "\n<!-- bsa-appendix:" . esc_html($debug) . " -->\n";
$body = substr($comment, strpos($comment, 'bsa-appendix:'), -6);

check('payload contains no "--" sequence', strpos($body, '--') === false);
check('comment has exactly one terminator', substr_count($comment, '-->') === 1);

echo "\n";
if ($failures > 0) {
    echo "FAIL: {$failures} of {$checks} checks failed.\n";
    exit(1);
}
echo "PASS: all {$checks} checks passed against real WordPress.\n";
exit(0);
