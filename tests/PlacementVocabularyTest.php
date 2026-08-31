<?php
/**
 * Placement vocabulary: legacy values migrate, unknown values fall back.
 *
 * @since 3.6.0
 */

use PHPUnit\Framework\TestCase;

class PlacementVocabularyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
    }

    /**
     * @dataProvider placementProvider
     */
    public function test_migrate_placement_value(string $stored, string $expected): void
    {
        $this->assertSame($expected, Bspt_Options::migrate_placement_value($stored));
    }

    public static function placementProvider(): array
    {
        return [
            ['bottom_of_content', 'bottom_of_content'],
            ['above_footer', 'above_footer'],
            ['bottom_of_page', 'bottom_of_page'],
            ['manual', 'manual'],
            ['in_content', 'bottom_of_content'],
            ['end_of_content', 'bottom_of_content'],
            ['bottom', 'bottom_of_content'],
            ['end_of_page', 'bottom_of_page'],
            ['below_footer', 'bottom_of_page'],
            ['shortcode', 'manual'],
            ['auto', 'bottom_of_page'],
            ['footer', 'bottom_of_page'],
            ['', 'bottom_of_page'],
        ];
    }

    public function test_sanitize_position_migrates_legacy(): void
    {
        $this->assertSame(
            'bottom_of_page',
            Bspt_Options::sanitize_option_value('injection_position', 'end_of_page')
        );
    }

    /**
     * @dataProvider footerPlacementProvider
     */
    public function test_is_footer_placement(string $value, bool $expected): void
    {
        $this->assertSame($expected, Bspt_Options::is_footer_placement($value));
    }

    public static function footerPlacementProvider(): array
    {
        return [
            ['above_footer', true],
            ['bottom_of_page', true],
            ['bottom_of_content', false],
            ['manual', false],
            ['end_of_page', false],
        ];
    }

    public function test_sanitize_anchor_accepts_valid_shape(): void
    {
        $anchor = Bspt_Options::sanitize_option_value(
            'placement_anchor',
            ['selector' => ' .site-footer ', 'position' => 'after']
        );

        $this->assertSame(['selector' => '.site-footer', 'position' => 'after'], $anchor);
    }

    public function test_sanitize_anchor_defaults_position_to_before(): void
    {
        $anchor = Bspt_Options::sanitize_option_value('placement_anchor', ['selector' => '#colophon']);

        $this->assertSame(['selector' => '#colophon', 'position' => 'before'], $anchor);
    }

    /**
     * @dataProvider badAnchorProvider
     */
    public function test_sanitize_anchor_rejects_unusable($value): void
    {
        $this->assertNull(Bspt_Options::sanitize_option_value('placement_anchor', $value));
    }

    public static function badAnchorProvider(): array
    {
        return [
            [null],
            [[]],
            [['position' => 'after']],
            [['selector' => '']],
            ['footer'],
        ];
    }
}
