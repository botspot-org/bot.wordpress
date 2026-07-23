<?php
/**
 * Options management class for the BotSpot WP plugin
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    Bspt
 * @subpackage Bspt/includes
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * Options management class for the BotSpot WP plugin.
 *
 * This class provides centralized methods for getting and setting plugin options
 * with proper defaults, validation, and type casting.
 *
 * @since      0.1.0
 * @package    Bspt
 * @subpackage Bspt/includes
 * @author     BotSpot Team
 */
class Bspt_Options
{
    /**
     * Default plugin options
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $defaults    Default option values.
     */
    private static $defaults = [
        // Connection
        "api_key" => "",
        "webhook_secret" => "",
        "webhook_id" => "",
        "connection_id" => "",
        "tenant_id" => "",

        // Sync
        "auto_sync_enabled" => true,
        "sync_sensitivity" => "medium",
        "sync_post_types" => ["post", "page"],

        // Display
        "appendix_enabled" => true,
        "jsonld_enabled" => true,
        "jsonld_conflict_mode" => "merge",
        "injection_position" => "bottom_of_content",
        "inject_on_post_types" => ["post", "page"],

        // Cache
        "cache_ttl" => 3600,

        // Debug
        "debug_mode" => false,
    ];

    /**
     * Get plugin option with default fallback
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without bspt_ prefix).
     * @param    mixed     $default        Optional. Default value if option doesn't exist.
     * @return   mixed                     The option value.
     */
    public static function get($option_name, $default = null)
    {
        if ($default === null) {
            $default = self::get_dynamic_default($option_name);
        }

        $value = get_option("bspt_" . $option_name, $default);

        return self::cast_option_value($option_name, $value);
    }

    /**
     * Get dynamic default for options that depend on environment.
     *
     * @since    3.4.0
     * @param    string    $option_name    The option name.
     * @return   mixed                     The default value.
     */
    private static function get_dynamic_default($option_name)
    {
        if ($option_name === 'sync_post_types' || $option_name === 'inject_on_post_types') {
            $types = ['post', 'page'];
            if (self::is_woocommerce_active()) {
                $types[] = 'product';
            }
            return $types;
        }

        return isset(self::$defaults[$option_name]) ? self::$defaults[$option_name] : null;
    }

    /**
     * Check if WooCommerce is active.
     *
     * @since    2.2.0
     * @return   bool
     */
    public static function is_woocommerce_active()
    {
        return class_exists('WooCommerce') || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins', [])), true);
    }

    /**
     * Set plugin option
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without bspt_ prefix).
     * @param    mixed     $value          The option value.
     * @return   bool                      True if the value was updated, false otherwise.
     */
    public static function set($option_name, $value)
    {
        $value = self::sanitize_option_value($option_name, $value);

        return update_option("bspt_" . $option_name, $value);
    }

    /**
     * Delete plugin option
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without bspt_ prefix).
     * @return   bool                      True if the option was deleted, false otherwise.
     */
    public static function delete($option_name)
    {
        return delete_option("bspt_" . $option_name);
    }

    /**
     * Get multiple options at once
     *
     * @since    0.1.0
     * @param    array     $option_names    Array of option names (without bspt_ prefix).
     * @return   array                      Associative array of option values.
     */
    public static function get_multiple($option_names)
    {
        $options = [];

        foreach ($option_names as $option_name) {
            $options[$option_name] = self::get($option_name);
        }

        return $options;
    }

