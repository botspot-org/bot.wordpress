<?php
/**
 * Unified content injector for the BotSpot WP plugin
 *
 * Injects both JSON-LD and appendix HTML from a single fetch.
 *
 * @link       https://bot.spot
 * @since      1.0.0
 *
 * @package    Bspt
 * @subpackage Bspt/includes
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * Unified content injector for JSON-LD and appendix HTML.
 *
 * Replaces the old Bspt_Injector and appendix injection logic
 * from Bspt_Public with a single class that handles both.
 *
 * @since      1.0.0
 * @package    Bspt
 * @subpackage Bspt/includes
 * @author     BotSpot Team
 */
class Bspt_Content_Injector
{
    /**
     * json_encode flags used for every JSON-LD payload.
     *
     * The JSON_HEX_* flags escape HTML-significant characters as \uXXXX
     * sequences, so no string value can terminate the script element or
     * introduce markup. JSON parsers decode these transparently, so consumers
     * receive identical data.
     *
     * @since 3.5.14
     * @var   int
     */
    const JSONLD_ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

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

        $appendix_enabled = Bspt_Options::get("appendix_enabled");
        if (!$appendix_enabled) {
            $data = Bspt_Content_Fetcher::fetch_jsonld($path);
        } else {
            $data = Bspt_Content_Fetcher::fetch($path);
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
        $jsonld = apply_filters("bspt_appendix_jsonld", $jsonld);

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
        $data = Bspt_Content_Fetcher::fetch($path);
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

        $jsonld = apply_filters("bspt_appendix_jsonld", $jsonld);
        if (empty($jsonld)) {
            return;
        }

        // Bail before emitting the wrapper comments if the payload cannot be
        // encoded, so a failure leaves no empty script tag behind.
        if ($this->encode_jsonld($jsonld) === "") {
            return;
        }

        $this->print_jsonld($jsonld);
    }

    /**
     * JSON-encode a JSON-LD graph for safe inline output.
     *
     * Kept in sync with print_jsonld() via the shared JSONLD_ENCODE_FLAGS
     * constant. Used to detect an unencodable payload before any output is
     * emitted; print_jsonld() re-encodes inline so that the escaping is
     * visible at the point of output.
     *
     * @since    3.5.14
     * @access   private
     * @param    mixed     $jsonld    The JSON-LD structure to encode.
     * @return   string               Encoded JSON, or "" on failure.
     */
    private function encode_jsonld($jsonld)
    {
        $json_string = wp_json_encode($jsonld, self::JSONLD_ENCODE_FLAGS);

        if (!is_string($json_string)) {
            return "";
        }

        return $json_string;
    }

