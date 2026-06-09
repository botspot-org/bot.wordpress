<?php
/**
 * Settings tab — Content to sync, Placement, SEO compatibility + Advanced.
 *
 * @package BotSpot_WP
 * @subpackage BotSpot_WP/admin/partials
 * @since 2.2.0
 *
 * Variables from parent partial:
 * - $bsa_post_types (WP post type objects)
 * - $bsa_woocommerce_active (bool)
 */

if (!defined("WPINC")) {
    die();
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Admin partial variables are local template state provided by the parent settings view.

$bsa_sync_post_types = BotSpot_WP_Options::get("sync_post_types", ["post", "page"]);
$bsa_inject_post_types = BotSpot_WP_Options::get("inject_on_post_types", ["post", "page"]);
$bsa_injection_position = BotSpot_WP_Options::get("injection_position", "bottom_of_content");
$bsa_jsonld_conflict = BotSpot_WP_Options::get("jsonld_conflict_mode", "merge");
$bsa_auto_sync = (bool) BotSpot_WP_Options::get("auto_sync_enabled", true);
$bsa_sync_sensitivity = BotSpot_WP_Options::get("sync_sensitivity", "medium");
$bsa_appendix_enabled = (bool) BotSpot_WP_Options::get("appendix_enabled", true);
$bsa_jsonld_enabled = (bool) BotSpot_WP_Options::get("jsonld_enabled", true);

// Platform-managed by default when connected - settings controlled from bot.spot dashboard
$bsa_is_platform_managed = !empty(BotSpot_WP_Options::get("webhook_id"));
$bsa_dashboard_url = "https://platform.bot.spot";

// Custom post types (exclude built-ins we handle explicitly)
$bsa_builtin_types = ["post", "page", "attachment", "product"];
$bsa_custom_types = array_filter($bsa_post_types, function ($pt) use ($bsa_builtin_types) {
    return !in_array($pt->name, $bsa_builtin_types, true);
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
                <?php if ($bsa_is_platform_managed): ?>
                <a href="<?php echo esc_url($bsa_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                    <?php esc_html_e("Managed in BotSpot", "botspot"); ?> ↗
                </a>
                <?php endif; ?>
            </h3>
            <p class="bsa-settings-row__desc">
                <?php esc_html_e("Updates for selected types are automatically pushed to bot.spot.", "botspot"); ?>
            </p>
        </div>
        <div class="bsa-settings-row__body">
            <?php if ($bsa_is_platform_managed): ?>
            <div class="bsa-readonly-list">
                <?php
                $type_labels = [
                    "post" => __("Posts", "botspot"),
                    "page" => __("Pages", "botspot"),
                    "product" => __("Products", "botspot"),
                ];
                foreach ($bsa_sync_post_types as $type):
                    $label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
                ?>
                <span class="bsa-readonly-item"><?php echo esc_html($label); ?></span>
                <?php endforeach; ?>
                <?php if (empty($bsa_sync_post_types)): ?>
                <span class="bsa-readonly-item bsa-readonly-item--none"><?php esc_html_e("None selected", "botspot"); ?></span>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="checkbox" name="botspot_wp_sync_post_types[]" value="post" <?php checked(in_array("post", $bsa_sync_post_types, true)); ?> />
                    <span><?php esc_html_e("Posts", "botspot"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="checkbox" name="botspot_wp_sync_post_types[]" value="page" <?php checked(in_array("page", $bsa_sync_post_types, true)); ?> />
                    <span><?php esc_html_e("Pages", "botspot"); ?></span>
                </label>
                <label class="bsa-check <?php echo $bsa_woocommerce_active ? "" : "bsa-check--disabled"; ?>">
                    <input type="checkbox" name="botspot_wp_sync_post_types[]" value="product" <?php checked(in_array("product", $bsa_sync_post_types, true)); ?> <?php echo $bsa_woocommerce_active ? "" : "disabled"; ?> />
                    <span>
                        <?php esc_html_e("Products", "botspot"); ?>
                        <span class="bsa-check__tag <?php echo esc_attr($bsa_woocommerce_active ? "bsa-check__tag--active" : ""); ?>">WooCommerce</span>
                    </span>
                </label>
                <?php if (!empty($bsa_custom_types)): ?>
                <details class="bsa-check bsa-check-group">
                    <summary><?php esc_html_e("Custom post types", "botspot"); ?></summary>
                    <div class="bsa-check-list bsa-check-list--nested">
                        <?php foreach ($bsa_custom_types as $pt): ?>
                        <label class="bsa-check">
                            <input type="checkbox" name="botspot_wp_sync_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $bsa_sync_post_types, true)); ?> />
                            <span><?php echo esc_html($pt->label); ?></span>
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
                <?php if ($bsa_is_platform_managed): ?>
                <a href="<?php echo esc_url($bsa_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                    <?php esc_html_e("Managed in BotSpot", "botspot"); ?> ↗
                </a>
                <?php endif; ?>
            </h3>
            <p class="bsa-settings-row__desc"><?php esc_html_e("Where the appendix is injected into the page.", "botspot"); ?></p>
        </div>
        <div class="bsa-settings-row__body">
            <?php if ($bsa_is_platform_managed): ?>
            <?php
            $placement_labels = [
                "bottom_of_content" => __("Bottom of content", "botspot"),
                "auto" => __("Automatic", "botspot"),
                "above_footer" => __("Above footer", "botspot"),
                "bottom_of_page" => __("Bottom of page", "botspot"),
                "footer" => __("Footer", "botspot"),
                "manual" => __("Manual placement", "botspot"),
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
                    <input type="radio" class="bsa-check-as-check" name="botspot_wp_injection_position" value="bottom_of_content" <?php checked($bsa_injection_position, "bottom_of_content"); ?> />
                    <span>
                        <?php esc_html_e("Bottom of content", "botspot"); ?>
                        <span class="bsa-check__tag"><?php esc_html_e("recommended", "botspot"); ?></span>
                    </span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botspot_wp_injection_position" value="above_footer" <?php checked($bsa_injection_position, "above_footer"); ?> />
                    <span><?php esc_html_e("Above footer", "botspot"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botspot_wp_injection_position" value="bottom_of_page" <?php checked($bsa_injection_position, "bottom_of_page"); ?> />
                    <span><?php esc_html_e("Bottom of page", "botspot"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botspot_wp_injection_position" value="manual" <?php checked($bsa_injection_position, "manual"); ?> />
                    <span>
                        <?php esc_html_e("Manual placement", "botspot"); ?>
                        <span class="bsa-check__tag"><code class="bsa-code">[botspot_appendix]</code></span>
                    </span>
                </label>
            </div>
            <p class="bsa-settings-row__note" id="bsa-footer-detection-note" style="display: none;">
                <strong><?php esc_html_e("Note:", "botspot"); ?></strong>
                <?php echo wp_kses_post(__("Footer detection relies on common HTML patterns (<code>&lt;footer&gt;</code>, <code>role=\"contentinfo\"</code>, etc.). If your theme uses a non-standard footer, add the attribute <code>data-botspot-footer</code> to your footer element for reliable placement.", "botspot")); ?>
            </p>
            <script>
            (function() {
                var radios = document.querySelectorAll('input[name="botspot_wp_injection_position"]');
                var note = document.getElementById('bsa-footer-detection-note');
                function toggle() {
                    var val = document.querySelector('input[name="botspot_wp_injection_position"]:checked');
                    note.style.display = (val && (val.value === 'above_footer' || val.value === 'bottom_of_page')) ? 'block' : 'none';
                }
                radios.forEach(function(r) { r.addEventListener('change', toggle); });
                toggle();
            })();
            </script>
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
                    <input type="radio" class="bsa-check-as-check" name="botspot_wp_jsonld_conflict_mode" value="merge" <?php checked($bsa_jsonld_conflict, "merge"); ?> />
                    <span>
                        <?php esc_html_e("Merge with existing output", "botspot"); ?>
                        <span class="bsa-check__tag"><?php esc_html_e("recommended", "botspot"); ?></span>
                    </span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botspot_wp_jsonld_conflict_mode" value="replace" <?php checked($bsa_jsonld_conflict, "replace"); ?> />
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
                <div class="bsa-check-list">
                    <label class="bsa-check">
                        <input type="checkbox" name="botspot_wp_auto_sync_enabled" value="1" <?php checked($bsa_auto_sync); ?> />
                        <span><?php esc_html_e("Enable auto-sync", "botspot"); ?></span>
                    </label>
                </div>
            </div>
        </section>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title"><?php esc_html_e("Sync sensitivity", "botspot"); ?></h3>
                <p class="bsa-settings-row__desc"><?php esc_html_e("Change threshold before resyncing on save.", "botspot"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <div class="bsa-radio-list">
                    <label class="bsa-radio">
                        <input type="radio" name="botspot_wp_sync_sensitivity" value="high" <?php checked($bsa_sync_sensitivity, "high"); ?> />
                        <div>
                            <div class="bsa-radio__title"><?php esc_html_e("High", "botspot"); ?></div>
                            <div class="bsa-radio__desc"><?php esc_html_e("Sync on every save.", "botspot"); ?></div>
                        </div>
                    </label>
                    <label class="bsa-radio">
                        <input type="radio" name="botspot_wp_sync_sensitivity" value="medium" <?php checked($bsa_sync_sensitivity, "medium"); ?> />
                        <div>
                            <div class="bsa-radio__title"><?php esc_html_e("Medium", "botspot"); ?></div>
                            <div class="bsa-radio__desc"><?php esc_html_e("Sync on ≥10% content change.", "botspot"); ?></div>
                        </div>
                    </label>
                    <label class="bsa-radio">
                        <input type="radio" name="botspot_wp_sync_sensitivity" value="low" <?php checked($bsa_sync_sensitivity, "low"); ?> />
                        <div>
                            <div class="bsa-radio__title"><?php esc_html_e("Low", "botspot"); ?></div>
                            <div class="bsa-radio__desc"><?php esc_html_e("Sync on ≥25% content change.", "botspot"); ?></div>
                        </div>
                    </label>
                </div>
            </div>
        </section>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title">
                    <?php esc_html_e("Output toggles", "botspot"); ?>
                    <?php if ($bsa_is_platform_managed): ?>
                    <a href="<?php echo esc_url($bsa_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                        <?php esc_html_e("Managed in BotSpot", "botspot"); ?> ↗
                    </a>
                    <?php endif; ?>
                </h3>
                <p class="bsa-settings-row__desc"><?php esc_html_e("Disable specific injection outputs.", "botspot"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <?php if ($bsa_is_platform_managed): ?>
                <div class="bsa-readonly-toggles">
                    <span class="bsa-readonly-toggle <?php echo esc_attr($bsa_appendix_enabled ? 'bsa-readonly-toggle--on' : 'bsa-readonly-toggle--off'); ?>">
                        <?php esc_html_e("HTML appendix", "botspot"); ?>: <?php echo esc_html($bsa_appendix_enabled ? __("Enabled", "botspot") : __("Disabled", "botspot")); ?>
                    </span>
                    <span class="bsa-readonly-toggle <?php echo esc_attr($bsa_jsonld_enabled ? 'bsa-readonly-toggle--on' : 'bsa-readonly-toggle--off'); ?>">
                        <?php esc_html_e("JSON-LD structured data", "botspot"); ?>: <?php echo esc_html($bsa_jsonld_enabled ? __("Enabled", "botspot") : __("Disabled", "botspot")); ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="bsa-check-list">
                    <label class="bsa-check">
                        <input type="checkbox" name="botspot_wp_appendix_enabled" value="1" <?php checked($bsa_appendix_enabled); ?> />
                        <span><?php esc_html_e("HTML appendix", "botspot"); ?></span>
                    </label>
                    <label class="bsa-check">
                        <input type="checkbox" name="botspot_wp_jsonld_enabled" value="1" <?php checked($bsa_jsonld_enabled); ?> />
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
                    <?php if ($bsa_is_platform_managed): ?>
                    <a href="<?php echo esc_url($bsa_dashboard_url); ?>" target="_blank" rel="noopener" class="bsa-managed-link">
                        <?php esc_html_e("Managed in BotSpot", "botspot"); ?> ↗
                    </a>
                    <?php endif; ?>
                </h3>
                <p class="bsa-settings-row__desc"><?php esc_html_e("Override which post types receive injected output (defaults to synced types).", "botspot"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <?php if ($bsa_is_platform_managed): ?>
                <div class="bsa-readonly-list">
                    <?php
                    $type_labels = [
                        "post" => __("Posts", "botspot"),
                        "page" => __("Pages", "botspot"),
                        "product" => __("Products", "botspot"),
                    ];
                    foreach ($bsa_inject_post_types as $type):
                        $label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
                    ?>
                    <span class="bsa-readonly-item"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($bsa_inject_post_types)): ?>
                    <span class="bsa-readonly-item bsa-readonly-item--none"><?php esc_html_e("None selected", "botspot"); ?></span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="bsa-check-list">
                    <?php foreach ($bsa_post_types as $pt): ?>
                    <label class="bsa-check">
                        <input type="checkbox" name="botspot_wp_inject_on_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $bsa_inject_post_types, true)); ?> />
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
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
