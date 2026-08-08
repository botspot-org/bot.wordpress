<?php
/**
 * BOT-348: the platform's placement value must survive normalization.
 *
 * @since 3.6.0
 */

use PHPUnit\Framework\TestCase;

class PlatformSettingsNormalizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-webhook-handler.php';
    }

    private function normalize(array $settings)
    {
        $ref = new ReflectionMethod('Bspt_Webhook_Handler', 'normalize_platform_settings');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }

        return $ref->invokeArgs(null, [$settings]);
    }

    /**
     * @dataProvider placementProvider
     */
    public function test_placement_survives(string $sent, string $expected): void
    {
        $result = $this->normalize(['placement_mode' => $sent]);

        $this->assertArrayHasKey('injection_position', $result);
        $this->assertSame($expected, $result['injection_position']);
    }

    public static function placementProvider(): array
    {
        return [
            ['in_content', 'in_content'],
            ['end_of_page', 'end_of_page'],
            ['manual', 'manual'],
            ['above_footer', 'end_of_page'],
            ['bottom_of_page', 'end_of_page'],
            ['bottom_of_content', 'in_content'],
        ];
    }

    public function test_anchor_is_carried(): void
    {
        $result = $this->normalize([
            'placement_mode' => 'end_of_page',
            'placement_anchor' => ['selector' => '.site-footer', 'position' => 'after'],
        ]);

        $this->assertSame(
            ['selector' => '.site-footer', 'position' => 'after'],
            $result['placement_anchor']
        );
    }

    public function test_absent_placement_yields_no_key(): void
    {
        $result = $this->normalize(['jsonld_enabled' => true]);

        $this->assertArrayNotHasKey('injection_position', $result);
    }
}
