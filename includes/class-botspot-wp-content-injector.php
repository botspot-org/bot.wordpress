<?php
/**
 * Unified content injector for the BotSpot WP plugin
 *
 * Injects both JSON-LD and appendix HTML from a single fetch.
 *
 * @link       https://bot.spot
 * @since      1.0.0
 *
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/includes
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * Unified content injector for JSON-LD and appendix HTML.
 *
 * Replaces the old BotSpot_WP_Injector and appendix injection logic
 * from BotSpot_WP_Public with a single class that handles both.
 *
 * @since      1.0.0
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/includes
 * @author     BotSpot Team
 */
class BotSpot_WP_Content_Injector
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
     * Trace of every render_shortcode invocation in the current request.
     * Surfaced via the ?bsa-debug=1 diagnostic so we can see who's calling
     * the shortcode handler out-of-band.
     */
    private $bsa_render_calls = [];

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

        $appendix_enabled = BotSpot_WP_Options::get("appendix_enabled");
        if (!$appendix_enabled) {
            $data = BotSpot_WP_Content_Fetcher::fetch_jsonld($path);
        } else {
            $data = BotSpot_WP_Content_Fetcher::fetch($path);
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
        $jsonld = apply_filters("botspot_wp_appendix_jsonld", $jsonld);

        if (empty($jsonld)) {
            $this->locus_jsonld_cache = null;
            return null;
        }

        $this->locus_jsonld_cache = $jsonld;
        return $jsonld;
    }

    /**
     * Resolve delivery_mode for the current page from the /render response.
     *
     * Returns 'full' when the field is absent (backward compat with older core).
     *
     * @since    2.8.0
     * @access   private
     * @return   string    One of: 'disabled', 'jsonld_only', 'full'.
     */
    private function get_delivery_mode()
    {
        $path = $this->get_current_url_path();
        $data = BotSpot_WP_Content_Fetcher::fetch($path);
        if (!$data || empty($data["delivery_mode"])) {
            return "full";
        }
        $mode = $data["delivery_mode"];
        if (!in_array($mode, ["disabled", "jsonld_only", "full"], true)) {
            return "full";
        }
        return $mode;
    }

    /**
     * Emit only the JSON-LD <script> block from a pre-fetched render response.
     *
     * @since    2.8.0
     * @access   private
     * @param    mixed    $jsonld_raw    Raw JSON-LD from the render response.
     */
    private function emit_jsonld_from_response($jsonld_raw)
    {
        if ($jsonld_raw === null) {
            return;
        }

        $jsonld = $jsonld_raw;
        if (is_string($jsonld)) {
            $decoded = json_decode($jsonld, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return;
            }
            $jsonld = $decoded;
        }

        $jsonld = apply_filters("botspot_wp_appendix_jsonld", $jsonld);
        if (empty($jsonld)) {
            return;
        }

        $json_string = $this->encode_jsonld($jsonld);
        if ($json_string === false) {
            return;
        }

        echo "\n<!-- BotSpot JSON-LD -->\n";
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- encode_jsonld() emits JSON encoded with HEX flags to prevent script breakout.
        echo '<script type="application/ld+json">' . $json_string . "</script>";
        echo "\n<!-- /BotSpot JSON-LD -->\n";
    }

    /**
     * Encode JSON-LD with script-breakout-safe flags.
     *
     * @since 3.0.8
     * @param mixed $jsonld Decoded JSON-LD value.
     * @return string|false
     */
    private function encode_jsonld($jsonld)
    {
        return wp_json_encode(
            $jsonld,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
        );
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
        $conflict_mode = BotSpot_WP_Options::get("jsonld_conflict_mode", "merge");
        if ($conflict_mode === "off") {
            $this->log_debug("JSON-LD conflict mode is 'off', skipping injection");
            return;
        }

        // When appendix is enabled, the render response carries delivery_mode.
        // Honor it here so disabled suppresses JSON-LD too.
        if (BotSpot_WP_Options::get("appendix_enabled")) {
            $mode = $this->get_delivery_mode();
            if ($mode === "disabled") {
                $this->log_debug("delivery_mode=disabled, skipping JSON-LD injection");
                return;
            }
            // For jsonld_only and full, emit JSON-LD via the shared render response.
            $path = $this->get_current_url_path();
            $data = BotSpot_WP_Content_Fetcher::fetch($path);
            $jsonld_raw = ($data && isset($data["jsonld"])) ? $data["jsonld"] : null;
            $this->emit_jsonld_from_response($jsonld_raw);
            $this->log_debug(sprintf("JSON-LD injected via wp_head (delivery_mode=%s)", $mode));
            return;
        }

        $decoded = $this->get_locus_jsonld();
        if ($decoded === null) {
            return;
        }

        $json_string = $this->encode_jsonld($decoded);
        if ($json_string === false) {
            return;
        }

        echo "\n<!-- BotSpot JSON-LD -->\n";
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- encode_jsonld() emits JSON encoded with HEX flags to prevent script breakout.
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
        // Skip when this the_content invocation is for a post other than the
        // URL's queried object. Themes like Newspack call apply_filters(
        // 'the_content', $child_post->post_content) inside a homepage block to
        // pre-render each child article — those calls would otherwise inject
        // the appendix into the child output (whose return value the theme
        // discards), set $appendix_injected, and prevent the real page render
        // from injecting at all.
        $queried_id = (int) get_queried_object_id();
        $current_id = (int) get_the_ID();
        if ($queried_id > 0 && $current_id > 0 && $queried_id !== $current_id) {
            return $content . $this->bsa_debug_comment("the_content", "skip_not_queried", [
                "queried_id" => $queried_id,
                "current_id" => $current_id,
            ]);
        }

        // Skip when the_content is invoked outside the WP main loop. SEO
        // plugins (Yoast, RankMath) and similar tooling call apply_filters(
        // 'the_content', $post->post_content) during wp_head to derive meta
        // descriptions and Schema graphs — those invocations don't go through
        // setup_postdata/the_post and thus aren't in_the_loop(). Without this
        // gate, the pre-scrape injects, sets $appendix_injected, and the
        // actual template render hits "already_injected" and skips.
        if (function_exists("in_the_loop") && !in_the_loop()) {
            return $content . $this->bsa_debug_comment("the_content", "skip_not_in_loop", [
                "queried_id" => $queried_id,
                "current_id" => $current_id,
                "current_filter" => function_exists("current_filter") ? current_filter() : null,
                "did_wp_head" => function_exists("did_action") ? (int) did_action("wp_head") : null,
                "did_wp_footer" => function_exists("did_action") ? (int) did_action("wp_footer") : null,
            ]);
        }

        // Don't add if already injected
        if ($this->appendix_injected) {
            return $content . $this->bsa_debug_comment("the_content", "already_injected", $this->bsa_debug_state());
        }

        if (!$this->should_inject_appendix()) {
            return $content . $this->bsa_debug_comment("the_content", "should_not_inject", $this->bsa_debug_state());
        }

        $position = $this->resolve_injection_position();

        // Skip the_content path for manual placement. bottom_of_content /
        // above_footer / bottom_of_page all render here; the JS placement script
        // (inject_placement_script) repositions above_footer/bottom_of_page at runtime.
        if ($position === "manual") {
            return $content . $this->bsa_debug_comment("the_content", "position_manual", ["position" => $position]);
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
        $data = BotSpot_WP_Content_Fetcher::fetch($path);

        // Dispatch on delivery_mode before consuming html.
        $delivery_mode = ($data && isset($data["delivery_mode"]) && $data["delivery_mode"]) ? $data["delivery_mode"] : "full";
        if (!in_array($delivery_mode, ["disabled", "jsonld_only", "full"], true)) {
            $delivery_mode = "full";
        }
        if ($delivery_mode === "disabled" || $delivery_mode === "jsonld_only") {
            $this->log_debug(sprintf("delivery_mode=%s, skipping appendix HTML injection", $delivery_mode));
            return $content . $this->bsa_debug_comment("the_content", "delivery_mode_skip", ["delivery_mode" => $delivery_mode]);
        }

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
        $html = apply_filters("botspot_wp_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            $wrapped = sprintf(
                '<div data-bsa-appendix data-bsa-position="%s">%s</div>',
                esc_attr($position),
                $html
            );
            $content .= $wrapped;
            $content .= $this->bsa_debug_comment("the_content", "injected", [
                "bytes" => strlen($html),
                "position" => $position,
            ]);
            $this->log_debug(sprintf(
                "Appendix injected via content filter (%d bytes, position=%s)",
                strlen($html),
                $position
            ));

            // --- Analytics: increment impression counters ---
            try {
                $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
                $bot_class = BotSpot_WP_Bot_Classifier::classify($ua);
                $post_id = get_the_ID();
                if ($post_id) {
                    BotSpot_WP_Analytics_Flusher::increment_post($post_id, $bot_class);
                }
            } catch (Throwable $e) {
                // Analytics must NEVER break the render path.
                BotSpot_WP_Logger::log_error('Analytics increment failed: ' . $e->getMessage());
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
     * Output the client-side placement script that relocates the appendix
     * marker (<div data-bsa-appendix>) to be the previous/next sibling of the
     * real <footer> element when position is above_footer / bottom_of_page.
     *
     * For position=bottom_of_content or manual the script is a no-op.
     *
     * Hook: wp_footer (priority 1) — runs before theme scripts so the <script>
     * tag is in the DOM early, but the placement runs on DOMContentLoaded so
     * the body is fully parsed.
     *
     * @since 2.7.0
     */
    public function inject_placement_script()
    {
        // Skip emitting on pages where injection is gated off — saves bytes.
        if (!$this->should_inject_appendix()) {
            return;
        }
        $position = $this->resolve_injection_position();
        if ($position === "manual") {
            // Manual placement; no JS reposition needed.
            return;
        }
        ?>
<script>
(function () {
    var SELECTORS = [
        "[data-botspot-footer]",
        "footer",
        "[role=contentinfo]",
        ".site-footer",
        "#colophon",
        ".footer",
        ".page-footer",
        "#footer",
        "#site-footer",
        ".elementor-location-footer",
        ".fl-builder-footer",
        "#main-footer",
        ".wp-block-template-part[data-area=footer]"
    ];
    function findFooter() {
        for (var i = 0; i < SELECTORS.length; i++) {
            var el = document.querySelector(SELECTORS[i]);
            if (el) return el;
        }
        return null;
    }
    function place() {
        var node = document.querySelector("[data-bsa-appendix]");
        if (!node) return;
        var pos = node.getAttribute("data-bsa-position");
        if (pos !== "above_footer" && pos !== "bottom_of_page") return;
        var footer = findFooter();
        if (!footer) {
            if (window.console && console.warn) {
                console.warn("[BotSpot] footer not detected, appendix left in-content");
            }
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get("bsa-debug") === "1") {
                var comment = document.createComment(" bsa-footer-detection: failed, fallback to bottom_of_content ");
                node.parentNode.insertBefore(comment, node);
            }
            return;
        }
        if (pos === "above_footer") {
            footer.parentNode.insertBefore(node, footer);
        } else {
            footer.parentNode.insertBefore(node, footer.nextSibling);
        }
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", place);
    } else {
        place();
    }
})();
</script>
        <?php
    }

    /**
     * Page-builder fallback: when the_content filter is bypassed by a page
     * builder (Elementor, Divi, WPBakery, Beaver Builder, Bricks), render the
     * wrapped appendix at wp_footer so the JS placement script can still
     * relocate it. For non-page-builder pages the_content already handled the
     * render, so this method is a no-op.
     *
     * Hook: wp_footer (priority 5)
     *
     * @since    2.7.0
     */
    public function inject_appendix_footer_fallback()
    {
        // Already injected by the_content path, nothing to do.
        if ($this->appendix_injected) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "already_injected");
            return;
        }

        // Only fall back when a page builder discarded the_content output.
        // Otherwise the_content's gate (in_the_loop, queried-object) already
        // chose not to inject and we should respect that.
        if (!$this->is_page_builder_active()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "no_page_builder_skip");
            return;
        }

        $position = $this->resolve_injection_position();
        if ($position === "manual") {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "position_manual");
            return;
        }

        $this->inject_footer_position($position);
    }

    /**
     * Resolve the effective injection position, normalizing unknown / empty
     * values to "bottom_of_content" so the appendix never silently disappears when the
     * stored option is missing or corrupt.
     *
     * Recognized positions: bottom_of_content, above_footer, bottom_of_page, manual.
     * Anything else falls back to "bottom_of_content".
     */
    private function resolve_injection_position()
    {
        $stored = BotSpot_WP_Options::get("injection_position", "bottom_of_content");
        $allowed = ["bottom_of_content", "above_footer", "bottom_of_page", "manual"];
        if (!is_string($stored) || !in_array($stored, $allowed, true)) {
            $this->log_debug(sprintf(
                "Unknown injection_position '%s', falling back to 'bottom_of_content'",
                is_string($stored) ? $stored : gettype($stored)
            ));
            return "bottom_of_content";
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only diagnostic flag; it does not mutate state or reveal secrets.
        $debug = isset($_GET["bsa-debug"]) ? sanitize_text_field(wp_unslash($_GET["bsa-debug"])) : "";
        return $debug === "1";
    }

    /**
     * Build an HTML comment describing a single decision point in the
     * injection pipeline. Returns "" when debug is not active.
     *
     * @param string $where    Hook name: the_content / above_footer / bottom_of_page.
     * @param string $reason   Short tag identifying which branch we took.
     * @param array  $extra    Optional structured payload to aid diagnosis.
     */
    private function bsa_debug_comment($where, $reason, array $extra = [])
    {
        if (!$this->bsa_debug_active()) {
            return "";
        }
        $payload = array_merge(
            [
                "where" => sanitize_key($where),
                "reason" => sanitize_key($reason),
            ],
            $this->sanitize_debug_payload($extra)
        );
        $json = wp_json_encode($payload);
        if ($json === false) {
            $json = '{"where":"' . esc_html(sanitize_key($where)) . '","reason":"json_encode_failed"}';
        }
        // Strip "--" so the payload can never close the comment early.
        $safe = str_replace("--", "-_-", $json);
        return "\n<!-- bsa-appendix:" . $safe . " -->\n";
    }

    /**
     * Sanitize diagnostic values before encoding them into an HTML comment.
     *
     * @param mixed $value Debug payload value.
     * @return mixed Sanitized debug payload value.
     */
    private function sanitize_debug_payload($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[sanitize_key($key)] = $this->sanitize_debug_payload($item);
            }
            return $clean;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return sanitize_text_field((string) $value);
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
            "queried_id" => (int) get_queried_object_id(),
            "current_id" => (int) get_the_ID(),
            "appendix_enabled" => (bool) BotSpot_WP_Options::get("appendix_enabled"),
            "inject_on_post_types" => BotSpot_WP_Options::get("inject_on_post_types", ["post", "page"]),
            "injection_position" => $this->resolve_injection_position(),
            "appendix_injected_flag" => $this->appendix_injected,
            "shortcode_used_flag" => $this->shortcode_used,
            "render_calls" => $this->bsa_render_calls,
        ];
    }

    /**
     * Shared logic for footer-based injection.
     *
     * Output is wrapped in <div data-bsa-appendix data-bsa-position="X"> so
     * the JS placement script (inject_placement_script) can relocate it for
     * above_footer / bottom_of_page positions. For position=bottom_of_content
     * on a page builder, the marker keeps its starting position.
     *
     * @since    1.4.0
     * @param    string    $position    The configured injection_position
     *                                  (bottom_of_content / above_footer / bottom_of_page).
     */
    private function inject_footer_position($position)
    {
        if ($this->appendix_injected) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "already_injected", $this->bsa_debug_state());
            return;
        }

        if (!$this->should_inject_appendix()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "should_not_inject", $this->bsa_debug_state());
            return;
        }

        // Check for manual placement
        global $post;
        if ($post && $this->has_manual_placement($post->post_content)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "manual_placement");
            return;
        }

        // Don't add on feeds
        if (is_feed()) {
            return;
        }

        $path = $this->get_current_url_path();
        $this->log_debug(sprintf("Fetching appendix for footer injection (%s), path: %s", $position, $path));
        $data = BotSpot_WP_Content_Fetcher::fetch($path);

        $delivery_mode = ($data && isset($data["delivery_mode"]) && $data["delivery_mode"]) ? $data["delivery_mode"] : "full";
        if (!in_array($delivery_mode, ["disabled", "jsonld_only", "full"], true)) {
            $delivery_mode = "full";
        }
        if ($delivery_mode === "disabled" || $delivery_mode === "jsonld_only") {
            $this->log_debug(sprintf("delivery_mode=%s, skipping footer appendix HTML injection", $delivery_mode));
            $debug_context = ["delivery_mode" => $delivery_mode];
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "delivery_mode_skip", $debug_context);
            return;
        }

        if (!$data || $data["html"] === null) {
            $api_status = ($data && isset($data["status"])) ? $data["status"] : "no_response";
            $api_reason = ($data && isset($data["reason"])) ? $data["reason"] : "unknown";
            $this->log_debug(sprintf(
                "No appendix HTML for path '%s' (api_status=%s, reason=%s)",
                $path,
                $api_status,
                $api_reason
            ));
            $debug_context = [
                "path" => $path,
                "api_status" => $api_status,
                "api_reason" => $api_reason,
                "data_present" => $data ? true : false,
            ];
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "fetch_null", $debug_context);
            return;
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botspot_wp_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            $appendix_markup = sprintf(
                '<div data-bsa-appendix data-bsa-position="%s">%s</div>',
                esc_attr($position),
                $html
            );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $appendix_markup contains appendix HTML sanitized by sanitize_html() before trusted site filters.
            echo $appendix_markup;
            $debug_context = [
                "bytes" => strlen($html),
                "position" => $position,
            ];
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "injected_fallback", $debug_context);
            $this->log_debug(sprintf("Appendix injected via wp_footer fallback (%d bytes, position=%s)", strlen($html), $position));
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bsa_debug_comment() strips comment terminators and only returns inert HTML comments.
            echo $this->bsa_debug_comment("wp_footer", "html_empty_after_sanitize");
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
        $queried_id = (int) get_queried_object_id();
        $current_id = (int) get_the_ID();

        // Capture context for diagnostic output. Helps identify which caller
        // (Yoast pre-scrape, Newspack child render, real page render, etc.)
        // triggered this shortcode invocation.
        $this->bsa_render_calls[] = [
            "queried_id" => $queried_id,
            "current_id" => $current_id,
            "current_filter" => function_exists("current_filter") ? current_filter() : null,
            "doing_the_content" => function_exists("doing_filter") ? doing_filter("the_content") : null,
            "in_the_loop" => function_exists("in_the_loop") ? in_the_loop() : null,
        ];

        // Don't mutate global flags from render_shortcode. Auto-injection
        // paths (the_content priority 20 and wp_footer) instead defer to
        // has_manual_placement, which inspects the queried post's raw
        // post_content for the shortcode/block — so out-of-band invocations
        // (Yoast/SEO scraping, themes pre-rendering child articles, etc.)
        // can no longer poison the real render.

        if (!$this->should_inject_appendix()) {
            return "";
        }

        $path = $this->get_current_url_path();
        $data = BotSpot_WP_Content_Fetcher::fetch($path);

        $delivery_mode = ($data && isset($data["delivery_mode"]) && $data["delivery_mode"]) ? $data["delivery_mode"] : "full";
        if (!in_array($delivery_mode, ["disabled", "jsonld_only", "full"], true)) {
            $delivery_mode = "full";
        }
        if ($delivery_mode === "disabled" || $delivery_mode === "jsonld_only") {
            $this->log_debug(sprintf("delivery_mode=%s, shortcode emits nothing", $delivery_mode));
            return "";
        }

        if (!$data || $data["html"] === null) {
            return "";
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botspot_wp_appendix_html", $html);

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
        if (!BotSpot_WP_Options::get("jsonld_enabled")) {
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
        if (!BotSpot_WP_Options::get("appendix_enabled")) {
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
            $allowed_types = BotSpot_WP_Options::get("inject_on_post_types", ["post", "page"]);
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
        return apply_filters("botspot_wp_should_inject", true);
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
        // Inspect the queried post's raw, unexpanded content too. By the time
        // the_content priority 20 runs, do_shortcode (priority 11) has already
        // rewritten [botdot_appendix] into HTML, so has_shortcode($content,...)
        // can return false even though the user did manually place the
        // shortcode. The queried post's post_content always holds the raw
        // form, so it's the authoritative source.
        $queried_id = (int) get_queried_object_id();
        if ($queried_id > 0) {
            $post_obj = get_post($queried_id);
            if ($post_obj && isset($post_obj->post_content)) {
                $raw = (string) $post_obj->post_content;
                if (function_exists("has_block") && (has_block("botspot-wp/appendix", $raw) || has_block("botdot-wp/appendix", $raw))) {
                    return true;
                }
                if (has_shortcode($raw, "botdot_appendix") || has_shortcode($raw, "botspot_appendix")) {
                    return true;
                }
            }
        }

        if (function_exists("has_block") && (has_block("botspot-wp/appendix", $content) || has_block("botdot-wp/appendix", $content))) {
            return true;
        }

        if (has_shortcode($content, "botdot_appendix") || has_shortcode($content, "botspot_appendix")) {
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
        $parsed = wp_parse_url($current_url);
        $path = isset($parsed["path"]) ? $parsed["path"] : "/";

        // Remove home path if WordPress is in a subdirectory
        $home_path = wp_parse_url(home_url(), PHP_URL_PATH);
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
        $path = apply_filters("botspot_wp_url_path", $path);

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
        $allowed["section"] = ["id" => true, "class" => true, "role" => true, "aria-label" => true];
        $allowed["details"] = ["class" => true, "open" => true, "id" => true, "data-type" => true];
        $allowed["summary"] = ["class" => true, "id" => true];
        $allowed["dl"] = ["class" => true, "id" => true];
        $allowed["dt"] = ["class" => true, "id" => true];
        $allowed["dd"] = ["class" => true, "id" => true];
        $allowed["svg"] = ["width" => true, "height" => true, "viewbox" => true, "fill" => true, "xmlns" => true, "class" => true];
        $allowed["path"] = ["d" => true, "stroke" => true, "stroke-width" => true, "stroke-linecap" => true, "stroke-linejoin" => true, "fill" => true];
        // Remote appendix HTML should rely on the plugin's bundled stylesheet,
        // not service-supplied inline CSS or style tags.
        unset($allowed["style"]);
        foreach ($allowed as $tag => $attrs) {
            if (is_array($attrs) && isset($allowed[$tag]["style"])) {
                unset($allowed[$tag]["style"]);
            }
        }

        return wp_kses($html, $allowed);
    }

    /**
     * Log debug message
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    private function log_debug($message)
    {
        if (BotSpot_WP_Options::get("debug_mode")) {
            BotSpot_WP_Logger::log_debug("[ContentInjector] " . $message);
        }
    }
}
