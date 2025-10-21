<?php
/**
 * Pages & Injection tab template
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

// Get selected post types
$selected_post_types = BotDot_WP_Options::get('inject_on_post_types', array('post', 'page'));

// Get injection status
$injection_status = BotDot_WP_Options::get('page_injection_status', array());

// Get all public post types
$post_types = get_post_types(array('public' => true), 'objects');

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

?>

<div class="botdot-pages-tab">

    <!-- Post Type Selection Form -->
    <form method="post" action="options.php" id="botdot-post-types-form">
        <?php settings_fields('botdot_wp_settings'); ?>

        <!-- Hidden fields to preserve other settings -->
        <input type="hidden" name="botdot_wp_mirror_domain" value="<?php echo esc_attr(BotDot_WP_Options::get('mirror_domain')); ?>">
        <input type="hidden" name="botdot_wp_enabled" value="<?php echo BotDot_WP_Options::get('enabled') ? '1' : '0'; ?>">
        <input type="hidden" name="botdot_wp_fetch_timeout" value="<?php echo esc_attr(BotDot_WP_Options::get('fetch_timeout', 10)); ?>">
        <input type="hidden" name="botdot_wp_debug_mode" value="<?php echo BotDot_WP_Options::get('debug_mode') ? '1' : '0'; ?>">
        <input type="hidden" name="botdot_wp_appendix_enabled" value="<?php echo BotDot_WP_Options::get('appendix_enabled') ? '1' : '0'; ?>">
        <input type="hidden" name="botdot_wp_appendix_title" value="<?php echo esc_attr(BotDot_WP_Options::get('appendix_title', 'AI Appendix')); ?>">
        <input type="hidden" name="botdot_wp_appendix_position" value="<?php echo esc_attr(BotDot_WP_Options::get('appendix_position', 'bottom')); ?>">
        <input type="hidden" name="botdot_wp_appendix_auto_placement" value="<?php echo esc_attr(BotDot_WP_Options::get('appendix_auto_placement', 'above_footer')); ?>">
        <input type="hidden" name="botdot_wp_appendix_open_default" value="<?php echo BotDot_WP_Options::get('appendix_open_default') ? '1' : '0'; ?>">
        <?php
        $appendix_post_types = BotDot_WP_Options::get('appendix_on_post_types', array('post', 'page'));
        foreach ($appendix_post_types as $pt) : ?>
            <input type="hidden" name="botdot_wp_appendix_on_post_types[]" value="<?php echo esc_attr($pt); ?>">
        <?php endforeach; ?>
        <?php
        // Preserve page injection status
        $injection_status = BotDot_WP_Options::get('page_injection_status', array());
        foreach ($injection_status as $page_id => $enabled) : ?>
            <input type="hidden" name="botdot_wp_page_injection_status[<?php echo absint($page_id); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>">
        <?php endforeach; ?>

        <div class="botdot-section">
            <h2><?php _e('Post Type Selection', 'botdot-wp'); ?></h2>
            <p class="description"><?php _e('Select which post types to manage. This filters the pages shown in the table below.', 'botdot-wp'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Inject on Post Types', 'botdot-wp'); ?></th>
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
                            <?php _e('Select which post types should have JSON-LD and appendix injection available.', 'botdot-wp'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Post Types', 'botdot-wp')); ?>
        </div>
    </form><!-- End Post Types Form -->

    <hr>

    <!-- Page Management Table -->
    <div class="botdot-section">
        <h2><?php _e('Page Management', 'botdot-wp'); ?></h2>
        <p class="description"><?php _e('Enable or disable injection for specific pages. Checked pages will have both JSON-LD and appendix injection.', 'botdot-wp'); ?></p>

        <!-- Search and Bulk Actions -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk-action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk Actions', 'botdot-wp'); ?></option>
                    <option value="enable"><?php _e('Enable Injection', 'botdot-wp'); ?></option>
                    <option value="disable"><?php _e('Disable Injection', 'botdot-wp'); ?></option>
                </select>
                <button type="button" id="doaction" class="button action"><?php _e('Apply', 'botdot-wp'); ?></button>
            </div>

            <div class="alignleft actions">
                <form method="get">
                    <input type="hidden" name="page" value="botdot-wp-pages">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search pages...', 'botdot-wp'); ?>">
                    <button type="submit" class="button"><?php _e('Search', 'botdot-wp'); ?></button>
                </form>
            </div>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf(_n('%s item', '%s items', $query->found_posts, 'botdot-wp'), number_format_i18n($query->found_posts)); ?></span>
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                    ));
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
                    <th scope="col" class="manage-column column-primary"><?php _e('Title', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Post Type', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('URL', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Status', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Last Modified', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column column-injection"><?php _e('Injection', 'botdot-wp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($query->have_posts()) : ?>
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <?php
                        $post_id = get_the_ID();
                        $is_enabled = isset($injection_status[$post_id]) ? $injection_status[$post_id] : true; // Default to enabled
                        $post_type_obj = get_post_type_object(get_post_type());
                        ?>
                        <tr data-page-id="<?php echo esc_attr($post_id); ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" class="page-checkbox" value="<?php echo esc_attr($post_id); ?>">
                            </th>
                            <td class="column-primary">
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" target="_blank">
                                        <?php echo esc_html(get_the_title() ?: __('(no title)', 'botdot-wp')); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <?php echo esc_html($post_type_obj ? $post_type_obj->labels->singular_name : get_post_type()); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(get_permalink()); ?>" target="_blank" class="botdot-url">
                                    <?php echo esc_html(wp_make_link_relative(get_permalink())); ?>
                                </a>
                            </td>
                            <td>
                                <?php
                                $status = get_post_status();
                                $status_obj = get_post_status_object($status);
                                echo esc_html($status_obj ? $status_obj->label : $status);
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html(human_time_diff(get_the_modified_time('U'), current_time('timestamp')) . ' ago'); ?>
                            </td>
                            <td class="column-injection">
                                <label class="botdot-toggle">
                                    <input type="checkbox"
                                           class="injection-toggle"
                                           data-page-id="<?php echo esc_attr($post_id); ?>"
                                           <?php checked($is_enabled); ?>>
                                    <span class="botdot-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            <?php _e('No pages found for the selected post types.', 'botdot-wp'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-2">
                    </td>
                    <th scope="col" class="manage-column column-primary"><?php _e('Title', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Post Type', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('URL', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Status', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Last Modified', 'botdot-wp'); ?></th>
                    <th scope="col" class="manage-column column-injection"><?php _e('Injection', 'botdot-wp'); ?></th>
                </tr>
            </tfoot>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf(_n('%s item', '%s items', $query->found_posts, 'botdot-wp'), number_format_i18n($query->found_posts)); ?></span>
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg(array('paged' => '%#%', 'tab' => 'pages'), admin_url('admin.php?page=botdot-wp')),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <hr>

    <!-- Appendix Settings Form -->
    <form method="post" action="options.php" id="botdot-appendix-settings-form">
        <?php settings_fields('botdot_wp_settings'); ?>

        <!-- Hidden fields to preserve other settings -->
        <input type="hidden" name="botdot_wp_mirror_domain" value="<?php echo esc_attr(BotDot_WP_Options::get('mirror_domain')); ?>">
        <input type="hidden" name="botdot_wp_enabled" value="<?php echo BotDot_WP_Options::get('enabled') ? '1' : '0'; ?>">
        <input type="hidden" name="botdot_wp_fetch_timeout" value="<?php echo esc_attr(BotDot_WP_Options::get('fetch_timeout', 10)); ?>">
        <input type="hidden" name="botdot_wp_debug_mode" value="<?php echo BotDot_WP_Options::get('debug_mode') ? '1' : '0'; ?>">
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
        <input type="hidden" name="botdot_wp_appendix_position" value="<?php echo esc_attr(BotDot_WP_Options::get('appendix_position', 'bottom')); ?>">
        <?php
        // Preserve page injection status
        $injection_status = BotDot_WP_Options::get('page_injection_status', array());
        foreach ($injection_status as $page_id => $enabled) : ?>
            <input type="hidden" name="botdot_wp_page_injection_status[<?php echo absint($page_id); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>">
        <?php endforeach; ?>

        <div class="botdot-section">
            <h2><?php _e('Appendix Settings', 'botdot-wp'); ?></h2>

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
                <th scope="row"><?php _e('Auto Placement', 'botdot-wp'); ?></th>
                <td>
                    <select name="botdot_wp_appendix_auto_placement">
                        <option value="above_footer" <?php selected(BotDot_WP_Options::get('appendix_auto_placement'), 'above_footer'); ?>>
                            <?php _e('Above Footer', 'botdot-wp'); ?>
                        </option>
                        <option value="bottom" <?php selected(BotDot_WP_Options::get('appendix_auto_placement'), 'bottom'); ?>>
                            <?php _e('Bottom of Content', 'botdot-wp'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Where to automatically place the appendix when not using manual placement (shortcode/block).', 'botdot-wp'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Default Title', 'botdot-wp'); ?></th>
                <td>
                    <input type="text"
                           name="botdot_wp_appendix_title"
                           value="<?php echo esc_attr(BotDot_WP_Options::get('appendix_title', 'AI Appendix')); ?>"
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Open by Default', 'botdot-wp'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="botdot_wp_appendix_open_default" value="1" <?php checked(BotDot_WP_Options::get('appendix_open_default'), true); ?>>
                        <?php _e('Expand appendix by default', 'botdot-wp'); ?>
                    </label>
                </td>
            </tr>
        </table>

            <?php submit_button(__('Save Appendix Settings', 'botdot-wp')); ?>
        </div>
    </form><!-- End Appendix Settings Form -->

</div>

<style>
.botdot-pages-tab {
    max-width: 100%;
}

.botdot-section {
    margin-bottom: 30px;
}

.botdot-section h2 {
    margin-top: 0;
}

#botdot-pages-table {
    margin-top: 10px;
}

#botdot-pages-table .column-cb {
    width: 2.2em;
}

#botdot-pages-table .column-injection {
    width: 100px;
    text-align: center;
}

#botdot-pages-table .botdot-url {
    color: #666;
    text-decoration: none;
    font-size: 13px;
}

#botdot-pages-table .botdot-url:hover {
    color: #2271b1;
}

/* Toggle Switch */
.botdot-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.botdot-toggle input {
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
    transition: 0.3s;
    border-radius: 24px;
}

