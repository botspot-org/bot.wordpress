<?php
/**
 * Settings tab — Content to sync, Placement, SEO compatibility + Advanced.
 *
 * @package Bspt
 * @subpackage Bspt/admin/partials
 * @since 2.2.0
 *
 * Variables from parent partial:
 * - $bspt_post_types (WP post type objects)
 * - $bspt_woocommerce_active (bool)
 */

if (!defined("WPINC")) {
    die();
}

$bspt_sync_post_types = Bspt_Options::get("sync_post_types", ["post", "page"]);
$bspt_inject_post_types = Bspt_Options::get("inject_on_post_types", ["post", "page"]);
$bspt_injection_position = Bspt_Options::get("injection_position", "bottom_of_content");
$bspt_jsonld_conflict = Bspt_Options::get("jsonld_conflict_mode", "merge");
$bspt_auto_sync = (bool) Bspt_Options::get("auto_sync_enabled", true);
$bspt_sync_sensitivity = Bspt_Options::get("sync_sensitivity", "high");
$bspt_appendix_enabled = (bool) Bspt_Options::get("appendix_enabled", true);
$bspt_jsonld_enabled = (bool) Bspt_Options::get("jsonld_enabled", true);

// Platform-managed only after dashboard pushes settings (not just on connect)
$bspt_platform_settings = get_option("bspt_platform_settings", []);
$bspt_is_platform_managed = !empty($bspt_platform_settings);
$bspt_dashboard_url = "https://platform.bot.spot";

