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

<style>
    .botdot-theme-collapsible {
        margin-top: 16px;
        border: 1px solid #dcdcde;
        border-radius: 4px;
        background: #ffffff;
    }

    .botdot-theme-collapsible summary {
        margin: 0;
        padding: 12px 16px;
        cursor: pointer;
        font-weight: 600;
        list-style: none;
    }

    .botdot-theme-collapsible summary:focus {
        outline: 2px solid #2271b1;
        outline-offset: 2px;
    }

    .botdot-theme-collapsible summary::-webkit-details-marker {
        display: none;
    }

    .botdot-theme-collapsible summary::after {
        content: '+';
        float: right;
        font-weight: 400;
    }

    .botdot-theme-collapsible[open] summary::after {
        content: '-';
    }

    .botdot-theme-collapsible table.form-table {
        margin-top: 0;
    }

    .botdot-theme-class-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-top: 8px;
    }

    .botdot-theme-class-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .botdot-theme-class-field__label {
        font-weight: 600;
    }

    .botdot-theme-detection {
        margin-top: 16px;
        padding: 16px;
        border: 1px solid #dcdcde;
        border-radius: 4px;
        background: #ffffff;
    }

    .botdot-theme-detection__table {
        width: 100%;
        border-collapse: collapse;
        margin: 12px 0;
    }

    .botdot-theme-detection__table th,
    .botdot-theme-detection__table td {
        padding: 6px 8px;
        border-bottom: 1px solid #e3e3e3;
        text-align: left;
    }

    .botdot-theme-detection__status p {
        margin: 0 0 4px;
    }

    .botdot-theme-preview {
        margin-top: 16px;
        padding: 16px;
        border: 1px dashed #ccd0d4;
        border-radius: 4px;
        background: #f6f7f7;
    }

    .botdot-theme-preview h4 {
        margin-top: 0;
    }

    .botdot-theme-preview details {
        margin-top: 8px;
    }

    .botdot-inline-status {
        margin-left: 8px;
    }

    .botdot-inline-status.is-success {
        color: #008a20;
    }

    .botdot-inline-status.is-error {
        color: #d63638;
    }
