<?php
/**
 * end_of_page renders at wp_footer, and the placement script runs only for an
 * explicit anchor.
 *
 * @since 3.6.0
 */

use PHPUnit\Framework\TestCase;

class PlacementRenderTest extends TestCase
{
    /** @var Bspt_Content_Injector */
    private $injector;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-content-injector.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_options'] = [];
        $this->injector = new Bspt_Content_Injector('botspot', '3.6.0');
    }

    private function invoke(string $method, array $args = [])
    {
        $ref = new ReflectionMethod($this->injector, $method);
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }

        return $ref->invokeArgs($this->injector, $args);
    }

    public function test_resolve_migrates_legacy_stored_value(): void
    {
        $GLOBALS['bspt_test_options']['bspt_injection_position'] = 'end_of_page';

        $this->assertSame('bottom_of_page', $this->invoke('resolve_injection_position'));
    }

    public function test_resolve_falls_back_to_bottom_of_content(): void
    {
        $GLOBALS['bspt_test_options']['bspt_injection_position'] = 'nonsense';

        $this->assertSame('bottom_of_content', $this->invoke('resolve_injection_position'));
    }

    public function test_anchor_script_is_empty_without_anchor(): void
    {
        $GLOBALS['bspt_test_options']['bspt_injection_position'] = 'end_of_page';

        $this->assertSame('', $this->invoke('build_anchor_script'));
    }

    public function test_anchor_script_carries_the_selector(): void
    {
        $GLOBALS['bspt_test_options']['bspt_injection_position'] = 'end_of_page';
        $GLOBALS['bspt_test_options']['bspt_placement_anchor'] = [
            'selector' => '.site-footer',
            'position' => 'before',
        ];

        $script = $this->invoke('build_anchor_script');

        $this->assertStringContainsString('.site-footer', $script);
        $this->assertStringContainsString('"before"', $script);
        $this->assertStringNotContainsString('#colophon', $script);
    }

    public function test_anchor_script_carries_the_selector_for_above_footer(): void
    {
        $GLOBALS['bspt_test_options']['bspt_injection_position'] = 'above_footer';
        $GLOBALS['bspt_test_options']['bspt_placement_anchor'] = [
            'selector' => '.site-footer',
            'position' => 'before',
        ];

        $this->assertStringContainsString('.site-footer', $this->invoke('build_anchor_script'));
    }

    public function test_anchor_script_is_empty_for_in_content(): void
    {
        $GLOBALS['bspt_test_options']['bspt_injection_position'] = 'in_content';
        $GLOBALS['bspt_test_options']['bspt_placement_anchor'] = [
            'selector' => '.site-footer',
            'position' => 'before',
        ];

        $this->assertSame('', $this->invoke('build_anchor_script'));
    }
}
