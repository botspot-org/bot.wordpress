<?php
/**
 * Provide a admin area view for the plugin settings with tabbed interface
 *
 * @link       https://botdot.ai
 * @since      0.3.0
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current tab (default to 'api')
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';

// Load common data needed for both tabs
$selected_post_types = BotDot_WP_Options::get('inject_on_post_types', array('post', 'page'));
$injection_status = BotDot_WP_Options::get('page_injection_status', array());
$post_types = get_post_types(array('public' => true), 'objects');

// For Pages tab: prepare pagination and query
if ($active_tab === 'pages') {

    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Query args
    $args = array(
        'post_type' => !empty($selected_post_types) ? $selected_post_types : 'post',
        'posts_per_page' => $per_page,
        'paged' => $current_page,
        'post_status' => array('publish', 'draft', 'pending', 'future'),
        'orderby' => 'modified',
        'order' => 'DESC',
    );

    if (!empty($search)) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);
    $total_pages = $query->max_num_pages;
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

    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=botdot-wp&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
            <?php _e('API & Connection', 'botdot-wp'); ?>
        </a>
        <a href="?page=botdot-wp&tab=pages" class="nav-tab <?php echo $active_tab === 'pages' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Pages & Injection', 'botdot-wp'); ?>
        </a>
    </h2>

    <!-- Single Form for All Settings -->
    <form method="post" action="options.php" id="botdot-wp-settings-form">
        <?php settings_fields('botdot_wp_settings'); ?>

        <?php if ($active_tab === 'api') : ?>
            <!-- Hidden fields to preserve Pages tab settings when saving from API tab -->
            <?php
            $inject_post_types = BotDot_WP_Options::get('inject_on_post_types', array('post', 'page'));
            foreach ($inject_post_types as $pt) : ?>
                <input type="hidden" name="botdot_wp_inject_on_post_types[]" value="<?php echo esc_attr($pt); ?>">
            <?php endforeach; ?>

            <?php
            $appendix_post_types = BotDot_WP_Options::get('appendix_on_post_types', array('post', 'page'));
            foreach ($appendix_post_types as $pt) : ?>
                <input type="hidden" name="botdot_wp_appendix_on_post_types[]" value="<?php echo esc_attr($pt); ?>">
            <?php endforeach; ?>

            <input type="hidden" name="botdot_wp_appendix_enabled" value="<?php echo BotDot_WP_Options::get('appendix_enabled') ? '1' : '0'; ?>">
            <input type="hidden" name="botdot_wp_appendix_auto_placement" value="<?php echo esc_attr(BotDot_WP_Options::get('appendix_auto_placement', 'above_footer')); ?>">
            <input type="hidden" name="botdot_wp_appendix_open_default" value="<?php echo BotDot_WP_Options::get('appendix_open_default') ? '1' : '0'; ?>">
            <input type="hidden" name="botdot_wp_appendix_position" value="<?php echo esc_attr(BotDot_WP_Options::get('appendix_position', 'bottom')); ?>">

            <?php
            $page_injection_status = BotDot_WP_Options::get('page_injection_status', array());
            foreach ($page_injection_status as $page_id => $enabled) : ?>
                <input type="hidden" name="botdot_wp_page_injection_status[<?php echo absint($page_id); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>">
            <?php endforeach; ?>

        <?php elseif ($active_tab === 'pages') : ?>
            <!-- Hidden fields to preserve API tab settings when saving from Pages tab -->
            <input type="hidden" name="botdot_wp_mirror_domain" value="<?php echo esc_attr(BotDot_WP_Options::get('mirror_domain')); ?>">
            <input type="hidden" name="botdot_wp_enabled" value="<?php echo BotDot_WP_Options::get('enabled') ? '1' : '0'; ?>">
            <input type="hidden" name="botdot_wp_fetch_timeout" value="<?php echo esc_attr(BotDot_WP_Options::get('fetch_timeout', 10)); ?>">
            <input type="hidden" name="botdot_wp_debug_mode" value="<?php echo BotDot_WP_Options::get('debug_mode') ? '1' : '0'; ?>">

            <!-- Hidden JSON field to sync AJAX page toggle updates -->
            <input type="hidden" name="botdot_wp_page_injection_status_json" value="<?php echo esc_attr(json_encode($injection_status)); ?>" id="botdot-page-injection-json">
        <?php endif; ?>

        <!-- Tab 1: API & Connection -->
        <div class="tab-content" id="tab-api" style="display: <?php echo $active_tab === 'api' ? 'block' : 'none'; ?>;">
            <?php
            do_settings_sections('botdot-wp');
            submit_button();
            ?>
        </div>

        <!-- Tab 2: Pages & Injection -->
        <div class="tab-content" id="tab-pages" style="display: <?php echo $active_tab === 'pages' ? 'block' : 'none'; ?>;">

            <!-- JSON-LD Injection Settings Section -->
            <div class="botdot-section">
                <h2><?php _e('JSON-LD Injection Settings', 'botdot-wp'); ?></h2>
                <p class="description"><?php _e('Configure which post types can have JSON-LD structured data injected into the page header. This determines which content types will appear in the page management table below.', 'botdot-wp'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable JSON-LD On', 'botdot-wp'); ?></th>
                        <td>
                            <?php foreach ($post_types as $post_type) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox"
                                           name="botdot_wp_inject_on_post_types[]"
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $selected_post_types)); ?>>
                                    <?php echo esc_html($post_type->label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php _e('Select which post types can have JSON-LD structured data injected into the &lt;head&gt; section. This data is fetched from your mirror domain and helps AI systems understand your content.', 'botdot-wp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <hr>

            <!-- Page Management Table Section -->
            <div class="botdot-section">
                <h2><?php _e('Page Management', 'botdot-wp'); ?></h2>
                <p class="description"><?php _e('Enable or disable injection for specific pages. Checked pages will have both JSON-LD and appendix injection.', 'botdot-wp'); ?></p>

                <!-- Search and Bulk Actions (Outside main form, uses GET) -->
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk-action" id="bulk-action-selector-top">
                            <option value="-1"><?php _e('Bulk Actions', 'botdot-wp'); ?></option>
                            <option value="enable"><?php _e('Enable Injection', 'botdot-wp'); ?></option>
                            <option value="disable"><?php _e('Disable Injection', 'botdot-wp'); ?></option>
                        </select>
                        <button type="button" id="doaction" class="button action"><?php _e('Apply', 'botdot-wp'); ?></button>
                    </div>

                    <div class="alignleft actions" style="margin-left: 10px;">
                        <form method="get" style="display: inline-block; margin: 0;">
                            <input type="hidden" name="page" value="botdot-wp">
                            <input type="hidden" name="tab" value="pages">
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search pages...', 'botdot-wp'); ?>">
                            <button type="submit" class="button"><?php _e('Search', 'botdot-wp'); ?></button>
                        </form>
                    </div>

                    <?php if ($total_pages > 1) : ?>
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(_n('%s item', '%s items', $query->found_posts, 'botdot-wp'), number_format_i18n($query->found_posts)); ?></span>
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg(array('paged' => '%#%', 'tab' => 'pages')),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page,
                            ));
                            echo $page_links;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pages Table -->
                <table class="wp-list-table widefat fixed striped" id="botdot-pages-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th scope="col" class="manage-column column-title column-primary">
                                <?php _e('Title', 'botdot-wp'); ?>
                            </th>
                            <th scope="col" class="manage-column">
                                <?php _e('Type', 'botdot-wp'); ?>
                            </th>
                            <th scope="col" class="manage-column">
                                <?php _e('Status', 'botdot-wp'); ?>
                            </th>
                            <th scope="col" class="manage-column">
                                <?php _e('Last Modified', 'botdot-wp'); ?>
                            </th>
                            <th scope="col" class="manage-column">
                                <?php _e('Injection Enabled', 'botdot-wp'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($query->have_posts()) : ?>
                            <?php while ($query->have_posts()) : $query->the_post(); ?>
                                <?php
                                $post_id = get_the_ID();
                                $is_enabled = isset($injection_status[$post_id]) ? $injection_status[$post_id] : true;
                                $post_type_obj = get_post_type_object(get_post_type());
                                ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="page_ids[]" value="<?php echo esc_attr($post_id); ?>" class="page-checkbox">
                                    </th>
                                    <td class="title column-title column-primary" data-colname="<?php esc_attr_e('Title', 'botdot-wp'); ?>">
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">
                                                <?php echo esc_html(get_the_title()); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="view">
                                                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank">
                                                    <?php _e('View', 'botdot-wp'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-colname="<?php esc_attr_e('Type', 'botdot-wp'); ?>">
                                        <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                                    </td>
                                    <td data-colname="<?php esc_attr_e('Status', 'botdot-wp'); ?>">
                                        <?php echo esc_html(get_post_status()); ?>
                                    </td>
                                    <td data-colname="<?php esc_attr_e('Last Modified', 'botdot-wp'); ?>">
                                        <?php echo esc_html(get_the_modified_date()); ?>
                                    </td>
                                    <td data-colname="<?php esc_attr_e('Injection Enabled', 'botdot-wp'); ?>">
                                        <label class="botdot-toggle-switch">
                                            <input type="checkbox"
                                                   class="botdot-page-toggle"
                                                   data-page-id="<?php echo esc_attr($post_id); ?>"
                                                   <?php checked($is_enabled, true); ?>>
                                            <span class="botdot-toggle-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php wp_reset_postdata(); ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6">
                                    <?php _e('No pages found.', 'botdot-wp'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Bottom Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php echo $page_links; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <hr>

            <!-- Appendix Injection Settings Section -->
            <div class="botdot-section">
                <h2><?php _e('Appendix Injection Settings', 'botdot-wp'); ?></h2>
                <p class="description"><?php _e('The appendix is an AI-friendly FAQ section that appears at the bottom of your pages. It provides additional context and information fetched from your mirror domain.', 'botdot-wp'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Appendix', 'botdot-wp'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="botdot_wp_appendix_enabled" value="1" <?php checked(BotDot_WP_Options::get('appendix_enabled'), true); ?>>
                                <?php _e('Enable appendix injection', 'botdot-wp'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Enable Appendix On', 'botdot-wp'); ?></th>
                        <td>
                            <?php $appendix_post_types = BotDot_WP_Options::get('appendix_on_post_types', array('post', 'page')); ?>
                            <?php foreach ($post_types as $post_type) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox"
                                           name="botdot_wp_appendix_on_post_types[]"
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $appendix_post_types)); ?>>
                                    <?php echo esc_html($post_type->label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php _e('Select which post types can display the appendix FAQ section. This is separate from JSON-LD injection and displays visible content on your pages.', 'botdot-wp'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto Placement', 'botdot-wp'); ?></th>
                        <td>
                            <select name="botdot_wp_appendix_auto_placement">
                                <option value="above_footer" <?php selected(BotDot_WP_Options::get('appendix_auto_placement', 'above_footer'), 'above_footer'); ?>>
                                    <?php _e('Above Footer (recommended)', 'botdot-wp'); ?>
                                </option>
                                <option value="bottom" <?php selected(BotDot_WP_Options::get('appendix_auto_placement', 'above_footer'), 'bottom'); ?>>
                                    <?php _e('Bottom of Content', 'botdot-wp'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Choose where the appendix should appear when not manually placed. Manual placement (via Gutenberg block or shortcode) will override this setting.', 'botdot-wp'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Default State', 'botdot-wp'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="botdot_wp_appendix_open_default" value="1" <?php checked(BotDot_WP_Options::get('appendix_open_default'), true); ?>>
                                <?php _e('Open first item by default', 'botdot-wp'); ?>
                            </label>
                            <p class="description">
                                <?php _e('If enabled, the first FAQ item in the appendix will be expanded when the page loads.', 'botdot-wp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(); ?>
        </div>
    </form>
</div>

<style>
/* Tab Content Styling */
.tab-content {
    padding-top: 20px;
}

