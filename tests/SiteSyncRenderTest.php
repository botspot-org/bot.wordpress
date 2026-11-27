<?php
/**
 * The render route: one entry per URL, hidden posts as error entries.
 *
 * @since 3.7.12
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/site-sync-stubs.php';

class SiteSyncRenderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-language.php';
        require_once __DIR__ . '/../includes/class-bspt-page-builder.php';
        require_once __DIR__ . '/../includes/class-bspt-sync.php';
        require_once __DIR__ . '/../includes/class-bspt-webhook-handler.php';
        require_once __DIR__ . '/../includes/class-bspt-site-sync.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_options'] = ['bspt_webhook_secret' => 'whsec_test'];
        $GLOBALS['bspt_test_home_url'] = 'https://example.com';
        $GLOBALS['bspt_test_posts'] = [];
        $GLOBALS['bspt_test_permalinks'] = [];
        $GLOBALS['bspt_test_post_meta'] = ['501:_wp_attachment_image_alt' => 'A waving hand'];
        $GLOBALS['bspt_test_url_ids'] = [
            'https://example.com/hello-world/' => 101,
            'https://example.com/about/' => 102,
            'https://example.com/draft-post/' => 104,
            'https://example.com/members-only/' => 105,
        ];
        $GLOBALS['bspt_test_pll_homes'] = ['en' => 'https://example.com/', 'fi' => 'https://example.fi/'];
        $GLOBALS['bspt_test_pll_post_languages'] = [101 => 'en', 102 => 'en'];
        $GLOBALS['bspt_test_thumbnails'] = [101 => 501];
        $GLOBALS['bspt_test_images'] = [501 => ['https://example.com/wp-content/uploads/2026/11/hello.jpg', 1200, 630, false]];
        $GLOBALS['bspt_test_mime_types'] = [501 => 'image/jpeg'];
        $GLOBALS['bspt_test_terms'] = ['101:post_tag' => ['news', 'welcome'], '101:category' => ['Announcements']];
        $GLOBALS['bspt_test_users'] = [7 => 'Site Editor'];
        $GLOBALS['bspt_test_yoast_schema'] = [101 => bspt_test_fixture('render_response')['items'][0]['source_jsonld']];

        $this->post([
            'ID' => 101,
            'post_title' => 'Hello world',
            'post_excerpt' => 'The first post on the site.',
            'post_author' => 7,
            'post_date_gmt' => '2025-11-24 13:13:20',
            'post_modified_gmt' => '2025-11-24 16:00:00',
            'post_content' => '<article><h1>Hello world</h1><p>Welcome to the site. This first post tells visitors what the site is about and how to reach the team.</p></article>',
        ]);
        $this->post([
            'ID' => 102,
            'post_type' => 'page',
            'post_title' => 'About us',
            'post_date_gmt' => '2025-11-13 02:13:20',
            'post_modified_gmt' => '2025-11-24 16:05:00',
            'post_content' => '<section><h1>About us</h1><p>We are a small team that writes about the products we make and the people who use them.</p></section>',
        ]);
        $this->post(['ID' => 104, 'post_status' => 'draft']);
        $this->post(['ID' => 105, 'post_password' => 'members']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['bspt_test_filters'],
            $GLOBALS['bspt_test_pll_homes'],
            $GLOBALS['bspt_test_pll_post_languages'],
            $GLOBALS['bspt_test_yoast_schema'],
            $GLOBALS['bspt_test_terms'],
            $GLOBALS['bspt_test_thumbnails']
        );
    }

    private function post(array $properties)
    {
        $GLOBALS['bspt_test_posts'][$properties['ID']] = new WP_Post($properties);
    }

    private function render_one($url, $send_author = false)
    {
        $body = ['op' => 'render', 'bundles' => ['post', 'page'], 'send_author' => $send_author, 'urls' => [$url]];
        return Bspt_Site_Sync::handle_render(bspt_test_signed_request(json_encode($body)))->get_data()['items'][0];
    }

    public function test_the_route_is_registered()
    {
        $GLOBALS['bspt_test_routes'] = [];

        (new ReflectionClass('Bspt_Webhook_Handler'))->newInstanceWithoutConstructor()->register_routes();

        $this->assertContains(
            ['bspt/v1', '/sync/render', [
                'methods' => 'POST',
                'callback' => ['Bspt_Site_Sync', 'handle_render'],
                'permission_callback' => '__return_true',
            ]],
            $GLOBALS['bspt_test_routes']
        );
    }

    /**
     * assertSame compares key order too, so the code builds each entry in the
     * fixture's key order.
     */
    public function test_the_fixture_request_renders_the_fixture_response()
    {
        $response = Bspt_Site_Sync::handle_render(bspt_test_signed_request(bspt_test_fixture('render_request', true)));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(bspt_test_fixture('render_response'), $response->get_data());
    }

    public function test_a_url_on_another_host_is_refused()
    {
        $this->assertSame(
            ['url' => 'https://evil.example.net/hello-world/', 'error' => 'host_mismatch', 'status' => 400],
            $this->render_one('https://evil.example.net/hello-world/')
        );
    }

    public function test_a_language_on_its_own_domain_passes_the_host_check()
    {
        $GLOBALS['bspt_test_url_ids']['https://example.fi/hei/'] = 101;

        $this->assertSame(101, $this->render_one('https://example.fi/hei/')['nid']);
    }

    public function test_a_draft_behind_a_plain_permalink_is_not_found()
    {
        $GLOBALS['bspt_test_url_ids']['https://example.com/?p=104'] = 104;

        $this->assertSame(
            ['url' => 'https://example.com/?p=104', 'error' => 'not_found', 'status' => 404],
            $this->render_one('https://example.com/?p=104')
        );
    }

    public function test_a_private_post_and_an_unknown_url_are_not_found()
    {
        $this->post(['ID' => 106, 'post_status' => 'private']);
        $GLOBALS['bspt_test_url_ids']['https://example.com/staff/'] = 106;

        $this->assertSame('not_found', $this->render_one('https://example.com/staff/')['error']);
        $this->assertSame('not_found', $this->render_one('https://example.com/gone/')['error']);
    }

    public function test_an_unrequested_type_or_a_post_the_filter_excludes_is_not_synced()
    {
        $this->post(['ID' => 107, 'post_type' => 'product']);
        $GLOBALS['bspt_test_url_ids']['https://example.com/shop/mug/'] = 107;

        $this->assertSame(
            ['url' => 'https://example.com/shop/mug/', 'error' => 'not_synced', 'status' => 403],
            $this->render_one('https://example.com/shop/mug/')
        );

        $GLOBALS['bspt_test_filters']['bspt_should_sync'] = function () {
            return false;
        };
        $this->assertSame('not_synced', $this->render_one('https://example.com/hello-world/')['error']);
    }

    public function test_the_author_stays_on_the_site_when_send_author_is_off()
    {
        $GLOBALS['bspt_test_options']['bspt_send_author'] = false;

        // The request asks for the author; the plugin's own option decides.
        $this->assertArrayNotHasKey('author', $this->render_one('https://example.com/hello-world/', true));
    }

    public function test_render_changed_equals_inventory_changed_in_any_timezone()
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Pacific/Auckland');
        try {
            $item = Bspt_Site_Sync::build_inventory_page([101], 1)['items'][0];
            $entry = $this->render_one('https://example.com/hello-world/');
        } finally {
            date_default_timezone_set($previous);
        }

        $this->assertSame(1764000000, $entry['changed']);
        $this->assertSame($item['changed'], $entry['changed']);
        $this->assertSame([$item['nid'], $item['langcode']], [$entry['nid'], $entry['langcode']]);
    }

    /**
     * @dataProvider badUrlLists
     */
    public function test_an_empty_or_oversized_url_list_is_refused(array $urls)
    {
        $body = array_merge(bspt_test_fixture('render_request'), ['urls' => $urls]);

        $this->assertSame(400, Bspt_Site_Sync::handle_render(bspt_test_signed_request(json_encode($body)))->get_status());
    }

    public static function badUrlLists(): array
    {
        return [
            'none' => [[]],
            'twenty-one' => [array_fill(0, 21, 'https://example.com/about/')],
        ];
    }
}
