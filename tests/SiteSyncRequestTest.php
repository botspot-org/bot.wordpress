<?php
/**
 * Core's sync calls are signed, and each route accepts only its own body.
 *
 * @since 3.7.12
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/site-sync-stubs.php';

class SiteSyncRequestTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-webhook-handler.php';
        require_once __DIR__ . '/../includes/class-bspt-site-sync.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_options'] = ['bspt_webhook_secret' => 'whsec_test'];
    }

    public function test_the_fixture_requests_verify()
    {
        foreach (['inventory', 'render'] as $op) {
            $request = bspt_test_signed_request(bspt_test_fixture($op . '_request', true));

            $this->assertSame(bspt_test_fixture($op . '_request'), Bspt_Site_Sync::verified_body($request, $op), $op);
        }
    }

    public function test_a_bad_signature_is_refused_before_the_body_is_read()
    {
        $request = bspt_test_signed_request('not json', 'another-secret');

        $response = Bspt_Site_Sync::verified_body($request, 'inventory');

        $this->assertSame(401, $response->get_status());
        $this->assertSame(['error' => 'Invalid signature'], $response->get_data());
    }

    public function test_a_site_without_a_secret_refuses_every_call()
    {
        $GLOBALS['bspt_test_options'] = [];
        $request = bspt_test_signed_request(bspt_test_fixture('inventory_request', true), '');

        $this->assertSame(401, Bspt_Site_Sync::verified_body($request, 'inventory')->get_status());
    }

    public function test_a_body_signed_for_one_route_is_refused_on_the_other()
    {
        $request = bspt_test_signed_request(bspt_test_fixture('inventory_request', true));

        $response = Bspt_Site_Sync::verified_body($request, 'render');

        $this->assertSame(400, $response->get_status());
        $this->assertSame(['error' => 'Invalid payload'], $response->get_data());
    }

    /**
     * @dataProvider malformedBodies
     */
    public function test_a_malformed_body_is_refused(array $body)
    {
        $response = Bspt_Site_Sync::verified_body(bspt_test_signed_request(json_encode($body)), 'inventory');

        $this->assertSame(400, $response->get_status());
    }

    public static function malformedBodies(): array
    {
        $valid = ['op' => 'inventory', 'bundles' => ['post'], 'send_author' => false, 'after' => null, 'limit' => 10];
        return [
            'extra key' => [$valid + ['debug' => true]],
            'missing key' => [array_diff_key($valid, ['limit' => true])],
            'bundles not a list' => [array_merge($valid, ['bundles' => ['a' => 'post']])],
            'bundle not a string' => [array_merge($valid, ['bundles' => [7]])],
            'send_author not a bool' => [array_merge($valid, ['send_author' => 'yes'])],
            'a list, not an object' => [['inventory']],
        ];
    }
}
