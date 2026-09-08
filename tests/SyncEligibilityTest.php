<?php
/**
 * Tests that a password-protected post never reaches the ingest payload.
 *
 * @since 3.7.9
 */

use PHPUnit\Framework\TestCase;

// bulk_sync() and register_page_stubs() both enumerate posts through
// get_posts(). Returning an empty set ends the batch loop on its first
// iteration and short-circuits register_page_stubs() before it makes an HTTP
// call, so the recorded arguments are all these tests need.
if (!function_exists('get_posts')) {
    function get_posts($args = [])
    {
        $GLOBALS['bspt_test_post_queries'][] = $args;
        return [];
    }
}
if (!function_exists('get_site_url')) {
    function get_site_url($blog_id = null, $path = '')
    {
        return 'https://example.com';
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return $component === -1 ? parse_url($url) : parse_url($url, $component);
    }
}

class SyncEligibilityTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-page-builder.php';
        require_once __DIR__ . '/../includes/class-bspt-language.php';
        require_once __DIR__ . '/../includes/class-bspt-sync.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_options'] = [
            'bspt_api_key' => 'sk_test',
            'bspt_sync_post_types' => ['post', 'page'],
        ];
        $GLOBALS['bspt_test_post_queries'] = [];
        $GLOBALS['bspt_test_author'] = 'Ada Lovelace';
        $GLOBALS['bspt_test_remote_response'] = null;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['bspt_test_author']);
    }

    public function test_a_password_protected_post_is_not_eligible()
    {
        $post = new WP_Post(['ID' => 1, 'post_password' => 'hunter2']);

        $this->assertFalse(Bspt_Sync::can_sync_post($post));
    }

    public function test_a_published_post_without_a_password_is_eligible()
    {
        $post = new WP_Post(['ID' => 2]);

        $this->assertTrue(Bspt_Sync::can_sync_post($post));
    }

    public function test_a_draft_is_not_eligible()
    {
        $post = new WP_Post(['ID' => 3, 'post_status' => 'draft']);

        $this->assertFalse(Bspt_Sync::can_sync_post($post));
    }

    public function test_an_unselected_post_type_is_not_eligible()
    {
        $post = new WP_Post(['ID' => 4, 'post_type' => 'attachment']);

        $this->assertFalse(Bspt_Sync::can_sync_post($post));
    }

    /**
     * can_sync_post() guards the per-post paths. Bulk enumeration never calls
     * it, so the exclusion has to live in the query itself or a protected post
     * still ships in a batch.
     */
    public function test_every_bulk_query_excludes_password_protected_posts()
    {
        Bspt_Sync::bulk_sync();

        $this->assertNotEmpty($GLOBALS['bspt_test_post_queries']);
        foreach ($GLOBALS['bspt_test_post_queries'] as $args) {
            $this->assertArrayHasKey('has_password', $args);
            $this->assertFalse($args['has_password']);
        }
    }

    public function test_bulk_sync_queries_both_the_stub_and_batch_paths()
    {
        Bspt_Sync::bulk_sync();

        $this->assertCount(2, $GLOBALS['bspt_test_post_queries']);
    }

    public function test_the_author_reaches_the_payload_by_default()
    {
        $payload = $this->ingest_payload_for(new WP_Post(['ID' => 10]));

        $this->assertSame('Ada Lovelace', $payload['metadata']['author']);
    }

    public function test_disabling_send_author_strips_the_author()
    {
        $GLOBALS['bspt_test_options']['bspt_send_author'] = false;

        $payload = $this->ingest_payload_for(new WP_Post(['ID' => 11]));

        $this->assertNull($payload['metadata']['author']);
    }

    private function ingest_payload_for(WP_Post $post)
    {
        $GLOBALS['bspt_test_posts'][$post->ID] = $post;

        $method = new ReflectionMethod('Bspt_Sync', 'build_ingest_payload');
        $method->setAccessible(true);

        return $method->invoke(null, $post);
    }
}
