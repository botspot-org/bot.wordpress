<?php
/**
 * Tests that the content-extraction path is reported, not silently swallowed.
 *
 * @since 3.5.15
 */

use PHPUnit\Framework\TestCase;

// HTTP-layer stubs, local to this test file (bootstrap.php is owned by another
// task). fetch_rendered_content() is untestable past its first branch without
// these — wp_remote_get() et al are not stubbed anywhere else in the suite.
// $GLOBALS['bspt_test_remote_response'] is the canned "response" each test
// sets before calling into Bspt_Sync, mirroring the get_post_meta/get_post
// pattern already used by bootstrap.php.
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $message;

        public function __construct($code = '', $message = '')
        {
            $this->message = $message;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url)
    {
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . $key . '=' . rawurlencode((string) $value);
    }
}
if (!function_exists('wp_remote_get')) {
    // Returns whatever the current test staged in $GLOBALS['bspt_test_remote_response'] —
    // either a WP_Error or an array shaped like ['response' => ['code' => int], 'body' => string].
    function wp_remote_get($url, $args = [])
    {
        return isset($GLOBALS['bspt_test_remote_response'])
            ? $GLOBALS['bspt_test_remote_response']
            : new WP_Error('http_request_failed', 'no canned response staged for this test');
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return is_array($response) && isset($response['response']['code']) ? $response['response']['code'] : 0;
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return is_array($response) && isset($response['body']) ? $response['body'] : '';
    }
}

// Stubs needed only by build_ingest_payload()'s non-content fields (Part 2:
// payload-shape test). Kept minimal — each returns a fixed, uninteresting
// value since the test only asserts on the page_builder/content_source
// placement, not on titles, categories, or dates.
if (!function_exists('get_permalink')) {
    function get_permalink($post_id = 0)
    {
        return 'https://example.com/payload-shape-test/';
    }
}
if (!function_exists('wp_get_post_categories')) {
    function wp_get_post_categories($post_id, $args = [])
    {
        return [];
    }
}
if (!function_exists('wp_get_post_tags')) {
    function wp_get_post_tags($post_id, $args = [])
    {
        return [];
    }
}
if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($post_id, $size = 'full')
    {
        return false;
    }
}
if (!function_exists('get_the_author_meta')) {
    function get_the_author_meta($field, $author_id = 0)
    {
        return '';
    }
}
if (!function_exists('get_post_time')) {
    function get_post_time($format = 'U', $gmt = false, $post = 0)
    {
        return '2026-01-01T00:00:00+00:00';
    }
}
if (!function_exists('get_post_modified_time')) {
    function get_post_modified_time($format = 'U', $gmt = false, $post = 0)
    {
        return '2026-01-01T00:00:00+00:00';
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value)
    {
        $GLOBALS['bspt_test_post_meta']["{$post_id}:{$key}"] = $value;
        return true;
    }
}
if (!function_exists('has_filter')) {
    function has_filter($tag, $callback = false)
    {
        return false;
    }
}
if (!function_exists('get_locale')) {
    function get_locale()
    {
        return 'en_US';
    }
}
if (!function_exists('get_option')) {
    // Bspt_Sync::log_debug() gates every debug-log call behind
    // Bspt_Options::get("debug_mode"), which bottoms out in get_option(). Always
    // returning the caller's default keeps debug_mode false, so log_debug() is a
    // no-op and Bspt_Logger never needs to be loaded.
    function get_option($name, $default = false)
    {
        return $default;
    }
}

class ContentSourceTelemetryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-sync.php';
        require_once __DIR__ . '/../includes/class-bspt-page-builder.php';
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-language.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_post_meta'] = [];
        $GLOBALS['bspt_test_posts'] = [];
        unset($GLOBALS['bspt_test_remote_response']);
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
     * @param int    $id
     * @param string $content
     * @return object
     */
    private function published_post($id, $content)
    {
        $post = (object) [
            'ID' => $id,
            'post_status' => 'publish',
            'post_content' => $content,
        ];
        $GLOBALS['bspt_test_posts'][$id] = $post;
        return $post;
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
     * The return contract is a two-element list, always — driven through every
     * remaining exit of a PUBLISHED post's fetch, each with the exact expected
     * $source string. Reverting the plumbing to a bare string return (or
     * mislabeling any one exit) fails this test: it is not just "returns an
     * array", it pins which array for which HTTP outcome.
     */
    public function test_fetch_rendered_content_always_returns_a_pair()
    {
        $cases = [
            'fetch_error' => new WP_Error('http_request_failed', 'Connection timed out'),
            'fetch_http_error' => ['response' => ['code' => 500], 'body' => 'Internal Server Error'],
            'fetch_empty' => ['response' => ['code' => 200], 'body' => ''],
            'extract_too_short' => ['response' => ['code' => 200], 'body' => '<html><body><p>Hi</p></body></html>'],
            'rendered_fetch' => [
                'response' => ['code' => 200],
                'body' => '<html><body><main><p>'
                    . str_repeat('Real rendered content long enough to clear the fifty character floor. ', 2)
                    . '</p></main></body></html>',
            ],
        ];

        $post_id = 310;
        foreach ($cases as $expected_source => $canned_response) {
            $post = $this->published_post($post_id++, '<p>fallback post_content for ' . $expected_source . '</p>');
            $GLOBALS['bspt_test_remote_response'] = $canned_response;

            $result = $this->invoke('fetch_rendered_content', [$post, 'https://example.com/x/']);

            $this->assertIsArray($result, "case: {$expected_source}");
            $this->assertCount(2, $result, "case: {$expected_source}");
            $this->assertIsString($result[0], "case: {$expected_source}");
            $this->assertSame($expected_source, $result[1], "case: {$expected_source}");
        }
    }

    /**
     * Payload shape: page_builder was silently dropped by the server when sent
     * under body (ContentBody has no extra="allow"). It must now live under
     * structured_data.data, alongside content_source, and NOT under body.
     */
    public function test_payload_moves_page_builder_into_structured_data_alongside_content_source()
    {
        $post = (object) [
            'ID' => 401,
            'post_status' => 'draft',
            'post_content' => '<p>' . str_repeat('Payload shape test body copy. ', 5) . '</p>',
            'post_title' => 'Payload Shape Test',
            'post_author' => 1,
            'post_date_gmt' => '0000-00-00 00:00:00',
            'post_modified_gmt' => '0000-00-00 00:00:00',
            'post_excerpt' => '',
            'post_type' => 'post',
        ];
        $GLOBALS['bspt_test_posts'][401] = $post;

        $payload = $this->invoke('build_ingest_payload', [$post]);

        $this->assertArrayNotHasKey('page_builder', $payload['body']);
        $this->assertArrayHasKey('page_builder', $payload['structured_data']['data']);
        $this->assertArrayHasKey('content_source', $payload['structured_data']['data']);
        $this->assertSame('not_published', $payload['structured_data']['data']['content_source']);
    }
}
