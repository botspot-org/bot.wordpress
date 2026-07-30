<?php
/**
 * Page builder content extraction
 *
 * Extracts content from page builders that store data outside post_content.
 *
 * @link       https://bot.spot
 * @since      2.9.2
 *
 * @package    Bspt
 * @subpackage Bspt/includes
 */

if (!defined("WPINC")) {
    die();
}

/**
 * Page builder content extraction class.
 *
 * Supports: Elementor, Divi, WPBakery, Beaver Builder, Bricks.
 *
 * @since      2.9.2
 * @package    Bspt
 * @subpackage Bspt/includes
 */
class Bspt_Page_Builder
{
    /**
     * Extract content from a post, handling page builder data
     *
     * @since    2.9.2
     * @param    WP_Post   $post    The post object.
     * @return   string             Extracted HTML content.
     */
    public static function extract_content($post)
    {
        $post_id = $post->ID;
        $content = $post->post_content;
        $builder = self::detect_builder($post_id);

        // Only trust the raw post_content length when no builder owns the page.
        // Divi and WPBakery store their pages as shortcodes *inside*
        // post_content, and wp_strip_all_tags() does not strip shortcodes — so a
        // length check here would always pass and return shortcode markup as if
        // it were prose.
        if ($builder === null) {
            $stripped = wp_strip_all_tags($content);
            if (mb_strlen($stripped) > 200) {
                return $content;
            }
        }

        $extracted = self::extract_for_builder($post, $builder);

        // No length floor. extract_for_builder() only returns non-null when a
        // builder was detected, and for those posts the alternative is raw
        // post_content — shortcode markup for Divi/WPBakery, empty for the
        // JSON-backed builders. Any extracted prose beats both, however short.
        // The old >50 floor silently discarded short builder pages in favour of
        // content strictly worse than what it threw away.
        if ($extracted !== null && wp_strip_all_tags($extracted) !== '') {
            return $extracted;
        }

        // Fallback: try rendering shortcodes in original content. has_shortcode()
        // returns false whenever the content holds no '[', so the strpos check
        // alone is equivalent to the union it replaces.
        if (strpos($content, '[') !== false) {
            $rendered = do_shortcode($content);
            if (mb_strlen(wp_strip_all_tags($rendered)) > mb_strlen(wp_strip_all_tags($content)) + 50) {
                return $rendered;
            }
        }

        return $content;
    }

    /**
     * Dispatch to the extractor for a detected builder.
     *
     * @since    3.5.15
     * @param    object       $post       The post object.
     * @param    string|null  $builder    Builder name from detect_builder().
     * @return   string|null              Extracted HTML, or null.
     */
    private static function extract_for_builder($post, $builder)
    {
        switch ($builder) {
            case 'elementor':
                return self::extract_elementor_content($post->ID);
            case 'divi':
                return self::extract_divi_content($post);
            case 'wpbakery':
                return self::extract_wpbakery_content($post);
            case 'beaver_builder':
                return self::extract_beaver_content($post->ID);
            case 'bricks':
                return self::extract_bricks_content($post->ID);
        }

        return null;
    }

    /**
     * Detect which page builder (if any) is used for a post
     *
     * @since    2.9.2
     * @param    int    $post_id    The post ID.
     * @return   string|null        Builder name or null.
     */
    public static function detect_builder($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        if (self::is_elementor_post($post_id)) {
            return 'elementor';
        }
        if (self::is_divi_post($post_id)) {
            return 'divi';
        }
        if (self::is_wpbakery_post($post)) {
            return 'wpbakery';
        }
        if (self::is_beaver_post($post_id)) {
            return 'beaver_builder';
        }
        if (self::is_bricks_post($post_id)) {
            return 'bricks';
        }

        return null;
    }

    /**
     * Check if Elementor is used for this post
     */
    private static function is_elementor_post($post_id)
    {
        return get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder'
            || get_post_meta($post_id, '_elementor_data', true);
    }

