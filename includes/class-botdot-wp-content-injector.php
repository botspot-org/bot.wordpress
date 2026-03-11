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
     * Collected @types from other SEO plugins (Yoast, RankMath).
     *
     * @since    1.2.0
     * @access   private
     * @var      array
     */
    private $external_jsonld_types = [];

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
     * Hook into Yoast SEO JSON-LD output to detect existing @types.
     *
     * Filter: wpseo_json_ld_output (priority 10)
     *
     * @since    1.2.0
     * @param    array    $data    Yoast JSON-LD data.
     * @return   array             Unmodified data.
     */
    public function detect_yoast_jsonld($data)
    {
        if (is_array($data)) {
            $this->collect_types_from_jsonld($data);
        }
        return $data;
    }

    /**
     * Hook into RankMath JSON-LD output to detect existing @types.
     *
     * Filter: rank_math/json_ld (priority 10)
     *
     * @since    1.2.0
     * @param    array    $data    RankMath JSON-LD data.
     * @return   array             Unmodified data.
     */
    public function detect_rankmath_jsonld($data)
    {
        if (is_array($data)) {
            $this->collect_types_from_jsonld($data);
        }
        return $data;
    }

    /**
     * Recursively collect @type values from a JSON-LD structure.
     *
     * @since    1.2.0
     * @access   private
     * @param    array    $data    JSON-LD data (may be nested).
     */
    private function collect_types_from_jsonld($data)
    {
        if (isset($data["@type"])) {
            $types = (array) $data["@type"];
            foreach ($types as $type) {
                $this->external_jsonld_types[] = $type;
            }
        }

        // Check @graph array (common in Yoast/RankMath)
        if (isset($data["@graph"]) && is_array($data["@graph"])) {
            foreach ($data["@graph"] as $node) {
                if (is_array($node)) {
                    $this->collect_types_from_jsonld($node);
                }
            }
        }

        // Check top-level numeric keys (array of nodes)
        foreach ($data as $key => $value) {
            if (is_int($key) && is_array($value)) {
                $this->collect_types_from_jsonld($value);
            }
        }
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

        $path = $this->get_current_url_path();

        // When appendix is disabled but jsonld is enabled, use the dedicated jsonld endpoint
        $appendix_enabled = BotDot_WP_Options::get("appendix_enabled");
        if (!$appendix_enabled) {
            $data = BotDot_WP_Content_Fetcher::fetch_jsonld($path);
        } else {
            $data = BotDot_WP_Content_Fetcher::fetch($path);
        }

        if (!$data || !isset($data["jsonld"]) || $data["jsonld"] === null) {
            return;
        }

        $jsonld = $data["jsonld"];

        // Apply filter
        $jsonld = apply_filters("botdot_wp_appendix_jsonld", $jsonld);

        if (empty($jsonld)) {
            return;
        }

        // Normalize JSON-LD: always decode to array for processing
        if (is_string($jsonld)) {
            $decoded = json_decode($jsonld, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Invalid JSON string, skip injection
                return;
            }
        } else {
            $decoded = $jsonld;
        }

        // Apply conflict detection in merge mode
        $conflict_mode = BotDot_WP_Options::get("jsonld_conflict_mode", "merge");
        if ($conflict_mode === "merge" && !empty($this->external_jsonld_types)) {
            $decoded = $this->filter_conflicting_types($decoded);
            if (empty($decoded)) {
                $this->log_debug("All JSON-LD nodes suppressed due to conflicts with other SEO plugins");
                return;
            }
        }

        $json_string = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Prevent script-tag breakout
        $json_string = str_replace("</script>", "<\/script>", $json_string);

        echo "\n<!-- BotSpot JSON-LD -->\n";
        echo '<script type="application/ld+json">' . $json_string . "</script>";
        echo "\n<!-- /BotSpot JSON-LD -->\n";

        $this->log_debug("JSON-LD injected into wp_head");
    }

    /**
     * Filter out JSON-LD nodes whose @type conflicts with other SEO plugins.
     *
     * In merge mode, suppress our nodes (identified by #locus- @id prefix)
     * if another plugin already emits the same @type.
     *
     * @since    1.2.0
     * @access   private
     * @param    array    $data    Decoded JSON-LD data.
     * @return   array             Filtered JSON-LD data.
     */
    private function filter_conflicting_types($data)
    {
        $external_types = array_map("strtolower", $this->external_jsonld_types);

        // Handle @graph structure
        if (isset($data["@graph"]) && is_array($data["@graph"])) {
            $filtered_graph = [];
            foreach ($data["@graph"] as $node) {
                if (!$this->is_conflicting_node($node, $external_types)) {
                    $filtered_graph[] = $node;
                }
            }
            if (empty($filtered_graph)) {
                return [];
            }
            $data["@graph"] = $filtered_graph;
            return $data;
        }

        // Handle flat array of nodes
        if (isset($data[0]) && is_array($data[0])) {
            $filtered = [];
            foreach ($data as $node) {
                if (!$this->is_conflicting_node($node, $external_types)) {
                    $filtered[] = $node;
                }
            }
            return $filtered;
        }

        // Handle single node
        if ($this->is_conflicting_node($data, $external_types)) {
            return [];
        }

        return $data;
    }

    /**
     * Check if a JSON-LD node conflicts with externally detected @types.
     *
     * Only suppresses nodes with a #locus- @id prefix (our own nodes).
     *
     * @since    1.2.0
     * @access   private
     * @param    array    $node              A JSON-LD node.
     * @param    array    $external_types    Lowercased external @type values.
     * @return   bool                        True if this node conflicts and should be suppressed.
     */
    private function is_conflicting_node($node, $external_types)
    {
        if (!is_array($node) || !isset($node["@type"])) {
            return false;
        }

        // Only filter our own nodes (identified by #locus- @id prefix)
        $node_id = isset($node["@id"]) ? $node["@id"] : "";
        if (strpos($node_id, "#locus-") === false) {
            return false;
        }

        $node_types = (array) $node["@type"];
        foreach ($node_types as $type) {
            if (in_array(strtolower($type), $external_types, true)) {
                $this->log_debug(sprintf(
                    "Suppressing JSON-LD node @id=%s (@type=%s) -- conflicts with other SEO plugin",
                    $node_id,
                    $type
                ));
                return true;
            }
        }

        return false;
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

        $path = $this->get_current_url_path();
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data["html"] === null) {
            return $content;
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botdot_wp_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            $content .= $html;
            $this->log_debug(sprintf("Appendix injected via content filter (%d bytes)", strlen($html)));
        }

        return $content;
    }

    /**
     * Inject appendix above footer
     *
     * Hook: wp_footer (priority 5)
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

        $position = BotDot_WP_Options::get("injection_position", "bottom");

        // Only inject via footer for 'above_footer' position
        if ($position !== "above_footer") {
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
        $data = BotDot_WP_Content_Fetcher::fetch($path);

        if (!$data || $data["html"] === null) {
            return;
        }

        $html = $this->sanitize_html($data["html"]);

        // Apply filter
        $html = apply_filters("botdot_wp_appendix_html", $html);

        if (!empty($html)) {
            $this->appendix_injected = true;
            echo $html;
            $this->log_debug(sprintf("Appendix injected via footer hook (%d bytes)", strlen($html)));
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
            return false;
        }

        // Don't inject on 404 or search
        if (is_404() || is_search()) {
            return false;
        }

        // Check valid page type
        if (!$this->is_valid_page_type()) {
            return false;
        }

        // Check post type
        $post_type = get_post_type();
        if ($post_type) {
            $allowed_types = BotDot_WP_Options::get("inject_on_post_types", ["post", "page"]);
            if (!in_array($post_type, $allowed_types)) {
                // Allow front page even if post type doesn't match
                if (!is_front_page()) {
                    return false;
                }
            }
        }

        // Check per-page override via post_meta
        $current_id = get_the_ID();
        if ($current_id) {
            $inject_meta = get_post_meta($current_id, "_botdot_inject_enabled", true);
            if ($inject_meta !== "") {
                return (bool) $inject_meta;
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

        if (has_shortcode($content, "botdot_appendix")) {
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
        $allowed = wp_kses_allowed_html("post");
        $allowed["details"] = ["class" => true, "open" => true, "id" => true];
        $allowed["summary"] = ["class" => true, "id" => true];
        $allowed["dl"] = ["class" => true, "id" => true];
        $allowed["dt"] = ["class" => true, "id" => true];
        $allowed["dd"] = ["class" => true, "id" => true];
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
        if (BotDot_WP_Options::get("debug_mode")) {
            BotDot_WP_Logger::log_debug("[ContentInjector] " . $message);
        }
    }
}
