<?php
/**
 * Appendix renderer class for the BotDot WP plugin
 *
 * @link       https://botdot.ai
 * @since      0.2.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Appendix renderer class for the BotDot WP plugin.
 *
 * This class handles rendering appendix content as an accordion.
 * Uses semantic HTML5 with minimal styling - inherits from theme.
 *
 * @since      0.2.0
 * @package    BotDot_WP
 * @subpackage BotDot_WP/includes
 * @author     BotDot Team
 */
class BotDot_WP_Appendix_Renderer {

    /**
     * Render appendix as accordion HTML
     *
     * @since    0.2.0
     * @param    array     $appendix_data    The appendix data.
     * @param    array     $args             Optional. Rendering arguments.
     * @return   string                      The rendered HTML.
     */
    public static function render($appendix_data, $args = array()) {
        if (empty($appendix_data)) {
            return '';
        }

        // Default arguments
        $defaults = array(
            'title' => BotDot_WP_Options::get('appendix_title', 'AI Appendix'),
            'open' => BotDot_WP_Options::get('appendix_open_default', false),
            'show_metadata' => true,
        );

        $args = wp_parse_args($args, $defaults);

        // Apply filter to allow customization
        $args = apply_filters('botdot_wp_appendix_args', $args, $appendix_data);

        // Start output buffering
        ob_start();

        ?>
        <!-- BotDot WP Appendix Start -->
        <aside class="botdot-appendix" id="botdot-appendix">
            <details class="botdot-appendix-details" <?php echo $args['open'] ? 'open' : ''; ?>>
                <summary class="botdot-appendix-summary">
                    <span class="botdot-appendix-title"><?php echo esc_html($args['title']); ?></span>
                </summary>

                <div class="botdot-appendix-content">
                    <?php echo self::render_content($appendix_data, $args); ?>
                </div>
            </details>
        </aside>
        <!-- /BotDot WP Appendix End -->
        <?php

        $html = ob_get_clean();

        // Apply filter to allow HTML customization
        return apply_filters('botdot_wp_appendix_html', $html, $appendix_data, $args);
    }

    /**
     * Render the appendix content
     *
     * @since    0.2.0
     * @access   private
     * @param    array     $data    The appendix data.
     * @param    array     $args    Rendering arguments.
     * @return   string             The rendered content HTML.
     */
    private static function render_content($data, $args) {
        ob_start();

        // Check if data has specific structure (JSON-LD)
        if (isset($data['@context']) && isset($data['@type'])) {
            // Render JSON-LD structured data
            echo self::render_json_ld($data);
        } elseif (is_array($data) && isset($data[0]) && isset($data[0]['@context'])) {
            // Array of JSON-LD objects
            foreach ($data as $item) {
                echo self::render_json_ld($item);
            }
        } else {
            // Generic data rendering
            echo self::render_generic($data);
        }

        // Show metadata if enabled
        if ($args['show_metadata'] && isset($data['_timestamp'])) {
            echo self::render_metadata($data);
        }

        return ob_get_clean();
    }

    /**
     * Render JSON-LD structured data
     *
     * @since    0.2.0
     * @access   private
     * @param    array     $data    JSON-LD data.
     * @return   string             Rendered HTML.
     */
    private static function render_json_ld($data) {
        ob_start();

        $type = isset($data['@type']) ? $data['@type'] : 'Unknown';

        ?>
        <div class="botdot-appendix-item botdot-appendix-type-<?php echo esc_attr(strtolower($type)); ?>">
            <h4 class="botdot-appendix-item-type"><?php echo esc_html($type); ?></h4>

            <dl class="botdot-appendix-properties">
                <?php
                foreach ($data as $key => $value) {
                    // Skip metadata and context
                    if (in_array($key, array('@context', '@type', '_timestamp', '_requested_path', '_type'))) {
                        continue;
                    }

                    echo '<dt class="botdot-appendix-property-key">' . esc_html(self::format_key($key)) . '</dt>';
                    echo '<dd class="botdot-appendix-property-value">' . self::format_value($value) . '</dd>';
                }
                ?>
            </dl>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render generic data
     *
     * @since    0.2.0
     * @access   private
     * @param    mixed     $data    Generic data.
     * @return   string             Rendered HTML.
     */
    private static function render_generic($data) {
        if (is_array($data)) {
            ob_start();
            ?>
            <dl class="botdot-appendix-data">
                <?php
                foreach ($data as $key => $value) {
                    // Skip internal metadata
                    if (strpos($key, '_') === 0) {
                        continue;
                    }
                    echo '<dt>' . esc_html(self::format_key($key)) . '</dt>';
                    echo '<dd>' . self::format_value($value) . '</dd>';
                }
                ?>
            </dl>
            <?php
            return ob_get_clean();
        } elseif (is_string($data)) {
            return '<div class="botdot-appendix-text">' . wp_kses_post(wpautop($data)) . '</div>';
        }

        return '';
    }

    /**
     * Render metadata section
     *
     * @since    0.2.0
     * @access   private
     * @param    array     $data    Data with metadata.
     * @return   string             Rendered metadata HTML.
     */
    private static function render_metadata($data) {
        ob_start();
        ?>
        <footer class="botdot-appendix-metadata">
            <small class="botdot-appendix-meta-text">
                <?php
                if (isset($data['_timestamp'])) {
                    echo esc_html(sprintf(__('Generated: %s', 'botdot-wp'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($data['_timestamp']))));
                }
                ?>
            </small>
        </footer>
        <?php
        return ob_get_clean();
    }

    /**
     * Format a property key for display
     *
     * @since    0.2.0
     * @access   private
     * @param    string    $key    The property key.
     * @return   string            Formatted key.
     */
    private static function format_key($key) {
        // Remove @ symbol from JSON-LD keys
        $key = ltrim($key, '@');

        // Convert camelCase to Title Case
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);

        // Capitalize first letter
        return ucfirst($key);
    }

    /**
     * Format a value for display
     *
     * @since    0.2.0
     * @access   private
     * @param    mixed     $value    The value to format.
     * @return   string              Formatted value HTML.
     */
    private static function format_value($value) {
        if (is_array($value)) {
            // Nested object/array
            if (isset($value['@type'])) {
                // Nested JSON-LD object
                return '<div class="botdot-appendix-nested">' . self::render_json_ld($value) . '</div>';
            } else {
                // Simple array
                $items = array_map('esc_html', $value);
                return '<ul class="botdot-appendix-list"><li>' . implode('</li><li>', $items) . '</li></ul>';
            }
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            // URL
            return '<a href="' . esc_url($value) . '" class="botdot-appendix-link" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
        } else {
            // Plain text
            return esc_html($value);
        }
    }
}
