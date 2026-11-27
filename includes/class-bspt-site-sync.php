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
