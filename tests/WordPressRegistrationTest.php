<?php
/**
 * BOT-350: connecting registers the site, whatever its post count.
 *
 * Registration used to depend on content ingest to set sites.source_type, so a
 * site with zero published posts never appeared as connected in the Dashboard.
 *
 * @since 3.7.0
 */

use PHPUnit\Framework\TestCase;

// Build-time constant, injected by build.sh per environment.
if (!defined('BSPT_LOCUS_API_URL')) {
    define('BSPT_LOCUS_API_URL', 'https://api.example.test');
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $message;
        public function __construct($code = '', $message = '')
        {
            $this->message = $message;
        }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return is_array($response) && isset($response['response']['code'])
            ? $response['response']['code']
            : 0;
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return is_array($response) && isset($response['body']) ? $response['body'] : '';
    }
}
// Other test files stub home_url() from the same global, so honour it here too.
if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        $base = isset($GLOBALS['bspt_test_home_url'])
            ? $GLOBALS['bspt_test_home_url']
            : 'https://example.com';
        return $base . $path;
    }
}
if (!function_exists('rest_url')) {
    function rest_url($path = '')
    {
        return 'https://example.com/wp-json/' . ltrim($path, '/');
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '')
    {
        return 'Example Site';
    }
}

// Records every request and replies from a per-URL queue the test stages.
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = [])
    {
        $GLOBALS['bspt_test_requests'][] = ['url' => $url, 'args' => $args];
        foreach ($GLOBALS['bspt_test_responses'] as $fragment => $response) {
            if (strpos($url, $fragment) !== false) {
                return $response;
            }
        }
        return new WP_Error('http_request_failed', 'no canned response for ' . $url);
    }
}

if (!class_exists('Bspt_Logger')) {
    class Bspt_Logger
    {
        public static function log_error($message)
        {
            $GLOBALS['bspt_test_errors'][] = $message;
        }
        public static function log_debug($message)
        {
        }
    }
}

class WordPressRegistrationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-webhook-handler.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_options'] = ['bspt_api_key' => 'sk_test_key'];
        $GLOBALS['bspt_test_requests'] = [];
        $GLOBALS['bspt_test_responses'] = [];
        $GLOBALS['bspt_test_errors'] = [];
        $GLOBALS['bspt_test_home_url'] = 'https://example.com';
    }

    private function stage($fragment, $code, array $body = [])
    {
        $GLOBALS['bspt_test_responses'][$fragment] = [
            'response' => ['code' => $code],
            'body' => json_encode($body),
        ];
    }

    private function lastRequestBody()
    {
        $requests = $GLOBALS['bspt_test_requests'];
        $last = end($requests);
        return json_decode($last['args']['body'], true);
    }

    private function requestedUrls()
    {
        return array_map(function ($r) {
            return $r['url'];
        }, $GLOBALS['bspt_test_requests']);
    }

    public function testRegistersTheSiteAndStoresTheWebhookCredentials()
    {
        $this->stage('/integrations/wordpress/register', 200, [
            'tenant_id' => 'org_01WP',
            'site_id' => 'site-uuid',
            'webhook_id' => 'hook-uuid',
            'webhook_secret' => 'hook-secret',
            'source_type' => 'wordpress',
            'site_created' => true,
            'source_type_promoted' => false,
        ]);

        $this->assertTrue(Bspt_Webhook_Handler::register_webhook());

        $this->assertSame('hook-uuid', get_option('bspt_webhook_id'));
        $this->assertSame('hook-secret', get_option('bspt_webhook_secret'));
        $this->assertSame('site-uuid', get_option('bspt_site_id'));
    }

    public function testSendsTheSiteUrlSoCoreCanClaimTheDomain()
    {
        $this->stage('/integrations/wordpress/register', 200, [
            'webhook_id' => 'hook-uuid',
            'webhook_secret' => 'hook-secret',
        ]);

        Bspt_Webhook_Handler::register_webhook();

        $body = $this->lastRequestBody();
        $this->assertSame('https://example.com', $body['site_url']);
        $this->assertSame(
            'https://example.com/wp-json/botspot/v1/webhook',
            $body['webhook_url']
        );
        $this->assertSame('Example Site', $body['site_name']);
    }

    /**
     * The endpoint reads no posts, so a site with none registers the same way.
     * This is the case BOT-350 reports as invisible in the Dashboard.
     */
    public function testRegistersWithZeroPublishedPosts()
    {
        $GLOBALS['bspt_test_published_posts'] = 0;
        $this->stage('/integrations/wordpress/register', 200, [
            'webhook_id' => 'hook-uuid',
            'webhook_secret' => 'hook-secret',
            'site_id' => 'site-uuid',
            'source_type' => 'wordpress',
        ]);

        $this->assertTrue(Bspt_Webhook_Handler::register_webhook());
        $this->assertSame('site-uuid', get_option('bspt_site_id'));
    }

    public function testReconnectIsIdempotent()
    {
        $this->stage('/integrations/wordpress/register', 200, [
            'webhook_id' => 'hook-uuid',
            'webhook_secret' => 'hook-secret',
            'site_id' => 'site-uuid',
            'site_created' => false,
            'source_type_promoted' => false,
        ]);

        $this->assertTrue(Bspt_Webhook_Handler::register_webhook());
        $this->assertTrue(Bspt_Webhook_Handler::register_webhook());

        $this->assertSame('hook-uuid', get_option('bspt_webhook_id'));
        $this->assertSame('site-uuid', get_option('bspt_site_id'));
    }

    public function testFallsBackToTheWebhookEndpointOnAnOlderCore()
    {
        $this->stage('/integrations/wordpress/register', 404);
        $this->stage('/api/v1/webhooks', 201, [
            'id' => 'legacy-hook-uuid',
            'secret' => 'legacy-secret',
        ]);

        $this->assertTrue(Bspt_Webhook_Handler::register_webhook());

        $urls = $this->requestedUrls();
        $this->assertStringContainsString('/integrations/wordpress/register', $urls[0]);
        $this->assertStringContainsString('/api/v1/webhooks', $urls[1]);
        $this->assertSame('legacy-hook-uuid', get_option('bspt_webhook_id'));
        $this->assertFalse(get_option('bspt_site_id'));
    }

    public function testReportsAConflictWithAnotherIntegration()
    {
        $this->stage('/integrations/wordpress/register', 409, [
            'detail' => 'Site example.com is registered to drupal',
        ]);

        $this->assertFalse(Bspt_Webhook_Handler::register_webhook());

        // A 409 never clears by retrying, so the admin needs to see why.
        $this->assertNotEmpty($GLOBALS['bspt_test_errors']);
        $this->assertStringContainsString(
            'another integration',
            $GLOBALS['bspt_test_errors'][0]
        );
        // No fallback: the legacy endpoint would register a webhook for a domain
        // another connector owns.
        $this->assertCount(1, $GLOBALS['bspt_test_requests']);
    }

    public function testWithoutAnApiKeyItMakesNoRequest()
    {
        $GLOBALS['bspt_test_options'] = [];

        $this->assertFalse(Bspt_Webhook_Handler::register_webhook());
        $this->assertCount(0, $GLOBALS['bspt_test_requests']);
    }
}
