<?php
/**
 * Provide a admin area view for the plugin settings
 *
 * @link       https://botdot.ai
 * @since      0.1.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <?php if (BotDot_WP_Logger::has_errors()) : ?>
        <div class="notice notice-warning">
            <h3><?php _e('Recent Errors', 'botdot-wp'); ?></h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach (BotDot_WP_Logger::get_recent_errors(5) as $error) : ?>
                    <li>
                        <strong><?php echo esc_html(ucfirst($error['type'])); ?>:</strong>
                        <?php echo esc_html($error['message']); ?>
                        <em>(<?php echo esc_html(human_time_diff($error['timestamp'], current_time('timestamp'))); ?> ago)</em>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <button type="button" id="botdot-wp-clear-errors" class="button">
                    <?php _e('Clear Errors', 'botdot-wp'); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php
        settings_fields('botdot_wp_settings');
        do_settings_sections('botdot-wp');
        submit_button();
        ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test connection button
    $('#botdot-wp-test-connection').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var result = $('#botdot-wp-test-result');

        button.prop('disabled', true).text('<?php _e('Testing...', 'botdot-wp'); ?>');
        result.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_test_connection',
                nonce: '<?php echo wp_create_nonce('botdot_wp_test_connection'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                result.html('<span style="color: red;">✗ <?php _e('Request failed', 'botdot-wp'); ?></span>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Test Connection', 'botdot-wp'); ?>');
            }
        });
    });

    // Clear errors button
    $('#botdot-wp-clear-errors').on('click', function(e) {
        e.preventDefault();
        var button = $(this);

        button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_clear_errors',
                nonce: '<?php echo wp_create_nonce('botdot_wp_clear_errors'); ?>'
            },
            success: function() {
                location.reload();
            },
            error: function() {
                alert('<?php _e('Failed to clear errors', 'botdot-wp'); ?>');
                button.prop('disabled', false);
            }
        });
    });
});
</script>