    /**
     * Extract content from Elementor data
     */
    private static function extract_elementor_content($post_id)
    {
        $data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($data)) {
            return null;
        }

        // Data can be JSON string or already decoded
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data)) {
            return null;
        }

        $texts = [];
        self::extract_elementor_texts($data, $texts);

        if (empty($texts)) {
            return null;
        }

        // Build HTML from extracted text blocks
        return self::texts_to_html($texts);
    }

    /**
     * Recursively extract text from Elementor widget data
     */
    private static function extract_elementor_texts($elements, &$texts)
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            // Extract text from widget settings
            if (isset($element['settings']) && is_array($element['settings'])) {
                $settings = $element['settings'];

                // Heading widget
                if (isset($settings['title']) && !empty($settings['title'])) {
                    $texts[] = ['type' => 'heading', 'content' => $settings['title']];
                }

                // Text editor widget
                if (isset($settings['editor']) && !empty($settings['editor'])) {
                    $texts[] = ['type' => 'text', 'content' => $settings['editor']];
                }

                // Text widget (legacy)
                if (isset($settings['text']) && !empty($settings['text'])) {
                    $texts[] = ['type' => 'text', 'content' => $settings['text']];
                }

                // Button text
                if (isset($settings['text']) && isset($element['widgetType']) && $element['widgetType'] === 'button') {
                    // Skip buttons, not main content
                }

                // Icon box
                if (isset($settings['title_text']) && !empty($settings['title_text'])) {
                    $texts[] = ['type' => 'heading', 'content' => $settings['title_text']];
                }
                if (isset($settings['description_text']) && !empty($settings['description_text'])) {
                    $texts[] = ['type' => 'text', 'content' => $settings['description_text']];
                }

                // Image box
                if (isset($settings['title_text_a']) && !empty($settings['title_text_a'])) {
                    $texts[] = ['type' => 'heading', 'content' => $settings['title_text_a']];
                }
                if (isset($settings['description_text_a']) && !empty($settings['description_text_a'])) {
                    $texts[] = ['type' => 'text', 'content' => $settings['description_text_a']];
                }

                // Testimonial
                if (isset($settings['testimonial_content']) && !empty($settings['testimonial_content'])) {
                    $texts[] = ['type' => 'text', 'content' => $settings['testimonial_content']];
                }

                // Tabs/Accordion content
                if (isset($settings['tabs']) && is_array($settings['tabs'])) {
                    foreach ($settings['tabs'] as $tab) {
                        if (isset($tab['tab_title']) && !empty($tab['tab_title'])) {
                            $texts[] = ['type' => 'heading', 'content' => $tab['tab_title']];
                        }
                        if (isset($tab['tab_content']) && !empty($tab['tab_content'])) {
                            $texts[] = ['type' => 'text', 'content' => $tab['tab_content']];
                        }
                    }
                }

                // Toggle/Accordion
                if (isset($settings['title_text']) && isset($settings['content'])) {
                    $texts[] = ['type' => 'text', 'content' => $settings['content']];
                }

                // Call to action
                if (isset($settings['title']) && !empty($settings['title'])) {
                    // Already handled above
                }
                if (isset($settings['description']) && !empty($settings['description'])) {
                    $texts[] = ['type' => 'text', 'content' => $settings['description']];
                }

                // Price list
                if (isset($settings['price_list']) && is_array($settings['price_list'])) {
                    foreach ($settings['price_list'] as $item) {
                        if (isset($item['title']) && !empty($item['title'])) {
                            $texts[] = ['type' => 'heading', 'content' => $item['title']];
                        }
                        if (isset($item['item_description']) && !empty($item['item_description'])) {
                            $texts[] = ['type' => 'text', 'content' => $item['item_description']];
                        }
                    }
                }
            }

            // Recurse into nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                self::extract_elementor_texts($element['elements'], $texts);
            }
        }
    }

    /**
     * Check if Divi is used for this post
     */
    private static function is_divi_post($post_id)
    {
        return get_post_meta($post_id, '_et_pb_use_builder', true) === 'on';
    }

    /**
     * Extract content from Divi shortcodes
     */
    private static function extract_divi_content($post)
    {
        $content = $post->post_content;

        // Divi uses shortcodes like [et_pb_text], [et_pb_blurb], etc.
        if (strpos($content, '[et_pb_') === false) {
            return null;
        }

        // Try to render shortcodes if Divi is active
        if (function_exists('et_pb_is_pagebuilder_used')) {
            // Let Divi render its shortcodes
            $rendered = do_shortcode($content);
            if (!empty($rendered) && $rendered !== $content) {
                return $rendered;
            }
        }

        // Fallback: extract text from shortcode content manually
        $texts = [];

        // Extract from [et_pb_text] shortcodes
        if (preg_match_all('/\[et_pb_text[^\]]*\](.*?)\[\/et_pb_text\]/s', $content, $matches)) {
            foreach ($matches[1] as $text) {
                $clean = trim($text);
                if (!empty($clean)) {
                    $texts[] = ['type' => 'text', 'content' => $clean];
                }
            }
        }

        // Extract from [et_pb_blurb] shortcodes (title in attribute, content inside)
        if (preg_match_all('/\[et_pb_blurb[^\]]*title="([^"]*)"[^\]]*\](.*?)\[\/et_pb_blurb\]/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $texts[] = ['type' => 'heading', 'content' => $match[1]];
                }
                if (!empty(trim($match[2]))) {
                    $texts[] = ['type' => 'text', 'content' => trim($match[2])];
                }
            }
        }

        // Extract titles from various modules
        if (preg_match_all('/\[et_pb_[a-z_]+[^\]]*title="([^"]+)"/', $content, $matches)) {
            foreach ($matches[1] as $title) {
                $texts[] = ['type' => 'heading', 'content' => $title];
            }
        }

        if (empty($texts)) {
            return null;
        }

        return self::texts_to_html($texts);
    }

    /**
     * Check if WPBakery is used for this post
     */
    private static function is_wpbakery_post($post)
    {
        $content = $post->post_content;
        return strpos($content, '[vc_') !== false || strpos($content, '[/vc_') !== false;
    }

    /**
     * Extract content from WPBakery shortcodes
     */
    private static function extract_wpbakery_content($post)
    {
        $content = $post->post_content;

        // Try to render shortcodes if WPBakery is active
        if (defined('WPB_VC_VERSION')) {
            $rendered = do_shortcode($content);
            if (!empty($rendered) && $rendered !== $content) {
                return $rendered;
            }
        }

        // Fallback: extract text manually
        $texts = [];

        // [vc_column_text] - main text container
        if (preg_match_all('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/s', $content, $matches)) {
            foreach ($matches[1] as $text) {
                $clean = trim($text);
                if (!empty($clean)) {
                    $texts[] = ['type' => 'text', 'content' => $clean];
                }
            }
        }

        // Custom headings
        if (preg_match_all('/\[vc_custom_heading[^\]]*text="([^"]+)"/', $content, $matches)) {
            foreach ($matches[1] as $heading) {
                $texts[] = ['type' => 'heading', 'content' => $heading];
            }
        }

        // Message boxes
        if (preg_match_all('/\[vc_message[^\]]*\](.*?)\[\/vc_message\]/s', $content, $matches)) {
            foreach ($matches[1] as $text) {
                $clean = trim($text);
                if (!empty($clean)) {
                    $texts[] = ['type' => 'text', 'content' => $clean];
                }
            }
        }

        if (empty($texts)) {
            return null;
        }

        return self::texts_to_html($texts);
    }

    /**
     * Check if Beaver Builder is used for this post
     */
    private static function is_beaver_post($post_id)
    {
        return get_post_meta($post_id, '_fl_builder_enabled', true) === '1'
            || get_post_meta($post_id, '_fl_builder_data', true);
    }

    /**
     * Extract content from Beaver Builder data
     */
    private static function extract_beaver_content($post_id)
    {
        $data = get_post_meta($post_id, '_fl_builder_data', true);
        if (empty($data) || !is_array($data)) {
            return null;
        }

        $texts = [];

        foreach ($data as $node) {
            if (!is_object($node) && !is_array($node)) {
                continue;
            }

            $node = (array) $node;
            $settings = isset($node['settings']) ? (array) $node['settings'] : [];

            // Rich text module
            if (isset($settings['text']) && !empty($settings['text'])) {
                $texts[] = ['type' => 'text', 'content' => $settings['text']];
            }

            // Heading module
            if (isset($settings['heading']) && !empty($settings['heading'])) {
                $texts[] = ['type' => 'heading', 'content' => $settings['heading']];
            }

            // Callout module
            if (isset($settings['title']) && !empty($settings['title'])) {
                $texts[] = ['type' => 'heading', 'content' => $settings['title']];
            }
            if (isset($settings['text']) && !empty($settings['text'])) {
                // Already handled above
            }

            // Icon group
            if (isset($settings['text']) && isset($node['type']) && $node['type'] === 'icon') {
                // Skip icon labels
            }
        }

        if (empty($texts)) {
            return null;
        }

        return self::texts_to_html($texts);
    }

    /**
     * Check if Bricks is used for this post
     */
    private static function is_bricks_post($post_id)
    {
        return get_post_meta($post_id, '_bricks_page_content_2', true)
            || get_post_meta($post_id, '_bricks_page_content', true);
    }

    /**
     * Extract content from Bricks data
     */
    private static function extract_bricks_content($post_id)
    {
        $data = get_post_meta($post_id, '_bricks_page_content_2', true);
        if (empty($data)) {
            $data = get_post_meta($post_id, '_bricks_page_content', true);
        }

        if (empty($data) || !is_array($data)) {
            return null;
        }

        $texts = [];

        foreach ($data as $element) {
            if (!is_array($element)) {
                continue;
            }

            $settings = isset($element['settings']) ? $element['settings'] : [];
            $name = isset($element['name']) ? $element['name'] : '';

            // Text element
            if ($name === 'text' && isset($settings['text']) && !empty($settings['text'])) {
                $texts[] = ['type' => 'text', 'content' => $settings['text']];
            }

            // Heading element
            if ($name === 'heading' && isset($settings['text']) && !empty($settings['text'])) {
                $texts[] = ['type' => 'heading', 'content' => $settings['text']];
            }

            // Rich text
            if (isset($settings['content']) && !empty($settings['content'])) {
                $texts[] = ['type' => 'text', 'content' => $settings['content']];
            }
        }

        if (empty($texts)) {
            return null;
        }

        return self::texts_to_html($texts);
    }

    /**
     * Convert extracted text blocks to HTML
     */
    private static function texts_to_html($texts)
    {
        $html = '';
        $seen = [];

        foreach ($texts as $item) {
            $content = trim($item['content']);
            if (empty($content)) {
                continue;
            }

            // Deduplicate
            $hash = md5($content);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            // Clean up the content
            $content = self::clean_content($content);

            if ($item['type'] === 'heading') {
                $html .= '<h2>' . $content . '</h2>' . "\n";
            } else {
                // If content already has HTML tags, use as-is
                if ($content !== wp_strip_all_tags($content)) {
                    $html .= $content . "\n";
                } else {
                    $html .= '<p>' . $content . '</p>' . "\n";
                }
            }
        }

        return $html;
    }

    /**
     * Clean up extracted content
     */
    private static function clean_content($content)
    {
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // Remove Elementor dynamic tags like [elementor-tag ...]
        $content = preg_replace('/\[elementor-tag[^\]]*\]/', '', $content);

        // Remove empty paragraphs
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);

        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }
}