</style>

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

    // Collapsible Theme & Styling section
    var themeIntro = $('#botdot-theme-section-intro');
    if (themeIntro.length) {
        var themeTable = themeIntro.next('table.form-table');
        if (themeTable.length) {
            var manualHasValues = $('#botdot-theme-class-fields input').filter(function() {
                return $(this).val().trim().length > 0;
            }).length > 0;
            var autoEnabledInit = $('#botdot-wp-theme-classes-enabled').is(':checked');
            var details = $('<details class="botdot-theme-collapsible"></details>');
            var summaryText = themeIntro.data('summary') || themeIntro.prev('h2').text();
            var summary = $('<summary class="botdot-theme-collapsible__summary"></summary>').text(summaryText);

            details.append(summary);
            themeIntro.before(details);
            details.append(themeIntro).append(themeTable);
            details.prop('open', manualHasValues || !autoEnabledInit);
        }
    }

    // Theme detection UI
    var detectionContainer = $('#botdot-theme-detection');
    if (detectionContainer.length) {
        var classKeys = ['wrapper', 'details', 'summary', 'title', 'content'];
        var preview = $('#botdot-theme-preview');
        var defaultClasses = preview.data('default-classes') || {};
        if (typeof defaultClasses === 'string') {
            try {
                defaultClasses = defaultClasses ? JSON.parse(defaultClasses) : {};
            } catch (err) {
                defaultClasses = {};
            }
        }

        var autoClasses = {};
        try {
            autoClasses = JSON.parse($('#botdot-wp-auto-classes').val() || '{}');
        } catch (err) {
            autoClasses = {};
        }

        var autoCheckbox = $('#botdot-wp-theme-classes-enabled');
        var manualInputs = $('#botdot-theme-class-fields input');
        var resultEl = $('#botdot-wp-detect-theme-result');
        var detectButton = $('#botdot-wp-detect-theme');
        var statusAuto = detectionContainer.find('[data-status-role="auto"]');
        var statusCustom = detectionContainer.find('[data-status-role="custom"]');

        var messages = {
            autoOn: detectionContainer.data('status-auto-on') || '',
            autoOff: detectionContainer.data('status-auto-off') || '',
            customOn: detectionContainer.data('status-custom-on') || '',
            customOff: detectionContainer.data('status-custom-off') || '',
            detectSuccess: detectionContainer.data('detect-success') || '',
            detectFailure: detectionContainer.data('detect-failure') || '',
            detectError: detectionContainer.data('detect-error') || ''
        };

        function getManualClasses() {
            var values = {};
            manualInputs.each(function() {
                var key = $(this).data('class-key');
                values[key] = $(this).val().trim();
            });
            return values;
        }

        function hasCustomClasses(manual) {
            for (var key in manual) {
                if (manual.hasOwnProperty(key) && manual[key]) {
                    return true;
                }
            }
            return false;
        }

        function computeEffectiveClasses() {
            var manual = getManualClasses();
            var effective = {};
            var autoEnabled = autoCheckbox.is(':checked');

            classKeys.forEach(function(key) {
                if (!autoEnabled) {
                    effective[key] = manual[key] || defaultClasses[key] || '';
                } else if (manual[key]) {
                    effective[key] = manual[key];
                } else {
                    effective[key] = autoClasses[key] || defaultClasses[key] || '';
                }
            });

            return {
                effective: effective,
                manual: manual,
                autoEnabled: autoEnabled
            };
        }

        function updateStatus(manual, autoEnabled) {
            if (statusAuto.length) {
                statusAuto.text(autoEnabled ? messages.autoOn : messages.autoOff);
            }
            if (statusCustom.length) {
                statusCustom.text(hasCustomClasses(manual) ? messages.customOn : messages.customOff);
            }
        }

        function updateClassTable(effective) {
            classKeys.forEach(function(key) {
                detectionContainer.find('.botdot-theme-class-value[data-class-key="' + key + '"]').text(effective[key] || '');
            });
        }

        function updatePreview(effective) {
            classKeys.forEach(function(key) {
                var element = $('#botdot-theme-preview [data-class-key="' + key + '"]');
                if (!element.length) {
                    return;
                }
                var baseClass = element.data('preview-base') || '';
                var combined = [];

                if (effective[key]) {
                    combined = combined.concat(String(effective[key]).split(/\s+/));
                }
                if (defaultClasses[key]) {
                    combined = combined.concat(String(defaultClasses[key]).split(/\s+/));
                }

                var unique = [];
                combined.forEach(function(item) {
                    item = item.trim();
                    if (item && unique.indexOf(item) === -1) {
                        unique.push(item);
                    }
                });

                var classList = unique.join(' ');
                var finalClass = $.trim((classList + ' ' + baseClass).replace(/\s+/g, ' '));
                element.attr('class', finalClass);
            });
        }

        function refreshView() {
            var state = computeEffectiveClasses();
            updateStatus(state.manual, state.autoEnabled);
            updateClassTable(state.effective);
            updatePreview(state.effective);
        }

        manualInputs.on('input', refreshView);
        autoCheckbox.on('change', refreshView);

        detectButton.on('click', function(e) {
            e.preventDefault();

            var button = $(this);
            var detectNonce = detectionContainer.data('detect-nonce') || '';
            var originalText = button.data('original-text') || button.text();
            var detectingText = button.data('detecting-text') || originalText;

            button.data('original-text', originalText);
            button.prop('disabled', true).text(detectingText);
            resultEl.removeClass('is-success is-error').text('');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'botdot_wp_detect_theme_classes',
                    nonce: detectNonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.classes) {
                        autoClasses = response.data.classes || {};
                        detectionContainer.find('#botdot-wp-auto-classes').val(JSON.stringify(autoClasses));
                        classKeys.forEach(function(key) {
                            if (response.data.classes.hasOwnProperty(key)) {
                                $('#botdot-wp-custom-theme-' + key).val(response.data.classes[key]);
                            }
                        });
                        refreshView();
                        resultEl.addClass('is-success').removeClass('is-error').text(messages.detectSuccess);
                    } else {
                        var failMessage = messages.detectFailure;
                        if (response.data && response.data.message) {
                            failMessage = response.data.message;
                        }
                        resultEl.addClass('is-error').removeClass('is-success').text(failMessage);
                    }
                },
                error: function() {
                    resultEl.addClass('is-error').removeClass('is-success').text(messages.detectError);
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        refreshView();
    }
});
</script>
