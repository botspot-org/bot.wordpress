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

        // Data can be a JSON string or already decoded
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data)) {
            return null;
        }

        $texts = [];
        self::harvest_prose($data, $texts);

        if (empty($texts)) {
            return null;
        }

        return self::texts_to_html($texts);
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
        if (empty($data)) {
            return null;
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            return null;
        }

        $texts = [];
        self::harvest_prose($data, $texts);

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

        if (empty($data)) {
            return null;
        }

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data)) {
            return null;
        }

        $texts = [];
        self::harvest_prose($data, $texts);

        if (empty($texts)) {
            return null;
        }

        return self::texts_to_html($texts);
    }

    /**
     * Keys whose values are headings rather than body prose.
     *
     * @since 3.5.15
     * @var   array
     */
    private static $heading_keys = [
        'title', 'heading', 'title_text', 'title_text_a', 'tab_title',
        'header_title', 'testimonial_name', 'headline',
    ];

    /**
     * Key fragments that are never content wherever they appear in the key.
     *
     * Substring matching is only safe for fragments that are not common English
     * substrings. "id" is not on this list precisely because it matches
     * video_description, slide_content, sidebar_text and guide_text.
     *
     * @since 3.5.15
     * @var   array
     */
    private static $skip_key_fragments = [
        'css', 'color', 'font', 'margin', 'padding', 'href', 'src',
        'animation', 'selector',
    ];

    /**
     * Key names that are never content, matched exactly or as a _suffix.
     *
     * Suffix matching keeps link_text, image_caption and title_text — which are
     * real copy — while rejecting link, image, _element_id and title_size.
     *
     * @since 3.5.15
     * @var   array
     */
    private static $skip_key_names = [
        'id', 'ids', 'url', 'link', 'class', 'icon', 'image', 'align',
        'size', 'width', 'height', 'template', 'shortcode', 'type', 'target',
    ];

    /**
     * Element-identity keys, matched exactly and never as a suffix.
     *
     * These hold the element's own type name ('container', 'accordion',
     * 'testimonial'), which is markup vocabulary, not copy. Exact-match only:
     * the _suffix rule would also eat display_name, which is real testimonial
     * text.
     *
     * @since 3.5.15
     * @var   array
     */
    private static $skip_key_exact = [
        'name', 'eltype', 'widgettype',
    ];

    /**
     * Recursively harvest prose from a builder's nested settings array.
     *
     * ponytail: a value-shape heuristic rather than a per-widget key allowlist.
     * The allowlist it replaces covered roughly a dozen of Elementor's hundred-
     * plus widgets and needed an edit for every new one. This over-collects a
     * little (a long button label can slip through); the ingest quality gate and
     * the dedupe in texts_to_html() absorb that. Tighten the heuristic if
     * noise shows up in real payloads.
     *
     * @since    3.5.15
     * @param    array        $data      Nested settings/elements array.
     * @param    array        $texts     Accumulator, by reference.
     * @param    string|null  $hint      'heading' when the enclosing element is
     *                                   a heading element, 'other' when it is a
     *                                   non-heading element, null when unknown.
     *                                   The promotion deliberately reaches only
     *                                   the element's own 'settings' array and
     *                                   no deeper — all three builders in scope
     *                                   put heading text there (Bricks
     *                                   settings.text, Beaver's explicit
     *                                   'heading' key, Elementor's 'title' via
     *                                   $heading_keys). A fourth builder that
     *                                   nests further would need this widened.
     * @return   void
     */
    private static function harvest_prose($data, &$texts, $hint = null)
    {
        // An element boundary either promotes its generic text/content key to a
        // heading, or resets an inherited promotion so it cannot bleed into a
        // sibling element.
        $local_hint = self::element_hint($data);
        if ($local_hint !== null) {
            $hint = $local_hint;
        }

        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $value = (array) $value;
            }

            if (is_array($value)) {
                // The promotion reaches the element's own settings and stops
                // there. Without this, a nested repeater inside a heading
                // element inherits 'heading' — it carries no element name of
                // its own to reset it — and whole body paragraphs come out as
                // <h2>.
                $child_hint = ($hint === 'heading' && is_string($key) && strtolower($key) === 'settings')
                    ? 'heading'
                    : null;
                self::harvest_prose($value, $texts, $child_hint);
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            // Numeric list indices carry no signal; treat them as unnamed.
            $key_lower = is_string($key) ? strtolower($key) : '';

            if ($key_lower !== '' && self::is_skipped_key($key_lower)) {
                continue;
            }

            $is_heading = in_array($key_lower, self::$heading_keys, true)
                || ($hint === 'heading' && ($key_lower === 'text' || $key_lower === 'content'));

            $plain = trim(wp_strip_all_tags($value));

            if ($is_heading) {
                if (mb_strlen($plain) >= 3 && mb_strlen($plain) <= 200 && !self::looks_like_setting($plain)) {
                    $texts[] = ['type' => 'heading', 'content' => $value];
                }
                continue;
            }

            if (self::is_prose($value)) {
                $texts[] = ['type' => 'text', 'content' => $value];
            }
        }
    }

    /**
     * Classify an element-shaped array by its builder-declared element name.
     *
     * @since    3.5.15
     * @param    array          $data
     * @return   string|null    'heading', 'other', or null when not an element.
     */
    private static function element_hint($data)
    {
        // Only arrays that actually are element boundaries carry a name worth
        // reading. A bare settings array can hold its own 'type' key (Bricks
        // buttons and alerts do), and treating that as an element boundary
        // would reset the enclosing element's heading promotion.
        $is_element = isset($data['settings']) || isset($data['elements']) || isset($data['children']);
        if (!$is_element) {
            return null;
        }

        $name_keys = ['name', 'widgetType', 'elType', 'type'];

        foreach ($name_keys as $name_key) {
            if (!isset($data[$name_key]) || !is_string($data[$name_key])) {
                continue;
            }

            $name = strtolower($data[$name_key]);
            if ($name === 'heading' || $name === 'title' || $name === 'post-title'
                || $name === 'theme-post-title') {
                return 'heading';
            }

            return 'other';
        }

        return null;
    }

    /**
     * Is this settings key one that never holds content?
     *
     * @since    3.5.15
     * @param    string   $key_lower   Lowercased key.
     * @return   bool
     */
    private static function is_skipped_key($key_lower)
    {
        if (in_array($key_lower, self::$skip_key_exact, true)) {
            return true;
        }

        foreach (self::$skip_key_fragments as $fragment) {
            if (strpos($key_lower, $fragment) !== false) {
                return true;
            }
        }

        foreach (self::$skip_key_names as $name) {
            if ($key_lower === $name) {
                return true;
            }
            // strlen, not mb_strlen: every suffix is ASCII and substr is
            // byte-based. substr() clamps when the key is shorter than the
            // suffix, so no length guard is needed.
            $suffix = '_' . $name;
            if (substr($key_lower, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this string read as body prose?
     *
     * The floor is deliberately low. The allowlists this replaces had no length
     * floor at all, so anything higher would make the "generic" extractor
     * return less than the hand-written one for heading-heavy builder pages —
     * card titles, list items, CTAs and price-list labels are routinely under
     * twenty characters. looks_like_setting() carries the rejection instead.
     *
     * The cost of that trade is single-token enum values (position=absolute,
     * text_transform=uppercase) reaching this function; looks_like_setting()
     * rejects the lowercase-ASCII ones, but expect some single-word noise to
     * survive. It dedupes away in texts_to_html() and is cheap next to the
     * recall the old allowlist lost.
     *
     * @since    3.5.15
     * @param    string   $value
     * @return   bool
     */
    private static function is_prose($value)
    {
        $plain = trim(wp_strip_all_tags($value));

        if (mb_strlen($plain) < 8) {
            return false;
        }

        // Single all-lowercase ASCII token: an enum-valued setting (absolute,
        // uppercase, mountains), not copy. This lives here rather than in
        // looks_like_setting() because the heading branch calls that too, and a
        // lowercase-styled one-word heading ("about", "espresso") is real
        // content. Heading keys are named explicitly by the builder, so no enum
        // ever routes through them and they need no such filter.
        if (preg_match('/^[a-z]{1,11}$/', $plain)) {
            return false;
        }

        return !self::looks_like_setting($plain);
    }

    /**
     * Reject values that are configuration rather than content.
     *
     * @since    3.5.15
     * @param    string   $plain   Tag-stripped value.
     * @return   bool
     */
    private static function looks_like_setting($plain)
    {
        // URLs and protocol-relative paths.
        if (preg_match('#^(https?:)?//#i', $plain) || preg_match('#^(mailto:|tel:|/wp-content/)#i', $plain)) {
            return true;
        }

        // Hex colours, rgb()/rgba(), CSS custom properties.
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $plain) || preg_match('/^(rgba?|hsla?)\s*\(/i', $plain)) {
            return true;
        }

        // CSS declaration blobs and rule bodies.
        if (strpos($plain, '{') !== false && strpos($plain, ':') !== false && strpos($plain, '}') !== false) {
            return true;
        }

        // Bare numbers with or without a unit.
        if (preg_match('/^-?\d+(\.\d+)?\s*(px|em|rem|%|vh|vw|pt|deg|s|ms)?$/i', $plain)) {
            return true;
        }

        // Slugs and identifiers: no word break, but glued with - or _.
        // Prose that short ("Shipping", "Wholesale") has neither.
        if (strpos($plain, ' ') === false
            && (strpos($plain, '-') !== false || strpos($plain, '_') !== false)) {
            return true;
        }

        return false;
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
