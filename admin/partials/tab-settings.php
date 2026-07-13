<?php
/**
 * Settings tab — Content to sync, Placement, SEO compatibility + Advanced.
 *
 * @package Bspt
 * @subpackage Bspt/admin/partials
 * @since 2.2.0
 *
 * Variables from parent partial:
 * - $bsa_post_types (WP post type objects)
 * - $bsa_woocommerce_active (bool)
 */

if (!defined("WPINC")) {
    die();
}

$bsa_sync_post_types = Bspt_Options::get("sync_post_types", ["post", "page"]);
$bsa_inject_post_types = Bspt_Options::get("inject_on_post_types", ["post", "page"]);
$bsa_injection_position = Bspt_Options::get("injection_position", "bottom_of_content");
$bsa_jsonld_conflict = Bspt_Options::get("jsonld_conflict_mode", "merge");
$bsa_auto_sync = (bool) Bspt_Options::get("auto_sync_enabled", true);
$bsa_sync_sensitivity = Bspt_Options::get("sync_sensitivity", "high");
$bsa_appendix_enabled = (bool) Bspt_Options::get("appendix_enabled", true);
$bsa_jsonld_enabled = (bool) Bspt_Options::get("jsonld_enabled", true);

// Platform-managed only after dashboard pushes settings (not just on connect)
$bsa_platform_settings = get_option("bspt_platform_settings", []);
$bsa_is_platform_managed = !empty($bsa_platform_settings);
$bsa_dashboard_url = "https://platform.bot.spot";

