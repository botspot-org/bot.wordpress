<?php
/**
 * Analytics tab — sync health + enrichment lifecycle + impressions cards.
 *
 * Loaded by admin/partials/botspot-wp-admin-settings.php when the
 * Analytics tab is active. All data is fetched asynchronously by
 * admin/js/botspot-admin.js via AJAX to the handlers in
 * admin/class-botspot-wp-admin.php.
 *
 * @package BotSpot_WP
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bsa-analytics">
    <header class="bsa-analytics__header">
        <h1><?php esc_html_e('Analytics', 'botspot-wp'); ?></h1>
        <p><?php esc_html_e('Insights into how your bot.spot content is syncing, enriching, and being served.', 'botspot-wp'); ?></p>

        <div class="bsa-analytics__window-selector" role="tablist">
            <button type="button" data-window="24h" class="bsa-btn bsa-btn--ghost"><?php esc_html_e('24h', 'botspot-wp'); ?></button>
            <button type="button" data-window="7d" class="bsa-btn bsa-btn--ghost is-active"><?php esc_html_e('7d', 'botspot-wp'); ?></button>
            <button type="button" data-window="30d" class="bsa-btn bsa-btn--ghost"><?php esc_html_e('30d', 'botspot-wp'); ?></button>
        </div>
    </header>

    <section data-bsa-analytics="sync" class="bsa-card bsa-analytics-card--sync">
        <h3><?php esc_html_e('Sync health', 'botspot-wp'); ?></h3>
        <div class="bsa-analytics-card__body" data-bsa-state="loading">
            <p class="bsa-muted"><?php esc_html_e('Loading...', 'botspot-wp'); ?></p>
        </div>
    </section>

    <section data-bsa-analytics="enrichment" class="bsa-card bsa-analytics-card--enrichment">
        <h3><?php esc_html_e('Enrichment lifecycle', 'botspot-wp'); ?></h3>
        <div class="bsa-analytics-card__body" data-bsa-state="loading">
            <p class="bsa-muted"><?php esc_html_e('Loading...', 'botspot-wp'); ?></p>
        </div>
    </section>

    <section data-bsa-analytics="impressions" class="bsa-card bsa-analytics-card--impressions">
        <h3><?php esc_html_e('Impressions', 'botspot-wp'); ?></h3>
        <div class="bsa-analytics-card__body" data-bsa-state="loading">
            <p class="bsa-muted"><?php esc_html_e('Loading...', 'botspot-wp'); ?></p>
        </div>
    </section>

    <footer class="bsa-analytics__footer">
        <span data-bsa-next-flush class="bsa-muted"><?php esc_html_e('Next flush: pending', 'botspot-wp'); ?></span>
        <button type="button" data-bsa-action="force-flush" class="bsa-btn bsa-btn--primary"><?php esc_html_e('Flush now', 'botspot-wp'); ?></button>
    </footer>
</div>
