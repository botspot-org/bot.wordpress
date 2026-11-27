<?php
/**
 * A push learns from core's answer whether core pulls the site itself.
 *
 * @since 3.7.12
 */

use PHPUnit\Framework\TestCase;

// Build-time constant, injected by build.sh per environment. Same value as WordPressRegistrationTest.php.
if (!defined('BSPT_LOCUS_API_URL')) {
    define('BSPT_LOCUS_API_URL', 'https://api.example.test');
}
if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0)
    {
        return '2026-11-27 12:00:00';
    }
}

class SyncEngineTest extends TestCase
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
        $GLOBALS['bspt_test_options'] = ['bspt_api_key' => 'sk_test', 'bspt_sync_post_types' => ['post', 'page']];
        $GLOBALS['bspt_test_requests'] = [];
        $GLOBALS['bspt_test_responses'] = [];
        $GLOBALS['bspt_test_posts'] = [];
        $GLOBALS['bspt_test_post_meta'] = [];
        $GLOBALS['bspt_test_post_queries'] = [];
        $GLOBALS['bspt_test_remote_response'] = null;
        $GLOBALS['bspt_test_errors'] = [];
        $GLOBALS['bspt_test_home_url'] = 'https://example.com';
        $GLOBALS['bspt_test_permalinks'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['bspt_test_get_posts'], $GLOBALS['bspt_test_pll_homes']);
    }

    /**
     * A post on the home host, or, with $url, on the host of that URL.
     */
    private function post($id, $url = null)
    {
        $post = new WP_Post([
            'ID' => $id,
            'post_title' => 'Post ' . $id,
            'post_content' => '<p>' . str_repeat('Body text that is long enough for the push. ', 6) . '</p>',
        ]);
        $GLOBALS['bspt_test_posts'][$id] = $post;
        if ($url !== null) {
            $GLOBALS['bspt_test_permalinks'][$id] = $url;
        }
        return $post;
    }

    private function with_swedish_domain()
    {
        $GLOBALS['bspt_test_pll_homes'] = ['en' => 'https://example.com/', 'sv' => 'https://example.se/'];
    }

    private function post_ids(array $request)
    {
        return array_map(function ($item) {
            return $item['structured_data']['data']['post_id'];
        }, json_decode($request['args']['body'], true)['items']);
    }

    private function stage($fragment, $code, array $body)
    {
        $GLOBALS['bspt_test_responses'][$fragment] = ['response' => ['code' => $code], 'body' => json_encode($body)];
    }

    private function sent($index)
    {
        return json_decode($GLOBALS['bspt_test_requests'][$index]['args']['body'], true);
    }

    private function batch_requests()
    {
        return array_values(array_filter($GLOBALS['bspt_test_requests'], function ($request) {
            return strpos($request['url'], '/connector/ingest/batch') !== false;
        }));
    }

    public function test_a_core_answer_turns_the_next_push_into_a_hint()
    {
        $this->stage('/connector/ingest', 202, ['status' => 'queued', 'sync_engine' => 'core']);
        $post = $this->post(11);

        $this->assertTrue(Bspt_Sync::send_webhook($post, 'content.updated'));
        $this->assertTrue(Bspt_Sync::send_webhook($post, 'content.updated'));

        $this->assertSame('core', get_option('bspt_sync_engine'));
        // wp_remote_get answers with a WP_Error in these tests, so the full path reports fetch_error.
        $this->assertSame('fetch_error', $this->sent(0)['structured_data']['data']['content_source']);
        $this->assertSame('core_engine', $this->sent(1)['structured_data']['data']['content_source']);
    }

    public function test_an_answer_without_sync_engine_keeps_the_push_path()
    {
        $this->stage('/connector/ingest', 201, ['artifact_id' => 'artifact-14']);
        $post = $this->post(14);

        Bspt_Sync::send_webhook($post, 'content.updated');
        Bspt_Sync::send_webhook($post, 'content.updated');

        $this->assertSame('push', get_option('bspt_sync_engine'));
        $this->assertSame('fetch_error', $this->sent(1)['structured_data']['data']['content_source']);
    }

    public function test_a_hint_that_core_ingests_is_sent_again_in_full()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        $this->stage('/connector/ingest', 201, ['artifact_id' => 'artifact-12']);

        $this->assertTrue(Bspt_Sync::send_webhook($this->post(12), 'content.updated'));

        $this->assertCount(2, $GLOBALS['bspt_test_requests']);
        $this->assertSame('core_engine', $this->sent(0)['structured_data']['data']['content_source']);
        $this->assertSame('fetch_error', $this->sent(1)['structured_data']['data']['content_source']);
        $this->assertSame('push', get_option('bspt_sync_engine'));
        $this->assertSame('artifact-12', get_post_meta(12, '_bspt_artifact_id', true));
    }

    public function test_an_error_answer_keeps_the_engine()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        $this->stage('/connector/ingest', 503, ['detail' => 'unavailable']);

        $this->assertFalse(Bspt_Sync::send_webhook($this->post(13), 'content.updated'));

        $this->assertCount(1, $GLOBALS['bspt_test_requests']);
        $this->assertSame('core', get_option('bspt_sync_engine'));
    }

    public function test_an_off_host_save_sends_a_full_push_and_keeps_the_engine()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        $this->stage('/connector/ingest', 201, ['artifact_id' => 'artifact-15']);

        $this->assertTrue(Bspt_Sync::send_webhook($this->post(15, 'https://example.se/hej/'), 'content.updated'));

        $this->assertCount(1, $GLOBALS['bspt_test_requests']);
        $this->assertSame('fetch_error', $this->sent(0)['structured_data']['data']['content_source']);
        $this->assertSame('core', get_option('bspt_sync_engine'));
    }

    public function test_a_bulk_sync_sends_no_home_host_batch_after_core_answers()
    {
        $posts = [];
        for ($id = 1001; $id <= 1200; $id++) {
            $posts[] = $this->post($id);
        }
        // Two batches of 100 are queued; without the stop the second batch call follows.
        $GLOBALS['bspt_test_get_posts'] = $posts;
        $this->stage('/connector/ingest/batch', 202, ['accepted' => 100, 'rejected' => 0, 'sync_engine' => 'core']);

        $result = Bspt_Sync::bulk_sync();

        $this->assertCount(1, $this->batch_requests());
        $this->assertSame('core', get_option('bspt_sync_engine'));
        $this->assertSame(100, $result['processed']);
        $this->assertSame('', get_post_meta(1001, '_bspt_sync_status', true));
    }

    public function test_a_bulk_sync_on_the_core_engine_sends_one_hint_and_no_stubs()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        $GLOBALS['bspt_test_get_posts'] = [$this->post(21), $this->post(22)];
        $this->stage('/connector/ingest/batch', 202, ['accepted' => 1, 'rejected' => 0, 'sync_engine' => 'core']);

        $result = Bspt_Sync::bulk_sync();

        $this->assertSame('queued', $result['status']);
        $this->assertCount(1, $GLOBALS['bspt_test_requests']);
        $items = $this->sent(0)['items'];
        $this->assertCount(1, $items);
        $this->assertSame('core_engine', $items[0]['structured_data']['data']['content_source']);
        $this->assertFalse($GLOBALS['bspt_test_post_queries'][0]['has_password']);
    }

    public function test_the_bulk_hint_is_a_home_host_post_and_off_host_posts_still_push()
    {
        $this->with_swedish_domain();
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        $GLOBALS['bspt_test_get_posts'] = [$this->post(61, 'https://example.se/hej/'), $this->post(62)];
        $this->stage('/connector/ingest/batch', 202, ['accepted' => 1, 'rejected' => 0, 'sync_engine' => 'core']);

        Bspt_Sync::bulk_sync();

        // The hint and one off-host batch: no page stubs while core is stored.
        $this->assertCount(2, $GLOBALS['bspt_test_requests']);
        $batches = $this->batch_requests();
        $this->assertCount(2, $batches);
        $this->assertSame([62], $this->post_ids($batches[0]));
        $this->assertSame([61], $this->post_ids($batches[1]));
        $this->assertSame('core', get_option('bspt_sync_engine'));
    }

    public function test_an_off_host_batch_never_changes_the_engine()
    {
        $this->with_swedish_domain();
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        // No home-host post on the first page, so no hint goes out.
        $GLOBALS['bspt_test_get_posts'] = [$this->post(71, 'https://example.se/a/'), $this->post(72, 'https://example.se/b/')];
        $this->stage('/connector/ingest/batch', 200, ['accepted' => 2, 'rejected' => 0]);

        Bspt_Sync::bulk_sync();

        $batches = $this->batch_requests();
        $this->assertCount(1, $batches);
        $this->assertSame([71, 72], $this->post_ids($batches[0]));
        $this->assertSame('core', get_option('bspt_sync_engine'));
        $this->assertSame('synced', get_post_meta(71, '_bspt_sync_status', true));
    }

    public function test_a_mixed_page_goes_out_as_two_batches()
    {
        $GLOBALS['bspt_test_get_posts'] = [$this->post(51), $this->post(52, 'https://example.se/hej/'), $this->post(53)];
        $this->stage('/connector/ingest/batch', 200, ['accepted' => 1, 'rejected' => 0]);

        Bspt_Sync::bulk_sync();

        $batches = $this->batch_requests();
        $this->assertCount(2, $batches);
        $this->assertSame([51, 53], $this->post_ids($batches[0]));
        $this->assertSame([52], $this->post_ids($batches[1]));
        $this->assertSame('push', get_option('bspt_sync_engine'));
    }

    public function test_after_core_answers_only_off_host_batches_follow()
    {
        $this->with_swedish_domain();
        $posts = [];
        for ($id = 2001; $id <= 2200; $id++) {
            $posts[] = in_array($id, [2050, 2150], true)
                ? $this->post($id, 'https://example.se/post-' . $id . '/')
                : $this->post($id);
        }
        $GLOBALS['bspt_test_get_posts'] = $posts;
        $this->stage('/connector/ingest/batch', 202, ['accepted' => 99, 'rejected' => 0, 'sync_engine' => 'core']);

        Bspt_Sync::bulk_sync();

        $batches = $this->batch_requests();
        $this->assertCount(3, $batches);
        $this->assertCount(99, $this->post_ids($batches[0]));
        $this->assertSame([2050], $this->post_ids($batches[1]));
        $this->assertSame([2150], $this->post_ids($batches[2]));
        $this->assertSame('core', get_option('bspt_sync_engine'));
    }

    public function test_a_failed_bulk_hint_on_the_core_engine_sends_nothing_more()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        $GLOBALS['bspt_test_get_posts'] = [$this->post(41), $this->post(42)];
        $this->stage('/connector/ingest/batch', 503, ['detail' => 'unavailable']);

        $this->assertFalse(Bspt_Sync::bulk_sync());

        $this->assertCount(1, $GLOBALS['bspt_test_requests']);
        $this->assertSame('core', get_option('bspt_sync_engine'));
    }

    public function test_a_bulk_hint_that_core_ingests_falls_back_to_the_full_walk()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        $GLOBALS['bspt_test_get_posts'] = [$this->post(31), $this->post(32)];
        $this->stage('/connector/ingest/batch', 200, ['accepted' => 1, 'rejected' => 0]);

        Bspt_Sync::bulk_sync();

        $batches = $this->batch_requests();
        $this->assertCount(2, $batches);
        $this->assertCount(1, json_decode($batches[0]['args']['body'], true)['items']);
        $full = json_decode($batches[1]['args']['body'], true)['items'];
        $this->assertCount(2, $full);
        $this->assertSame('fetch_error', $full[0]['structured_data']['data']['content_source']);
        $this->assertSame('push', get_option('bspt_sync_engine'));
    }
}