    /**
     * Emit a JSON-LD script element.
     *
     * The payload is encoded inline with the JSON_HEX_* flags so that every
     * HTML-significant character in a string value becomes a \uXXXX escape.
     * Nothing in the payload can therefore close the element or introduce
     * markup. esc_html() is deliberately not used: it would encode the
     * structural quotes and leave consumers with invalid JSON.
     *
     * @since    3.5.14
     * @access   private
     * @param    mixed    $jsonld    The JSON-LD structure to emit.
     */
    private function print_jsonld($jsonld)
    {
        echo "\n<!-- BotSpot JSON-LD -->\n";
        echo '<script type="application/ld+json">';
        echo wp_json_encode($jsonld, self::JSONLD_ENCODE_FLAGS);
        echo "</script>";
        echo "\n<!-- /BotSpot JSON-LD -->\n";
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
        $conflict_mode = Bspt_Options::get("jsonld_conflict_mode", "merge");
        if ($conflict_mode === "off") {
            $this->log_debug("JSON-LD conflict mode is 'off', skipping injection");
            return;
        }

        // When appendix is enabled, the render response carries delivery_mode.
        // Honor it here so disabled suppresses JSON-LD too.
        if (Bspt_Options::get("appendix_enabled")) {
            $mode = $this->get_delivery_mode();
            if ($mode === "disabled") {
                $this->log_debug("delivery_mode=disabled, skipping JSON-LD injection");
                return;
            }
            // For jsonld_only and full, emit JSON-LD via the shared render response.
            $path = $this->get_current_url_path();
            $data = Bspt_Content_Fetcher::fetch($path);
            $jsonld_raw = ($data && isset($data["jsonld"])) ? $data["jsonld"] : null;
            $this->emit_jsonld_from_response($jsonld_raw);
            $this->log_debug(sprintf("JSON-LD injected via wp_head (delivery_mode=%s)", $mode));
            return;
        }

        $decoded = $this->get_locus_jsonld();
        if ($decoded === null) {
            return;
        }

        if ($this->encode_jsonld($decoded) === "") {
            return;
        }

        $this->print_jsonld($decoded);

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

        // manual renders via shortcode; end_of_page renders at wp_footer.
        if ($position === "manual" || $position === "end_of_page") {
            return $content . $this->bsa_debug_comment("the_content", "position_deferred", ["position" => $position]);
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
        $data = Bspt_Content_Fetcher::fetch($path);

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

        $html = $this->strip_injection_config($data["html"]);

        // Apply filter before escaping so the escaping happens as late as
        // possible — filtered output is escaped too, not just the service HTML.
        $html = apply_filters("bspt_appendix_html", $html);

        // Escape late: single wp_kses pass at the point of output.
        $html = wp_kses($html, $this->allowed_appendix_html());

        if (!empty($html)) {
            $this->appendix_injected = true;
            $content .= $this->wrap_appendix($html, $position);
            $content .= $this->bsa_debug_comment("the_content", "injected", [
                "bytes" => strlen($html),
                "position" => $position,
            ]);
            $this->log_debug(sprintf(
                "Appendix injected via content filter (%d bytes, position=%s)",
                strlen($html),
                $position
            ));
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
     * Build the anchor-relocation script, or "" when no anchor applies.
     *
     * Only end_of_page with an explicit selector relocates. Every other mode
     * renders where it renders. This replaced a 13-selector footer search that
     * guessed wrong on themes which position their own components (BOT-348).
     *
     * @since 3.6.0
     * @return string    JavaScript body, or "" when nothing to do.
     */
    private function build_anchor_script()
    {
        if ($this->resolve_injection_position() !== "end_of_page") {
            return "";
        }

        $anchor = Bspt_Options::sanitize_option_value(
            "placement_anchor",
            Bspt_Options::get("placement_anchor")
        );
        if ($anchor === null) {
            return "";
        }

        // JSON_HEX_TAG: the value lands inside an inline <script>, where a "<"
        // in the selector would close the element. sanitize_text_field strips
        // tags upstream; this does not depend on that.
        $config = wp_json_encode($anchor, JSON_HEX_TAG);
        if ($config === false) {
            return "";
        }

        return '(function(){var cfg=' . $config . ';' .
            'var node=document.querySelector("[data-bsa-appendix]");if(!node)return;' .
            'var target=document.querySelector(cfg.selector);' .
            'if(!target){node.setAttribute("data-bsa-anchor-missed",cfg.selector);' .
            'if(window.console&&console.warn)console.warn("[BotSpot] placement anchor not found: "+cfg.selector);return;}' .
            'if(cfg.position==="after"){target.parentNode.insertBefore(node,target.nextSibling);}' .
            'else{target.parentNode.insertBefore(node,target);}})();';
    }

    /**
     * Output the anchor-relocation script.
     *
     * Hook: wp_footer (priority 20) — after inject_appendix_footer_fallback
     * has rendered the marker, so the node exists when the script runs.
     *
     * @since 2.7.0
     */
    public function inject_placement_script()
    {
        if (!$this->should_inject_appendix()) {
            return;
        }

        $script = $this->build_anchor_script();
        if ($script === "") {
            return;
        }

        wp_register_script("bspt-placement", false, [], BSPT_VERSION, true);
        wp_enqueue_script("bspt-placement");
        wp_add_inline_script("bspt-placement", $script);
    }

    /**
     * Render end_of_page here unconditionally, since the_content defers it.
     * Also the page-builder fallback for in_content: Elementor, Divi,
     * WPBakery, Beaver Builder, and Bricks all discard the_content output, so
     * those pages get their appendix here instead, or not at all.
     *
     * Hook: wp_footer (priority 5)
     *
     * @since    2.7.0
     */
    public function inject_appendix_footer_fallback()
    {
        // Already injected by the_content path, nothing to do.
        if ($this->appendix_injected) {
            $this->print_debug_comment("wp_footer", "already_injected");
            return;
        }

        $position = $this->resolve_injection_position();
        if ($position === "manual") {
            $this->print_debug_comment("wp_footer", "position_manual");
            return;
        }

        // end_of_page always renders here. Other positions reach wp_footer only
        // as the page-builder fallback, because a builder discarded the_content.
        if ($position !== "end_of_page" && !$this->is_page_builder_active()) {
            $this->print_debug_comment("wp_footer", "no_page_builder_skip");
            return;
        }

        $this->inject_footer_position($position);
    }

    /**
     * Resolve the effective injection position.
     *
     * Recognized positions: in_content, end_of_page, manual. Anything else,
     * including legacy stored values, migrates through migrate_placement_value.
     */
    private function resolve_injection_position()
    {
        $stored = Bspt_Options::get("injection_position", "in_content");
        return Bspt_Options::migrate_placement_value($stored);
    }

    /**
     * Whether the current request asked for diagnostic comments
     * (?bsa-debug=1). Used to surface why injection skipped without
     * leaking diagnostics to normal traffic.
     */
    private function bsa_debug_active()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostic flag check, no state change or form processing
        return isset($_GET["bsa-debug"]) && (string) $_GET["bsa-debug"] === "1";
    }

    /**
     * Build the diagnostic payload for a single decision point in the
     * injection pipeline. Returns "" when debug is not active.
     *
     * The returned value is the comment *payload* only, not the comment
     * delimiters, so callers escape it before output.
     *
     * @since 3.5.14
     * @param string $where    Hook name: the_content / wp_footer.
     * @param string $reason   Short tag identifying which branch we took.
     * @param array  $extra    Optional structured payload to aid diagnosis.
     * @return string          JSON payload, or "" when debug is inactive.
     */
    private function bsa_debug_payload($where, $reason, array $extra = [])
    {
        if (!$this->bsa_debug_active()) {
            return "";
        }
        $payload = array_merge(["where" => $where, "reason" => $reason], $extra);
        $json = wp_json_encode($payload);
        if ($json === false) {
            $json = '{"where":"' . $where . '","reason":"json_encode_failed"}';
        }
        // Strip "--" so the payload can never close the comment early.
        return str_replace("--", "-_-", $json);
    }

    /**
     * Build an HTML comment describing a single decision point in the
     * injection pipeline. Returns "" when debug is not active.
     *
     * Used by the the_content path, whose return value is the output; the
     * payload is escaped here because that is the point of output.
     *
     * @param string $where    Hook name: the_content / wp_footer.
     * @param string $reason   Short tag identifying which branch we took.
     * @param array  $extra    Optional structured payload to aid diagnosis.
     */
    private function bsa_debug_comment($where, $reason, array $extra = [])
    {
        $payload = $this->bsa_debug_payload($where, $reason, $extra);
        if ($payload === "") {
            return "";
        }
        return "\n<!-- bsa-appendix:" . esc_html($payload) . " -->\n";
    }

    /**
     * Echo the diagnostic comment for a decision point on an output hook.
     *
     * Exists so the payload is escaped inline at the point of output rather
     * than being built into a variable and echoed later.
     *
     * @since 3.5.14
     * @param string $where    Hook name.
     * @param string $reason   Short tag identifying which branch we took.
     * @param array  $extra    Optional structured payload to aid diagnosis.
     */
    private function print_debug_comment($where, $reason, array $extra = [])
    {
        $payload = $this->bsa_debug_payload($where, $reason, $extra);
        if ($payload === "") {
            return;
        }
        echo "\n<!-- bsa-appendix:" . esc_html($payload) . " -->\n";
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
            "appendix_enabled" => (bool) Bspt_Options::get("appendix_enabled"),
            "inject_on_post_types" => Bspt_Options::get("inject_on_post_types", ["post", "page"]),
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
     * the JS placement script (inject_placement_script) can relocate it when
     * an explicit placement_anchor is set. Without an anchor, the marker
     * keeps its position in the wp_footer render.
     *
     * @since    1.4.0
     * @param    string    $position    The configured injection_position
     *                                  (in_content / end_of_page / manual).
     */
    private function inject_footer_position($position)
    {
        if ($this->appendix_injected) {
            $this->print_debug_comment("wp_footer", "already_injected", $this->bsa_debug_state());
            return;
        }

        if (!$this->should_inject_appendix()) {
            $this->print_debug_comment("wp_footer", "should_not_inject", $this->bsa_debug_state());
            return;
        }

        // Check for manual placement
        global $post;
        if ($post && $this->has_manual_placement($post->post_content)) {
            $this->print_debug_comment("wp_footer", "manual_placement");
            return;
        }

        // Don't add on feeds
        if (is_feed()) {
            return;
        }

        $path = $this->get_current_url_path();
        $this->log_debug(sprintf("Fetching appendix for footer injection (%s), path: %s", $position, $path));
        $data = Bspt_Content_Fetcher::fetch($path);

        $delivery_mode = ($data && isset($data["delivery_mode"]) && $data["delivery_mode"]) ? $data["delivery_mode"] : "full";
        if (!in_array($delivery_mode, ["disabled", "jsonld_only", "full"], true)) {
            $delivery_mode = "full";
        }
        if ($delivery_mode === "disabled" || $delivery_mode === "jsonld_only") {
            $this->log_debug(sprintf("delivery_mode=%s, skipping footer appendix HTML injection", $delivery_mode));
            $this->print_debug_comment("wp_footer", "delivery_mode_skip", ["delivery_mode" => $delivery_mode]);
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
            $this->print_debug_comment("wp_footer", "fetch_null", [
                "path" => $path,
                "api_status" => $api_status,
                "api_reason" => $api_reason,
                "data_present" => $data ? true : false,
            ]);
            return;
        }

        $html = $this->strip_injection_config($data["html"]);

        // Apply the filter before escaping so that escaping happens as late as
        // possible and covers filtered output too, not just the service HTML.
        $html = apply_filters("bspt_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            // Escape late: a single wp_kses pass, inline at the point of output.
            echo '<div data-bsa-appendix data-bsa-position="' . esc_attr($position) . '">';
            echo wp_kses($html, $this->allowed_appendix_html());
            echo '</div>';
            $this->print_debug_comment("wp_footer", "injected_fallback", [
                "bytes" => strlen($html),
                "position" => $position,
            ]);
            $this->log_debug(sprintf("Appendix injected via wp_footer fallback (%d bytes, position=%s)", strlen($html), $position));
        } else {
            $this->print_debug_comment("wp_footer", "html_empty_after_sanitize");
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
        $data = Bspt_Content_Fetcher::fetch($path);

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

        $html = $this->strip_injection_config($data["html"]);

        // Apply the filter before escaping so that escaping happens as late as
        // possible and covers filtered output too, not just the service HTML.
        $html = apply_filters("bspt_appendix_html", $html);

        // Escape late: the shortcode's return value is the output, so escape here.
        return wp_kses($html, $this->allowed_appendix_html());
    }

    /**
     * Check if JSON-LD injection should happen on the current page
     *
     * @since    1.2.0
     * @return   bool    True if should inject, false otherwise.
     */
    private function should_inject_jsonld()
    {
        if (!Bspt_Options::get("jsonld_enabled")) {
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
        if (!Bspt_Options::get("appendix_enabled")) {
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
            $allowed_types = Bspt_Options::get("inject_on_post_types", ["post", "page"]);
            if (!in_array($post_type, $allowed_types, true)) {
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
        return apply_filters("bspt_should_inject", true);
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
        // rewritten [bspt_appendix] into HTML, so has_shortcode($content,...)
        // can return false even though the user did manually place the
        // shortcode. The queried post's post_content always holds the raw
        // form, so it's the authoritative source.
        $queried_id = (int) get_queried_object_id();
        if ($queried_id > 0) {
            $post_obj = get_post($queried_id);
            if ($post_obj && isset($post_obj->post_content)) {
                if ($this->content_has_appendix_marker((string) $post_obj->post_content)) {
                    return true;
                }
            }
        }

        return $this->content_has_appendix_marker($content);
    }

    /**
     * Whether a content string carries any appendix block or shortcode.
     *
     * Covers the current names and every legacy alias, so manual placement is
     * detected regardless of which generation of the markup the page was saved
     * with. Missing an alias here would auto-inject a second appendix onto a
     * page that already places one manually.
     *
     * @since    3.5.14
     * @access   private
     * @param    string    $content    Content to inspect.
     * @return   bool
     */
    private function content_has_appendix_marker($content)
    {
        if (!is_string($content) || $content === "") {
            return false;
        }

        if (function_exists("has_block")) {
            foreach (["bspt/appendix", "botspot-wp/appendix"] as $block) {
                if (has_block($block, $content)) {
                    return true;
                }
            }
        }

        foreach (["bspt_appendix", "botspot_appendix", "botdot_appendix"] as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                return true;
            }
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
        $path = apply_filters("bspt_url_path", $path);

        return $path;
    }

    /**
     * Strip the injection config script tag from the service payload.
     *
     * This is a content transform, not an escaping step: the tag carries
     * configuration for the client and must never be rendered. Kept separate
     * from escaping so the wp_kses pass can happen as late as possible.
     *
     * @since    3.5.14
     * @access   private
     * @param    string    $html    The raw HTML from the service.
     * @return   string             HTML with the config script removed.
     */
    private function strip_injection_config($html)
    {
        if (!is_string($html)) {
            return "";
        }

        return preg_replace('/<script[^>]*id="locus-injection-config"[^>]*>.*?<\/script>/s', '', $html);
    }

    /**
     * The wp_kses allowlist used for appendix HTML.
     *
     * Extends the standard "post" context with the elements and attributes the
     * appendix markup relies on.
     *
     * @since    3.5.14
     * @access   private
     * @return   array    Allowed HTML elements and attributes for wp_kses().
     */
    private function allowed_appendix_html()
    {
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
        // Admits the appendix's inline <style> block. This CSS is first-party,
        // service-rendered output from the disclosed BotSpot API (not arbitrary
        // user input) and is fully self-scoped to the .ba-root subtree
        // (contain: style; isolation: isolate), so it cannot affect the host
        // page. Kept until the appendix CSS is delivered as a separate,
        // version-cached payload (see the CSS-decoupling follow-up).
        $allowed["style"] = ["id" => true, "type" => true];

        // Allow style attribute on span/div (for CSS custom property wrappers)
        $allowed["span"]["style"] = true;
        $allowed["div"]["style"] = true;

        return $allowed;
    }

    /**
     * Wrap already-escaped appendix HTML in the placement marker.
     *
     * The wrapper carries the position so the client-side placement script
     * (inject_placement_script) can relocate the appendix.
     *
     * @since    3.5.14
     * @access   private
     * @param    string    $html        Appendix HTML, escaped by the caller via wp_kses().
     * @param    string    $position    The resolved injection position.
     * @return   string                 The wrapped markup, safe to output.
     */
    private function wrap_appendix($html, $position)
    {
        return '<div data-bsa-appendix data-bsa-position="' . esc_attr($position) . '">' . $html . '</div>';
    }


    /**
     * Log debug message
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    private function log_debug($message)
    {
        if (Bspt_Options::get("debug_mode")) {
            Bspt_Logger::log_debug("[ContentInjector] " . $message);
        }
    }
}
