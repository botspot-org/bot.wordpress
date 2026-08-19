<?php
/**
 * BOT-348: a settings.updated payload for another site must not be applied.
 *
 * @since 3.6.0
 */

use PHPUnit\Framework\TestCase;

if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        $base = isset($GLOBALS['bspt_test_home_url'])
            ? $GLOBALS['bspt_test_home_url']
            : 'https://alpha.com';
        return $base . $path;
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return $component === -1 ? parse_url($url) : parse_url($url, $component);
    }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0)
    {
        $GLOBALS['bspt_test_transients'][$key] = $value;
        return true;
    }
}

class SettingsSiteScopeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-webhook-handler.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_options'] = [];
        $GLOBALS['bspt_test_transients'] = [];
        $GLOBALS['bspt_test_home_url'] = 'https://alpha.com';
    }

    private function apply($settings, $site_domain)
    {
        $handler = (new ReflectionClass('Bspt_Webhook_Handler'))->newInstanceWithoutConstructor();

        $targets = new ReflectionMethod('Bspt_Webhook_Handler', 'payload_targets_this_site');
        if (PHP_VERSION_ID < 80100) {
            $targets->setAccessible(true);
        }
        if (!$targets->invokeArgs(null, [$site_domain])) {
            return;
        }

        $handle = new ReflectionMethod('Bspt_Webhook_Handler', 'handle_settings_updated');
        if (PHP_VERSION_ID < 80100) {
            $handle->setAccessible(true);
        }
        $handle->invokeArgs($handler, [$settings]);
    }

    public function test_matching_domain_is_applied(): void
    {
        $this->apply(['placement_mode' => 'end_of_page'], 'alpha.com');

        $this->assertSame('bottom_of_page', get_option('bspt_injection_position'));
    }

    public function test_www_prefix_still_matches(): void
    {
        $GLOBALS['bspt_test_home_url'] = 'https://www.Alpha.com';

        $this->apply(['placement_mode' => 'end_of_page'], 'alpha.com');

        $this->assertSame('bottom_of_page', get_option('bspt_injection_position'));
    }

    public function test_mismatched_domain_is_ignored(): void
    {
        $this->apply(['placement_mode' => 'end_of_page'], 'beta.com');

        $this->assertFalse(get_option('bspt_injection_position'));
        $this->assertFalse(get_option('bspt_platform_settings'));
    }

    public function test_absent_site_domain_is_applied(): void
    {
        $this->apply(['placement_mode' => 'end_of_page'], null);

        $this->assertSame('bottom_of_page', get_option('bspt_injection_position'));
    }
}