// Custom post types (exclude built-ins we handle explicitly)
$bsa_builtin_types = ["post", "page", "attachment", "product"];
$bsa_custom_types = array_filter($bsa_post_types, function ($pt) use ($bsa_builtin_types) {
    return !in_array($pt->name, $bsa_builtin_types, true);
});
?>
<div class="bsa-settings">

    <div class="bsa-section-head bsa-reveal bsa-reveal--1">
        <h1 class="bsa-h1"><?php _e("Settings", "botspot-wp"); ?></h1>
        <p class="bsa-description">
            <?php _e("Optional WordPress-specific runtime settings. Content, review flow, styling, and publishing are managed in bot.spot.", "botspot-wp"); ?>
        </p>
    </div>

    <!-- Content to sync -->
    <section class="bsa-settings-row bsa-reveal bsa-reveal--2">
        <div class="bsa-settings-row__meta">
            <h3 class="bsa-settings-row__title">
                <?php _e("Content to sync", "botspot-wp"); ?>
                <?php if ($bsa_is_platform_managed): ?>
                <a href="<?php echo esc_url($bsa_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                    <?php _e("Managed in bot.spot", "botspot-wp"); ?> ↗
                </a>
                <?php endif; ?>
            </h3>
            <p class="bsa-settings-row__desc">
                <?php _e("Updates for selected types are automatically pushed to bot.spot.", "botspot-wp"); ?>
            </p>
        </div>
        <div class="bsa-settings-row__body">
            <?php if ($bsa_is_platform_managed): ?>
            <div class="bsa-readonly-list">
                <?php
                $type_labels = [
                    "post" => __("Posts", "botspot-wp"),
                    "page" => __("Pages", "botspot-wp"),
                    "product" => __("Products", "botspot-wp"),
                ];
                foreach ($bsa_sync_post_types as $type):
                    $label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
                ?>
                <span class="bsa-readonly-item"><?php echo esc_html($label); ?></span>
                <?php endforeach; ?>
                <?php if (empty($bsa_sync_post_types)): ?>
                <span class="bsa-readonly-item bsa-readonly-item--none"><?php _e("None selected", "botspot-wp"); ?></span>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="checkbox" name="bspt_sync_post_types[]" value="post" <?php checked(in_array("post", $bsa_sync_post_types, true)); ?> />
                    <span><?php _e("Posts", "botspot-wp"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="checkbox" name="bspt_sync_post_types[]" value="page" <?php checked(in_array("page", $bsa_sync_post_types, true)); ?> />
                    <span><?php _e("Pages", "botspot-wp"); ?></span>
                </label>
                <label class="bsa-check <?php echo $bsa_woocommerce_active ? "" : "bsa-check--disabled"; ?>">
                    <input type="checkbox" name="bspt_sync_post_types[]" value="product" <?php checked(in_array("product", $bsa_sync_post_types, true)); ?> <?php echo $bsa_woocommerce_active ? "" : "disabled"; ?> />
                    <span>
                        <?php _e("Products", "botspot-wp"); ?>
                        <span class="bsa-check__tag <?php echo $bsa_woocommerce_active ? "bsa-check__tag--active" : ""; ?>">WooCommerce</span>
                    </span>
                </label>
                <?php if (!empty($bsa_custom_types)): ?>
                <details class="bsa-check bsa-check-group">
                    <summary><?php _e("Custom post types", "botspot-wp"); ?></summary>
                    <div class="bsa-check-list bsa-check-list--nested">
                        <?php foreach ($bsa_custom_types as $pt): ?>
                        <label class="bsa-check">
                            <input type="checkbox" name="bspt_sync_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $bsa_sync_post_types, true)); ?> />
                            <span><?php echo esc_html($pt->label); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php else: ?>
                <label class="bsa-check bsa-check--disabled">
                    <input type="checkbox" disabled />
                    <span><?php _e("Custom post types", "botspot-wp"); ?></span>
                </label>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Placement -->
    <section class="bsa-settings-row bsa-reveal bsa-reveal--3">
        <div class="bsa-settings-row__meta">
            <h3 class="bsa-settings-row__title">
                <?php _e("Placement", "botspot-wp"); ?>
                <?php if ($bsa_is_platform_managed): ?>
                <a href="<?php echo esc_url($bsa_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                    <?php _e("Managed in bot.spot", "botspot-wp"); ?> ↗
                </a>
                <?php endif; ?>
            </h3>
            <p class="bsa-settings-row__desc"><?php _e("Where the appendix is injected into the page.", "botspot-wp"); ?></p>
        </div>
        <div class="bsa-settings-row__body">
            <?php if ($bsa_is_platform_managed): ?>
            <?php
            $placement_labels = [
                "bottom_of_content" => __("Bottom of content", "botspot-wp"),
                "auto" => __("Automatic", "botspot-wp"),
                "above_footer" => __("Above footer", "botspot-wp"),
                "bottom_of_page" => __("Bottom of page", "botspot-wp"),
                "footer" => __("Footer", "botspot-wp"),
                "manual" => __("Manual placement", "botspot-wp"),
            ];
            $placement_label = isset($placement_labels[$bsa_injection_position]) ? $placement_labels[$bsa_injection_position] : ucfirst($bsa_injection_position);
            ?>
            <div class="bsa-readonly-value">
                <?php echo esc_html($placement_label); ?>
                <?php if ($bsa_injection_position === "manual"): ?>
                <code class="bsa-code">[botspot_appendix]</code>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_injection_position" value="bottom_of_content" <?php checked($bsa_injection_position, "bottom_of_content"); ?> />
                    <span>
                        <?php _e("Bottom of content", "botspot-wp"); ?>
                        <span class="bsa-check__tag"><?php _e("recommended", "botspot-wp"); ?></span>
                    </span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_injection_position" value="above_footer" <?php checked($bsa_injection_position, "above_footer"); ?> />
                    <span><?php _e("Above footer", "botspot-wp"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_injection_position" value="bottom_of_page" <?php checked($bsa_injection_position, "bottom_of_page"); ?> />
                    <span><?php _e("Bottom of page", "botspot-wp"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_injection_position" value="manual" <?php checked($bsa_injection_position, "manual"); ?> />
                    <span>
                        <?php _e("Manual placement", "botspot-wp"); ?>
                        <span class="bsa-check__tag"><code class="bsa-code">[botspot_appendix]</code></span>
                    </span>
                </label>
            </div>
            <p class="bsa-settings-row__note" id="bsa-footer-detection-note" style="display: none;">
                <strong><?php _e("Note:", "botspot-wp"); ?></strong>
                <?php _e("Footer detection relies on common HTML patterns (<code>&lt;footer&gt;</code>, <code>role=\"contentinfo\"</code>, etc.). If your theme uses a non-standard footer, add the attribute <code>data-botspot-footer</code> to your footer element for reliable placement.", "botspot-wp"); ?>
            </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- SEO compatibility -->
    <section class="bsa-settings-row bsa-reveal bsa-reveal--4">
        <div class="bsa-settings-row__meta">
            <h3 class="bsa-settings-row__title"><?php _e("SEO compatibility", "botspot-wp"); ?></h3>
            <p class="bsa-settings-row__desc"><?php _e("How JSON-LD output interacts with other SEO plugins.", "botspot-wp"); ?></p>
        </div>
        <div class="bsa-settings-row__body">
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_jsonld_conflict_mode" value="merge" <?php checked($bsa_jsonld_conflict, "merge"); ?> />
                    <span>
                        <?php _e("Merge with existing output", "botspot-wp"); ?>
                        <span class="bsa-check__tag"><?php _e("recommended", "botspot-wp"); ?></span>
                    </span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_jsonld_conflict_mode" value="replace" <?php checked($bsa_jsonld_conflict, "replace"); ?> />
                    <span><?php _e("Replace conflicting output", "botspot-wp"); ?></span>
                </label>
            </div>
        </div>
    </section>

    <!-- Advanced (collapsible) -->
    <details class="bsa-advanced bsa-reveal bsa-reveal--4">
        <summary class="bsa-advanced__summary">
            <span><?php _e("Advanced", "botspot-wp"); ?></span>
            <span class="bsa-advanced__chevron" aria-hidden="true">▸</span>
        </summary>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title"><?php _e("Auto-sync on publish", "botspot-wp"); ?></h3>
                <p class="bsa-settings-row__desc"><?php _e("Automatically push content changes to bot.spot on save.", "botspot-wp"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <label class="bsa-check">
                    <input type="checkbox" name="bspt_auto_sync_enabled" value="1" <?php checked($bsa_auto_sync); ?> />
                    <span><?php _e("Enable auto-sync", "botspot-wp"); ?></span>
                </label>
            </div>
        </section>

        <!-- ponytail: sensitivity hidden, defaults to high. Unhide when dashboard supports it. -->
        <input type="hidden" name="bspt_sync_sensitivity" value="high" />

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title">
                    <?php _e("Output toggles", "botspot-wp"); ?>
                    <?php if ($bsa_is_platform_managed): ?>
                    <a href="<?php echo esc_url($bsa_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                        <?php _e("Managed in bot.spot", "botspot-wp"); ?> ↗
                    </a>
                    <?php endif; ?>
                </h3>
                <p class="bsa-settings-row__desc"><?php _e("Disable specific injection outputs.", "botspot-wp"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <?php if ($bsa_is_platform_managed): ?>
                <div class="bsa-readonly-toggles">
                    <span class="bsa-readonly-toggle <?php echo $bsa_appendix_enabled ? 'bsa-readonly-toggle--on' : 'bsa-readonly-toggle--off'; ?>">
                        <?php _e("HTML appendix", "botspot-wp"); ?>: <?php echo $bsa_appendix_enabled ? __("Enabled", "botspot-wp") : __("Disabled", "botspot-wp"); ?>
                    </span>
                    <span class="bsa-readonly-toggle <?php echo $bsa_jsonld_enabled ? 'bsa-readonly-toggle--on' : 'bsa-readonly-toggle--off'; ?>">
                        <?php _e("JSON-LD structured data", "botspot-wp"); ?>: <?php echo $bsa_jsonld_enabled ? __("Enabled", "botspot-wp") : __("Disabled", "botspot-wp"); ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="bsa-check-list">
                    <label class="bsa-check">
                        <input type="checkbox" name="bspt_appendix_enabled" value="1" <?php checked($bsa_appendix_enabled); ?> />
                        <span><?php _e("HTML appendix", "botspot-wp"); ?></span>
                    </label>
                    <label class="bsa-check">
                        <input type="checkbox" name="bspt_jsonld_enabled" value="1" <?php checked($bsa_jsonld_enabled); ?> />
                        <span><?php _e("JSON-LD structured data", "botspot-wp"); ?></span>
                    </label>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title">
                    <?php _e("Inject on post types", "botspot-wp"); ?>
                    <?php if ($bsa_is_platform_managed): ?>
                    <a href="<?php echo esc_url($bsa_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                        <?php _e("Managed in bot.spot", "botspot-wp"); ?> ↗
                    </a>
                    <?php endif; ?>
                </h3>
                <p class="bsa-settings-row__desc"><?php _e("Override which post types receive injected output (defaults to synced types).", "botspot-wp"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <?php if ($bsa_is_platform_managed): ?>
                <div class="bsa-readonly-list">
                    <?php
                    $type_labels = [
                        "post" => __("Posts", "botspot-wp"),
                        "page" => __("Pages", "botspot-wp"),
                        "product" => __("Products", "botspot-wp"),
                    ];
                    foreach ($bsa_inject_post_types as $type):
                        $label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
                    ?>
                    <span class="bsa-readonly-item"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($bsa_inject_post_types)): ?>
                    <span class="bsa-readonly-item bsa-readonly-item--none"><?php _e("None selected", "botspot-wp"); ?></span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="bsa-check-list">
                    <?php foreach ($bsa_post_types as $pt): ?>
                    <label class="bsa-check">
                        <input type="checkbox" name="bspt_inject_on_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $bsa_inject_post_types, true)); ?> />
                        <span><?php echo esc_html($pt->label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </details>

    <!-- Footer: save bar -->
    <div class="bsa-save-bar">
        <span class="bsa-save-bar__status" data-bsa-save-status><?php _e("All changes saved", "botspot-wp"); ?></span>
        <div class="bsa-save-bar__actions">
            <button type="button" class="bsa-btn" data-bsa-action="reset-form">
                <?php _e("Reset", "botspot-wp"); ?>
            </button>
            <button type="submit" class="bsa-btn bsa-btn--primary" name="submit">
                <?php _e("Save changes", "botspot-wp"); ?>
            </button>
        </div>
    </div>
</div>
