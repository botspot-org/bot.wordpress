<?php
/**
 * Tests for Bspt_Page_Builder content extraction.
 *
 * @since 3.5.15
 */

use PHPUnit\Framework\TestCase;

class PageBuilderExtractionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-page-builder.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['bspt_test_post_meta'] = [];
        $GLOBALS['bspt_test_posts'] = [];
    }

    /**
     * Register a fake post plus its meta, and return the post object.
     *
     * @param int    $post_id
     * @param string $content
     * @param array  $meta     meta_key => meta_value
     * @return object
     */
    private function make_post($post_id, $content, array $meta = [])
    {
        $post = (object) ['ID' => $post_id, 'post_content' => $content];
        $GLOBALS['bspt_test_posts'][$post_id] = $post;
        foreach ($meta as $key => $value) {
            $GLOBALS['bspt_test_post_meta']["{$post_id}:{$key}"] = $value;
        }
        return $post;
    }

    /**
     * A Divi page's post_content is long enough to clear the 200-char
     * short-circuit, but it is shortcode markup, not prose. Extraction must
     * still run and must return the prose, not the shortcodes.
     */
    public function test_divi_post_is_extracted_despite_long_shortcode_content()
    {
        $content = '[et_pb_section fb_built="1" admin_label="Section" _builder_version="4.9.0"]'
            . '[et_pb_row _builder_version="4.9.0"][et_pb_column type="4_4"]'
            . '[et_pb_text _builder_version="4.9.0"]'
            . '<p>Cold brew is steeped for twelve hours at room temperature.</p>'
            . '[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';

        $this->assertGreaterThan(200, mb_strlen(wp_strip_all_tags($content)), 'fixture must clear the short-circuit');

        $post = $this->make_post(101, $content, ['_et_pb_use_builder' => 'on']);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('Cold brew is steeped for twelve hours', $result);
        $this->assertStringNotContainsString('[et_pb_', $result);
    }

    /**
     * Same defect, WPBakery flavour.
     */
    public function test_wpbakery_post_is_extracted_despite_long_shortcode_content()
    {
        $content = '[vc_row css=".vc_custom_1234{padding-top:40px;padding-bottom:40px}"]'
            . '[vc_column width="1/2"][vc_custom_heading text="Our Roasting Process"]'
            . '[vc_column_text]<p>Every batch is roasted to a medium profile in small drums.</p>[/vc_column_text]'
            . '[/vc_column][/vc_row]';

        $this->assertGreaterThan(200, mb_strlen(wp_strip_all_tags($content)), 'fixture must clear the short-circuit');

        $post = $this->make_post(102, $content);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('Our Roasting Process', $result);
        $this->assertStringContainsString('Every batch is roasted to a medium profile', $result);
        $this->assertStringNotContainsString('[vc_', $result);
    }

    /**
     * A plain classic-editor post has no builder. Its content must be returned
     * untouched — the gate must not start mangling normal posts.
     */
    public function test_plain_post_content_is_returned_unchanged()
    {
        $content = '<p>' . str_repeat('An ordinary paragraph of editorial copy. ', 10) . '</p>';
        $post = $this->make_post(103, $content);

        $this->assertSame($content, Bspt_Page_Builder::extract_content($post));
    }

    /**
     * detect_builder must keep reporting the builder name — the payload's
     * page_builder field depends on it.
     */
    public function test_detect_builder_reports_divi()
    {
        $this->make_post(104, '[et_pb_section][/et_pb_section]', ['_et_pb_use_builder' => 'on']);

        $this->assertSame('divi', Bspt_Page_Builder::detect_builder(104));
    }

    public function test_detect_builder_returns_null_for_plain_post()
    {
        $this->make_post(105, '<p>Plain.</p>');

        $this->assertNull(Bspt_Page_Builder::detect_builder(105));
    }
}
