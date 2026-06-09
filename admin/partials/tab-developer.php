<?php
/**
 * Developer tab — live log viewer + actions + options + environment.
 *
 * @package BotSpot_WP
 * @subpackage BotSpot_WP/admin/partials
 * @since 2.2.0
 *
 * Variables from parent partial:
 * - $bsa_is_connected (bool)
 * - $bsa_webhook_id (string)
 * - $bsa_tenant_id (string)
 */

if (!defined("WPINC")) {
    die();
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Admin partial variables are local template state provided by the parent settings view.

$bsa_debug_mode = (bool) BotSpot_WP_Options::get("debug_mode", false);
$bsa_cache_ttl = (int) BotSpot_WP_Options::get("cache_ttl", 3600);
?>
<div class="bsa-developer">

    <div class="bsa-developer__grid">

        <!-- Left: Log viewer -->
        <section class="bsa-reveal bsa-reveal--1">
            <div class="bsa-log-head">
                <div class="bsa-log-head__left">
                    <span class="bsa-log-head__title"><?php esc_html_e("Recent logs", "botspot"); ?></span>
                    <span class="bsa-divider-v"></span>
                    <span class="bsa-log-head__count bsa-mono bsa-tabular-nums" data-bsa-log-count>—</span>
                </div>
                <div class="bsa-log-head__actions">
                    <select class="bsa-select bsa-mono" data-bsa-log-filter>
                        <option value="all"><?php esc_html_e("All levels", "botspot"); ?></option>
                        <option value="info"><?php esc_html_e("Info", "botspot"); ?></option>
                        <option value="warning"><?php esc_html_e("Warning", "botspot"); ?></option>
                        <option value="error"><?php esc_html_e("Error", "botspot"); ?></option>
                    </select>
                    <button type="button" class="bsa-icon-btn" data-bsa-action="refresh-logs" title="<?php esc_attr_e("Refresh", "botspot"); ?>">
                        <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M4 4v6h6M20 20v-6h-6M20 8a8 8 0 00-14.9-2M4 16a8 8 0 0014.9 2"/></svg>
                    </button>
                    <button type="button" class="bsa-icon-btn" data-bsa-action="download-logs" title="<?php esc_attr_e("Download", "botspot"); ?>">
                        <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                    </button>
                </div>
            </div>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
            <div class="bsa-log-list" data-bsa-log-list>
                <div class="bsa-log-empty" data-bsa-log-empty><?php esc_html_e("Loading logs...", "botspot"); ?></div>
            </div>
        </section>

        <!-- Right: Controls sidebar -->
        <aside class="bsa-reveal bsa-reveal--2">
            <div class="bsa-sidebar-head">
                <span class="bsa-sidebar-head__label"><?php esc_html_e("Controls", "botspot"); ?></span>
            </div>

            <div class="bsa-sidebar-body">

                <div class="bsa-sidebar-section">
                    <h4 class="bsa-sidebar-section__title"><?php esc_html_e("Actions", "botspot"); ?></h4>
                    <div class="bsa-sidebar-actions">
                        <button type="button" class="bsa-sidebar-btn" data-bsa-action="force-resync">
                            <span><?php esc_html_e("Force re-sync", "botspot"); ?></span>
                            <svg class="bsa-icon bsa-icon--faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M4 4v6h6M20 20v-6h-6M20 8a8 8 0 00-14.9-2M4 16a8 8 0 0014.9 2"/></svg>
                        </button>
                        <button type="button" class="bsa-sidebar-btn" data-bsa-action="clear-cache">
                            <span><?php esc_html_e("Clear cache", "botspot"); ?></span>
                            <svg class="bsa-icon bsa-icon--faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M3 6h18M8 6V4h8v2M6 6v14h12V6"/></svg>
                        </button>
                        <button type="button" class="bsa-sidebar-btn" data-bsa-action="copy-diagnostics">
                            <span><?php esc_html_e("Copy diagnostics", "botspot"); ?></span>
                            <svg class="bsa-icon bsa-icon--faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11"/><path stroke-linecap="square" d="M5 15V5h10"/></svg>
                        </button>
                    </div>
                </div>

                <div class="bsa-sidebar-section">
                    <h4 class="bsa-sidebar-section__title"><?php esc_html_e("Options", "botspot"); ?></h4>
                    <div class="bsa-check-list">
                        <label class="bsa-check">
                            <input type="checkbox" name="botspot_wp_debug_mode" value="1" <?php checked($bsa_debug_mode); ?> />
                            <span><?php esc_html_e("Debug logging", "botspot"); ?></span>
                        </label>
                        <label class="bsa-check">
                            <input type="checkbox" data-bsa-option="verbose_runtime" />
                            <span><?php esc_html_e("Verbose runtime", "botspot"); ?></span>
                        </label>
                        <label class="bsa-check bsa-check--disabled">
                            <input type="checkbox" data-bsa-option="persist_logs" disabled title="<?php esc_attr_e("Currently logs use transients (auto-expire). DB persistence coming soon.", "botspot"); ?>" />
                            <span><?php esc_html_e("Persist logs to DB", "botspot"); ?></span>
                        </label>
                    </div>
                </div>

                <div class="bsa-sidebar-section">
                    <h4 class="bsa-sidebar-section__title"><?php esc_html_e("Cache TTL", "botspot"); ?></h4>
                    <div class="bsa-ttl-row">
                        <input
                            type="number"
                            name="botspot_wp_cache_ttl"
                            value="<?php echo esc_attr($bsa_cache_ttl); ?>"
                            min="60"
                            max="86400"
                            class="bsa-ttl-input bsa-mono bsa-tabular-nums"
                        />
                        <span class="bsa-ttl-label"><?php esc_html_e("seconds", "botspot"); ?></span>
                    </div>
                    <p class="bsa-ttl-help"><?php esc_html_e("How long frontend output is cached. Default: 3600.", "botspot"); ?></p>
                </div>

                <div class="bsa-sidebar-section">
                    <h4 class="bsa-sidebar-section__title"><?php esc_html_e("Connection IDs", "botspot"); ?></h4>
                    <?php if ($bsa_is_connected && !empty($bsa_tenant_id)): ?>
                    <div class="bsa-connection-info bsa-connection-info--sidebar">
                        <div class="bsa-kv">
                            <span class="bsa-kv__key"><?php esc_html_e("Webhook ID", "botspot"); ?></span>
                            <code class="bsa-kv__value bsa-mono"><?php echo esc_html($bsa_webhook_id); ?></code>
                        </div>
                        <div class="bsa-kv">
                            <span class="bsa-kv__key"><?php esc_html_e("Tenant ID", "botspot"); ?></span>
                            <code class="bsa-kv__value bsa-mono"><?php echo esc_html($bsa_tenant_id); ?></code>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="bsa-ttl-help"><?php esc_html_e("Connect this site to view assigned IDs.", "botspot"); ?></p>
                    <?php endif; ?>
                </div>

                <div class="bsa-sidebar-section bsa-sidebar-section--env">
                    <h4 class="bsa-sidebar-section__title"><?php esc_html_e("Environment", "botspot"); ?></h4>
                    <dl class="bsa-env">
                        <div class="bsa-env__row">
                            <dt>plugin</dt>
                            <dd class="bsa-tabular-nums"><?php echo esc_html(BOTSPOT_WP_VERSION); ?></dd>
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

</div>