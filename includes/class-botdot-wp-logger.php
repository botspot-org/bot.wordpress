<?php
/**
 * Logger class for the BotDot WP plugin
 *
 * @link       https://bot.spot
 * @since      0.1.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
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
class BotDot_WP_Logger
{
    /**
     * Maximum number of errors to store in transient
     *
     * @since    0.1.0
     * @access   private
     * @var      int    $max_errors    Maximum number of errors to keep.
     */
    private static $max_errors = 50;

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
     * Errors are ALWAYS logged to PHP error_log (regardless of WP_DEBUG)
     * to ensure production issues are visible.
     *
     * @since    0.1.0
     * @param    string    $message    The error message.
     * @param    array     $context    Optional. Additional context data.
     */
    public static function log_error($message, $context = [])
    {
        // ALWAYS log errors to PHP error_log - errors should never be silent
        error_log("[BotSpot WP] ERROR: " . $message);
        if (!empty($context)) {
            error_log("[BotSpot WP] Context: " . print_r($context, true));
        }

        // Store in transient for admin display
        self::store_error($message, "error", $context);
    }

    /**
     * Log a debug message
     *
     * @since    0.1.0
     * @param    string    $message    The debug message.
     * @param    array     $context    Optional. Additional context data.
     */
    public static function log_debug($message, $context = [])
    {
        // Only log if debug mode is enabled in plugin settings
        if (!BotDot_WP_Options::get("debug_mode")) {
            return;
        }

        // Write to PHP error_log
        error_log("[BotSpot WP] DEBUG: " . $message);
        if (!empty($context)) {
            error_log("[BotSpot WP] Context: " . print_r($context, true));
        }

        // Store in transient for display in plugin dashboard
        self::store_error($message, "debug", $context);
    }

    /**
     * Log a warning message
     *
     * Warnings are ALWAYS logged to PHP error_log (regardless of WP_DEBUG)
     * to ensure production issues are visible.
     *
     * @since    0.1.0
     * @param    string    $message    The warning message.
     * @param    array     $context    Optional. Additional context data.
     */
    public static function log_warning($message, $context = [])
    {
        // ALWAYS log warnings to PHP error_log - warnings should never be silent
        error_log("[BotSpot WP] WARNING: " . $message);
        if (!empty($context)) {
            error_log("[BotSpot WP] Context: " . print_r($context, true));
        }

        // Store in transient for admin display
        self::store_error($message, "warning", $context);
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
    private static function store_error($message, $type = "error", $context = [])
    {
        $errors = get_transient("botdot_wp_recent_errors");

        if (!is_array($errors)) {
            $errors = [];
        }

        // Add new error
        $errors[] = [
            "message" => $message,
            "type" => $type,
            "context" => $context,
            "timestamp" => time(),
        ];

        // Keep only the most recent errors
        if (count($errors) > self::$max_errors) {
            $errors = array_slice($errors, -self::$max_errors);
        }

        // Store in transient
        set_transient("botdot_wp_recent_errors", $errors, self::$transient_expiration);
    }

    /**
     * Get recent errors from transient
     *
     * @since    0.1.0
     * @param    int      $limit    Optional. Maximum number of errors to return.
     * @return   array              Array of recent errors.
     */
    public static function get_recent_errors($limit = null)
    {
        $errors = get_transient("botdot_wp_recent_errors");

        if (!is_array($errors)) {
            return [];
        }

        // Sort by timestamp, newest first
        usort($errors, function ($a, $b) {
            return $b["timestamp"] - $a["timestamp"];
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
    public static function clear_errors()
    {
        // Also clear fatal errors captured by shutdown handler
        delete_option('botdot_wp_fatal_errors');
        return delete_transient("botdot_wp_recent_errors");
    }

    /**
     * Get error count
     *
     * @since    0.1.0
     * @return   int    Number of stored errors.
     */
    public static function get_error_count()
    {
        $errors = self::get_recent_errors();
        return count($errors);
    }

    /**
     * Check if there are any recent errors
     *
     * @since    0.1.0
     * @return   bool    True if there are errors, false otherwise.
     */
    public static function has_errors()
    {
        return self::get_error_count() > 0;
    }

    /**
     * Get the last error
     *
     * @since    0.1.0
     * @return   array|null    The last error array, or null if no errors.
     */
    public static function get_last_error()
    {
        $errors = self::get_recent_errors(1);
        return !empty($errors) ? $errors[0] : null;
    }

    /**
     * Get logs shaped for the Developer tab log viewer.
     *
     * Returns entries sorted newest-first with:
     * - timestamp: ISO 8601 string
     * - time_display: HH:MM:SS.mmm for the viewer column
     * - level: "info" | "warn" | "error"
     * - source: inferred short tag (sync, runtime, api, cache, auth, etc.)
     * - message: the log message
     *
     * @since    2.2.0
     * @param    string    $level_filter    "all" | "info" | "warning" | "error"
     * @return   array
     */
    public static function get_logs_for_viewer($level_filter = "all")
    {
        $raw = self::get_recent_errors();

        // Merge in fatal errors captured by shutdown handler
        $fatal_errors = get_option('botdot_wp_fatal_errors', []);
        if (is_array($fatal_errors)) {
            foreach ($fatal_errors as $fatal) {
                $raw[] = [
                    'message' => isset($fatal['message']) ? $fatal['message'] : 'Unknown fatal error',
                    'type' => 'error',
                    'context' => ['source' => 'fatal'],
                    'timestamp' => isset($fatal['timestamp']) ? $fatal['timestamp'] : time(),
                ];
            }
        }

        $result = [];

        foreach ($raw as $entry) {
            // Normalize level
            $type = isset($entry["type"]) ? $entry["type"] : "debug";
            if ($type === "error") {
                $level = "error";
            } elseif ($type === "warning" || $type === "warn") {
                $level = "warn";
            } else {
                $level = "info";
            }

            // Apply filter
            if ($level_filter !== "all") {
                $requested = $level_filter === "warning" ? "warn" : $level_filter;
                if ($level !== $requested) {
                    continue;
                }
            }

            // Infer source tag from context or message prefix
            $source = "system";
            $context = isset($entry["context"]) && is_array($entry["context"]) ? $entry["context"] : [];
            if (isset($context["source"]) && is_string($context["source"])) {
                $source = $context["source"];
            } else {
                // Best-effort guess from message
                $msg_lower = strtolower((string) $entry["message"]);
                if (strpos($msg_lower, "sync") !== false || strpos($msg_lower, "push") !== false) {
                    $source = "sync";
                } elseif (strpos($msg_lower, "appendix") !== false || strpos($msg_lower, "inject") !== false || strpos($msg_lower, "runtime") !== false) {
                    $source = "runtime";
                } elseif (strpos($msg_lower, "http") !== false || strpos($msg_lower, "api") !== false || strpos($msg_lower, "endpoint") !== false) {
                    $source = "api";
                } elseif (strpos($msg_lower, "cache") !== false || strpos($msg_lower, "transient") !== false) {
                    $source = "cache";
                } elseif (strpos($msg_lower, "auth") !== false || strpos($msg_lower, "key") !== false || strpos($msg_lower, "token") !== false) {
                    $source = "auth";
                } elseif (strpos($msg_lower, "webhook") !== false) {
                    $source = "webhook";
                } elseif (strpos($msg_lower, "seo") !== false || strpos($msg_lower, "yoast") !== false || strpos($msg_lower, "rankmath") !== false) {
                    $source = "seo";
                }
            }

            $ts = isset($entry["timestamp"]) ? (int) $entry["timestamp"] : time();

            $result[] = [
                "timestamp" => gmdate("c", $ts),
                "time_display" => gmdate("H:i:s", $ts) . sprintf(".%03d", 0),
                "level" => $level,
                "source" => $source,
                "message" => isset($entry["message"]) ? (string) $entry["message"] : "",
            ];
        }

        return $result;
    }
}
