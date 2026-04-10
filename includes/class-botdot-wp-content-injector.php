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
     * Whether JSON-LD has been merged into an SEO plugin's output.
     *
     * @since    1.3.0
     * @access   private
     * @var      bool
     */
    private $jsonld_merged_into_seo_plugin = false;

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
     * Merge locus JSON-LD into Yoast SEO's @graph array (modern Yoast 14.0+).
     *
     * Filter: wpseo_schema_graph (priority 99)
     *
     * This filter receives the @graph array directly (not the full JSON-LD wrapper).
     *
     * @since    1.3.0
     * @param    array    $graph    Yoast @graph array of nodes.
     * @return   array              Modified @graph array with locus nodes merged in.
     */
    public function merge_into_yoast_graph($graph)
    {
        if (!is_array($graph)) {
            return $graph;
        }

        // Already merged via this hook
        if ($this->jsonld_merged_into_seo_plugin) {
            return $graph;
        }

        $conflict_mode = BotDot_WP_Options::get("jsonld_conflict_mode", "merge");
        if ($conflict_mode === "off") {
            $this->log_debug("JSON-LD conflict mode is 'off', skipping Yoast graph merge");
            return $graph;
        }

        $locus_jsonld = $this->get_locus_jsonld();
        if ($locus_jsonld === null) {
            $this->log_debug("No locus JSON-LD available for Yoast graph merge");
            return $graph;
        }

        $locus_nodes = $this->extract_graph_nodes($locus_jsonld);
        if (empty($locus_nodes)) {
            $this->log_debug("No locus @graph nodes to merge");
            return $graph;
        }

        // Build a map of existing @types in Yoast's graph (lowercased)
        $seo_type_index = [];
        foreach ($graph as $idx => $node) {
            if (!is_array($node) || !isset($node["@type"])) {
                continue;
            }
            $types = (array) $node["@type"];
            foreach ($types as $type) {
                $seo_type_index[strtolower($type)] = $idx;
            }
        }

        foreach ($locus_nodes as $locus_node) {
            if (!is_array($locus_node) || !isset($locus_node["@type"])) {
                $graph[] = $locus_node;
                continue;
            }

            $locus_types = (array) $locus_node["@type"];
            $collision_idx = null;

            if ($conflict_mode === "merge") {
                foreach ($locus_types as $type) {
                    $key = strtolower($type);
                    if (isset($seo_type_index[$key])) {
                        $collision_idx = $seo_type_index[$key];
                        break;
                    }
                }
            }

            if ($collision_idx !== null) {
                foreach ($locus_node as $prop => $value) {
                    if (!isset($graph[$collision_idx][$prop])) {
                        $graph[$collision_idx][$prop] = $value;
                    }
                }
                $node_id = isset($locus_node["@id"]) ? $locus_node["@id"] : "unknown";
                $this->log_debug(sprintf(
                    "Merged locus node @id=%s into Yoast node @type=%s (additive)",
                    $node_id,
                    implode(",", $locus_types)
                ));
            } else {
                $graph[] = $locus_node;
                $node_id = isset($locus_node["@id"]) ? $locus_node["@id"] : "unknown";
                $this->log_debug(sprintf(
                    "Appended locus node @id=%s (@type=%s) to Yoast @graph",
                    $node_id,
                    implode(",", $locus_types)
                ));
            }
        }

        $this->jsonld_merged_into_seo_plugin = true;
        $this->log_debug(sprintf("JSON-LD merge into Yoast graph complete (%d locus nodes processed)", count($locus_nodes)));

        return $graph;
    }

    /**
     * Merge locus JSON-LD into Yoast SEO's full output (legacy fallback).
     *
     * Filter: wpseo_json_ld_output (priority 99)
     * Only runs if wpseo_schema_graph didn't fire (old Yoast versions).
     *
     * @since    1.3.0
     * @param    array    $data    Yoast JSON-LD data.
     * @return   array             Modified data with locus nodes merged in.
     */
    public function merge_into_yoast_jsonld($data)
    {
        if ($this->jsonld_merged_into_seo_plugin) {
            return $data;
        }
        return $this->merge_into_seo_jsonld($data, "Yoast");
    }

    /**
     * Merge locus JSON-LD into RankMath's @graph output.
     *
     * Filter: rank_math/json_ld (priority 99)
     *
     * @since    1.3.0
     * @param    array    $data    RankMath JSON-LD data.
     * @return   array             Modified data with locus nodes merged in.
     */
    public function merge_into_rankmath_jsonld($data)
    {
        return $this->merge_into_seo_jsonld($data, "RankMath");
    }

    /**
     * Core merge logic for SEO plugin JSON-LD integration.
     *
     * @since    1.3.0
     * @access   private
     * @param    array     $data          SEO plugin's JSON-LD data.
     * @param    string    $plugin_name   Name of the SEO plugin (for logging).
     * @return   array                    Modified JSON-LD data.
     */
    private function merge_into_seo_jsonld($data, $plugin_name)
    {
        if (!is_array($data)) {
            return $data;
        }

        $conflict_mode = BotDot_WP_Options::get("jsonld_conflict_mode", "merge");
        if ($conflict_mode === "off") {
            $this->log_debug(sprintf("JSON-LD conflict mode is 'off', skipping %s merge", $plugin_name));
            return $data;
        }

        $locus_jsonld = $this->get_locus_jsonld();
        if ($locus_jsonld === null) {
            $this->log_debug(sprintf("No locus JSON-LD available for %s merge", $plugin_name));
            return $data;
        }

        $locus_nodes = $this->extract_graph_nodes($locus_jsonld);
        if (empty($locus_nodes)) {
            $this->log_debug("No locus @graph nodes to merge");
            return $data;
        }

        // Ensure @graph exists in the SEO data
        if (!isset($data["@graph"]) || !is_array($data["@graph"])) {
            $data["@graph"] = [];
        }

        // Build a map of existing @types in the SEO plugin's graph (lowercased)
        $seo_type_index = [];
        foreach ($data["@graph"] as $idx => $node) {
            if (!is_array($node) || !isset($node["@type"])) {
                continue;
            }
            $types = (array) $node["@type"];
            foreach ($types as $type) {
                $seo_type_index[strtolower($type)] = $idx;
            }
        }

        foreach ($locus_nodes as $locus_node) {
            if (!is_array($locus_node) || !isset($locus_node["@type"])) {
                // No @type - just append
                $data["@graph"][] = $locus_node;
                continue;
            }

            $locus_types = (array) $locus_node["@type"];
            $collision_idx = null;

            if ($conflict_mode === "merge") {
                // Check for @type collision
                foreach ($locus_types as $type) {
                    $key = strtolower($type);
                    if (isset($seo_type_index[$key])) {
                        $collision_idx = $seo_type_index[$key];
                        break;
                    }
                }
            }

            if ($collision_idx !== null) {
                // Merge: add locus properties into existing node (additive only)
                foreach ($locus_node as $prop => $value) {
                    if (!isset($data["@graph"][$collision_idx][$prop])) {
                        $data["@graph"][$collision_idx][$prop] = $value;
                    }
                }
                $node_id = isset($locus_node["@id"]) ? $locus_node["@id"] : "unknown";
                $this->log_debug(sprintf(
                    "Merged locus node @id=%s into %s node @type=%s (additive)",
                    $node_id,
                    $plugin_name,
                    implode(",", $locus_types)
                ));
            } else {
                // No collision or replace mode: append
                $data["@graph"][] = $locus_node;
                $node_id = isset($locus_node["@id"]) ? $locus_node["@id"] : "unknown";
                $this->log_debug(sprintf(
                    "Appended locus node @id=%s (@type=%s) to %s @graph",
                    $node_id,
                    implode(",", $locus_types),
                    $plugin_name
                ));
            }
        }

        $this->jsonld_merged_into_seo_plugin = true;
        $this->log_debug(sprintf("JSON-LD merge into %s complete (%d locus nodes processed)", $plugin_name, count($locus_nodes)));

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

        // Skip standalone output if already merged into SEO plugin's @graph
        if ($this->jsonld_merged_into_seo_plugin) {
            $this->log_debug("JSON-LD already merged into SEO plugin output, skipping standalone injection");
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

        $this->log_debug("JSON-LD injected into wp_head (standalone, no SEO plugin detected)");
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
            return $content;
        }

        if (!$this->should_inject_appendix()) {
            return $content;
        }

        $position = BotDot_WP_Options::get("injection_position", "bottom");

        // Only inject via content filter for 'bottom' position
        if ($position !== "bottom") {
            return $content;
        }

        // Check for manual placement
        if ($this->has_manual_placement($content)) {
            return $content;
        }

        // Don't add on feeds
        if (is_feed()) {
            return $content;
        }

        // Detect page builders that discard the_content output.
        // In those cases, skip injection here and let wp_footer handle it.
        if ($this->is_page_builder_active()) {
            $this->log_debug("Page builder detected, deferring appendix to footer fallback");
            return $content;
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
            return $content;
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botdot_wp_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            $content .= $html;
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
        if ($this->appendix_injected) {
            return;
        }

        if (!$this->should_inject_appendix()) {
            return;
        }

        // Check for manual placement
        global $post;
        if ($post && $this->has_manual_placement($post->post_content)) {
            return;
        }

        // Don't add on feeds
        if (is_feed()) {
            return;
        }

        $path = $this->get_current_url_path();
        $this->log_debug(sprintf("Fetching appendix for footer injection (fallback), path: %s", $path));
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
            return;
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botdot_wp_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            echo $html;
            $this->log_debug(sprintf("Appendix injected via footer fallback (%d bytes)", strlen($html)));
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
        $this->shortcode_used = true;

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

        $this->appendix_injected = true;

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
