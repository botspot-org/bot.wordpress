<?php
/**
 * Regression tests for the WordPress.org round-2 review fixes.
 *
 * These exercise the real plugin methods via reflection rather than mirroring
 * their logic, so a future refactor that changes behaviour fails here.
 *
 * Covered:
 *  - the appendix wrapper escapes the position attribute
 *  - the injection-config script is stripped, and stripping is separate from
 *    escaping (so wp_kses can run last, after the bspt_appendix_html filter)
 *  - JSON-LD encoding leaves no HTML-significant character in string values
 *  - diagnostic comments cannot terminate the HTML comment early
 *  - the legacy [botdot_appendix] tag is rewritten to the prefixed shortcode
 *  - manual-placement detection covers every registered block/shortcode name
 *
 * @since 3.5.14
 */

use PHPUnit\Framework\TestCase;

class ReviewFixesTest extends TestCase
{
    /** @var Bspt_Content_Injector */
    private $injector;

    /** @var Bspt_Public */
    private $public;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-content-injector.php';
        require_once __DIR__ . '/../public/class-bspt-public.php';
    }

    protected function setUp(): void
    {
        $this->injector = new Bspt_Content_Injector('botspot', '3.5.14');
        $this->public = new Bspt_Public('botspot', '3.5.14');
    }

    /**
     * Call a private/protected method on an object.
     *
     * @param object $object
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    private function invoke($object, string $method, array $args = [])
    {
        $ref = new ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }

        return $ref->invokeArgs($object, $args);
    }

    // -------------------------------------------------------------------------
    // Appendix wrapper: the position attribute is escaped
    // -------------------------------------------------------------------------

    public function test_wrap_appendix_escapes_position(): void
    {
        $markup = $this->invoke($this->injector, 'wrap_appendix', ['<p>body</p>', 'above_footer']);

        $this->assertSame(
            '<div data-bsa-appendix data-bsa-position="above_footer"><p>body</p></div>',
            $markup
        );
    }

    public function test_wrap_appendix_neutralises_attribute_breakout(): void
    {
        $markup = $this->invoke(
            $this->injector,
            'wrap_appendix',
            ['<p>body</p>', '"><script>alert(1)</script>']
        );

        $this->assertStringNotContainsString('<script>', $markup);
        $this->assertStringContainsString('&lt;script&gt;', $markup);
    }

    // -------------------------------------------------------------------------
    // strip_injection_config: a content transform, not an escaping step
    // -------------------------------------------------------------------------

    public function test_strip_injection_config_removes_config_script(): void
    {
        $html = '<section class="ba-root">keep</section>'
            . '<script type="application/json" id="locus-injection-config">{"a":1}</script>';

        $result = $this->invoke($this->injector, 'strip_injection_config', [$html]);

        $this->assertStringNotContainsString('locus-injection-config', $result);
        $this->assertStringContainsString('keep', $result);
    }

    public function test_strip_injection_config_preserves_appendix_payload(): void
    {
        // The style block, SVG icons and inline style attributes are the parts
        // most at risk from a change in the escaping pipeline.
        $html = '<style id="ba-style">.ba-root > p{color:red}</style>'
            . '<section class="ba-root" style="--x:1"><svg width="16" height="16">'
            . '<path d="M1 1L2 2" stroke="currentColor"/></svg>'
            . '<details><summary>Q</summary><dl><dt>k</dt><dd>v</dd></dl></details></section>';

        $result = $this->invoke($this->injector, 'strip_injection_config', [$html]);

        $this->assertSame($html, $result, 'stripping must not alter the appendix payload');
    }

    public function test_strip_injection_config_handles_non_string(): void
    {
        $this->assertSame('', $this->invoke($this->injector, 'strip_injection_config', [null]));
    }

    // -------------------------------------------------------------------------
    // JSON-LD encoding: no HTML-significant character survives in values
    // -------------------------------------------------------------------------

    public function test_encode_jsonld_escapes_script_breakout(): void
    {
        $json = $this->invoke($this->injector, 'encode_jsonld', [[
            '@context' => 'https://schema.org',
            'name' => '</script><script>alert(1)</script>',
        ]]);

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString('>', $json);
        // Round-trips to the original value for consumers.
        $decoded = json_decode($json, true);
        $this->assertSame('</script><script>alert(1)</script>', $decoded['name']);
    }

    public function test_encode_jsonld_escapes_ampersand_and_quotes(): void
    {
        $json = $this->invoke($this->injector, 'encode_jsonld', [[
            'name' => 'Tom & "Jerry\'s"',
        ]]);

        // & " ' must not appear literally inside the encoded value.
        $value_portion = substr($json, strpos($json, ':') + 1);
        $this->assertStringNotContainsString('&', $value_portion);
        $this->assertStringNotContainsString("'", $value_portion);
        $this->assertSame('Tom & "Jerry\'s"', json_decode($json, true)['name']);
    }

    public function test_encode_jsonld_keeps_urls_readable_after_decode(): void
    {
        $json = $this->invoke($this->injector, 'encode_jsonld', [[
            'url' => 'https://example.com/a/b?x=1&y=2',
        ]]);

        // JSON_UNESCAPED_SLASHES keeps slashes literal; & is hex-escaped but
        // decodes back to the exact URL.
        $this->assertStringContainsString('https://example.com/a/b', $json);
        $this->assertSame(
            'https://example.com/a/b?x=1&y=2',
            json_decode($json, true)['url']
        );
    }

    // -------------------------------------------------------------------------
    // Diagnostic comments cannot close the HTML comment early
    // -------------------------------------------------------------------------

    public function test_debug_payload_is_empty_when_debug_inactive(): void
    {
        unset($_GET['bsa-debug']);

        $this->assertSame(
            '',
            $this->invoke($this->injector, 'bsa_debug_payload', ['the_content', 'injected'])
        );
    }

    public function test_debug_payload_neutralises_comment_terminator(): void
    {
        $_GET['bsa-debug'] = '1';

        $payload = $this->invoke($this->injector, 'bsa_debug_payload', [
            'the_content',
            'injected',
            ['path' => '--> broken'],
        ]);

        unset($_GET['bsa-debug']);

        $this->assertNotSame('', $payload);
        $this->assertStringNotContainsString('--', $payload);
    }

    public function test_debug_comment_escapes_payload(): void
    {
        $_GET['bsa-debug'] = '1';

        $comment = $this->invoke($this->injector, 'bsa_debug_comment', [
            'the_content',
            'injected',
            ['path' => '<script>'],
        ]);

        unset($_GET['bsa-debug']);

        $this->assertStringStartsWith("\n<!-- bsa-appendix:", $comment);
        $this->assertStringEndsWith(" -->\n", $comment);
        $this->assertStringNotContainsString('<script>', $comment);
    }

    // -------------------------------------------------------------------------
    // Legacy shortcode rewrite replaces the non-prefixed registration
    // -------------------------------------------------------------------------

    /** @dataProvider legacyShortcodeProvider */
    public function test_rewrite_legacy_shortcode(string $content, string $expected): void
    {
        $this->assertSame($expected, $this->public->rewrite_legacy_shortcode($content));
    }

    public static function legacyShortcodeProvider(): array
    {
        return [
            'bare tag' => [
                'before [botdot_appendix] after',
                'before [bspt_appendix] after',
            ],
            'tag with attributes' => [
                '[botdot_appendix foo="bar"]',
                '[bspt_appendix foo="bar"]',
            ],
            'closing tag' => [
                '[botdot_appendix]x[/botdot_appendix]',
                '[bspt_appendix]x[/bspt_appendix]',
            ],
            'multiple occurrences' => [
                '[botdot_appendix] and [botdot_appendix]',
                '[bspt_appendix] and [bspt_appendix]',
            ],
            'untouched when absent' => [
                'no shortcode here',
                'no shortcode here',
            ],
            'does not rewrite a longer tag name' => [
                '[botdot_appendix_other]',
                '[botdot_appendix_other]',
            ],
            'leaves the current tag alone' => [
                '[bspt_appendix]',
                '[bspt_appendix]',
            ],
        ];
    }

    public function test_rewrite_legacy_shortcode_handles_non_string(): void
    {
        $this->assertNull($this->public->rewrite_legacy_shortcode(null));
    }

    // -------------------------------------------------------------------------
    // Manual-placement detection must cover every registered name, or the
    // auto-injector adds a second appendix to a page that already has one.
    // -------------------------------------------------------------------------

    /** @dataProvider manualPlacementProvider */
    public function test_content_has_appendix_marker(string $content, bool $expected): void
    {
        $this->assertSame(
            $expected,
            $this->invoke($this->injector, 'content_has_appendix_marker', [$content])
        );
    }

    public static function manualPlacementProvider(): array
    {
        return [
            'primary shortcode' => ['[bspt_appendix]', true],
            'botspot alias' => ['[botspot_appendix]', true],
            'botdot alias' => ['[botdot_appendix]', true],
            'primary block' => ['<!-- wp:bspt/appendix /-->', true],
            'legacy block' => ['<!-- wp:botspot-wp/appendix /-->', true],
            'no marker' => ['<p>ordinary content</p>', false],
            'empty content' => ['', false],
        ];
    }
}
