<?php
/**
 * Unified content injector for the BotDot WP plugin
 *
 * Injects both JSON-LD and appendix HTML from a single fetch.
 *
 * @link       https://bot.spot
 * @since      1.0.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * Unified content injector for JSON-LD and appendix HTML.
 *
 * Replaces the old BotDot_WP_Injector and appendix injection logic
 * from BotDot_WP_Public with a single class that handles both.
 *
 * @since      1.0.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Content_Injector
{
    /**
     * The plugin name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $version;

    /**
     * Whether appendix has already been injected on current request.
     *
     * @since    1.0.0
     * @access   private
     * @var      bool
     */
    private $appendix_injected = false;

    /**
     * Whether shortcode was used on current page.
     *
     * @since    1.0.0
     * @access   private
     * @var      bool
     */
    private $shortcode_used = false;

    /**
     * Per-request cache of locus JSON-LD data.
     *
     * @since    1.3.0
     * @access   private
     * @var      array|null
     */
    private $locus_jsonld_cache = null;

    /**
     * Whether locus JSON-LD cache has been populated.
     *
     * @since    1.3.0
     * @access   private
     * @var      bool
     */
    private $locus_jsonld_fetched = false;

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    The plugin name.
     * @param    string    $version        The plugin version.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Pass Yoast SEO's @graph array through unchanged (peer-schema model).
     *
     * Filter: wpseo_schema_graph (priority 99)
     *
     * Prior to 2.5.0 this hook merged locus nodes into Yoast's graph. The
     * peer-schema model treats BotSpot and Yoast as independent publishers
     * of JSON-LD — each emits its own <script> tag on the page. Locus
     * nodes are injected separately via inject_jsonld() at wp_head:99.
     *
     * @since    1.3.0
     * @param    array    $graph    Yoast @graph array of nodes.
     * @return   array              Unmodified graph.
     */
    public function merge_into_yoast_graph($graph)
    {
        return $graph;
    }

    /**
     * Pass Yoast SEO's full JSON-LD output through unchanged (peer-schema model).
     *
     * Filter: wpseo_json_ld_output (priority 99) — legacy Yoast pre-14.0.
     *
     * @since    1.3.0
     * @param    array    $data    Yoast JSON-LD data.
     * @return   array             Unmodified data.
     */
    public function merge_into_yoast_jsonld($data)
    {
        return $data;
    }

    /**
     * Pass RankMath's JSON-LD output through unchanged (peer-schema model).
     *
     * Filter: rank_math/json_ld (priority 99)
     *
     * @since    1.3.0
     * @param    array    $data    RankMath JSON-LD data.
     * @return   array             Unmodified data.
     */
    public function merge_into_rankmath_jsonld($data)
    {
        return $data;
    }

    /**
     * Fetch and cache locus JSON-LD for the current page.
     *
     * @since    1.3.0
     * @access   private
     * @return   array|null    Decoded JSON-LD data, or null if unavailable.
     */
    private function get_locus_jsonld()
    {
        if ($this->locus_jsonld_fetched) {
            return $this->locus_jsonld_cache;
        }

        $this->locus_jsonld_fetched = true;
        $path = $this->get_current_url_path();

        $appendix_enabled = BotDot_WP_Options::get("appendix_enabled");
        if (!$appendix_enabled) {
            $data = BotDot_WP_Content_Fetcher::fetch_jsonld($path);
        } else {
            $data = BotDot_WP_Content_Fetcher::fetch($path);
        }

        if (!$data || !isset($data["jsonld"]) || $data["jsonld"] === null) {
            $this->locus_jsonld_cache = null;
            return null;
        }

        $jsonld = $data["jsonld"];

        // Normalize: decode string to array if needed
        if (is_string($jsonld)) {
            $decoded = json_decode($jsonld, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->locus_jsonld_cache = null;
                return null;
            }
            $jsonld = $decoded;
        }

        // Apply filter
        $jsonld = apply_filters("botdot_wp_appendix_jsonld", $jsonld);

        if (empty($jsonld)) {
            $this->locus_jsonld_cache = null;
            return null;
        }

        // Annotate with BotSpot sdPublisher attribution
        $nodes = $this->extract_graph_nodes($jsonld);
        if (!empty($nodes)) {
            $nodes = $this->annotate_nodes_with_botspot($nodes);

            // Append BotSpot org node if not already present
            $existing_ids = array_filter(array_map(function ($n) {
                return is_array($n) && isset($n["@id"]) ? $n["@id"] : null;
            }, $nodes));
            if (!in_array("https://bot.spot/#botspot", $existing_ids)) {
                $nodes[] = $this->get_botspot_org_node();
            }

            // Re-wrap as @graph structure
            $context = isset($jsonld["@context"]) ? $jsonld["@context"] : "https://schema.org";
            $jsonld = ["@context" => $context, "@graph" => $nodes];
        }

        $this->locus_jsonld_cache = $jsonld;
        return $jsonld;
    }

    /**
     * Extract @graph nodes from a JSON-LD structure.
     *
     * Handles both @graph arrays and single-node structures.
     *
     * @since    1.3.0
     * @access   private
     * @param    array    $jsonld    Decoded JSON-LD data.
     * @return   array               Array of individual nodes.
     */
    private function extract_graph_nodes($jsonld)
    {
        if (!is_array($jsonld)) {
            return [];
        }

        // Has @graph array
        if (isset($jsonld["@graph"]) && is_array($jsonld["@graph"])) {
            return $jsonld["@graph"];
        }

        // Flat array of nodes
        if (isset($jsonld[0]) && is_array($jsonld[0])) {
            return $jsonld;
        }

        // Single node (has @type)
        if (isset($jsonld["@type"])) {
            return [$jsonld];
        }

        return [];
    }

    /**
     * Inject JSON-LD into wp_head
     *
     * Hook: wp_head (priority 99, after other SEO plugins)
     *
     * @since    1.0.0
     */
    public function inject_jsonld()
    {
        if (!$this->should_inject_jsonld()) {
            return;
        }

        // Respect the "off" option as a full disable, regardless of SEO plugin
        // presence. "merge" and "replace" modes are legacy; in the peer-schema
        // model both behave the same (emit the standalone tag).
        $conflict_mode = BotDot_WP_Options::get("jsonld_conflict_mode", "merge");
        if ($conflict_mode === "off") {
            $this->log_debug("JSON-LD conflict mode is 'off', skipping injection");
            return;
        }

        $decoded = $this->get_locus_jsonld();
        if ($decoded === null) {
            return;
        }

        $json_string = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Prevent script-tag breakout
        $json_string = str_replace("</script>", "<\/script>", $json_string);

        echo "\n<!-- BotSpot JSON-LD -->\n";
        echo '<script type="application/ld+json">' . $json_string . "</script>";
        echo "\n<!-- /BotSpot JSON-LD -->\n";

        $this->log_debug("JSON-LD injected into wp_head as peer schema tag");
    }

    /**
     * Inject appendix content via the_content filter
     *
     * Hook: the_content (priority 20)
     *
     * @since    1.0.0
     * @param    string    $content    The post content.
     * @return   string                Modified content with appendix.
     */
    public function inject_appendix_content($content)
    {
        // Don't add if already injected
        if ($this->appendix_injected) {
            return $content . $this->bsa_debug_comment("the_content", "already_injected");
        }

        if (!$this->should_inject_appendix()) {
            return $content . $this->bsa_debug_comment("the_content", "should_not_inject", $this->bsa_debug_state());
        }

        $position = $this->resolve_injection_position();

        // Only inject via content filter for 'bottom' position
        if ($position !== "bottom") {
            return $content . $this->bsa_debug_comment("the_content", "position_not_bottom", ["position" => $position]);
        }

        // Check for manual placement
        if ($this->has_manual_placement($content)) {
            return $content . $this->bsa_debug_comment("the_content", "manual_placement");
        }

        // Don't add on feeds
        if (is_feed()) {
            return $content;
        }

        // Detect page builders that discard the_content output.
        // In those cases, skip injection here and let wp_footer handle it.
        if ($this->is_page_builder_active()) {
            $this->log_debug("Page builder detected, deferring appendix to footer fallback");
            return $content . $this->bsa_debug_comment("the_content", "page_builder_active");
        }

        $path = $this->get_current_url_path();
        $this->log_debug(sprintf("Fetching appendix for path: %s", $path));
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data["html"] === null) {
            $api_status = ($data && isset($data["status"])) ? $data["status"] : "no_response";
            $api_reason = ($data && isset($data["reason"])) ? $data["reason"] : "unknown";
            $this->log_debug(sprintf(
                "No appendix HTML for path '%s' (api_status=%s, reason=%s)",
                $path,
                $api_status,
                $api_reason
            ));
            return $content . $this->bsa_debug_comment("the_content", "fetch_null", [
                "path" => $path,
                "api_status" => $api_status,
                "api_reason" => $api_reason,
                "data_present" => $data ? true : false,
            ]);
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botdot_wp_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            $content .= $html;
            $content .= $this->bsa_debug_comment("the_content", "injected", ["bytes" => strlen($html)]);
            $this->log_debug(sprintf("Appendix injected via content filter (%d bytes)", strlen($html)));

            // --- Analytics: increment impression counters ---
            try {
                $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
                $bot_class = BotDot_WP_Bot_Classifier::classify($ua);
                $post_id = get_the_ID();
                if ($post_id) {
                    BotDot_WP_Analytics_Flusher::increment_post($post_id, $bot_class);
                }
            } catch (Throwable $e) {
                // Analytics must NEVER break the render path.
                BotDot_WP_Logger::log_error('Analytics increment failed: ' . $e->getMessage());
            }
        }

        return $content;
    }

    /**
     * Detect if a page builder is actively rendering the current page.
     *
     * Page builders like Elementor, Divi, WPBakery, Beaver Builder, and Bricks
     * call the_content filter but discard its output, so injecting there is futile.
     *
     * @since    1.3.0
     * @access   private
     * @return   bool    True if a page builder is rendering.
     */
    private function is_page_builder_active()
    {
        // Elementor
        if (defined("ELEMENTOR_VERSION")) {
            $post_id = get_the_ID();
            if ($post_id && get_post_meta($post_id, "_elementor_edit_mode", true) === "builder") {
                return true;
            }
        }

        // Divi Builder
        if (defined("ET_BUILDER_VERSION")) {
            $post_id = get_the_ID();
            if ($post_id && get_post_meta($post_id, "_et_pb_use_builder", true) === "on") {
                return true;
            }
        }

        // WPBakery (Visual Composer)
        if (defined("WPB_VC_VERSION")) {
            return true;
        }

        // Beaver Builder
        if (class_exists("FLBuilderModel")) {
            $post_id = get_the_ID();
            if ($post_id && get_post_meta($post_id, "_fl_builder_enabled", true)) {
                return true;
            }
        }

        // Bricks Builder
        if (defined("BRICKS_VERSION")) {
            $post_id = get_the_ID();
            if ($post_id && get_post_meta($post_id, "_bricks_editor_mode", true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inject appendix via wp_footer as fallback or primary method.
     *
     * Hook: wp_footer (priority 5)
     *
     * Fires as a universal fallback: if the_content filter ran but a page builder
     * (Elementor, Divi, WPBakery, etc.) discarded the output, $appendix_injected
     * will still be false and we inject here instead. Also fires as primary method
     * when injection_position is 'above_footer'.
     *
     * @since    1.0.0
     */
    public function inject_above_footer()
    {
        $position = $this->resolve_injection_position();

        // Run as primary for above_footer, or as fallback for bottom (page builder case)
        if ($position === "above_footer" || $position === "bottom") {
            $this->inject_footer_position("above_footer");
        } else {
            echo $this->bsa_debug_comment("above_footer", "position_skip", ["position" => $position]);
        }
    }

    /**
     * Inject appendix at the very bottom of the page.
     *
     * Hook: wp_footer (priority 99)
     *
     * @since    1.4.0
     */
    public function inject_below_footer()
    {
        $position = $this->resolve_injection_position();

        if ($position === "below_footer") {
            $this->inject_footer_position("below_footer");
        } else {
            echo $this->bsa_debug_comment("below_footer", "position_skip", ["position" => $position]);
        }
    }

    /**
     * Resolve the effective injection position, normalizing unknown / empty
     * values to "bottom" so the appendix never silently disappears when the
     * stored option is missing or corrupt.
     *
     * Recognized positions: bottom, above_footer, below_footer, shortcode.
     * Anything else falls back to "bottom".
     */
    private function resolve_injection_position()
    {
        $stored = BotDot_WP_Options::get("injection_position", "bottom");
        $allowed = ["bottom", "above_footer", "below_footer", "shortcode"];
        if (!is_string($stored) || !in_array($stored, $allowed, true)) {
            $this->log_debug(sprintf(
                "Unknown injection_position '%s', falling back to 'bottom'",
                is_string($stored) ? $stored : gettype($stored)
            ));
            return "bottom";
        }
        return $stored;
    }

    /**
     * Whether the current request asked for diagnostic comments
     * (?bsa-debug=1). Used to surface why injection skipped without
     * leaking diagnostics to normal traffic.
     */
    private function bsa_debug_active()
    {
        return isset($_GET["bsa-debug"]) && (string) $_GET["bsa-debug"] === "1";
    }

    /**
     * Build an HTML comment describing a single decision point in the
     * injection pipeline. Returns "" when debug is not active.
     *
     * @param string $where    Hook name: the_content / above_footer / below_footer.
     * @param string $reason   Short tag identifying which branch we took.
     * @param array  $extra    Optional structured payload to aid diagnosis.
     */
    private function bsa_debug_comment($where, $reason, array $extra = [])
    {
        if (!$this->bsa_debug_active()) {
            return "";
        }
        $payload = array_merge(["where" => $where, "reason" => $reason], $extra);
        $json = wp_json_encode($payload);
        if ($json === false) {
            $json = '{"where":"' . esc_html($where) . '","reason":"json_encode_failed"}';
        }
        // Strip "--" so the payload can never close the comment early.
        $safe = str_replace("--", "-_-", $json);
        return "\n<!-- bsa-appendix:" . $safe . " -->\n";
    }

    /**
     * Snapshot of the page-state booleans that should_inject_common
     * checks, so a "should_not_inject" debug entry tells us *which*
     * predicate was the blocker.
     */
    private function bsa_debug_state()
    {
        return [
            "is_admin" => is_admin(),
            "is_404" => is_404(),
            "is_search" => is_search(),
            "is_front_page" => is_front_page(),
            "is_home" => is_home(),
            "is_singular" => is_singular(),
            "post_type" => get_post_type(),
            "appendix_enabled" => (bool) BotDot_WP_Options::get("appendix_enabled"),
            "inject_on_post_types" => BotDot_WP_Options::get("inject_on_post_types", ["post", "page"]),
            "injection_position" => $this->resolve_injection_position(),
        ];
    }

    /**
     * Shared logic for footer-based injection.
     *
     * @since    1.4.0
     * @param    string    $target_position    The position this method handles.
     */
    private function inject_footer_position($target_position)
    {
        if ($this->appendix_injected) {
            echo $this->bsa_debug_comment($target_position, "already_injected");
            return;
        }

        if (!$this->should_inject_appendix()) {
            echo $this->bsa_debug_comment($target_position, "should_not_inject", $this->bsa_debug_state());
            return;
        }

        // Check for manual placement
        global $post;
        if ($post && $this->has_manual_placement($post->post_content)) {
            echo $this->bsa_debug_comment($target_position, "manual_placement");
            return;
        }

        // Don't add on feeds
        if (is_feed()) {
            return;
        }

        $path = $this->get_current_url_path();
        $this->log_debug(sprintf("Fetching appendix for footer injection (%s), path: %s", $target_position, $path));
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data["html"] === null) {
            $api_status = ($data && isset($data["status"])) ? $data["status"] : "no_response";
            $api_reason = ($data && isset($data["reason"])) ? $data["reason"] : "unknown";
            $this->log_debug(sprintf(
                "No appendix HTML for path '%s' (api_status=%s, reason=%s)",
                $path,
                $api_status,
                $api_reason
            ));
            echo $this->bsa_debug_comment($target_position, "fetch_null", [
                "path" => $path,
                "api_status" => $api_status,
                "api_reason" => $api_reason,
                "data_present" => $data ? true : false,
            ]);
            return;
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botdot_wp_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            echo $html;
            echo $this->bsa_debug_comment($target_position, "injected", ["bytes" => strlen($html)]);
            $this->log_debug(sprintf("Appendix injected via %s (%d bytes)", $target_position, strlen($html)));
        } else {
            echo $this->bsa_debug_comment($target_position, "html_empty_after_sanitize");
        }
    }

    /**
     * Render shortcode
     *
     * @since    1.0.0
     * @param    array     $atts    Shortcode attributes.
     * @return   string             Rendered appendix HTML.
     */
    public function render_shortcode($atts)
    {
        // Only mark the appendix as "handled" when we're genuinely inside the
        // the_content filter chain. Out-of-band callers (SEO plugins running
        // do_shortcode for meta-description scraping, REST excerpt generators,
        // homepage-articles blocks pre-rendering child posts, etc.) would
        // otherwise flip our flags and silently disable the auto-injection
        // path on the real page render.
        $in_the_content = function_exists("doing_filter") && doing_filter("the_content");

        if ($in_the_content) {
            $this->shortcode_used = true;
        }

        if (!$this->should_inject_appendix()) {
            return "";
        }

        $path = $this->get_current_url_path();
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data["html"] === null) {
            return "";
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botdot_wp_appendix_html", $html);

        if ($in_the_content) {
            $this->appendix_injected = true;
        }

        return $html;
    }

    /**
     * Check if JSON-LD injection should happen on the current page
     *
     * @since    1.2.0
     * @return   bool    True if should inject, false otherwise.
     */
    private function should_inject_jsonld()
    {
        if (!BotDot_WP_Options::get("jsonld_enabled")) {
            return false;
        }

        return $this->should_inject_common();
    }

    /**
     * Check if appendix injection should happen on the current page
     *
     * @since    1.2.0
     * @return   bool    True if should inject, false otherwise.
     */
    private function should_inject_appendix()
    {
        if (!BotDot_WP_Options::get("appendix_enabled")) {
            return false;
        }

        return $this->should_inject_common();
    }

    /**
     * Common injection checks shared by both JSON-LD and appendix
     *
     * @since    1.2.0
     * @access   private
     * @return   bool    True if should inject, false otherwise.
     */
    private function should_inject_common()
    {
        // Don't inject in admin
        if (is_admin()) {
            $this->log_debug("Injection blocked: is_admin()=true");
            return false;
        }

        // Don't inject on 404 or search
        if (is_404()) {
            $this->log_debug("Injection blocked: is_404()=true");
            return false;
        }
        if (is_search()) {
            $this->log_debug("Injection blocked: is_search()=true");
            return false;
        }

        // Check valid page type
        if (!$this->is_valid_page_type()) {
            $this->log_debug(sprintf(
                "Injection blocked: invalid page type (is_front_page=%s, is_home=%s, is_singular=%s)",
                is_front_page() ? "true" : "false",
                is_home() ? "true" : "false",
                is_singular() ? "true" : "false"
            ));
            return false;
        }

        // Check post type
        $post_type = get_post_type();
        if ($post_type) {
            $allowed_types = BotDot_WP_Options::get("inject_on_post_types", ["post", "page"]);
            if (!in_array($post_type, $allowed_types)) {
                // Allow front page even if post type doesn't match
                if (!is_front_page()) {
                    $this->log_debug(sprintf(
                        "Injection blocked: post_type '%s' not in allowed types [%s]",
                        $post_type,
                        implode(", ", $allowed_types)
                    ));
                    return false;
                }
            }
        }

        // Apply filter
        return apply_filters("botdot_wp_should_inject", true);
    }

    /**
     * Check if current page is a valid page type for injection
     *
     * @since    1.0.0
     * @access   private
     * @return   bool
     */
    private function is_valid_page_type()
    {
        if (is_front_page() || is_home() || is_singular()) {
            return true;
        }

        return false;
    }

    /**
     * Check if manual placement (block or shortcode) is used
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $content    The post content.
     * @return   bool
     */
    private function has_manual_placement($content)
    {
        if (function_exists("has_block") && has_block("botdot-wp/appendix", $content)) {
            return true;
        }

        if (has_shortcode($content, "botdot_appendix") || has_shortcode($content, "botspot_appendix")) {
            return true;
        }

        if ($this->shortcode_used) {
            return true;
        }

        return false;
    }

    /**
     * Get the current URL path relative to home
     *
     * @since    1.0.0
     * @access   private
     * @return   string    The URL path.
     */
    private function get_current_url_path()
    {
        global $wp;
        $current_url = home_url(add_query_arg([], $wp->request));
        $parsed = parse_url($current_url);
        $path = isset($parsed["path"]) ? $parsed["path"] : "/";

        // Remove home path if WordPress is in a subdirectory
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path && $home_path !== "/") {
            $path = str_replace($home_path, "", $path);
        }

        if (!empty($path) && $path[0] !== "/") {
            $path = "/" . $path;
        }

        if (empty($path)) {
            $path = "/";
        }

        // Apply filter
        $path = apply_filters("botdot_wp_url_path", $path);

        return $path;
    }

    /**
     * Sanitize external HTML using wp_kses with extended allowlist
     *
     * @since    1.0.1
     * @access   private
     * @param    string    $html    The HTML to sanitize.
     * @return   string             Sanitized HTML.
     */
    private function sanitize_html($html)
    {
        // Strip the injection config script tag (not for rendering)
        $html = preg_replace('/<script[^>]*id="locus-injection-config"[^>]*>.*?<\/script>/s', '', $html);

        $allowed = wp_kses_allowed_html("post");

        // Appendix-specific elements
        $allowed["section"] = ["id" => true, "class" => true, "style" => true, "role" => true, "aria-label" => true];
        $allowed["details"] = ["class" => true, "open" => true, "id" => true, "data-type" => true];
        $allowed["summary"] = ["class" => true, "id" => true];
        $allowed["dl"] = ["class" => true, "id" => true];
        $allowed["dt"] = ["class" => true, "id" => true];
        $allowed["dd"] = ["class" => true, "id" => true];
        $allowed["svg"] = ["width" => true, "height" => true, "viewbox" => true, "fill" => true, "xmlns" => true, "class" => true];
        $allowed["path"] = ["d" => true, "stroke" => true, "stroke-width" => true, "stroke-linecap" => true, "stroke-linejoin" => true, "fill" => true];
        $allowed["style"] = ["id" => true, "type" => true];

        // Allow style attribute on span/div (for CSS custom property wrappers)
        $allowed["span"]["style"] = true;
        $allowed["div"]["style"] = true;

        return wp_kses($html, $allowed);
    }

    /**
     * Annotate JSON-LD nodes with BotSpot sdPublisher attribution.
     *
     * Adds sdPublisher property to each node and renames #locus- to #botspot-
     * in @id values for public-facing output.
     *
     * @since    2.3.0
     * @access   private
     * @param    array    $nodes    Array of JSON-LD nodes.
     * @return   array              Annotated nodes.
     */
    private function annotate_nodes_with_botspot($nodes)
    {
        $botspot_ref = ["@id" => "https://bot.spot/#botspot"];

        return array_map(function ($node) use ($botspot_ref) {
            if (!is_array($node)) {
                return $node;
            }

            // Rename #locus- to #botspot- in @id for public-facing output
            if (isset($node["@id"]) && strpos($node["@id"], "#locus-") !== false) {
                $node["@id"] = str_replace("#locus-", "#botspot-", $node["@id"]);
            }

            // Add sdPublisher if not already set
            if (!isset($node["sdPublisher"])) {
                $node["sdPublisher"] = $botspot_ref;
            }

            return $node;
        }, $nodes);
    }

    /**
     * Get the BotSpot Organization node for schema attribution.
     *
     * @since    2.3.0
     * @access   private
     * @return   array    BotSpot Organization JSON-LD node.
     */
    private function get_botspot_org_node()
    {
        return [
            "@type" => "Organization",
            "@id" => "https://bot.spot/#botspot",
            "name" => "BotSpot",
            "url" => "https://bot.spot",
            "description" => "AI-powered content enrichment and structured data.",
        ];
    }

    /**
     * Log debug message
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    private function log_debug($message)
    {
        if (BotDot_WP_Options::get("debug_mode")) {
            BotDot_WP_Logger::log_debug("[ContentInjector] " . $message);
        }
    }
}
