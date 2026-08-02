<?php
/**
 * Runtime smoke test for the 3.5.14 review fixes.
 *
 * Runs inside WordPress Playground with the plugin active, and drives the real
 * hooks rather than calling methods directly, so it catches wiring mistakes
 * (a filter registered on the wrong hook, a renamed callback, a fatal on load)
 * that static analysis and unit tests cannot see.
 *
 * Deliberately does NOT contact the BotSpot API: fetches return null on a
 * disconnected site, so this verifies the plumbing, the gates, and the
 * non-network output paths. The escaping of the appendix payload itself is
 * covered by scripts/verify-escaping.php.
 *
 * Usage (from the plugin root):
 *   just check-runtime
 */

if (!function_exists('add_filter')) {
    foreach (['/wordpress/wp-load.php'] as $bspt_wp_load) {
        if (file_exists($bspt_wp_load)) {
            define('WP_USE_THEMES', false);
            require_once $bspt_wp_load;
            break;
        }
    }
}

if (!function_exists('add_filter')) {
    fwrite(STDERR, "This script must run inside WordPress.\n");
    exit(1);
}

$failures = 0;
$checks = 0;

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

echo "\nWordPress " . get_bloginfo('version') . ", PHP " . PHP_VERSION . "\n";

// ---------------------------------------------------------------------------
echo "\n=== 1. Plugin loads and registers its hooks ===\n";

check('plugin is active', is_plugin_active('botspot/botspot.php'));
check('BSPT_VERSION is defined', defined('BSPT_VERSION'));

// Read the expected version from the plugin header rather than hardcoding it.
// A literal here is a fifth place a release has to remember to bump, and it is
// the one `just check-version` cannot see — so it only ever fails later, inside
// Playground, long after the other four were updated together.
$bspt_plugin_file = WP_PLUGIN_DIR . '/botspot/botspot.php';
$bspt_header_version = '';
if (file_exists($bspt_plugin_file)) {
    $bspt_plugin_data = get_plugin_data($bspt_plugin_file, false, false);
    $bspt_header_version = isset($bspt_plugin_data['Version']) ? $bspt_plugin_data['Version'] : '';
}
check(
    'version constant matches the plugin header',
    $bspt_header_version !== '' && defined('BSPT_VERSION') && BSPT_VERSION === $bspt_header_version,
    'header=' . $bspt_header_version . ' constant=' . (defined('BSPT_VERSION') ? BSPT_VERSION : 'undefined')
);
check('injector class loaded', class_exists('Bspt_Content_Injector'));
check('public class loaded', class_exists('Bspt_Public'));
check('cache class loaded', class_exists('Bspt_Cache'));

// ---------------------------------------------------------------------------
echo "\n=== 2. Shortcode registration: prefixed only ===\n";

global $shortcode_tags;

check('[bspt_appendix] is registered', shortcode_exists('bspt_appendix'));
check('[botspot_appendix] is registered', shortcode_exists('botspot_appendix'));
check(
    '[botdot_appendix] is NOT registered (the review finding)',
    !shortcode_exists('botdot_appendix'),
    'registered tags: ' . implode(', ', array_keys($shortcode_tags))
);

// Compare against core's own shortcodes rather than assuming every registered
// tag belongs to this plugin — WordPress itself registers gallery, caption etc.
$core_shortcodes = [
    'wp_caption', 'caption', 'gallery', 'playlist', 'audio', 'video', 'embed',
];
$non_prefixed = [];
foreach (array_keys($shortcode_tags) as $tag) {
    if (in_array($tag, $core_shortcodes, true)) {
        continue;
    }
    if (strpos($tag, 'bspt') !== 0 && strpos($tag, 'botspot') !== 0) {
        $non_prefixed[] = $tag;
    }
}
check(
    'this plugin registers no shortcode without a prefix',
    $non_prefixed === [],
    'found: ' . implode(', ', $non_prefixed)
);

// ---------------------------------------------------------------------------
echo "\n=== 3. Legacy shortcode rewrite is wired to the_content ===\n";

check(
    'rewrite_legacy_shortcode is hooked on the_content',
    has_filter('the_content') !== false
);

$rewritten = apply_filters('the_content', 'x [botdot_appendix] y');
check(
    'legacy [botdot_appendix] no longer appears verbatim after the_content',
    strpos($rewritten, '[botdot_appendix]') === false,
    'got: ' . trim(substr($rewritten, 0, 200))
);

// Priority must be below core's do_shortcode (11) or the rewrite is pointless.
$priority = null;
global $wp_filter;
if (isset($wp_filter['the_content'])) {
    foreach ($wp_filter['the_content']->callbacks as $prio => $callbacks) {
        foreach ($callbacks as $cb) {
            if (is_array($cb['function']) && is_object($cb['function'][0])
                && $cb['function'][0] instanceof Bspt_Public
                && $cb['function'][1] === 'rewrite_legacy_shortcode') {
                $priority = $prio;
            }
        }
    }
}
check(
    'rewrite runs before core do_shortcode (priority < 11)',
    $priority !== null && $priority < 11,
    'priority: ' . var_export($priority, true)
);

