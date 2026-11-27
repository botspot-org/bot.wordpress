<?php
/**
 * On the core engine the post screens show no per-post push status.
 *
 * @since 3.7.12
 */

use PHPUnit\Framework\TestCase;

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default')
    {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        $id = is_object($post) ? $post->ID : (int) $post;
        return isset($GLOBALS['bspt_test_posts'][$id]) ? $GLOBALS['bspt_test_posts'][$id]->post_type : false;
    }
}

class AdminSyncStatusTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-options.php';
        require_once __DIR__ . '/../includes/class-bspt-page-builder.php';
        require_once __DIR__ . '/../includes/class-bspt-language.php';
        require_once __DIR__ . '/../includes/class-bspt-sync.php';
        require_once __DIR__ . '/../admin/class-bspt-admin.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_options'] = ['bspt_sync_post_types' => ['post', 'page']];
        $GLOBALS['bspt_test_post_meta'] = ['41:_bspt_sync_status' => 'error'];
        $GLOBALS['bspt_test_posts'] = [41 => new WP_Post(['ID' => 41])];
        $GLOBALS['bspt_test_home_url'] = 'https://example.com';
        $GLOBALS['bspt_test_permalinks'] = [];
    }

    private function meta_box()
    {
        ob_start();
        (new Bspt_Admin('botspot', 'test'))->render_sync_meta_box($GLOBALS['bspt_test_posts'][41]);
        return ob_get_clean();
    }

    public function test_the_meta_box_shows_the_push_status_on_the_push_engine()
    {
        $html = $this->meta_box();

        $this->assertStringContainsString('Status:', $html);
        $this->assertStringContainsString('Error', $html);
    }

    public function test_the_meta_box_hides_the_push_status_on_the_core_engine()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';

        $html = $this->meta_box();

        $this->assertStringNotContainsString('Status:', $html);
        $this->assertStringContainsString('reads this site', $html);
        $this->assertStringContainsString('botspot-sync-now', $html);
    }

    public function test_a_language_domain_post_shows_its_push_status_on_the_core_engine()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';
        $GLOBALS['bspt_test_permalinks'][41] = 'https://example.se/hej/';

        $html = $this->meta_box();

        $this->assertStringContainsString('Status:', $html);
    }

    public function test_the_list_column_hides_the_push_status_on_the_core_engine()
    {
        $GLOBALS['bspt_test_options']['bspt_sync_engine'] = 'core';

        ob_start();
        (new Bspt_Admin('botspot', 'test'))->render_sync_column('botspot_sync', 41);
        $html = ob_get_clean();

        $this->assertSame('<span style="color: #999;">&#8212;</span>', $html);
    }
}
