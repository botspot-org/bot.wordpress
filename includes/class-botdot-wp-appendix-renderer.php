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
     * Detected theme FAQ/accordion classes
     *
     * @since    0.2.0
     * @access   private
     * @var      array|null    $theme_classes    Cached theme classes.
     */
    private static $theme_classes = null;

    /**
     * Log debug message if debug mode is enabled
     *
     * @since    0.6.6
     * @access   private
     * @param    string    $message    The message to log.
     */
    private static function log_debug($message) {
        if (BotDot_WP_Options::get('debug_mode')) {
            BotDot_WP_Logger::log_debug('[AppendixRenderer] ' . $message);
        }
    }

    /**
     * Log error message (always logged)
     *
     * @since    0.6.6
     * @access   private
     * @param    string    $message    The message to log.
     */
    private static function log_error($message) {
        BotDot_WP_Logger::log_error('[AppendixRenderer] ' . $message);
    }

    /**
     * Get the default BotDot appendix classes
     *
     * @since    0.3.0
     * @access   private
     * @return   array    Default class mapping.
     */
    private static function get_default_classes() {
        return array(
            'wrapper' => 'botdot-appendix',
            'details' => 'botdot-appendix-details',
            'summary' => 'botdot-appendix-summary',
            'title' => 'botdot-appendix-title',
            'content' => 'botdot-appendix-content',
        );
    }

    /**
     * Detect theme's FAQ/accordion structure
     *
     * @since    0.2.0
     * @return   array    Array of theme-specific classes.
     */
    private static function detect_theme_classes($force_refresh = false) {
        if (!$force_refresh && self::$theme_classes !== null) {
            return self::$theme_classes;
        }

        $defaults = self::get_default_classes();

        // Get current theme
        $theme = wp_get_theme();
        $theme_name = $theme->get('Name');
        $theme_template = $theme->get_template();

        // Always use auto-detection
        $classes = self::auto_detect_theme_classes($theme_name, $theme_template);

        $classes = wp_parse_args($classes, $defaults);
        $classes = apply_filters('botdot_wp_theme_classes', $classes, $theme_name, $theme_template);

        self::$theme_classes = $classes;
        return $classes;
    }

    /**
     * Auto-detect theme FAQ/accordion classes
     *
     * @since    0.3.0
     * @access   private
     * @param    string   $theme_name       Active theme name.
     * @param    string   $theme_template   Active theme template (slug).
     * @return   array                      Auto-detected class mapping.
     */
    private static function auto_detect_theme_classes($theme_name, $theme_template) {
        $classes = self::get_default_classes();

        // Common theme patterns
        $theme_patterns = array(
            // Divi
            'Divi' => array(
                'wrapper' => 'et_pb_toggle',
                'details' => 'et_pb_toggle_content',
                'summary' => 'et_pb_toggle_title',
                'title' => 'et_pb_toggle_heading',
                'content' => 'et_pb_toggle_content',
            ),
            // Avada
            'Avada' => array(
                'wrapper' => 'fusion-accordian',
                'details' => 'fusion-panel',
                'summary' => 'panel-title',
                'title' => 'fusion-toggle-heading',
                'content' => 'panel-body',
            ),
            // Astra
            'Astra' => array(
                'wrapper' => 'ast-accordion',
                'details' => 'ast-accordion-item',
                'summary' => 'ast-accordion-header',
                'title' => 'ast-accordion-title',
                'content' => 'ast-accordion-content',
            ),
            // GeneratePress
            'GeneratePress' => array(
                'wrapper' => 'accordion-container',
                'details' => 'accordion-item',
                'summary' => 'accordion-title',
                'title' => 'accordion-heading',
                'content' => 'accordion-content',
            ),
            // OceanWP
            'OceanWP' => array(
                'wrapper' => 'oceanwp-accordion',
                'details' => 'oceanwp-accordion-item',
                'summary' => 'oceanwp-accordion-title',
                'title' => 'accordion-heading',
                'content' => 'oceanwp-accordion-content',
            ),
        );

        // Check if theme has a known pattern
        foreach ($theme_patterns as $pattern_name => $pattern_classes) {
            if (stripos($theme_name, $pattern_name) !== false || stripos($theme_template, $pattern_name) !== false) {
                $classes = array_merge($classes, $pattern_classes);
                break;
            }
        }

        return $classes;
    }

    /**
     * Get the effective theme classes after applying plugin settings
     *
     * @since    0.3.0
     * @param    bool     $force_refresh   Optional. Recalculate classes even if cached.
     * @return   array                     Class mapping used during rendering.
     */
    public static function get_theme_classes($force_refresh = false) {
        return self::detect_theme_classes($force_refresh);
    }

    /**
     * Get the raw auto-detected theme classes (ignores plugin settings)
     *
     * @since    0.3.0
     * @return   array    Class mapping discovered via detection heuristics.
     */
    public static function get_auto_detected_theme_classes() {
        $theme = wp_get_theme();
        return self::auto_detect_theme_classes($theme->get('Name'), $theme->get_template());
    }

    /**
     * Get the default BotDot appendix class mapping
     *
     * @since    0.3.0
     * @return   array    Default class mapping used when no detection applies.
     */
    public static function get_default_theme_classes() {
        return self::get_default_classes();
    }

    /**
     * Provide a summary of the current theme detection state
     *
     * @since    0.3.0
     * @return   array    Summary data for use in the admin UI.
     */
    public static function get_detection_summary() {
        $theme = wp_get_theme();
        $theme_name = $theme->get('Name');
        $theme_template = $theme->get_template();

        return array(
            'theme_name' => $theme_name,
            'theme_template' => $theme_template,
            'accordion_plugin' => self::detect_accordion_plugin(),
            'auto_classes' => self::auto_detect_theme_classes($theme_name, $theme_template),
            'effective_classes' => self::get_theme_classes(true),
            'default_classes' => self::get_default_theme_classes(),
        );
    }

    /**
     * Check if theme uses a specific accordion plugin
     *
     * @since    0.2.0
     * @return   string|false    Plugin identifier or false.
     */
    private static function detect_accordion_plugin() {
        // Check for popular accordion plugins
        $plugins = array(
            'elementor' => class_exists('\\Elementor\\Plugin'),
            'wpbakery' => class_exists('Vc_Manager'),
            'gutenberg-blocks' => function_exists('register_block_type'),
        );

        foreach ($plugins as $plugin => $active) {
            if ($active) {
                return $plugin;
            }
        }

        return false;
    }

    /**
     * Render appendix as direct content HTML
     *
     * @since    0.2.0
     * @param    array     $appendix_data    The appendix data.
     * @param    array     $args             Optional. Rendering arguments.
     * @return   string                      The rendered HTML.
     */
    public static function render($appendix_data, $args = array()) {
        self::log_debug('render() called');

        if (empty($appendix_data)) {
            self::log_error('render() received empty appendix_data');
            return '';
        }

        $data_type = gettype($appendix_data);
        $data_size = is_string($appendix_data) ? strlen($appendix_data) : count($appendix_data);
        self::log_debug(sprintf('Rendering appendix data: type=%s, size=%s', $data_type, $data_size));

        // Default arguments
        $defaults = array(
            'title' => BotDot_WP_Options::get('appendix_title', 'AI Appendix'),
            'open' => BotDot_WP_Options::get('appendix_open_default', false),
            'show_metadata' => true,
            'use_theme_classes' => true,
        );

        $args = wp_parse_args($args, $defaults);

        self::log_debug(sprintf(
            'Render args: title="%s", open=%s, use_theme_classes=%s',
            $args['title'],
            $args['open'] ? 'true' : 'false',
            $args['use_theme_classes'] ? 'true' : 'false'
        ));

        // Apply filter to allow customization
        $args = apply_filters('botdot_wp_appendix_args', $args, $appendix_data);

        // Get theme classes if enabled
        $theme_classes = $args['use_theme_classes']
            ? self::get_theme_classes()
            : self::get_default_theme_classes();

        self::log_debug(sprintf('Using theme classes: %s', json_encode($theme_classes)));

        // Start output buffering
        ob_start();

        ?>
        <!-- BotDot WP Appendix Start -->
        <div class="<?php echo esc_attr($theme_classes['content']); ?> botdot-appendix-content" id="botdot-appendix">
            <?php echo self::render_content($appendix_data, $args); ?>
        </div>
        <!-- /BotDot WP Appendix End -->
        <?php

        $html = ob_get_clean();

        if (empty($html)) {
            self::log_error('render() produced empty HTML output');
            return '';
        }

        self::log_debug(sprintf('render() produced %d bytes of HTML', strlen($html)));

        // Apply filter to allow HTML customization
        $filtered_html = apply_filters('botdot_wp_appendix_html', $html, $appendix_data, $args);

        if ($filtered_html !== $html) {
            self::log_debug(sprintf('HTML modified by botdot_wp_appendix_html filter: %d -> %d bytes', strlen($html), strlen($filtered_html)));
        }

        return $filtered_html;
    }

    /**
     * Render the appendix content
     *
     * @since    0.2.0
     * @access   private
     * @param    mixed     $data    The appendix data (HTML string or array).
     * @param    array     $args    Rendering arguments.
     * @return   string             The rendered content HTML.
     */
    private static function render_content($data, $args) {
        ob_start();

        // If data is a string, treat it as HTML and output directly
        if (is_string($data)) {
            self::log_debug(sprintf('render_content: Outputting raw HTML string (%d bytes)', strlen($data)));
            echo $data;
        }
        // Check if data has specific structure (JSON-LD)
        elseif (isset($data['@context']) && isset($data['@type'])) {
            self::log_debug(sprintf('render_content: Rendering single JSON-LD object (type: %s)', $data['@type']));
            // Render JSON-LD structured data
            echo self::render_json_ld($data);
        } elseif (is_array($data) && isset($data[0]) && isset($data[0]['@context'])) {
            self::log_debug(sprintf('render_content: Rendering array of %d JSON-LD objects', count($data)));
            // Array of JSON-LD objects
            foreach ($data as $item) {
                echo self::render_json_ld($item);
            }
        } else {
            self::log_debug('render_content: Rendering generic data');
            // Generic data rendering
            echo self::render_generic($data);
        }

        // Show metadata if enabled (only for array data)
        if (is_array($data) && $args['show_metadata'] && isset($data['_timestamp'])) {
            self::log_debug('render_content: Appending metadata');
            echo self::render_metadata($data);
        }

        $content = ob_get_clean();
        self::log_debug(sprintf('render_content: Produced %d bytes', strlen($content)));

        return $content;
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
