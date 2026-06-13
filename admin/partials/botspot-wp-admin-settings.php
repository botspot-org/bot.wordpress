<?php
/**
 * Admin settings view — new 3-tab dark UI (BotSpot).
 *
 * Structure: Header + Nav + three panel partials. Tab switching is handled
 * entirely client-side via botspot-admin.js (hash-persisted). The form
 * submits through WP's standard options.php with all field names present
 * in the DOM at submit time (inside hidden Advanced section or current tab).
 *
 * @package    Bspt
 * @subpackage Bspt/admin/partials
 * @since      2.2.0
 */

if (!defined("WPINC")) {
    die();
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Admin partial variables are local template state shared with child partials.

// Migrate legacy injection_enabled → split appendix_enabled + jsonld_enabled
Bspt_Options::migrate_injection_toggles();

// Common data used across partials
$bsa_site_domain = wp_parse_url(home_url(), PHP_URL_HOST);
$bsa_post_types = get_post_types(["public" => true], "objects");
$bsa_has_api_key = !empty(Bspt_Options::get("api_key"));
$bsa_webhook_id = Bspt_Options::get("webhook_id");
$bsa_tenant_id = Bspt_Options::get("tenant_id");
$bsa_is_connected = $bsa_has_api_key && !empty($bsa_webhook_id);

// WooCommerce detection
$bsa_woocommerce_active = class_exists("WooCommerce");
?>
<div class="wrap">
    <h1 class="screen-reader-text"><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <div class="botspot-admin-root" data-tab="connect">

        <!-- ============================================================
             HEADER
             ============================================================ -->
        <header class="bsa-header">
            <div class="bsa-header__brand">
                <span class="bsa-header__divider"></span>
                <span class="bsa-header__mark">BOT.SPOT</span>
                <span class="bsa-header__divider"></span>
                <span class="bsa-header__subtitle"><?php esc_html_e("WordPress plugin", "botspot"); ?></span>
            </div>

            <div class="bsa-header__status">
                <div class="bsa-status-pill" data-bsa-status="connection" title="<?php esc_attr_e("Checking connection...", "botspot"); ?>">
                    <span class="bsa-dot bsa-dot--pending"></span>
                    <span class="bsa-status-pill__label"><?php esc_html_e("Connection", "botspot"); ?></span>
                </div>
                <span class="bsa-header__divider bsa-header__divider--tiny"></span>
                <div class="bsa-status-pill" data-bsa-status="sync" title="<?php esc_attr_e("Checking sync...", "botspot"); ?>">
                    <span class="bsa-dot bsa-dot--pending"></span>
                    <span class="bsa-status-pill__label"><?php esc_html_e("Sync", "botspot"); ?></span>
                </div>
                <span class="bsa-header__divider bsa-header__divider--tiny"></span>
                <div class="bsa-status-pill" data-bsa-status="runtime" title="<?php esc_attr_e("Checking runtime...", "botspot"); ?>">
                    <span class="bsa-dot bsa-dot--pending"></span>
                    <span class="bsa-status-pill__label"><?php esc_html_e("Runtime", "botspot"); ?></span>
                </div>
                <span class="bsa-header__divider"></span>
                <span class="bsa-header__domain bsa-mono bsa-tabular-nums"><?php echo esc_html($bsa_site_domain); ?></span>
            </div>
        </header>

        <!-- ============================================================
             TAB NAV
             ============================================================ -->
        <nav class="bsa-tabs">
            <div class="bsa-tabs__group">
                <button type="button" class="bsa-tab" data-bsa-tab="connect" aria-selected="true">
                    <?php esc_html_e("Connect", "botspot"); ?>
                    <span class="bsa-tab__underline"></span>
                </button>
                <button type="button" class="bsa-tab" data-bsa-tab="settings" aria-selected="false">
                    <?php esc_html_e("Settings", "botspot"); ?>
                    <span class="bsa-tab__underline"></span>
                </button>
            </div>
            <div class="bsa-tabs__spacer"></div>
            <div class="bsa-tabs__group bsa-tabs__group--right">
                <button type="button" class="bsa-tab bsa-tab--dev" data-bsa-tab="developer" aria-selected="false">
                    <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="square" d="M8 9l-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"/></svg>
                    <?php esc_html_e("Developer", "botspot"); ?>
                    <span class="bsa-tab__underline"></span>
                </button>
            </div>
        </nav>

        <!-- ============================================================
             SETTINGS FORM (spans all tabs so save works from any tab)
             Settings are saved via AJAX to avoid WP options.php memory issues.
             ============================================================ -->
        <form method="post" id="bsa-settings-form" class="bsa-form" data-bsa-ajax-save="1">

            <!-- Connect tab -->
            <main class="bsa-panel" data-bsa-panel="connect">
                <?php require BSPT_PLUGIN_PATH . "admin/partials/tab-connect.php"; ?>
            </main>

            <!-- Settings tab -->
            <main class="bsa-panel bsa-hidden" data-bsa-panel="settings">
                <?php require BSPT_PLUGIN_PATH . "admin/partials/tab-settings.php"; ?>
            </main>

            <!-- Developer tab (includes Analytics — debug-only, not a top-level tab) -->
            <main class="bsa-panel bsa-hidden" data-bsa-panel="developer">
                <?php require BSPT_PLUGIN_PATH . "admin/partials/tab-developer.php"; ?>
            </main>
        </form>

    </div><!-- /.botspot-admin-root -->
</div><!-- /.wrap -->
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