    /**
     * Set multiple options at once
     *
     * @since    0.1.0
     * @param    array     $options    Associative array of option_name => value pairs.
     * @return   bool                  True if all options were updated successfully.
     */
    public static function set_multiple($options)
    {
        $success = true;

        foreach ($options as $option_name => $value) {
            if (!self::set($option_name, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check if an option exists
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without bspt_ prefix).
     * @return   bool                      True if option exists, false otherwise.
     */
    public static function exists($option_name)
    {
        return get_option("bspt_" . $option_name) !== false;
    }

    /**
     * Get all plugin options
     *
     * @since    0.1.0
     * @return   array    All plugin options with their current values.
     */
    public static function get_all()
    {
        $options = [];

        foreach (array_keys(self::$defaults) as $option_name) {
            $options[$option_name] = self::get($option_name);
        }

        return $options;
    }

    /**
     * Reset option to default value
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without bspt_ prefix).
     * @return   bool                      True if option was reset successfully.
     */
    public static function reset_to_default($option_name)
    {
        if (isset(self::$defaults[$option_name])) {
            return self::set($option_name, self::$defaults[$option_name]);
        }

        return false;
    }

    /**
     * Reset all options to default values
     *
     * @since    0.1.0
     * @return   bool    True if all options were reset successfully.
     */
    public static function reset_all_to_defaults()
    {
        $success = true;

        foreach (self::$defaults as $option_name => $default_value) {
            if (!self::set($option_name, $default_value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Cast option value to appropriate type
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $option_name    The option name.
     * @param    mixed     $value          The option value.
     * @return   mixed                     The cast value.
     */
    private static function cast_option_value($option_name, $value)
    {
        switch ($option_name) {
            case "auto_sync_enabled":
            case "injection_enabled":
            case "appendix_enabled":
            case "jsonld_enabled":
            case "debug_mode":
                return (bool) $value;

            case "cache_ttl":
                return (int) $value;

            case "sync_post_types":
            case "inject_on_post_types":
                return is_array($value) ? $value : [];

            case "api_key":
            case "webhook_secret":
            case "webhook_id":
            case "connection_id":
            case "tenant_id":
            case "sync_sensitivity":
            case "jsonld_conflict_mode":
                return is_string($value) ? trim($value) : "";

            case "injection_position":
                $val = is_string($value) ? trim($value) : "";
                return self::migrate_placement_value($val);

            default:
                return $value;
        }
    }

    /**
     * Sanitize option value before saving
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $option_name    The option name.
     * @param    mixed     $value          The option value.
     * @return   mixed                     The sanitized value.
     */
    public static function sanitize_option_value($option_name, $value)
    {
        switch ($option_name) {
            case "api_key":
            case "webhook_secret":
            case "webhook_id":
            case "connection_id":
            case "tenant_id":
                return sanitize_text_field(trim($value));

            case "auto_sync_enabled":
            case "injection_enabled":
            case "appendix_enabled":
            case "jsonld_enabled":
            case "debug_mode":
                return (bool) $value;

            case "sync_sensitivity":
                $allowed = ["high", "medium", "low"];
                return in_array($value, $allowed, true) ? $value : "medium";

            case "jsonld_conflict_mode":
                $allowed = ["merge", "replace", "off"];
                return in_array($value, $allowed, true) ? $value : "merge";

            case "injection_position":
                $value = self::migrate_placement_value($value);
                $allowed = ["bottom_of_content", "above_footer", "bottom_of_page", "manual"];
                return in_array($value, $allowed, true) ? $value : "bottom_of_content";

            case "sync_post_types":
            case "inject_on_post_types":
                if (is_array($value)) {
                    return array_map("sanitize_text_field", $value);
                }
                return [];

            case "cache_ttl":
                return max(60, min(86400, (int) $value));

            default:
                if (is_string($value)) {
                    return sanitize_text_field($value);
                }
                return $value;
        }
    }

    /**
     * Migrate legacy placement values to new vocabulary.
     *
     * @since    1.1.0
     * @param    string    $value    The placement value.
     * @return   string              The migrated value.
     */
    private static function migrate_placement_value($value)
    {
        $migration_map = [
            "bottom"       => "bottom_of_content",
            "below_footer" => "bottom_of_page",
            "shortcode"    => "manual",
        ];
        return isset($migration_map[$value]) ? $migration_map[$value] : $value;
    }

    /**
     * Get default value for an option
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without bspt_ prefix).
     * @return   mixed                     The default value, or null if not found.
     */
    public static function get_default($option_name)
    {
        return isset(self::$defaults[$option_name]) ? self::$defaults[$option_name] : null;
    }

    /**
     * Validate option value
     *
     * @since    1.0.0
     * @param    string    $option_name    The option name.
     * @param    mixed     $value          The option value.
     * @return   bool|WP_Error             True if valid, WP_Error if invalid.
     */
    public static function validate($option_name, $value)
    {
        switch ($option_name) {
            case "api_key":
                if (empty($value)) {
                    return new WP_Error("empty_api_key", __("API key cannot be empty", "botspot-wp"));
                }
                break;

            case "cache_ttl":
                if ($value < 60 || $value > 86400) {
                    return new WP_Error(
                        "invalid_cache_ttl",
                        __("Cache TTL must be between 60 and 86400 seconds", "botspot-wp"),
                    );
                }
                break;

            case "sync_post_types":
            case "inject_on_post_types":
                if (!is_array($value) || empty($value)) {
                    return new WP_Error(
                        "invalid_post_types",
                        __("At least one post type must be selected", "botspot-wp"),
                    );
                }
                break;
        }

        return true;
    }

    /**
     * Migrate legacy injection_enabled option to split toggles
     *
     * If the old injection_enabled option exists, copy its value to both
     * appendix_enabled and jsonld_enabled, then delete the old option.
     *
     * @since    1.2.0
     */
    public static function migrate_injection_toggles()
    {
        $legacy = get_option("bspt_injection_enabled");
        if ($legacy !== false) {
            $enabled = (bool) $legacy;
            if (!self::exists("appendix_enabled")) {
                self::set("appendix_enabled", $enabled);
            }
            if (!self::exists("jsonld_enabled")) {
                self::set("jsonld_enabled", $enabled);
            }
            delete_option("bspt_injection_enabled");
        }
    }

    /**
     * Remove legacy botspot_key option
     *
     * The botspot_key was a redundant copy of api_key that caused stale
     * state bugs. All code now reads api_key directly.
     *
     * @since    1.3.0
     */
    public static function migrate_remove_botspot_key()
    {
        delete_option("bspt_botspot_key");
    }

    /**
     * Get the Locus API URL from build-time constant
     *
     * @since    1.1.0
     * @return   string
     */
    public static function get_locus_api_url()
    {
        return BSPT_LOCUS_API_URL;
    }

    /**
     * Get the Connector URL from build-time constant
     *
     * @since    1.1.0
     * @return   string
     */
    public static function get_connector_url()
    {
        return BSPT_CONNECTOR_URL;
    }

}
