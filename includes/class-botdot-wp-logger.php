<?php
/**
 * Logger class for the BotDot WP plugin
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
 * Logger class for the BotDot WP plugin.
 *
 * This class handles error logging and stores recent errors
 * for display in admin notices.
 *
 * @since      0.1.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Logger {

    /**
     * Maximum number of errors to store in transient
     *
     * @since    0.1.0
     * @access   private
     * @var      int    $max_errors    Maximum number of errors to keep.
     */
    private static $max_errors = 10;

    /**
     * Transient expiration time (in seconds)
     *
     * @since    0.1.0
     * @access   private
     * @var      int    $transient_expiration    Expiration time in seconds.
     */
    private static $transient_expiration = 3600; // 1 hour

    /**
     * Log an error message
     *
     * @since    0.1.0
     * @param    string    $message    The error message.
     * @param    array     $context    Optional. Additional context data.
     */
    public static function log_error($message, $context = array()) {
        // Log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BotDot WP] ERROR: ' . $message);
            if (!empty($context)) {
                error_log('[BotDot WP] Context: ' . print_r($context, true));
            }
        }

        // Store in transient for admin display
        self::store_error($message, 'error', $context);
    }

    /**
     * Log a debug message
     *
     * @since    0.1.0
     * @param    string    $message    The debug message.
     * @param    array     $context    Optional. Additional context data.
     */
    public static function log_debug($message, $context = array()) {
        // Only log if debug mode is enabled
        if (!BotDot_WP_Options::get('debug_mode')) {
            return;
        }

        // Log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BotDot WP] DEBUG: ' . $message);
            if (!empty($context)) {
                error_log('[BotDot WP] Context: ' . print_r($context, true));
            }
        }
    }

    /**
     * Log a warning message
     *
     * @since    0.1.0
     * @param    string    $message    The warning message.
     * @param    array     $context    Optional. Additional context data.
     */
    public static function log_warning($message, $context = array()) {
        // Log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BotDot WP] WARNING: ' . $message);
            if (!empty($context)) {
                error_log('[BotDot WP] Context: ' . print_r($context, true));
            }
        }

        // Store in transient for admin display
        self::store_error($message, 'warning', $context);
    }

    /**
     * Store an error in transient for admin display
     *
     * @since    0.1.0
     * @access   private
     * @param    string    $message    The error message.
     * @param    string    $type       The error type (error, warning, info).
     * @param    array     $context    Optional. Additional context data.
     */
    private static function store_error($message, $type = 'error', $context = array()) {
        $errors = get_transient('botdot_wp_recent_errors');

        if (!is_array($errors)) {
            $errors = array();
        }

        // Add new error
        $errors[] = array(
            'message' => $message,
            'type' => $type,
            'context' => $context,
            'timestamp' => current_time('timestamp'),
        );

        // Keep only the most recent errors
        if (count($errors) > self::$max_errors) {
            $errors = array_slice($errors, -self::$max_errors);
        }

        // Store in transient
        set_transient('botdot_wp_recent_errors', $errors, self::$transient_expiration);
    }

    /**
     * Get recent errors from transient
     *
     * @since    0.1.0
     * @param    int      $limit    Optional. Maximum number of errors to return.
     * @return   array              Array of recent errors.
     */
    public static function get_recent_errors($limit = null) {
        $errors = get_transient('botdot_wp_recent_errors');

        if (!is_array($errors)) {
            return array();
        }

        // Sort by timestamp, newest first
        usort($errors, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        // Apply limit if specified
        if ($limit !== null && $limit > 0) {
            $errors = array_slice($errors, 0, $limit);
        }

        return $errors;
    }

    /**
     * Clear all stored errors
     *
     * @since    0.1.0
     * @return   bool    True if errors were cleared, false otherwise.
     */
    public static function clear_errors() {
        return delete_transient('botdot_wp_recent_errors');
    }

    /**
     * Get error count
     *
     * @since    0.1.0
     * @return   int    Number of stored errors.
     */
    public static function get_error_count() {
        $errors = self::get_recent_errors();
        return count($errors);
    }

    /**
     * Check if there are any recent errors
     *
     * @since    0.1.0
     * @return   bool    True if there are errors, false otherwise.
     */
    public static function has_errors() {
        return self::get_error_count() > 0;
    }

    /**
     * Get the last error
     *
     * @since    0.1.0
     * @return   array|null    The last error array, or null if no errors.
     */
    public static function get_last_error() {
        $errors = self::get_recent_errors(1);
        return !empty($errors) ? $errors[0] : null;
    }
}
