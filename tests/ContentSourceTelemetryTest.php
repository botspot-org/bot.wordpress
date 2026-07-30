<?php
/**
 * Tests that the content-extraction path is reported, not silently swallowed.
 *
 * @since 3.5.15
 */

use PHPUnit\Framework\TestCase;

class ContentSourceTelemetryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-sync.php';
        require_once __DIR__ . '/../includes/class-bspt-page-builder.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_post_meta'] = [];
        $GLOBALS['bspt_test_posts'] = [];
    }

    /**
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    private function invoke($method, array $args)
    {
        $ref = new ReflectionMethod('Bspt_Sync', $method);
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        return $ref->invokeArgs(null, $args);
    }

    /**
     * A draft cannot be fetched over HTTP (no public URL), so extraction falls
     * back to the builder path — and must say so.
     */
    public function test_unpublished_post_reports_not_published()
    {
        $post = (object) [
            'ID' => 301,
            'post_status' => 'draft',
            'post_content' => '<p>' . str_repeat('Draft body copy. ', 10) . '</p>',
        ];
        $GLOBALS['bspt_test_posts'][301] = $post;

        list($content, $source) = $this->invoke('fetch_rendered_content', [$post, 'https://example.com/draft/']);

        $this->assertSame('not_published', $source);
        $this->assertStringContainsString('Draft body copy', $content);
    }

    /**
     * The return contract is a two-element list, always.
     */
    public function test_fetch_rendered_content_always_returns_a_pair()
    {
        $post = (object) [
            'ID' => 302,
            'post_status' => 'draft',
            'post_content' => '<p>Body.</p>',
        ];
        $GLOBALS['bspt_test_posts'][302] = $post;

        $result = $this->invoke('fetch_rendered_content', [$post, 'https://example.com/x/']);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertIsString($result[0]);
        $this->assertIsString($result[1]);
    }
}
