<?php
/**
 * Plugin Name: BotSpot WordPress
 * Plugin URI: https://bot.spot
 * Description: Server-side JSON-LD injection from mirror domain for AI discoverability. Fetches and injects JSON-LD from a configurable mirror domain into page headers.
 * Version: 0.6.0
 * Author: BotSpot Team
 * Author URI: https://bot.spot
 * License: Proprietary
 * License URI: https://bot.spot/license
 * Text Domain: botdot-wp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package BotDot_WP
 * @version 0.6.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin version.
 * Start at version 0.1.0 and use SemVer - https://semver.org
 */
define('BOTDOT_WP_VERSION', '0.6.0');

/**
 * Plugin file path
 */
define('BOTDOT_WP_PLUGIN_FILE', __FILE__);

/**
 * Plugin directory path
 */
define('BOTDOT_WP_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL
 */
define('BOTDOT_WP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin basename
 */
define('BOTDOT_WP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin text domain for translations
 */
define('BOTDOT_WP_TEXT_DOMAIN', 'botdot-wp');

/**
 * Minimum WordPress version required
 */
define('BOTDOT_WP_MIN_WP_VERSION', '5.0');

/**
 * Minimum PHP version required
 */
define('BOTDOT_WP_MIN_PHP_VERSION', '7.4');

/**
 * The code that runs during plugin activation.
 */
function activate_botdot_wp() {
    // Load required classes for activation
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-options.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-logger.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-cache-clearer.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-activator.php';

    try {
        BotDot_WP_Activator::activate();
    } catch (Exception $e) {
        // Log activation error
        error_log('BotSpot WP Activation Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());

        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));

        // Show error to user
        wp_die(
            'BotSpot WP could not be activated. Error: ' . esc_html($e->getMessage()) .
            '<br><br>Check your error log for more details.' .
            '<br><br><a href="' . admin_url('plugins.php') . '">Back to Plugins</a>'
        );
    }
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_botdot_wp() {
    // Load required classes for deactivation
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-options.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-logger.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-cache-clearer.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-deactivator.php';

    try {
        BotDot_WP_Deactivator::deactivate();
    } catch (Exception $e) {
        error_log('BotSpot WP Deactivation Error: ' . $e->getMessage());
    }
}

/**
 * Register activation and deactivation hooks
 */
register_activation_hook(__FILE__, 'activate_botdot_wp');
register_deactivation_hook(__FILE__, 'deactivate_botdot_wp');

/**
 * Check system requirements before loading the plugin
 */
function botdot_wp_check_requirements() {
    $errors = array();

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), BOTDOT_WP_MIN_WP_VERSION, '<')) {
        $errors[] = sprintf(
            __('BotSpot WP requires WordPress %s or higher. You are running version %s.', BOTDOT_WP_TEXT_DOMAIN),
            BOTDOT_WP_MIN_WP_VERSION,
            get_bloginfo('version')
        );
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, BOTDOT_WP_MIN_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            __('BotSpot WP requires PHP %s or higher. You are running version %s.', BOTDOT_WP_TEXT_DOMAIN),
            BOTDOT_WP_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    // Check for required PHP extensions
    if (!extension_loaded('curl')) {
        $errors[] = __('BotSpot WP requires the PHP cURL extension.', BOTDOT_WP_TEXT_DOMAIN);
    }

    if (!extension_loaded('json')) {
        $errors[] = __('BotSpot WP requires the PHP JSON extension.', BOTDOT_WP_TEXT_DOMAIN);
    }

    // If there are errors, deactivate plugin and show admin notice
    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            echo '<div class="error"><p>';
            echo '<strong>' . __('BotSpot WP Plugin Error:', BOTDOT_WP_TEXT_DOMAIN) . '</strong><br>';
            foreach ($errors as $error) {
                echo $error . '<br>';
            }
            echo '</p></div>';
        });

        // Deactivate the plugin
        add_action('admin_init', function() {
            deactivate_plugins(plugin_basename(__FILE__));
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        });

        return false;
    }

    return true;
}

/**
 * Load plugin text domain for internationalization
 */
function botdot_wp_load_textdomain() {
    load_plugin_textdomain(
        BOTDOT_WP_TEXT_DOMAIN,
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

/**
 * Initialize the plugin
 */
function botdot_wp_init() {
    // Check requirements first
    if (!botdot_wp_check_requirements()) {
        return;
    }

    // Load text domain
    botdot_wp_load_textdomain();

    try {
        // Include the main plugin class
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp.php';

        // Run the plugin
        $plugin = new BotDot_WP();
        $plugin->run();
    } catch (Exception $e) {
        // Log initialization error
        error_log('BotSpot WP Initialization Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());

        // Show admin notice
        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>BotSpot WP Error:</strong>
                    <?php echo esc_html($e->getMessage()); ?>
                </p>
                <p>Check your error log for more details.</p>
            </div>
            <?php
        });
    }
}

/**
 * Begin execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
add_action('plugins_loaded', 'botdot_wp_init');

/**
 * Admin notice for successful activation
 */
function botdot_wp_activation_notice() {
    if (get_transient('botdot_wp_activation_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    __('BotSpot WP plugin activated successfully! <a href="%s">Configure your settings</a> to start injecting JSON-LD.', BOTDOT_WP_TEXT_DOMAIN),
                    admin_url('options-general.php?page=botdot-wp')
                );
                ?>
            </p>
        </div>
        <?php
        delete_transient('botdot_wp_activation_notice');
    }
}
add_action('admin_notices', 'botdot_wp_activation_notice');
