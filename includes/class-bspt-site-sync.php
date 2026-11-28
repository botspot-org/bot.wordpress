<?php
/**
 * The signed routes that core's site sync calls
 *
 * @link       https://bot.spot
 * @since      3.7.12
 *
 * @package    Bspt
 * @subpackage Bspt/includes
 */

if (!defined("WPINC")) {
    die();
}

/**
 * Serves core's pull sync with the contract of the botspot-drupal module.
 *
 * @since      3.7.12
 * @package    Bspt
 * @subpackage Bspt/includes
 */
class Bspt_Site_Sync
{
    /**
     * The exact key set of each route's body.
     *
     * The signature covers the timestamp and the body, not the route, so a
     * body signed for one route would verify on the other. Each route accepts
     * only a body whose op names it and whose keys are exactly its own.
     *
     * @since 3.7.12
     */
    const KEYS = [
        "inventory" => ["after", "bundles", "limit", "op", "send_author"],
        "render" => ["bundles", "op", "send_author", "urls"],
    ];

    /**
     * Largest inventory page.
     *
     * get_permalink() loads each post whole, post_content included, and 2000
     * posts can exhaust a 128 MB memory limit. Core follows `next`, so a
     * smaller page costs only more calls.
     *
     * @since 3.7.12
     */
    const INVENTORY_MAX_LIMIT = 500;

    /**
     * Most URLs one render call may carry.
     *
     * @since 3.7.12
     */
    const MAX_URLS = 20;

    /**
     * The verified, decoded body of a sync call, or the response that refuses it.
     *
     * @since    3.7.12
     * @param    WP_REST_Request    $request    The request.
     * @param    string             $op         "inventory" or "render".
     * @return   array|WP_REST_Response
     */
    public static function verified_body($request, $op)
    {
        if (!Bspt_Webhook_Handler::verify_signed_request($request, get_option("bspt_webhook_secret", ""))) {
            return new WP_REST_Response(["error" => "Invalid signature"], 401);
        }

        $body = json_decode($request->get_body(), true);
        if (!is_array($body) || !isset($body["op"]) || $body["op"] !== $op) {
            return self::invalid();
        }
        $keys = array_keys($body);
        sort($keys);
        if ($keys !== self::KEYS[$op] || !self::is_string_list($body["bundles"]) || !is_bool($body["send_author"])) {
            return self::invalid();
        }

        return $body;
    }

    /**
     * POST bspt/v1/sync/inventory: one page of the posts core syncs.
     *
     * @since    3.7.12
     * @param    WP_REST_Request    $request    The request.
     * @return   WP_REST_Response
     */
    public static function handle_inventory($request)
    {
        $body = self::verified_body($request, "inventory");
        if ($body instanceof WP_REST_Response) {
            return $body;
        }

        $after = $body["after"];
        $valid_after = $after === null || (
            is_array($after) && array_values($after) === $after && count($after) === 2
            && is_int($after[0]) && is_string($after[1])
        );
        if (!$valid_after || !is_int($body["limit"]) || $body["limit"] < 1) {
            return self::invalid();
        }

        $page = self::inventory_page(self::viewable_types($body["bundles"]), $after === null ? 0 : $after[0], $body["limit"]);
        if ($page === null) {
            // Not translated, like the other strings only core reads.
            return new WP_REST_Response(["error" => "Inventory query failed"], 500);
        }

        return new WP_REST_Response($page, 200);
    }

