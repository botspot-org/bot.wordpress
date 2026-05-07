<?php
/**
 * Settings tab — Content to sync, Placement, SEO compatibility + Advanced.
 *
 * @package BotDot_WP
 * @subpackage BotDot_WP/admin/partials
 * @since 2.2.0
 *
 * Variables from parent partial:
 * - $bsa_post_types (WP post type objects)
 * - $bsa_woocommerce_active (bool)
 */

if (!defined("WPINC")) {
    die();
}

$bsa_sync_post_types = BotDot_WP_Options::get("sync_post_types", ["post", "page"]);
$bsa_inject_post_types = BotDot_WP_Options::get("inject_on_post_types", ["post", "page"]);
$bsa_injection_position = BotDot_WP_Options::get("injection_position", "bottom_of_content");
$bsa_jsonld_conflict = BotDot_WP_Options::get("jsonld_conflict_mode", "merge");
$bsa_auto_sync = (bool) BotDot_WP_Options::get("auto_sync_enabled", true);
$bsa_sync_sensitivity = BotDot_WP_Options::get("sync_sensitivity", "medium");
$bsa_appendix_enabled = (bool) BotDot_WP_Options::get("appendix_enabled", true);
$bsa_jsonld_enabled = (bool) BotDot_WP_Options::get("jsonld_enabled", true);