// ---------------------------------------------------------------------------
echo "\n=== 4. Blocks are registered under a prefixed namespace ===\n";

if (class_exists('WP_Block_Type_Registry')) {
    $registry = WP_Block_Type_Registry::get_instance();
    check('bspt/appendix block registered', $registry->is_registered('bspt/appendix'));

    $bad_blocks = [];
    foreach (array_keys($registry->get_all_registered()) as $name) {
        if (strpos($name, 'bspt/') === 0 || strpos($name, 'botspot') === 0) {
            continue;
        }
        if (strpos($name, 'core/') === 0) {
            continue;
        }
        $bad_blocks[] = $name;
    }
    check(
        'no plugin block outside a prefixed namespace',
        $bad_blocks === [],
        'found: ' . implode(', ', $bad_blocks)
    );
}

// ---------------------------------------------------------------------------
echo "\n=== 5. Activation notice output is escaped ===\n";

set_transient('bspt_activation_notice', 1, 60);
ob_start();
bspt_activation_notice();
$notice = ob_get_clean();

check('notice renders', strpos($notice, 'notice-success') !== false);
check(
    'notice links to the settings page',
    strpos($notice, 'page=botspot') !== false,
    trim($notice)
);
check(
    'notice contains no unescaped placeholder or raw markup',
    strpos($notice, '%s') === false && strpos($notice, '<a href="%') === false
);
check('notice is dismissible', strpos($notice, 'is-dismissible') !== false);

// ---------------------------------------------------------------------------
echo "\n=== 6. Cache purge is safe with no cache plugin installed ===\n";

// The function_exists()/defined() guards must make every third-party call a
// no-op rather than a fatal.
$purge_fired = 0;
add_action('bspt_after_purge_post', function () use (&$purge_fired) {
    ++$purge_fired;
});

$post_id = wp_insert_post([
    'post_title' => 'BotSpot runtime probe',
    'post_content' => 'Body',
    'post_status' => 'publish',
]);

$fatal = false;
try {
    Bspt_Cache::purge_page_caches_for_post($post_id);
    Bspt_Cache::purge_page_caches_all();
} catch (Throwable $e) {
    $fatal = true;
    echo "        exception: " . $e->getMessage() . "\n";
}

check('purge helpers run without error when no cache plugin is present', !$fatal);
check('bspt_after_purge_post still fires', $purge_fired === 1, "fired {$purge_fired} time(s)");

// ---------------------------------------------------------------------------
echo "\n=== 7. Diagnostic comments are gated behind ?bsa-debug=1 ===\n";

$injector = new Bspt_Content_Injector('botspot', BSPT_VERSION);

unset($_GET['bsa-debug']);
$quiet = $injector->inject_appendix_content('BODY');
check(
    'no diagnostics leak without the debug flag',
    strpos($quiet, 'bsa-appendix:') === false,
    trim(substr($quiet, 0, 200))
);
check('content passes through unchanged', strpos($quiet, 'BODY') !== false);

$_GET['bsa-debug'] = '1';
$loud = $injector->inject_appendix_content('BODY');
unset($_GET['bsa-debug']);

check('diagnostics appear with the debug flag', strpos($loud, 'bsa-appendix:') !== false);
check(
    'diagnostic payload is escaped (no raw quotes in the comment)',
    strpos($loud, 'bsa-appendix:{"') === false,
    trim(substr($loud, strpos($loud, 'bsa-appendix:') ?: 0, 160))
);
check(
    'diagnostic comment cannot terminate early',
    substr_count($loud, '-->') === substr_count($loud, '<!--')
);

// ---------------------------------------------------------------------------
echo "\n=== 8. Shortcode render is safe on a disconnected site ===\n";

$rendered = do_shortcode('[bspt_appendix]');
check('shortcode returns a string', is_string($rendered));
check('shortcode emits nothing when not connected', trim($rendered) === '', 'got: ' . trim($rendered));

$legacy_rendered = apply_filters('the_content', '[botdot_appendix]');
check(
    'legacy tag renders without leaving the raw shortcode behind',
    strpos($legacy_rendered, '[botdot_appendix]') === false
        && strpos($legacy_rendered, '[bspt_appendix]') === false,
    'got: ' . trim($legacy_rendered)
);

wp_delete_post($post_id, true);

// ---------------------------------------------------------------------------
echo "\n";
if ($failures > 0) {
    echo "FAIL: {$failures} of {$checks} checks failed.\n";
    exit(1);
}
echo "PASS: all {$checks} runtime checks passed.\n";
exit(0);