/* Toggle Switch Styling */
.botdot-toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.botdot-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.botdot-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.botdot-toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.botdot-toggle-switch input:checked + .botdot-toggle-slider {
    background-color: #2271b1;
}

.botdot-toggle-switch input:checked + .botdot-toggle-slider:before {
    transform: translateX(20px);
}

.botdot-section {
    margin-bottom: 30px;
}

.botdot-section h2 {
    margin-top: 0;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Ensure ajaxurl is defined
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }

    // Helper function to update the hidden page injection status field
    function updatePageInjectionHiddenField(pageId, enabled) {
        var hiddenField = $('#botdot-page-injection-json');
        if (!hiddenField.length) {
            return; // Hidden field only exists on Pages tab
        }

        try {
            var currentStatus = JSON.parse(hiddenField.val() || '{}');
            currentStatus[pageId] = enabled;
            hiddenField.val(JSON.stringify(currentStatus));
        } catch (e) {
            console.error('Failed to update page injection status:', e);
        }
    }

    // Test connection button
    $('#botdot-wp-test-connection').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var result = $('#botdot-wp-test-result');
        var domain = $('input[name="botdot_wp_mirror_domain"]').val().trim();

        if (!domain) {
            result.html('<span style="color: #d63638; background: #fcf0f1; padding: 4px 8px; border-radius: 3px;">✗ <?php _e('Please enter a mirror domain first', 'botdot-wp'); ?></span>');
            return;
        }

        button.prop('disabled', true).text('<?php _e('Testing...', 'botdot-wp'); ?>');
        result.html('<span style="color: #666;">⏳ <?php _e('Connecting...', 'botdot-wp'); ?></span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_test_connection',
                nonce: '<?php echo wp_create_nonce('botdot_wp_test_connection'); ?>',
                domain: domain
            },
            success: function(response) {
                if (response.success) {
                    result.html('<span style="color: #00a32a; background: #edfaef; padding: 4px 8px; border-radius: 3px;">✓ ' + response.data.message + '</span>');
                } else {
                    var message = (response.data && response.data.message) ? response.data.message : '<?php _e('Connection failed', 'botdot-wp'); ?>';
                    result.html('<span style="color: #d63638; background: #fcf0f1; padding: 4px 8px; border-radius: 3px;">✗ ' + message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                result.html('<span style="color: #d63638; background: #fcf0f1; padding: 4px 8px; border-radius: 3px;">✗ <?php _e('Request failed', 'botdot-wp'); ?>: ' + (error || status) + '</span>');
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

    // Manual cache poll button
    $('#botdot-wp-manual-poll').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var result = $('#botdot-wp-manual-poll-result');

        button.prop('disabled', true).text('<?php _e('Polling...', 'botdot-wp'); ?>');
        result.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_manual_cache_poll',
                nonce: '<?php echo wp_create_nonce('botdot_wp_manual_poll'); ?>'
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
                button.prop('disabled', false).text('<?php _e('Trigger Cache Poll Manually', 'botdot-wp'); ?>');
            }
        });
    });

    // Manual cache clear button
    $('#botdot-wp-manual-clear').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var result = $('#botdot-wp-manual-clear-result');

        button.prop('disabled', true).text('<?php _e('Clearing...', 'botdot-wp'); ?>');
        result.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_manual_cache_clear',
                nonce: '<?php echo wp_create_nonce('botdot_wp_manual_clear'); ?>'
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
                button.prop('disabled', false).text('<?php _e('Clear Site Cache Now', 'botdot-wp'); ?>');
            }
        });
    });

    // Page toggle switches (AJAX)
    $('.botdot-page-toggle').on('change', function() {
        var checkbox = $(this);
        var pageId = checkbox.data('page-id');
        var enabled = checkbox.is(':checked');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_toggle_page_injection',
                nonce: '<?php echo wp_create_nonce('botdot_wp_toggle_page'); ?>',
                page_id: pageId,
                enabled: enabled ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    // Update hidden field to preserve state on form submit
                    updatePageInjectionHiddenField(pageId, enabled);
                } else {
                    // Revert on error
                    checkbox.prop('checked', !enabled);
                    alert(response.data.message || '<?php _e('Failed to update page status', 'botdot-wp'); ?>');
                }
            },
            error: function() {
                // Revert on error
                checkbox.prop('checked', !enabled);
                alert('<?php _e('Request failed', 'botdot-wp'); ?>');
            }
        });
    });

    // Select all checkbox
    $('#cb-select-all-1').on('change', function() {
        $('.page-checkbox').prop('checked', $(this).is(':checked'));
    });

    // Bulk actions
    $('#doaction').on('click', function(e) {
        e.preventDefault();
        var action = $('#bulk-action-selector-top').val();

        if (action === '-1') {
            alert('<?php _e('Please select an action', 'botdot-wp'); ?>');
            return;
        }

        var selectedPages = [];
        $('.page-checkbox:checked').each(function() {
            selectedPages.push($(this).val());
        });

        if (selectedPages.length === 0) {
            alert('<?php _e('Please select at least one page', 'botdot-wp'); ?>');
            return;
        }

        var enabled = action === 'enable';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_bulk_update_pages',
                nonce: '<?php echo wp_create_nonce('botdot_wp_bulk_pages'); ?>',
                page_ids: selectedPages,
                enabled: enabled ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    // Update hidden field for all affected pages
                    selectedPages.forEach(function(pageId) {
                        updatePageInjectionHiddenField(parseInt(pageId), enabled);
                    });
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('Bulk update failed', 'botdot-wp'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Request failed', 'botdot-wp'); ?>');
            }
        });
    });
});
</script>