.botdot-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.botdot-toggle input:checked + .botdot-toggle-slider {
    background-color: #2271b1;
}

.botdot-toggle input:checked + .botdot-toggle-slider:before {
    transform: translateX(26px);
}

.botdot-toggle input:disabled + .botdot-toggle-slider {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Loading state */
.injection-toggle.loading + .botdot-toggle-slider {
    opacity: 0.6;
    cursor: wait;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Select all checkboxes
    $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
        var checked = $(this).prop('checked');
        $('.page-checkbox').prop('checked', checked);
        $('#cb-select-all-1, #cb-select-all-2').prop('checked', checked);
    });

    // Individual page checkbox
    $('.page-checkbox').on('change', function() {
        var allChecked = $('.page-checkbox:checked').length === $('.page-checkbox').length;
        $('#cb-select-all-1, #cb-select-all-2').prop('checked', allChecked);
    });

    // Toggle injection status
    $('.injection-toggle').on('change', function() {
        var $toggle = $(this);
        var pageId = $toggle.data('page-id');
        var enabled = $toggle.prop('checked');

        // Add loading state
        $toggle.addClass('loading').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_toggle_page_injection',
                nonce: '<?php echo wp_create_nonce('botdot_wp_toggle_page'); ?>',
                page_id: pageId,
                enabled: enabled
            },
            success: function(response) {
                if (!response.success) {
                    // Revert on error
                    $toggle.prop('checked', !enabled);
                    alert(response.data.message || '<?php _e('Failed to update page status', 'botdot-wp'); ?>');
                }
            },
            error: function() {
                // Revert on error
                $toggle.prop('checked', !enabled);
                alert('<?php _e('Request failed', 'botdot-wp'); ?>');
            },
            complete: function() {
                $toggle.removeClass('loading').prop('disabled', false);
            }
        });
    });

    // Bulk actions
    $('#doaction').on('click', function(e) {
        e.preventDefault();

        var action = $('#bulk-action-selector-top').val();
        if (action === '-1') {
            alert('<?php _e('Please select a bulk action', 'botdot-wp'); ?>');
            return;
        }

        var selectedPages = $('.page-checkbox:checked').map(function() {
            return parseInt($(this).val());
        }).get();

        if (selectedPages.length === 0) {
            alert('<?php _e('Please select at least one page', 'botdot-wp'); ?>');
            return;
        }

        var enabled = (action === 'enable');
        var button = $(this);

        button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'botdot_wp_bulk_update_pages',
                nonce: '<?php echo wp_create_nonce('botdot_wp_bulk_pages'); ?>',
                page_ids: selectedPages,
                enabled: enabled
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    selectedPages.forEach(function(pageId) {
                        $('tr[data-page-id="' + pageId + '"]')
                            .find('.injection-toggle')
                            .prop('checked', enabled);
                    });

                    // Show success message
                    alert(response.data.count + ' <?php _e('pages updated successfully', 'botdot-wp'); ?>');

                    // Clear selections
                    $('.page-checkbox').prop('checked', false);
                    $('#cb-select-all-1, #cb-select-all-2').prop('checked', false);
                } else {
                    alert(response.data.message || '<?php _e('Bulk update failed', 'botdot-wp'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Request failed', 'botdot-wp'); ?>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});
</script>
