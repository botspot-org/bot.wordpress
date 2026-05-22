<?php
/**
 * Plugin Name: BotSpot WordPress
 * Plugin URI: https://bot.spot
 * Description: Push-based content sync and AI appendix injection. Syncs content to locus-core and renders JSON-LD + appendix.
 * Version: 2.9.2
 * Author: bot.spot Team
 * Author URI: https://bot.spot
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: botdot-wp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package BotDot_WP
 * @version 2.8.2
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
 * Fatal error handler - captures PHP fatal errors for the Developer tab.
 * Registered early so it catches errors even if the plugin fails to load.
 */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    // Only capture fatal errors
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatal_types, true)) {
        return;
    }
    // Only capture errors from our plugin
    if (strpos($error['file'], 'botdot-wp') === false && strpos($error['file'], 'botspot') === false) {
        return;
    }
    // Store in option (not transient) so it persists across the fatal redirect
    $fatal_log = get_option('botdot_wp_fatal_errors', []);
    if (!is_array($fatal_log)) {
        $fatal_log = [];
    }
    $fatal_log[] = [
        'type' => 'fatal',
        'message' => sprintf('%s in %s on line %d', $error['message'], basename($error['file']), $error['line']),
        'file' => $error['file'],
        'line' => $error['line'],
        'timestamp' => time(),
    ];
    // Keep last 20 fatal errors
    if (count($fatal_log) > 20) {
        $fatal_log = array_slice($fatal_log, -20);
    }
    update_option('botdot_wp_fatal_errors', $fatal_log, false);
});

/**
 * Plugin version.
 */
define('BOTDOT_WP_VERSION', '2.9.2');

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
 * Locus API URL (overridable via wp-config.php).
 *
 * The URL is a build-time sentinel (@LOCUS_API_URL@) that build.sh rewrites
 * to the appropriate staging or production URL based on the TARGET env var
 * or the --production flag. Source-tree default is staging so that local
 * development and zero-config builds work without any flags.
 */
if (!defined('BOTDOT_WP_LOCUS_API_URL')) {
    define('BOTDOT_WP_LOCUS_API_URL', 'https://locus-staging-api.bot.spot');
}

/**
 * Connector URL (overridable via wp-config.php).
 * Same build-time rewrite rule as BOTDOT_WP_LOCUS_API_URL.
 */
if (!defined('BOTDOT_WP_CONNECTOR_URL')) {
    define('BOTDOT_WP_CONNECTOR_URL', 'https://staging-locus-connectors.bot.spot');
}

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
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-options.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-logger.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-activator.php';

    try {
        BotDot_WP_Activator::activate();
    } catch (Exception $e) {
        error_log('BotSpot WP Activation Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());

        deactivate_plugins(plugin_basename(__FILE__));

        wp_die(
            'bot.spot WP could not be activated. Error: ' . esc_html($e->getMessage()) .
            '<br><br>Check your error log for more details.' .
            '<br><br><a href="' . admin_url('plugins.php') . '">Back to Plugins</a>'
        );
    }
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_botdot_wp() {
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-options.php';
    require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp-logger.php';
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

    if (version_compare(get_bloginfo('version'), BOTDOT_WP_MIN_WP_VERSION, '<')) {
        $errors[] = sprintf(
            __('bot.spot WP requires WordPress %s or higher. You are running version %s.', BOTDOT_WP_TEXT_DOMAIN),
            BOTDOT_WP_MIN_WP_VERSION,
            get_bloginfo('version')
        );
    }

    if (version_compare(PHP_VERSION, BOTDOT_WP_MIN_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            __('bot.spot WP requires PHP %s or higher. You are running version %s.', BOTDOT_WP_TEXT_DOMAIN),
            BOTDOT_WP_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    if (!extension_loaded('curl')) {
        $errors[] = __('bot.spot WP requires the PHP cURL extension.', BOTDOT_WP_TEXT_DOMAIN);
    }

    if (!extension_loaded('json')) {
        $errors[] = __('bot.spot WP requires the PHP JSON extension.', BOTDOT_WP_TEXT_DOMAIN);
    }

    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            echo '<div class="error"><p>';
            echo '<strong>' . __('bot.spot WP Plugin Error:', BOTDOT_WP_TEXT_DOMAIN) . '</strong><br>';
            foreach ($errors as $error) {
                echo $error . '<br>';
            }
            echo '</p></div>';
        });

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
    if (!botdot_wp_check_requirements()) {
        return;
    }

    botdot_wp_load_textdomain();

    try {
        require_once BOTDOT_WP_PLUGIN_PATH . 'includes/class-botdot-wp.php';

        $plugin = new BotDot_WP();
        $plugin->run();
    } catch (Exception $e) {
        error_log('BotSpot WP Initialization Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());

        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>bot.spot WP Error:</strong>
                    <?php echo esc_html($e->getMessage()); ?>
                </p>
                <p>Check your error log for more details.</p>
            </div>
            <?php
        });
    }
}

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
                    __('bot.spot WP plugin activated successfully! <a href="%s">Configure your settings</a> to get started.', BOTDOT_WP_TEXT_DOMAIN),
                    admin_url('admin.php?page=botdot-wp')
                );
                ?>
            </p>
        </div>
        <?php
        delete_transient('botdot_wp_activation_notice');
    }
}
add_action('admin_notices', 'botdot_wp_activation_notice');
