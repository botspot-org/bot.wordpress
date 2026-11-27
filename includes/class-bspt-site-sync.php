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

        return new WP_REST_Response(
            self::inventory_page(self::viewable_types($body["bundles"]), $after === null ? 0 : $after[0], $body["limit"]),
            200
        );
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
     * @return   array                      The inventory response body.
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
