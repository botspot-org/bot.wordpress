<?php
/**
 * Tests for delivery_mode dispatch logic.
 *
 * These are logic-level unit tests for the delivery_mode resolution that
 * Bspt_Content_Fetcher::fetch() stores and the injector consumes.
 * They do not require a full WP environment.
 *
 * @since 2.8.0
 */

use PHPUnit\Framework\TestCase;

class DeliveryModeDispatchTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Fetcher: $data array shape includes delivery_mode and placement
    // -------------------------------------------------------------------------

    /**
     * When the /render response carries delivery_mode, it must be stored in
     * the $data array returned (and cached) by fetch().
     *
     * We test the normalization logic directly here because fetch() has
     * side-effects (HTTP, transients). The shape contract is:
     *   $data['delivery_mode'] = $body['delivery_mode'] ?? null
     *   $data['placement']     = $body['placement']     ?? null
     */
    public function test_data_shape_includes_delivery_mode_when_present(): void
    {
        $body = [
            'html'          => '<p>Hello</p>',
            'jsonld'        => ['@context' => 'https://schema.org'],
            'content_hash'  => 'abc123',
            'delivery_mode' => 'jsonld_only',
            'placement'     => 'bottom_of_content',
        ];

        $data = $this->normalize_fetch_response($body);

        $this->assertSame('jsonld_only', $data['delivery_mode']);
        $this->assertSame('bottom_of_content', $data['placement']);
    }

    public function test_data_shape_delivery_mode_null_when_absent(): void
    {
        $body = [
            'html'         => '<p>Hello</p>',
            'jsonld'       => ['@context' => 'https://schema.org'],
            'content_hash' => 'abc123',
        ];

        $data = $this->normalize_fetch_response($body);

        $this->assertNull($data['delivery_mode']);
        $this->assertNull($data['placement']);
    }

    // -------------------------------------------------------------------------
    // Delivery mode resolution (mirrors get_delivery_mode() logic)
    // -------------------------------------------------------------------------

    /** @dataProvider deliveryModeProvider */
    public function test_resolve_delivery_mode(mixed $raw_mode, string $expected): void
    {
        $resolved = $this->resolve_delivery_mode($raw_mode);
        $this->assertSame($expected, $resolved);
    }

    public static function deliveryModeProvider(): array
    {
        return [
            'disabled'            => ['disabled',   'disabled'],
            'jsonld_only'         => ['jsonld_only', 'jsonld_only'],
            'full'                => ['full',        'full'],
            'null falls to full'  => [null,          'full'],
            'empty falls to full' => ['',            'full'],
            'unknown falls to full' => ['partial',   'full'],
        ];
    }

    // -------------------------------------------------------------------------
    // HTML injection gate: disabled and jsonld_only skip HTML
    // -------------------------------------------------------------------------

    /** @dataProvider htmlGateProvider */
    public function test_html_injection_gate(string $mode, bool $expect_html): void
    {
        $injects_html = $this->would_inject_html($mode);
        $this->assertSame($expect_html, $injects_html);
    }

    public static function htmlGateProvider(): array
    {
        return [
            'disabled skips HTML'    => ['disabled',   false],
            'jsonld_only skips HTML' => ['jsonld_only', false],
            'full allows HTML'       => ['full',        true],
        ];
    }

    // -------------------------------------------------------------------------
    // JSON-LD injection gate: disabled suppresses JSON-LD, jsonld_only allows it
    // -------------------------------------------------------------------------

    /** @dataProvider jsonldGateProvider */
    public function test_jsonld_injection_gate(string $mode, bool $expect_jsonld): void
    {
        $injects_jsonld = $this->would_inject_jsonld($mode);
        $this->assertSame($expect_jsonld, $injects_jsonld);
    }

    public static function jsonldGateProvider(): array
    {
        return [
            'disabled suppresses JSON-LD'  => ['disabled',   false],
            'jsonld_only allows JSON-LD'   => ['jsonld_only', true],
            'full allows JSON-LD'          => ['full',        true],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers that mirror the plugin's inline dispatch logic
    // -------------------------------------------------------------------------

    /**
     * Mirrors the $data array construction in Bspt_Content_Fetcher::fetch().
     */
    private function normalize_fetch_response(array $body): array
    {
        return [
            'html'          => isset($body['html']) ? $body['html'] : null,
            'jsonld'        => isset($body['jsonld']) ? $body['jsonld'] : null,
            'content_hash'  => isset($body['content_hash']) ? $body['content_hash'] : null,
            'status'        => isset($body['status']) ? $body['status'] : null,
            'reason'        => isset($body['reason']) ? $body['reason'] : null,
            'delivery_mode' => isset($body['delivery_mode']) ? $body['delivery_mode'] : null,
            'placement'     => isset($body['placement']) ? $body['placement'] : null,
        ];
    }

    /**
     * Mirrors get_delivery_mode() in Bspt_Content_Injector.
     */
    private function resolve_delivery_mode(mixed $raw): string
    {
        if (!$raw || !is_string($raw)) {
            return 'full';
        }
        if (!in_array($raw, ['disabled', 'jsonld_only', 'full'], true)) {
            return 'full';
        }
        return $raw;
    }

    /**
     * Mirrors the HTML injection gate in inject_appendix_content() /
     * inject_footer_position() / render_shortcode().
     */
    private function would_inject_html(string $mode): bool
    {
        return !($mode === 'disabled' || $mode === 'jsonld_only');
    }

    /**
     * Mirrors the JSON-LD injection gate in inject_jsonld() when
     * appendix_enabled=true: disabled suppresses, jsonld_only and full allow.
     */
    private function would_inject_jsonld(string $mode): bool
    {
        return $mode !== 'disabled';
    }
}
