<?php
/**
 * The inventory route: keyset pages of published, unprotected posts.
 *
 * @since 3.7.12
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/site-sync-stubs.php';

class SiteSyncInventoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-language.php';
        require_once __DIR__ . '/../includes/class-bspt-webhook-handler.php';
        require_once __DIR__ . '/../includes/class-bspt-site-sync.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_options'] = ['bspt_webhook_secret' => 'whsec_test'];
        $GLOBALS['bspt_test_home_url'] = 'https://example.com';
        $GLOBALS['bspt_test_posts'] = [];
        $GLOBALS['bspt_test_permalinks'] = [];
        $GLOBALS['bspt_test_pll_homes'] = ['en' => 'https://example.com/', 'fi' => 'https://example.com/fi/'];
        $GLOBALS['bspt_test_pll_post_languages'] = [];
        $GLOBALS['wpdb'] = new Bspt_Test_Wpdb();
        $this->post(101, 'post', 'en', 'https://example.com/hello-world/', '2025-11-24 16:00:00');
        $this->post(102, 'page', 'en', 'https://example.com/about/', '2025-11-24 16:05:00');
        $this->post(103, 'post', 'fi', 'https://example.com/fi/hei-maailma/', '2025-11-24 16:10:00');
        $this->post(104, 'post', 'fi', 'https://example.com/fi/toinen/', '2025-11-24 16:15:00');
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wpdb'],
            $GLOBALS['bspt_test_filters'],
            $GLOBALS['bspt_test_pll_homes'],
            $GLOBALS['bspt_test_pll_post_languages']
        );
    }

    private function post($id, $type, $lang, $url, $modified_gmt)
    {
        $GLOBALS['bspt_test_posts'][$id] = new WP_Post([
            'ID' => $id,
            'post_type' => $type,
            'post_modified_gmt' => $modified_gmt,
        ]);
        $GLOBALS['bspt_test_permalinks'][$id] = $url;
        $GLOBALS['bspt_test_pll_post_languages'][$id] = $lang;
    }

    private function inventory(array $overrides)
    {
        $body = array_merge(bspt_test_fixture('inventory_request'), $overrides);
        return Bspt_Site_Sync::handle_inventory(bspt_test_signed_request(json_encode($body)));
    }

    public function test_the_route_is_registered()
    {
        $GLOBALS['bspt_test_routes'] = [];

        (new ReflectionClass('Bspt_Webhook_Handler'))->newInstanceWithoutConstructor()->register_routes();

        $this->assertContains(
            ['bspt/v1', '/sync/inventory', [
                'methods' => 'POST',
                'callback' => ['Bspt_Site_Sync', 'handle_inventory'],
                'permission_callback' => '__return_true',
            ]],
            $GLOBALS['bspt_test_routes']
        );
    }

    public function test_a_page_matches_the_fixture_response()
    {
        $this->assertSame(
            bspt_test_fixture('inventory_response'),
            Bspt_Site_Sync::build_inventory_page([101, 102, 103, 104], 3)
        );
    }

    public function test_the_fixture_response_comes_from_string_ids_as_a_query_returns_them()
    {
        $GLOBALS['wpdb']->ids = ['101', '102', '103', '104'];

        $response = $this->inventory(['limit' => 3]);

        $this->assertSame(bspt_test_fixture('inventory_response'), $response->get_data());
    }

    public function test_a_failed_query_is_an_error_not_an_empty_end_of_the_site()
    {
        $GLOBALS['wpdb']->last_error = 'Lost connection to MySQL server';

        $response = $this->inventory([]);

        $this->assertSame(500, $response->get_status());
        $this->assertSame(['error' => 'Inventory query failed'], $response->get_data());
    }

    public function test_the_fixture_request_reads_published_unprotected_posts_of_the_requested_types()
    {
        $response = Bspt_Site_Sync::handle_inventory(bspt_test_signed_request(bspt_test_fixture('inventory_request', true)));

        $this->assertSame(200, $response->get_status());
        list($query, $args) = $GLOBALS['wpdb']->prepared[0];
        foreach (["post_status = 'publish'", "post_password = ''", "post_type IN (%s,%s)", "ID > %d", "ORDER BY ID ASC", "LIMIT %d"] as $part) {
            $this->assertStringContainsString($part, $query);
        }
        // 2000 asked for, capped at 500, plus the row that shows a next page exists.
        $this->assertSame(['post', 'page', 0, 501], $args);
    }

    public function test_the_cursor_continues_after_its_post()
    {
        $this->inventory(['after' => [103, 'fi'], 'limit' => 2]);

        $this->assertSame(['post', 'page', 103, 3], $GLOBALS['wpdb']->prepared[0][1]);
    }

    public function test_the_last_page_has_no_next()
    {
        $this->assertNull(Bspt_Site_Sync::build_inventory_page([101, 102], 3)['next']);
    }

    public function test_an_excluded_post_is_left_out()
    {
        $GLOBALS['bspt_test_filters']['bspt_should_sync'] = function ($sync, $post_id) {
            return $post_id !== 102;
        };

        $page = Bspt_Site_Sync::build_inventory_page([101, 102, 103], 3);

        $this->assertSame([101, 103], array_column($page['items'], 'nid'));
    }

    public function test_a_page_whose_posts_are_all_excluded_still_moves_the_cursor()
    {
        $GLOBALS['bspt_test_filters']['bspt_should_sync'] = function () {
            return false;
        };

        $page = Bspt_Site_Sync::build_inventory_page([101, 102, 103, 104], 3);

        $this->assertSame([], $page['items']);
        $this->assertSame([103, 'fi'], $page['next']);
    }

    public function test_a_post_on_a_language_domain_is_left_out_and_the_cursor_moves_on()
    {
        $this->post(105, 'post', 'sv', 'https://example.se/hej-varlden/', '2025-11-24 16:20:00');
        $this->post(106, 'post', 'en', 'https://example.com/later/', '2025-11-24 16:25:00');

        $page = Bspt_Site_Sync::build_inventory_page([101, 105, 106], 2);

        $this->assertSame([101], array_column($page['items'], 'nid'));
        $this->assertSame([105, 'sv'], $page['next']);
    }

    public function test_the_last_page_still_lists_every_language()
    {
        $page = Bspt_Site_Sync::build_inventory_page([101], 3);

        $this->assertSame(['en', 'fi'], array_keys($page['languages']));
    }

    public function test_a_site_without_a_multilingual_plugin_uses_its_locale()
    {
        $GLOBALS['bspt_test_pll_homes'] = [];
        $GLOBALS['bspt_test_pll_post_languages'] = [];

        $page = Bspt_Site_Sync::build_inventory_page([101], 3);

        $this->assertSame('en', $page['items'][0]['langcode']);
        $this->assertSame(['en' => 'https://example.com/wp-json/'], $page['languages']);
    }

    public function test_post_types_that_are_not_public_are_never_queried()
    {
        $this->inventory(['bundles' => ['post', 'acf-field-group']]);

        $this->assertSame(['post', 0, 501], $GLOBALS['wpdb']->prepared[0][1]);
    }

    public function test_no_public_post_type_queries_nothing()
    {
        $response = $this->inventory(['bundles' => ['acf-field-group']]);

        $this->assertSame([], $GLOBALS['wpdb']->prepared);
        $this->assertSame([], $response->get_data()['items']);
        $this->assertNull($response->get_data()['next']);
    }

    /**
     * @dataProvider badCursors
     */
    public function test_a_bad_cursor_or_limit_is_refused(array $overrides)
    {
        $this->assertSame(400, $this->inventory($overrides)->get_status());
    }

    public static function badCursors(): array
    {
        return [
            'cursor without its langcode' => [['after' => [103]]],
            'cursor id as a string' => [['after' => ['103', 'fi']]],
            'limit zero' => [['limit' => 0]],
            'limit as a string' => [['limit' => '5']],
        ];
    }
}
