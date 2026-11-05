<?php
/**
 * BOT-472: webhooks must carry a timestamped V2 signature.
 */

use PHPUnit\Framework\TestCase;

if (!class_exists('Bspt_Logger')) {
    // Same shape as the stub in WordPressRegistrationTest; whichever file
    // loads first defines the class for the whole run.
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

class Bspt_Test_Signed_Request
{
    private $headers;
    private $body;

    public function __construct(array $headers, $body)
    {
        $this->headers = $headers;
        $this->body = $body;
    }

    public function get_header($name)
    {
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    public function get_body()
    {
        return $this->body;
    }
}

class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test';
    private const NOW = 1790000000;
    private const BODY = '{"event":"settings.updated"}';

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-webhook-handler.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_errors'] = [];
    }

    private function sign($timestamp, $body = self::BODY)
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, self::SECRET);
    }

    private function request(array $headers, $body = self::BODY)
    {
        return new Bspt_Test_Signed_Request($headers, $body);
    }

    private function verify($request)
    {
        return Bspt_Webhook_Handler::verify_signed_request($request, self::SECRET, self::NOW);
    }

    private function iso($offset)
    {
        return gmdate('Y-m-d\TH:i:s\Z', self::NOW + $offset);
    }

    public function test_valid_v2_signature_passes()
    {
        $ts = $this->iso(0);
        $request = $this->request([
            'X-Webhook-Timestamp' => $ts,
            'X-Webhook-Signature-V2' => $this->sign($ts),
        ]);

        $this->assertTrue($this->verify($request));
        $this->assertSame([], $GLOBALS['bspt_test_errors']);
    }

    public function test_timestamp_at_the_window_edge_passes()
    {
        foreach ([-300, 300] as $offset) {
            $ts = $this->iso($offset);
            $request = $this->request([
                'X-Webhook-Timestamp' => $ts,
                'X-Webhook-Signature-V2' => $this->sign($ts),
            ]);
            $this->assertTrue($this->verify($request), "offset {$offset}");
        }
    }

    public function test_missing_v2_signature_is_rejected_even_with_a_valid_old_signature()
    {
        $ts = $this->iso(0);
        $request = $this->request([
            'X-Webhook-Timestamp' => $ts,
            'X-Webhook-Signature' => 'sha256=' . hash_hmac('sha256', self::BODY, self::SECRET),
        ]);

        $this->assertFalse($this->verify($request));
        $this->assertStringContainsString('missing V2 signature', $GLOBALS['bspt_test_errors'][0]);
    }

    public function test_wrong_v2_signature_is_rejected()
    {
        $ts = $this->iso(0);
        $request = $this->request([
            'X-Webhook-Timestamp' => $ts,
            'X-Webhook-Signature-V2' => 'sha256=' . str_repeat('0', 64),
        ]);

        $this->assertFalse($this->verify($request));
        $this->assertStringContainsString('mismatch', $GLOBALS['bspt_test_errors'][0]);
    }

    public function test_signature_over_a_different_timestamp_is_rejected()
    {
        $request = $this->request([
            'X-Webhook-Timestamp' => $this->iso(1),
            'X-Webhook-Signature-V2' => $this->sign($this->iso(0)),
        ]);

        $this->assertFalse($this->verify($request));
    }

    public function test_tampered_body_is_rejected()
    {
        $ts = $this->iso(0);
        $request = $this->request([
            'X-Webhook-Timestamp' => $ts,
            'X-Webhook-Signature-V2' => $this->sign($ts),
        ], '{"event":"other"}');

        $this->assertFalse($this->verify($request));
    }

    public function test_stale_timestamp_is_rejected()
    {
        $ts = $this->iso(-301);
        $request = $this->request([
            'X-Webhook-Timestamp' => $ts,
            'X-Webhook-Signature-V2' => $this->sign($ts),
        ]);

        $this->assertFalse($this->verify($request));
        $this->assertStringContainsString('window', $GLOBALS['bspt_test_errors'][0]);
    }

    public function test_future_timestamp_is_rejected()
    {
        $ts = $this->iso(301);
        $request = $this->request([
            'X-Webhook-Timestamp' => $ts,
            'X-Webhook-Signature-V2' => $this->sign($ts),
        ]);

        $this->assertFalse($this->verify($request));
        $this->assertStringContainsString('window', $GLOBALS['bspt_test_errors'][0]);
    }

    public function test_missing_timestamp_is_rejected()
    {
        $request = $this->request([
            'X-Webhook-Signature-V2' => $this->sign($this->iso(0)),
        ]);

        $this->assertFalse($this->verify($request));
        $this->assertStringContainsString('missing timestamp', $GLOBALS['bspt_test_errors'][0]);
    }

    public function test_unparseable_timestamp_is_rejected()
    {
        $request = $this->request([
            'X-Webhook-Timestamp' => 'not-a-date',
            'X-Webhook-Signature-V2' => $this->sign('not-a-date'),
        ]);

        $this->assertFalse($this->verify($request));
        $this->assertStringContainsString('unparseable', $GLOBALS['bspt_test_errors'][0]);
    }
}