// Custom post types (exclude built-ins we handle explicitly)
$bsa_builtin_types = ["post", "page", "attachment", "product"];
$bsa_custom_types = array_filter($bsa_post_types, function ($pt) use ($bsa_builtin_types) {
    return !in_array($pt->name, $bsa_builtin_types, true);
});
?>
<div class="bsa-settings">

    <div class="bsa-section-head bsa-reveal bsa-reveal--1">
        <h1 class="bsa-h1"><?php _e("Settings", "botdot-wp"); ?></h1>
        <p class="bsa-description">
            <?php _e("Optional WordPress-specific runtime settings. Content, review flow, styling, and publishing are managed in bot.spot.", "botdot-wp"); ?>
        </p>
    </div>

    <!-- Content to sync -->
    <section class="bsa-settings-row bsa-reveal bsa-reveal--2">
        <div class="bsa-settings-row__meta">
            <h3 class="bsa-settings-row__title"><?php _e("Content to sync", "botdot-wp"); ?></h3>
            <p class="bsa-settings-row__desc">
                <?php _e("Updates for selected types are automatically pushed to bot.spot.", "botdot-wp"); ?>
            </p>
        </div>
        <div class="bsa-settings-row__body">
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="checkbox" name="botdot_wp_sync_post_types[]" value="post" <?php checked(in_array("post", $bsa_sync_post_types, true)); ?> />
                    <span><?php _e("Posts", "botdot-wp"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="checkbox" name="botdot_wp_sync_post_types[]" value="page" <?php checked(in_array("page", $bsa_sync_post_types, true)); ?> />
                    <span><?php _e("Pages", "botdot-wp"); ?></span>
                </label>
                <label class="bsa-check <?php echo $bsa_woocommerce_active ? "" : "bsa-check--disabled"; ?>">
                    <input type="checkbox" name="botdot_wp_sync_post_types[]" value="product" <?php checked(in_array("product", $bsa_sync_post_types, true)); ?> <?php echo $bsa_woocommerce_active ? "" : "disabled"; ?> />
                    <span>
                        <?php _e("Products", "botdot-wp"); ?>
                        <span class="bsa-check__tag <?php echo $bsa_woocommerce_active ? "bsa-check__tag--active" : ""; ?>">WooCommerce</span>
                    </span>
                </label>
                <?php if (!empty($bsa_custom_types)): ?>
                <details class="bsa-check bsa-check-group">
                    <summary><?php _e("Custom post types", "botdot-wp"); ?></summary>
                    <div class="bsa-check-list bsa-check-list--nested">
                        <?php foreach ($bsa_custom_types as $pt): ?>
                        <label class="bsa-check">
                            <input type="checkbox" name="botdot_wp_sync_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $bsa_sync_post_types, true)); ?> />
                            <span><?php echo esc_html($pt->label); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php else: ?>
                <label class="bsa-check bsa-check--disabled">
                    <input type="checkbox" disabled />
                    <span><?php _e("Custom post types", "botdot-wp"); ?></span>
                </label>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Placement -->
    <section class="bsa-settings-row bsa-reveal bsa-reveal--3">
        <div class="bsa-settings-row__meta">
            <h3 class="bsa-settings-row__title"><?php _e("Placement", "botdot-wp"); ?></h3>
            <p class="bsa-settings-row__desc"><?php _e("Where the appendix is injected into the page.", "botdot-wp"); ?></p>
        </div>
        <div class="bsa-settings-row__body">
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botdot_wp_injection_position" value="bottom_of_content" <?php checked($bsa_injection_position, "bottom_of_content"); ?> />
                    <span>
                        <?php _e("Bottom of content", "botdot-wp"); ?>
                        <span class="bsa-check__tag"><?php _e("recommended", "botdot-wp"); ?></span>
                    </span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botdot_wp_injection_position" value="above_footer" <?php checked($bsa_injection_position, "above_footer"); ?> />
                    <span><?php _e("Above footer", "botdot-wp"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botdot_wp_injection_position" value="bottom_of_page" <?php checked($bsa_injection_position, "bottom_of_page"); ?> />
                    <span><?php _e("Bottom of page", "botdot-wp"); ?></span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botdot_wp_injection_position" value="manual" <?php checked($bsa_injection_position, "manual"); ?> />
                    <span>
                        <?php _e("Manual placement", "botdot-wp"); ?>
                        <span class="bsa-check__tag"><code class="bsa-code">[botspot_appendix]</code></span>
                    </span>
                </label>
            </div>
            <p class="bsa-settings-row__note" id="bsa-footer-detection-note" style="display: none; margin-top: 12px; padding: 10px; background: #fff8e5; border-left: 3px solid #ffb900; font-size: 13px;">
                <strong><?php _e("Note:", "botdot-wp"); ?></strong>
                <?php _e("Footer detection relies on common HTML patterns (<code>&lt;footer&gt;</code>, <code>role=\"contentinfo\"</code>, etc.). If your theme uses a non-standard footer, add the attribute <code>data-botspot-footer</code> to your footer element for reliable placement.", "botdot-wp"); ?>
            </p>
            <script>
            (function() {
                var radios = document.querySelectorAll('input[name="botdot_wp_injection_position"]');
                var note = document.getElementById('bsa-footer-detection-note');
                function toggle() {
                    var val = document.querySelector('input[name="botdot_wp_injection_position"]:checked');
                    note.style.display = (val && (val.value === 'above_footer' || val.value === 'bottom_of_page')) ? 'block' : 'none';
                }
                radios.forEach(function(r) { r.addEventListener('change', toggle); });
                toggle();
            })();
            </script>
        </div>
    </section>

    <!-- SEO compatibility -->
    <section class="bsa-settings-row bsa-reveal bsa-reveal--4">
        <div class="bsa-settings-row__meta">
            <h3 class="bsa-settings-row__title"><?php _e("SEO compatibility", "botdot-wp"); ?></h3>
            <p class="bsa-settings-row__desc"><?php _e("How JSON-LD output interacts with other SEO plugins.", "botdot-wp"); ?></p>
        </div>
        <div class="bsa-settings-row__body">
            <div class="bsa-check-list">
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botdot_wp_jsonld_conflict_mode" value="merge" <?php checked($bsa_jsonld_conflict, "merge"); ?> />
                    <span>
                        <?php _e("Merge with existing output", "botdot-wp"); ?>
                        <span class="bsa-check__tag"><?php _e("recommended", "botdot-wp"); ?></span>
                    </span>
                </label>
                <label class="bsa-check">
                    <input type="radio" class="bsa-check-as-check" name="botdot_wp_jsonld_conflict_mode" value="replace" <?php checked($bsa_jsonld_conflict, "replace"); ?> />
                    <span><?php _e("Replace conflicting output", "botdot-wp"); ?></span>
                </label>
            </div>
        </div>
    </section>

    <!-- Advanced (collapsible) -->
    <details class="bsa-advanced bsa-reveal bsa-reveal--4">
        <summary class="bsa-advanced__summary">
            <span><?php _e("Advanced", "botdot-wp"); ?></span>
            <span class="bsa-advanced__chevron" aria-hidden="true">▸</span>
        </summary>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title"><?php _e("Auto-sync on publish", "botdot-wp"); ?></h3>
                <p class="bsa-settings-row__desc"><?php _e("Automatically push content changes to bot.spot on save.", "botdot-wp"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <label class="bsa-toggle-row">
                    <input type="checkbox" class="bsa-toggle" name="botdot_wp_auto_sync_enabled" value="1" <?php checked($bsa_auto_sync); ?> />
                    <span><?php _e("Enable auto-sync", "botdot-wp"); ?></span>
                </label>
            </div>
        </section>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title"><?php _e("Sync sensitivity", "botdot-wp"); ?></h3>
                <p class="bsa-settings-row__desc"><?php _e("Change threshold before resyncing on save.", "botdot-wp"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <div class="bsa-radio-list">
                    <label class="bsa-radio">
                        <input type="radio" name="botdot_wp_sync_sensitivity" value="high" <?php checked($bsa_sync_sensitivity, "high"); ?> />
                        <div>
                            <div class="bsa-radio__title"><?php _e("High", "botdot-wp"); ?></div>
                            <div class="bsa-radio__desc"><?php _e("Sync on every save.", "botdot-wp"); ?></div>
                        </div>
                    </label>
                    <label class="bsa-radio">
                        <input type="radio" name="botdot_wp_sync_sensitivity" value="medium" <?php checked($bsa_sync_sensitivity, "medium"); ?> />
                        <div>
                            <div class="bsa-radio__title"><?php _e("Medium", "botdot-wp"); ?></div>
                            <div class="bsa-radio__desc"><?php _e("Sync on ≥10% content change.", "botdot-wp"); ?></div>
                        </div>
                    </label>
                    <label class="bsa-radio">
                        <input type="radio" name="botdot_wp_sync_sensitivity" value="low" <?php checked($bsa_sync_sensitivity, "low"); ?> />
                        <div>
                            <div class="bsa-radio__title"><?php _e("Low", "botdot-wp"); ?></div>
                            <div class="bsa-radio__desc"><?php _e("Sync on ≥25% content change.", "botdot-wp"); ?></div>
                        </div>
                    </label>
                </div>
            </div>
        </section>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title"><?php _e("Output toggles", "botdot-wp"); ?></h3>
                <p class="bsa-settings-row__desc"><?php _e("Disable specific injection outputs.", "botdot-wp"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <label class="bsa-toggle-row">
                    <input type="checkbox" class="bsa-toggle" name="botdot_wp_appendix_enabled" value="1" <?php checked($bsa_appendix_enabled); ?> />
                    <span><?php _e("HTML appendix", "botdot-wp"); ?></span>
                </label>
                <label class="bsa-toggle-row">
                    <input type="checkbox" class="bsa-toggle" name="botdot_wp_jsonld_enabled" value="1" <?php checked($bsa_jsonld_enabled); ?> />
                    <span><?php _e("JSON-LD structured data", "botdot-wp"); ?></span>
                </label>
            </div>
        </section>

        <section class="bsa-settings-row">
            <div class="bsa-settings-row__meta">
                <h3 class="bsa-settings-row__title"><?php _e("Inject on post types", "botdot-wp"); ?></h3>
                <p class="bsa-settings-row__desc"><?php _e("Override which post types receive injected output (defaults to synced types).", "botdot-wp"); ?></p>
            </div>
            <div class="bsa-settings-row__body">
                <div class="bsa-check-list">
                    <?php foreach ($bsa_post_types as $pt): ?>
                    <label class="bsa-check">
                        <input type="checkbox" name="botdot_wp_inject_on_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $bsa_inject_post_types, true)); ?> />
                        <span><?php echo esc_html($pt->label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </details>

    <!-- Footer: save bar -->
    <div class="bsa-save-bar">
        <span class="bsa-save-bar__status" data-bsa-save-status><?php _e("All changes saved", "botdot-wp"); ?></span>
        <div class="bsa-save-bar__actions">
            <button type="button" class="bsa-btn" data-bsa-action="reset-form">
                <?php _e("Reset", "botdot-wp"); ?>
            </button>
            <button type="submit" class="bsa-btn bsa-btn--primary" name="submit">
                <?php _e("Save changes", "botdot-wp"); ?>
            </button>
        </div>
    </div>
</div>
