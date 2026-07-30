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

    /**
     * A widget type the old allowlist never covered (Elementor's icon-list).
     * The harvester must find its prose without anyone adding a key for it.
     */
    public function test_elementor_extracts_widget_types_outside_the_old_allowlist()
    {
        $data = wp_json_encode([
            [
                'elType' => 'section',
                'elements' => [
                    [
                        'elType' => 'column',
                        'elements' => [
                            [
                                'elType' => 'widget',
                                'widgetType' => 'icon-list',
                                'settings' => [
                                    'icon_list' => [
                                        ['text' => 'Single-origin beans sourced directly from the farm cooperative.'],
                                        ['text' => 'Roasted in small batches every Tuesday and Friday morning.'],
                                    ],
                                ],
                            ],
                            [
                                'elType' => 'widget',
                                'widgetType' => 'toggle',
                                'settings' => [
                                    'tabs' => [
                                        [
                                            'tab_title' => 'Shipping',
                                            'tab_content' => 'Orders placed before noon ship the same business day.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $post = $this->make_post(201, '', [
            '_elementor_edit_mode' => 'builder',
            '_elementor_data' => $data,
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('Single-origin beans sourced directly', $result);
        $this->assertStringContainsString('Roasted in small batches every Tuesday', $result);
        $this->assertStringContainsString('Orders placed before noon ship', $result);
        $this->assertStringContainsString('<h2>Shipping</h2>', $result);
    }

    /**
     * The harvester must not sweep up CSS, URLs, colours, or numeric settings.
     */
    public function test_harvester_skips_non_prose_settings()
    {
        $data = wp_json_encode([
            [
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => [
                    'editor' => '<p>The tasting notes lean toward stone fruit and brown sugar.</p>',
                    'custom_css' => '.selector{background-color:#ff00aa;padding:20px 40px 20px 40px}',
                    'link' => ['url' => 'https://example.com/very/long/path/to/a/landing/page'],
                    'background_color' => '#1a1a1a',
                    'typography_font_family' => 'Helvetica Neue',
                    '_element_id' => 'section-hero-primary-container',
                ],
            ],
        ]);

        $post = $this->make_post(202, '', [
            '_elementor_edit_mode' => 'builder',
            '_elementor_data' => $data,
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('stone fruit and brown sugar', $result);
        $this->assertStringNotContainsString('background-color', $result);
        $this->assertStringNotContainsString('https://example.com', $result);
        $this->assertStringNotContainsString('#1a1a1a', $result);
        $this->assertStringNotContainsString('Helvetica Neue', $result);
    }

    /**
     * Bricks stores elements as a flat array, but rich-text and nested repeater
     * settings still need recursion.
     */
    public function test_bricks_extracts_nested_element_settings()
    {
        $post = $this->make_post(203, '', [
            '_bricks_page_content_2' => [
                [
                    'name' => 'heading',
                    'settings' => ['text' => 'Our Tasting Room'],
                ],
                [
                    'name' => 'accordion',
                    'settings' => [
                        'items' => [
                            [
                                'title' => 'Opening hours',
                                'content' => 'The tasting room is open from ten in the morning until six.',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('<h2>Our Tasting Room</h2>', $result);
        $this->assertStringContainsString('open from ten in the morning', $result);
    }

    /**
     * Beaver Builder stores nodes as objects with object settings.
     */
    public function test_beaver_extracts_object_settings()
    {
        $node = new stdClass();
        $node->type = 'module';
        $node->settings = (object) [
            'heading' => 'Wholesale Enquiries',
            'text' => 'We supply cafés across the region with weekly deliveries of fresh beans.',
        ];

        $post = $this->make_post(204, '', [
            '_fl_builder_enabled' => '1',
            '_fl_builder_data' => [$node],
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('<h2>Wholesale Enquiries</h2>', $result);
        $this->assertStringContainsString('We supply cafés across the region', $result);
    }

    /**
     * The allowlists this replaces had no length floor: any non-empty value for
     * a known key was emitted. Builder pages are heading-heavy, so short card
     * titles and list items must survive or the "generic" extractor returns
     * less than the hand-written one it replaces.
     */
    public function test_short_content_is_not_dropped()
    {
        $data = wp_json_encode([
            [
                'elType' => 'widget',
                'widgetType' => 'icon-list',
                'settings' => [
                    'icon_list' => [
                        ['text' => 'Free shipping'],
                        ['text' => 'Roasted weekly'],
                    ],
                ],
            ],
        ]);

        $post = $this->make_post(205, '', [
            '_elementor_edit_mode' => 'builder',
            '_elementor_data' => $data,
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('Free shipping', $result);
        $this->assertStringContainsString('Roasted weekly', $result);
    }

    /**
     * Key rejection must be anchored. Substring matching on "id"/"size"/"link"
     * silently eats slide_content, video_description, title_size and link_text —
     * all of which are real copy. Elementor's slides widget uses slide_content.
     */
    public function test_content_keys_containing_skip_fragments_survive()
    {
        $data = wp_json_encode([
            [
                'elType' => 'widget',
                'widgetType' => 'slides',
                'settings' => [
                    'slide_content' => 'Our espresso blend balances chocolate and citrus.',
                    'video_description' => 'A short tour of the roastery floor.',
                    'link_text' => 'Read the full sourcing report',
                    '_element_id' => 'section-hero-primary-container',
                    'title_size' => 'h3',
                ],
            ],
        ]);

        $post = $this->make_post(206, '', [
            '_elementor_edit_mode' => 'builder',
            '_elementor_data' => $data,
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('Our espresso blend balances', $result);
        $this->assertStringContainsString('A short tour of the roastery floor', $result);
        $this->assertStringContainsString('Read the full sourcing report', $result);
        $this->assertStringNotContainsString('section-hero-primary-container', $result);
    }

    /**
     * An element's own type name is markup vocabulary, not copy. elType and
     * widgetType are not covered by the _suffix rule ('eltype' does not end in
     * '_type'), so without an exact-match list every Elementor 3.6 container
     * ships a stray <p>container</p> and every Bricks accordion a
     * <p>accordion</p>.
     */
    public function test_element_type_names_are_not_emitted_as_content()
    {
        $post = $this->make_post(207, '', [
            '_bricks_page_content_2' => [
                [
                    'name' => 'accordion',
                    'settings' => [
                        'items' => [
                            ['content' => 'An answer long enough to count as body copy.'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('An answer long enough to count', $result);
        $this->assertStringNotContainsString('accordion', $result);
    }

    /**
     * A settings array can carry its own 'type' key (Bricks buttons and
     * alerts do). That must not read as an element boundary and cancel the
     * enclosing heading element's promotion.
     */
    public function test_settings_type_key_does_not_reset_the_heading_hint()
    {
        $post = $this->make_post(208, '', [
            '_bricks_page_content_2' => [
                [
                    'name' => 'heading',
                    'settings' => [
                        'type' => 'primary',
                        'text' => 'Our Tasting Room',
                    ],
                ],
            ],
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('<h2>Our Tasting Room</h2>', $result);
    }

    /**
     * The heading promotion must not reach a nested repeater. Repeater items
     * carry no element name of their own, so an inherited hint would never be
     * reset and full body paragraphs would come out as <h2>.
     */
    public function test_heading_hint_does_not_bleed_into_nested_items()
    {
        $post = $this->make_post(209, '', [
            '_bricks_page_content_2' => [
                [
                    'name' => 'heading',
                    'settings' => [
                        'text' => 'Our Tasting Room',
                        'items' => [
                            ['content' => 'This is a long body paragraph belonging to a nested repeater.'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('<h2>Our Tasting Room</h2>', $result);
        $this->assertStringContainsString('<p>This is a long body paragraph belonging to a nested repeater.</p>', $result);
        $this->assertStringNotContainsString('<h2>This is a long body paragraph', $result);
    }

    /**
     * The 8-char floor with no word-break rule lets enum-valued settings
     * through unless looks_like_setting() rejects them.
     */
    public function test_enum_valued_settings_are_not_emitted_as_content()
    {
        $data = wp_json_encode([
            [
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => [
                    'editor' => '<p>The tasting notes lean toward stone fruit.</p>',
                    'position' => 'absolute',
                    'text_transform' => 'uppercase',
                    'shape_divider_top' => 'mountains',
                ],
            ],
        ]);

        $post = $this->make_post(210, '', [
            '_elementor_edit_mode' => 'builder',
            '_elementor_data' => $data,
        ]);

        $result = Bspt_Page_Builder::extract_content($post);

        $this->assertStringContainsString('stone fruit', $result);
        $this->assertStringNotContainsString('absolute', $result);
        $this->assertStringNotContainsString('uppercase', $result);
        $this->assertStringNotContainsString('mountains', $result);
    }

    /**
     * The enum-token filter must not reach the heading branch. Lowercase
     * one-word headings ("about", "espresso") are a common design choice, and
     * both the hint path and the explicit $heading_keys path route through
     * looks_like_setting() — so the rule has to live in is_prose() instead.
     */
    public function test_lowercase_one_word_heading_survives()
    {
        $post = $this->make_post(211, '', [
            '_bricks_page_content_2' => [
                ['name' => 'heading', 'settings' => ['text' => 'espresso']],
            ],
        ]);

        $this->assertStringContainsString('<h2>espresso</h2>', Bspt_Page_Builder::extract_content($post));
    }
}
