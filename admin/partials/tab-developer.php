<?php
/**
 * Developer tab — live log viewer + actions + options + environment.
 *
 * @package BotDot_WP
 * @subpackage BotDot_WP/admin/partials
 * @since 2.2.0
 */

if (!defined("WPINC")) {
    die();
}

$bsa_debug_mode = (bool) BotDot_WP_Options::get("debug_mode", false);
$bsa_cache_ttl = (int) BotDot_WP_Options::get("cache_ttl", 3600);
?>
<div class="bsa-developer">

    <div class="bsa-developer__grid">

        <!-- Left: Log viewer -->
        <section class="bsa-reveal bsa-reveal--1">
            <div class="bsa-log-head">
                <div class="bsa-log-head__left">
                    <span class="bsa-log-head__title"><?php _e("Recent logs", "botdot-wp"); ?></span>
                    <span class="bsa-divider-v"></span>
                    <span class="bsa-log-head__count bsa-mono bsa-tabular-nums" data-bsa-log-count>—</span>
                </div>
                <div class="bsa-log-head__actions">
                    <select class="bsa-select bsa-mono" data-bsa-log-filter>
                        <option value="all"><?php _e("All levels", "botdot-wp"); ?></option>
                        <option value="info"><?php _e("Info", "botdot-wp"); ?></option>
                        <option value="warning"><?php _e("Warning", "botdot-wp"); ?></option>
                        <option value="error"><?php _e("Error", "botdot-wp"); ?></option>
                    </select>
                    <button type="button" class="bsa-icon-btn" data-bsa-action="refresh-logs" title="<?php esc_attr_e("Refresh", "botdot-wp"); ?>">
                        <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M4 4v6h6M20 20v-6h-6M20 8a8 8 0 00-14.9-2M4 16a8 8 0 0014.9 2"/></svg>
                    </button>
                    <button type="button" class="bsa-icon-btn" data-bsa-action="download-logs" title="<?php esc_attr_e("Download", "botdot-wp"); ?>">
                        <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                    </button>
                </div>
            </div>
            <div class="bsa-log-list" data-bsa-log-list>
                <div class="bsa-log-empty" data-bsa-log-empty><?php _e("Loading logs...", "botdot-wp"); ?></div>
            </div>
        </section>

        <!-- Right: Controls sidebar -->
        <aside class="bsa-reveal bsa-reveal--2">
            <div class="bsa-sidebar-head">
                <span class="bsa-sidebar-head__label"><?php _e("Controls", "botdot-wp"); ?></span>
            </div>

            <div class="bsa-sidebar-body">

                <div class="bsa-sidebar-section">
                    <h4 class="bsa-sidebar-section__title"><?php _e("Actions", "botdot-wp"); ?></h4>
                    <div class="bsa-sidebar-actions">
                        <button type="button" class="bsa-sidebar-btn" data-bsa-action="test-connection">
                            <span><?php _e("Test connection", "botdot-wp"); ?></span>
                            <svg class="bsa-icon bsa-icon--faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M5 12h14M13 5l7 7-7 7"/></svg>
                        </button>
                        <button type="button" class="bsa-sidebar-btn" data-bsa-action="force-resync">
                            <span><?php _e("Force re-sync", "botdot-wp"); ?></span>
                            <svg class="bsa-icon bsa-icon--faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M4 4v6h6M20 20v-6h-6M20 8a8 8 0 00-14.9-2M4 16a8 8 0 0014.9 2"/></svg>
                        </button>
                        <button type="button" class="bsa-sidebar-btn" data-bsa-action="clear-cache">
                            <span><?php _e("Clear cache", "botdot-wp"); ?></span>
                            <svg class="bsa-icon bsa-icon--faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M3 6h18M8 6V4h8v2M6 6v14h12V6"/></svg>
                        </button>
                        <button type="button" class="bsa-sidebar-btn" data-bsa-action="copy-diagnostics">
                            <span><?php _e("Copy diagnostics", "botdot-wp"); ?></span>
                            <svg class="bsa-icon bsa-icon--faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11"/><path stroke-linecap="square" d="M5 15V5h10"/></svg>
                        </button>
                    </div>
                </div>

                <div class="bsa-sidebar-section">
                    <h4 class="bsa-sidebar-section__title"><?php _e("Options", "botdot-wp"); ?></h4>
                    <div class="bsa-sidebar-toggles">
                        <label class="bsa-toggle-row">
                            <span><?php _e("Debug logging", "botdot-wp"); ?></span>
                            <input type="checkbox" class="bsa-toggle" name="botdot_wp_debug_mode" value="1" <?php checked($bsa_debug_mode); ?> />
                        </label>
                        <label class="bsa-toggle-row">
                            <span><?php _e("Verbose runtime", "botdot-wp"); ?></span>
                            <input type="checkbox" class="bsa-toggle" data-bsa-option="verbose_runtime" />
                        </label>
                        <label class="bsa-toggle-row">
                            <span><?php _e("Persist logs to DB", "botdot-wp"); ?></span>
                            <input type="checkbox" class="bsa-toggle" data-bsa-option="persist_logs" disabled title="<?php esc_attr_e("Currently logs use transients (auto-expire). DB persistence coming soon.", "botdot-wp"); ?>" />
                        </label>
                    </div>
                </div>

                <div class="bsa-sidebar-section">
                    <h4 class="bsa-sidebar-section__title"><?php _e("Cache TTL", "botdot-wp"); ?></h4>
                    <div class="bsa-ttl-row">
                        <input
                            type="number"
                            name="botdot_wp_cache_ttl"
                            value="<?php echo esc_attr($bsa_cache_ttl); ?>"
                            min="60"
                            max="86400"
                            class="bsa-ttl-input bsa-mono bsa-tabular-nums"
                        />
                        <span class="bsa-ttl-label"><?php _e("seconds", "botdot-wp"); ?></span>
                    </div>
                    <p class="bsa-ttl-help"><?php _e("How long frontend output is cached. Default: 3600.", "botdot-wp"); ?></p>
                </div>

                <div class="bsa-sidebar-section bsa-sidebar-section--env">
                    <h4 class="bsa-sidebar-section__title"><?php _e("Environment", "botdot-wp"); ?></h4>
                    <dl class="bsa-env">
                        <div class="bsa-env__row">
                            <dt>plugin</dt>
                            <dd class="bsa-tabular-nums"><?php echo esc_html(BOTDOT_WP_VERSION); ?></dd>
                        </div>
                        <div class="bsa-env__row">
                            <dt>wordpress</dt>
                            <dd class="bsa-tabular-nums"><?php echo esc_html(get_bloginfo("version")); ?></dd>
                        </div>
                        <div class="bsa-env__row">
                            <dt>php</dt>
                            <dd class="bsa-tabular-nums"><?php echo esc_html(phpversion()); ?></dd>
                        </div>
                        <div class="bsa-env__row">
                            <dt>api</dt>
                            <dd>v1 (stable)</dd>
                        </div>
                    </dl>
                </div>

            </div>
        </aside>

    </div>

    <!-- Analytics (debugging-only; moved to bottom so logs + controls lead). -->
    <section class="bsa-developer__analytics bsa-reveal bsa-reveal--3">
        <?php require BOTDOT_WP_PLUGIN_PATH . "admin/partials/tab-analytics.php"; ?>
    </section>
</div>