    /**
     * Published posts without a password, of $post_types, after post $after_id.
     *
     * Keyset paging on ID: a post inserted or deleted during core's walk never
     * shifts another post out of the list, which core would read as a removal.
     *
     * @since    3.7.12
     * @param    string[]    $post_types    Post types to list.
     * @param    int         $after_id      ID of the last post core has seen; 0 for the first page.
     * @param    int         $limit         Page size core asked for.
     * @return   array|null                 The inventory response body; null when the query failed.
     */
    public static function inventory_page(array $post_types, $after_id, $limit)
    {
        global $wpdb;

        $limit = min($limit, self::INVENTORY_MAX_LIMIT);
        $ids = [];
        if ($post_types) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP_Query has no keyset argument, and a cursor page must not be cached.
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_password = ''"
                        . " AND post_type IN (" . implode(",", array_fill(0, count($post_types), "%s")) . ")"
                        . " AND ID > %d ORDER BY ID ASC LIMIT %d",
                    array_merge($post_types, [(int) $after_id, $limit + 1])
                )
            );
            // A failed query returns [], which core would read as the end of the site.
            if ($wpdb->last_error !== "") {
                return null;
            }
        }

        return self::build_inventory_page(array_map("intval", $ids), $limit);
    }

    /**
     * Turns up to $limit + 1 post IDs into a page and its cursor.
     *
     * The cursor is the last ID read, not the last item sent. When the
     * bspt_should_sync filter removes every post of a full page, core must
     * still move on; at `next: null` it stops and unpublishes every post after.
     *
     * @since    3.7.12
     * @param    int[]    $ids      Post IDs in ascending order.
     * @param    int      $limit    Page size.
     * @return   array              The inventory response body.
     */
    public static function build_inventory_page(array $ids, $limit)
    {
        $more = count($ids) > $limit;
        $items = [];
        $next = null;
        foreach (array_slice($ids, 0, $limit) as $id) {
            $langcode = Bspt_Language::get_post_language($id);
            $next = [$id, $langcode];
            $post = get_post($id);
            if (!$post || !apply_filters("bspt_should_sync", true, $id, $post)) {
                continue;
            }
            $url = get_permalink($id);
            // A language served from its own domain keeps pushing: core matches
            // those pushes to that domain's own Site, and url_to_postid() could
            // not resolve its URLs here anyway.
            if (!Bspt_Language::is_home_host($url)) {
                continue;
            }
            $items[] = [
                "nid" => $id,
                "uuid" => (string) $id,
                "langcode" => $langcode,
                "url" => $url,
                "changed" => self::epoch($post->post_modified_gmt),
                "bundle" => $post->post_type,
            ];
        }

        return [
            "items" => $items,
            "next" => $more ? $next : null,
            "languages" => self::rest_bases($items),
        ];
    }

    /**
     * POST bspt/v1/sync/render: one entry per URL, in the order of the request.
     *
     * @since    3.7.12
     * @param    WP_REST_Request    $request    The request.
     * @return   WP_REST_Response
     */
    public static function handle_render($request)
    {
        $body = self::verified_body($request, "render");
        if ($body instanceof WP_REST_Response) {
            return $body;
        }

        $urls = $body["urls"];
        if (!self::is_string_list($urls) || $urls === [] || count($urls) > self::MAX_URLS) {
            return self::invalid();
        }

        $post_types = self::viewable_types($body["bundles"]);
        $hosts = self::home_hosts();
        $items = [];
        foreach ($urls as $url) {
            $items[] = self::render_url($url, $post_types, $hosts);
        }

        return new WP_REST_Response(["items" => $items], 200);
    }

    /**
     * The entry of one URL. Core reads a 403 or 404 entry as a hidden page and unpublishes it.
     *
     * @since    3.7.12
     * @param    string      $url           The URL as core sent it; echoed unchanged.
     * @param    string[]    $post_types    Post types core syncs.
     * @param    string[]    $hosts         Lowercase hosts of the site's language homes.
     * @return   array
     */
    public static function render_url($url, array $post_types, array $hosts)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || !in_array(strtolower($host), $hosts, true)) {
            return self::error_entry($url, "host_mismatch", 400);
        }

        $post_id = self::query_var_post_id($url, $post_types);
        if (!$post_id) {
            $post_id = url_to_postid($url);
        }
        $post = $post_id ? get_post($post_id) : null;
        // url_to_postid() answers a ?p= URL without a status check, so a draft
        // behind a plain permalink resolves to its ID.
        if (!$post || $post->post_status !== "publish") {
            return self::error_entry($url, "not_found", 404);
        }
        if ($post->post_password !== "") {
            return self::error_entry($url, "access_denied", 403);
        }
        if (!in_array($post->post_type, $post_types, true) || !apply_filters("bspt_should_sync", true, $post->ID, $post)) {
            return self::error_entry($url, "not_synced", 403);
        }

        return self::rendered_entry($url, $post);
    }

    /**
     * The post of a plain-permalink URL such as /?product=mug.
     *
     * url_to_postid() resolves only p, page_id and attachment_id there, and
     * answers the static front page or 0 for a custom post type.
     *
     * @since    3.7.12
     * @param    string      $url           The URL as core sent it.
     * @param    string[]    $post_types    Post types core syncs.
     * @return   int                        The post ID, 0 when no query variable matches.
     */
    private static function query_var_post_id($url, array $post_types)
    {
        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (!is_string($query)) {
            return 0;
        }
        parse_str($query, $vars);
        foreach ($post_types as $type) {
            $object = get_post_type_object($type);
            $key = $object && is_string($object->query_var) ? $object->query_var : "";
            if ($key !== "" && isset($vars[$key]) && is_string($vars[$key]) && $vars[$key] !== "") {
                $post = get_page_by_path($vars[$key], OBJECT, $type);
                if ($post) {
                    return (int) $post->ID;
                }
            }
        }

        return 0;
    }

    /**
     * @since    3.7.12
     * @param    string     $url     The URL as core sent it.
     * @param    WP_Post    $post    A published post without a password.
     * @return   array
     */
    private static function rendered_entry($url, $post)
    {
        $entry = [
            "url" => $url,
            "nid" => (int) $post->ID,
            "uuid" => (string) $post->ID,
            "langcode" => Bspt_Language::get_post_language((int) $post->ID),
            "bundle" => $post->post_type,
            "title" => (string) $post->post_title,
            "description" => (string) $post->post_excerpt,
            "created" => self::epoch($post->post_date_gmt),
            "changed" => self::epoch($post->post_modified_gmt),
            "html" => Bspt_Page_Builder::extract_content($post),
            "terms" => self::term_names((int) $post->ID, "post_tag"),
            "categories" => self::term_names((int) $post->ID, "category"),
        ];

        // The plugin's own option decides; the request's send_author is ignored.
        $user = Bspt_Options::get("send_author") ? get_userdata((int) $post->post_author) : false;
        if ($user && (string) $user->display_name !== "") {
            $entry["author"] = mb_substr((string) $user->display_name, 0, 255);
        }

        $entry["media"] = self::featured_media((int) $post->ID);
        $entry["source_jsonld"] = Bspt_Sync::extract_page_jsonld((int) $post->ID);

        return $entry;
    }

    /**
     * @since    3.7.12
     * @return   array
     */
    private static function error_entry($url, $error, $status)
    {
        return ["url" => $url, "error" => $error, "status" => $status];
    }

    /**
     * Hosts of home_url() and of every language home, lowercase.
     *
     * @since    3.7.12
     * @return   string[]
     */
    private static function home_hosts()
    {
        $hosts = [];
        foreach (array_merge([home_url("/")], array_values(Bspt_Language::site_languages())) as $home) {
            $host = wp_parse_url($home, PHP_URL_HOST);
            if (is_string($host)) {
                $hosts[] = strtolower($host);
            }
        }
        return $hosts;
    }

    /**
     * @since    3.7.12
     * @return   string[]
     */
    private static function term_names($post_id, $taxonomy)
    {
        $terms = get_the_terms($post_id, $taxonomy);
        $names = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            $names[] = $term->name;
        }
        return $names;
    }

    /**
     * The featured image as a media entry list.
     *
     * @since    3.7.12
     * @return   array
     */
    private static function featured_media($post_id)
    {
        $attachment_id = (int) get_post_thumbnail_id($post_id);
        $image = $attachment_id ? wp_get_attachment_image_src($attachment_id, "full") : false;
        if (!is_array($image) || empty($image[0])) {
            return [];
        }

        $entry = [
            "url" => $image[0],
            "media_type" => get_post_mime_type($attachment_id) ?: "image/jpeg",
        ];
        $alt = trim((string) get_post_meta($attachment_id, "_wp_attachment_image_alt", true));
        if ($alt !== "") {
            $entry["alt_text"] = mb_substr($alt, 0, 500);
        }
        // Core drops a media entry whose width or height is below 1.
        if ((int) $image[1] > 0 && (int) $image[2] > 0) {
            $entry["width"] = (int) $image[1];
            $entry["height"] = (int) $image[2];
        }

        return [$entry];
    }

    /**
     * Every site language, and every language on this page, mapped to the REST base.
     *
     * Core replaces its language map with each page's map, so every page lists
     * every language, not only the languages of its own items.
     *
     * @since    3.7.12
     * @param    array    $items    The page's items.
     * @return   string[]
     */
    private static function rest_bases(array $items)
    {
        $codes = array_keys(Bspt_Language::site_languages());
        foreach ($items as $item) {
            $codes[] = $item["langcode"];
        }

        return array_fill_keys(array_unique($codes), rest_url());
    }

    /**
     * The requested post types that the public can view.
     *
     * A signed request still cannot list post types that store forms or field
     * groups with the publish status.
     *
     * @since    3.7.12
     * @param    string[]    $post_types
     * @return   string[]
     */
    private static function viewable_types(array $post_types)
    {
        return array_values(array_filter($post_types, "is_post_type_viewable"));
    }

    /**
     * A GMT MySQL datetime as epoch seconds, 0 for the zero date.
     *
     * Inventory and render both use it. Core compares the inventory `changed`
     * with the render `changed` it stored, so the two must be equal.
     *
     * @since    3.7.12
     * @param    string    $gmt    A *_gmt column value.
     * @return   int
     */
    private static function epoch($gmt)
    {
        $time = strtotime($gmt . " UTC");
        return $time > 0 ? $time : 0;
    }

    /**
     * @since    3.7.12
     * @return   WP_REST_Response
     */
    private static function invalid()
    {
        return new WP_REST_Response(["error" => "Invalid payload"], 400);
    }

    /**
     * Whether a value is a JSON array of strings.
     *
     * @since    3.7.12
     * @param    mixed    $value
     * @return   bool
     */
    private static function is_string_list($value)
    {
        if (!is_array($value) || array_values($value) !== $value) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }
        return true;
    }
}