// Custom post types (exclude built-ins we handle explicitly)
$bspt_builtin_types = ["post", "page", "attachment", "product"];
$bspt_custom_types = array_filter($bspt_post_types, function ($bspt_pt) use ($bspt_builtin_types) {
    return !in_array($bspt_pt->name, $bspt_builtin_types, true);
});
?>
<div class="bsa-settings">

    <div class="bsa-section-head bsa-reveal bsa-reveal--1">
        <h1 class="bsa-h1"><?php esc_html_e("Settings", "botspot"); ?></h1>
        <p class="bsa-description">
            <?php esc_html_e("Optional WordPress-specific runtime settings. Content, review flow, styling, and publishing are managed in bot.spot.", "botspot"); ?>
        </p>
    </div>

    <!-- Content to sync -->
    <section class="bsa-settings-row bsa-reveal bsa-reveal--2">
        <div class="bsa-settings-row__meta">
            <h3 class="bsa-settings-row__title">
                <?php esc_html_e("Content to sync", "botspot"); ?>
                <?php if ($bspt_is_platform_managed): ?>
                <a href="<?php echo esc_url($bspt_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                    <?php esc_html_e("Managed in bot.spot", "botspot"); ?> ↗
                </a>
                <?php endif; ?>
            </h3>
            <p class="bsa-settings-row__desc">
                <?php esc_html_e("Updates for selected types are automatically pushed to bot.spot.", "botspot"); ?>
            </p>
        </div>
        <div class="bsa-settings-row__body">
            <?php if ($bspt_is_platform_managed): ?>
            <div class="bsa-readonly-list">
                <?php
                $bspt_type_labels = [
                    "post" => __("Posts", "botspot"),
                    "page" => __("Pages", "botspot"),
                    "product" => __("Products", "botspot"),
                ];
                foreach ($bspt_sync_post_types as $type):
                    $bspt_label = isset($bspt_type_labels[$type]) ? $bspt_type_labels[$type] : ucfirst($type);
                ?>
                <span class="bsa-readonly-item"><?php echo esc_html($bspt_label); ?></span>
                <?php endforeach; ?>
                <?php if (empty($bspt_sync_post_types)): ?>
                <span class="bsa-readonly-item bsa-readonly-item--none"><?php esc_html_e("None selected", "botspot"); ?></span>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="checkbox" name="bspt_sync_post_types[]" value="post" <?php checked(in_array("post", $bspt_sync_post_types, true)); ?> />
                    <span><?php esc_html_e("Posts", "botspot"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="checkbox" name="bspt_sync_post_types[]" value="page" <?php checked(in_array("page", $bspt_sync_post_types, true)); ?> />
                    <span><?php esc_html_e("Pages", "botspot"); ?></span>
                </label>
                <label class="bsa-check <?php echo $bspt_woocommerce_active ? "" : "bsa-check--disabled"; ?>">
                    <input type="checkbox" name="bspt_sync_post_types[]" value="product" <?php checked(in_array("product", $bspt_sync_post_types, true)); ?> <?php echo $bspt_woocommerce_active ? "" : "disabled"; ?> />
                    <span>
                        <?php esc_html_e("Products", "botspot"); ?>
                        <span class="bsa-check__tag <?php echo $bspt_woocommerce_active ? "bsa-check__tag--active" : ""; ?>">WooCommerce</span>
                    </span>
                </label>
                <?php if (!empty($bspt_custom_types)): ?>
                <details class="bsa-check bsa-check-group">
                    <summary><?php esc_html_e("Custom post types", "botspot"); ?></summary>
                    <div class="bsa-check-list bsa-check-list--nested">
                        <?php foreach ($bspt_custom_types as $bspt_pt): ?>
                        <label class="bsa-check">
                            <input type="checkbox" name="bspt_sync_post_types[]" value="<?php echo esc_attr($bspt_pt->name); ?>" <?php checked(in_array($bspt_pt->name, $bspt_sync_post_types, true)); ?> />
                            <span><?php echo esc_html($bspt_pt->label); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php else: ?>
                <label class="bsa-check bsa-check--disabled">
                    <input type="checkbox" disabled />
                    <span><?php esc_html_e("Custom post types", "botspot"); ?></span>
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
                <?php esc_html_e("Placement", "botspot"); ?>
                <?php if ($bspt_is_platform_managed): ?>
                <a href="<?php echo esc_url($bspt_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                    <?php esc_html_e("Managed in bot.spot", "botspot"); ?> ↗
                </a>
                <?php endif; ?>
            </h3>
            <p class="bsa-settings-row__desc"><?php esc_html_e("Where the appendix is injected into the page.", "botspot"); ?></p>
        </div>
        <div class="bsa-settings-row__body">
            <?php if ($bspt_is_platform_managed): ?>
            <?php
            $bspt_placement_labels = [
                "bottom_of_content" => __("Bottom of content", "botspot"),
                "auto" => __("Automatic", "botspot"),
                "above_footer" => __("Above footer", "botspot"),
                "bottom_of_page" => __("Bottom of page", "botspot"),
                "footer" => __("Footer", "botspot"),
                "manual" => __("Manual placement", "botspot"),
            ];
            $bspt_placement_label = isset($bspt_placement_labels[$bspt_injection_position]) ? $bspt_placement_labels[$bspt_injection_position] : ucfirst($bspt_injection_position);
            ?>
            <div class="bsa-readonly-value">
                <?php echo esc_html($bspt_placement_label); ?>
                <?php if ($bspt_injection_position === "manual"): ?>
                <code class="bsa-code">[botspot_appendix]</code>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_injection_position" value="bottom_of_content" <?php checked($bspt_injection_position, "bottom_of_content"); ?> />
                    <span>
                        <?php esc_html_e("Bottom of content", "botspot"); ?>
                        <span class="bsa-check__tag"><?php esc_html_e("recommended", "botspot"); ?></span>
                    </span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_injection_position" value="above_footer" <?php checked($bspt_injection_position, "above_footer"); ?> />
                    <span><?php esc_html_e("Above footer", "botspot"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_injection_position" value="bottom_of_page" <?php checked($bspt_injection_position, "bottom_of_page"); ?> />
                    <span><?php esc_html_e("Bottom of page", "botspot"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_injection_position" value="manual" <?php checked($bspt_injection_position, "manual"); ?> />
                    <span>
                        <?php esc_html_e("Manual placement", "botspot"); ?>
                        <span class="bsa-check__tag"><code class="bsa-code">[botspot_appendix]</code></span>
                    </span>
                </label>
            </div>
            <p class="bsa-settings-row__note" id="bsa-footer-detection-note" style="display: none;">
                <strong><?php esc_html_e("Note:", "botspot"); ?></strong>
                <?php esc_html_e("Footer detection relies on common HTML patterns (<code>&lt;footer&gt;</code>, <code>role=\"contentinfo\"</code>, etc.). If your theme uses a non-standard footer, add the attribute <code>data-botspot-footer</code> to your footer element for reliable placement.", "botspot"); ?>
            </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- SEO compatibility -->
    <section class="bsa-settings-row bsa-reveal bsa-reveal--4">
        <div class="bsa-settings-row__meta">
            <h3 class="bsa-settings-row__title"><?php esc_html_e("SEO compatibility", "botspot"); ?></h3>
            <p class="bsa-settings-row__desc"><?php esc_html_e("How JSON-LD output interacts with other SEO plugins.", "botspot"); ?></p>
        </div>
        <div class="bsa-settings-row__body">
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_jsonld_conflict_mode" value="merge" <?php checked($bspt_jsonld_conflict, "merge"); ?> />
                    <span>
                        <?php esc_html_e("Merge with existing output", "botspot"); ?>
                        <span class="bsa-check__tag"><?php esc_html_e("recommended", "botspot"); ?></span>
                    </span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="bspt_jsonld_conflict_mode" value="replace" <?php checked($bspt_jsonld_conflict, "replace"); ?> />
                    <span><?php esc_html_e("Replace conflicting output", "botspot"); ?></span>
                </label>
            </div>
        </div>
    </section>

    <!-- Advanced (collapsible) -->
    <details class="bsa-advanced bsa-reveal bsa-reveal--4">
        <summary class="bsa-advanced__summary">
            <span><?php esc_html_e("Advanced", "botspot"); ?></span>
            <span class="bsa-advanced__chevron" aria-hidden="true">▸</span>
        </summary>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title"><?php esc_html_e("Auto-sync on publish", "botspot"); ?></h3>
                <p class="bsa-settings-row__desc"><?php esc_html_e("Automatically push content changes to bot.spot on save.", "botspot"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <label class="bsa-check">
                    <input type="checkbox" name="bspt_auto_sync_enabled" value="1" <?php checked($bspt_auto_sync); ?> />
                    <span><?php esc_html_e("Enable auto-sync", "botspot"); ?></span>
                </label>
            </div>
        </section>

        <!-- ponytail: sensitivity hidden, defaults to high. Unhide when dashboard supports it. -->
        <input type="hidden" name="bspt_sync_sensitivity" value="high" />

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title">
                    <?php esc_html_e("Output toggles", "botspot"); ?>
                    <?php if ($bspt_is_platform_managed): ?>
                    <a href="<?php echo esc_url($bspt_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                        <?php esc_html_e("Managed in bot.spot", "botspot"); ?> ↗
                    </a>
                    <?php endif; ?>
                </h3>
                <p class="bsa-settings-row__desc"><?php esc_html_e("Disable specific injection outputs.", "botspot"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <?php if ($bspt_is_platform_managed): ?>
                <div class="bsa-readonly-toggles">
                    <span class="bsa-readonly-toggle <?php echo $bspt_appendix_enabled ? 'bsa-readonly-toggle--on' : 'bsa-readonly-toggle--off'; ?>">
                        <?php esc_html_e("HTML appendix", "botspot"); ?>: <?php echo $bspt_appendix_enabled ? esc_html__("Enabled", "botspot") : esc_html__("Disabled", "botspot"); ?>
                    </span>
                    <span class="bsa-readonly-toggle <?php echo $bspt_jsonld_enabled ? 'bsa-readonly-toggle--on' : 'bsa-readonly-toggle--off'; ?>">
                        <?php esc_html_e("JSON-LD structured data", "botspot"); ?>: <?php echo $bspt_jsonld_enabled ? esc_html__("Enabled", "botspot") : esc_html__("Disabled", "botspot"); ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="bsa-check-list">
                    <label class="bsa-check">
                        <input type="checkbox" name="bspt_appendix_enabled" value="1" <?php checked($bspt_appendix_enabled); ?> />
                        <span><?php esc_html_e("HTML appendix", "botspot"); ?></span>
                    </label>
                    <label class="bsa-check">
                        <input type="checkbox" name="bspt_jsonld_enabled" value="1" <?php checked($bspt_jsonld_enabled); ?> />
                        <span><?php esc_html_e("JSON-LD structured data", "botspot"); ?></span>
                    </label>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title">
                    <?php esc_html_e("Inject on post types", "botspot"); ?>
                    <?php if ($bspt_is_platform_managed): ?>
                    <a href="<?php echo esc_url($bspt_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                        <?php esc_html_e("Managed in bot.spot", "botspot"); ?> ↗
                    </a>
                    <?php endif; ?>
                </h3>
                <p class="bsa-settings-row__desc"><?php esc_html_e("Override which post types receive injected output (defaults to synced types).", "botspot"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <?php if ($bspt_is_platform_managed): ?>
                <div class="bsa-readonly-list">
                    <?php
                    $bspt_type_labels = [
                        "post" => __("Posts", "botspot"),
                        "page" => __("Pages", "botspot"),
                        "product" => __("Products", "botspot"),
                    ];
                    foreach ($bspt_inject_post_types as $type):
                        $bspt_label = isset($bspt_type_labels[$type]) ? $bspt_type_labels[$type] : ucfirst($type);
                    ?>
                    <span class="bsa-readonly-item"><?php echo esc_html($bspt_label); ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($bspt_inject_post_types)): ?>
                    <span class="bsa-readonly-item bsa-readonly-item--none"><?php esc_html_e("None selected", "botspot"); ?></span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="bsa-check-list">
                    <?php foreach ($bspt_post_types as $bspt_pt): ?>
                    <label class="bsa-check">
                        <input type="checkbox" name="bspt_inject_on_post_types[]" value="<?php echo esc_attr($bspt_pt->name); ?>" <?php checked(in_array($bspt_pt->name, $bspt_inject_post_types, true)); ?> />
                        <span><?php echo esc_html($bspt_pt->label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </details>

    <!-- Footer: save bar -->
    <div class="bsa-save-bar">
        <span class="bsa-save-bar__status" data-bsa-save-status><?php esc_html_e("All changes saved", "botspot"); ?></span>
        <div class="bsa-save-bar__actions">
            <button type="button" class="bsa-btn" data-bsa-action="reset-form">
                <?php esc_html_e("Reset", "botspot"); ?>
            </button>
            <button type="submit" class="bsa-btn bsa-btn--primary" name="submit">
                <?php esc_html_e("Save changes", "botspot"); ?>
            </button>
        </div>
    </div>
</div>
