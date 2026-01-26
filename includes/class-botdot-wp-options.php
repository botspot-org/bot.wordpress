<?php
/**
 * Options management class for the BotDot WP plugin
 *
 * @link       https://botdot.ai
 * @since      0.1.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Options management class for the BotDot WP plugin.
 *
 * This class provides centralized methods for getting and setting plugin options
 * with proper defaults, validation, and type casting.
 *
 * @since      0.1.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Options {

    /**
     * Default plugin options
     *
     * @since    0.1.0
     * @access   private
     * @var      array    $defaults    Default option values.
     */
    private static $defaults = array(
        'mirror_domain' => '',
        'enabled' => true,
        'fetch_timeout' => 10,
        'inject_on_post_types' => array('post', 'page'),
        'exclude_page_ids' => array(),
        'debug_mode' => false,
        // Appendix settings
        'appendix_enabled' => true,
        'appendix_title' => 'AI Appendix',
        'appendix_position' => 'bottom',
        'appendix_auto_placement' => 'above_footer',
        'appendix_open_default' => false,
        'appendix_on_post_types' => array('post', 'page'),
        'page_injection_status' => array(), // [page_id => bool] map for enabled pages
        // Cache busting
        'css_cache_buster' => 0,  // Unix timestamp for CSS cache invalidation
        // Smart cache settings
        'smart_cache_enabled' => true,
        'smart_cache_check_path' => '/',      // Path to check for changes
        'content_hash_appendix' => '',        // SHA256 of appendix HTML
        'content_hash_jsonld' => '',          // SHA256 of JSON-LD
    );

    /**
     * Get plugin option with default fallback
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without botdot_wp_ prefix).
     * @param    mixed     $default        Optional. Default value if option doesn't exist.
     * @return   mixed                     The option value.
     */
    public static function get($option_name, $default = null) {
        // Use provided default, or fall back to class default, or null
        if ($default === null) {
            $default = isset(self::$defaults[$option_name]) ? self::$defaults[$option_name] : null;
        }

        $value = get_option('botdot_wp_' . $option_name, $default);

        // Type casting for specific options
        return self::cast_option_value($option_name, $value);
    }

    /**
     * Set plugin option
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without botdot_wp_ prefix).
     * @param    mixed     $value          The option value.
     * @return   bool                      True if the value was updated, false otherwise.
     */
    public static function set($option_name, $value) {
        // Validate and sanitize value
        $value = self::sanitize_option_value($option_name, $value);

        return update_option('botdot_wp_' . $option_name, $value);
    }

    /**
     * Delete plugin option
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without botdot_wp_ prefix).
     * @return   bool                      True if the option was deleted, false otherwise.
     */
    public static function delete($option_name) {
        return delete_option('botdot_wp_' . $option_name);
    }

    /**
     * Get multiple options at once
     *
     * @since    0.1.0
     * @param    array     $option_names    Array of option names (without botdot_wp_ prefix).
     * @return   array                      Associative array of option values.
     */
    public static function get_multiple($option_names) {
        $options = array();

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
    public static function set_multiple($options) {
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
     * @param    string    $option_name    The option name (without botdot_wp_ prefix).
     * @return   bool                      True if option exists, false otherwise.
     */
    public static function exists($option_name) {
        return get_option('botdot_wp_' . $option_name) !== false;
    }

    /**
     * Get all plugin options
     *
     * @since    0.1.0
     * @return   array    All plugin options with their current values.
     */
    public static function get_all() {
        $options = array();

        foreach (array_keys(self::$defaults) as $option_name) {
            $options[$option_name] = self::get($option_name);
        }

        return $options;
    }

    /**
     * Reset option to default value
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without botdot_wp_ prefix).
     * @return   bool                      True if option was reset successfully.
     */
    public static function reset_to_default($option_name) {
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
    public static function reset_all_to_defaults() {
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
     * @since    0.1.0
     * @access   private
     * @param    string    $option_name    The option name.
     * @param    mixed     $value          The option value.
     * @return   mixed                     The cast value.
     */
    private static function cast_option_value($option_name, $value) {
        switch ($option_name) {
            case 'enabled':
            case 'debug_mode':
            case 'appendix_enabled':
            case 'appendix_open_default':
                return (bool) $value;

            case 'fetch_timeout':
                return (int) $value;

            case 'inject_on_post_types':
            case 'exclude_page_ids':
            case 'appendix_on_post_types':
                return is_array($value) ? $value : array();

            case 'mirror_domain':
            case 'appendix_title':
            case 'appendix_position':
                return trim($value);

            default:
                return $value;
        }
    }

    /**
     * Sanitize option value before saving
     *
     * @since    0.1.0
     * @access   public
     * @param    string    $option_name    The option name.
     * @param    mixed     $value          The option value.
     * @return   mixed                     The sanitized value.
     */
    public static function sanitize_option_value($option_name, $value) {
        switch ($option_name) {
            case 'mirror_domain':
                // Remove http/https and trailing slash
                $value = trim($value);
                $value = preg_replace('#^https?://#', '', $value);
                $value = rtrim($value, '/');
                return sanitize_text_field($value);

            case 'enabled':
            case 'debug_mode':
            case 'appendix_enabled':
            case 'appendix_open_default':
                return (bool) $value;

            case 'fetch_timeout':
                return max(1, min(60, (int) $value)); // Between 1 and 60 seconds

            case 'appendix_title':
                return sanitize_text_field($value);

            case 'appendix_position':
                $allowed_positions = array('bottom', 'shortcode');
                return in_array($value, $allowed_positions) ? $value : 'bottom';

            case 'appendix_auto_placement':
                $allowed_placements = array('above_footer', 'bottom');
                return in_array($value, $allowed_placements) ? $value : 'above_footer';

            case 'inject_on_post_types':
            case 'appendix_on_post_types':
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return array();

            case 'exclude_page_ids':
                if (is_array($value)) {
                    return array_map('absint', $value);
                }
                return array();

            case 'page_injection_status':
                if (!is_array($value)) {
                    return array();
                }
                // Ensure all keys are integers (page IDs) and values are booleans
                $sanitized = array();
                foreach ($value as $page_id => $enabled) {
                    $page_id = absint($page_id);
                    if ($page_id > 0) {
                        $sanitized[$page_id] = (bool) $enabled;
                    }
                }
                return $sanitized;

            default:
                if (is_string($value)) {
                    return sanitize_text_field($value);
                }
                return $value;
        }
    }

    /**
     * Get default value for an option
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name (without botdot_wp_ prefix).
     * @return   mixed                     The default value, or null if not found.
     */
    public static function get_default($option_name) {
        return isset(self::$defaults[$option_name]) ? self::$defaults[$option_name] : null;
    }

    /**
     * Validate option value
     *
     * @since    0.1.0
     * @param    string    $option_name    The option name.
     * @param    mixed     $value          The option value.
     * @return   bool|WP_Error             True if valid, WP_Error if invalid.
     */
    public static function validate($option_name, $value) {
        switch ($option_name) {
            case 'mirror_domain':
                if (empty($value)) {
                    return new WP_Error('empty_mirror_domain', __('Mirror domain cannot be empty', 'botdot-wp'));
                }
                // Basic domain validation (allows optional port like localhost:5000)
                if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-_.]+[a-zA-Z0-9](:\d+)?$/', $value)) {
                    return new WP_Error('invalid_mirror_domain', __('Invalid mirror domain format', 'botdot-wp'));
                }
                break;

            case 'fetch_timeout':
                if ($value < 1 || $value > 60) {
                    return new WP_Error('invalid_timeout', __('Timeout must be between 1 and 60 seconds', 'botdot-wp'));
                }
                break;

            case 'inject_on_post_types':
                if (!is_array($value) || empty($value)) {
                    return new WP_Error('invalid_post_types', __('At least one post type must be selected', 'botdot-wp'));
                }
                break;
        }

        return true;
    }
}
