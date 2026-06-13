<?php
/**
 * Connect tab — access key entry, connect, setup guide.
 *
 * @package Bspt
 * @subpackage Bspt/admin/partials
 * @since 2.2.0
 *
 * Variables from parent partial:
 * - $bsa_has_api_key (bool)
 * - $bsa_is_connected (bool)
 * - $bsa_webhook_id (string)
 * - $bsa_tenant_id (string)
 */

if (!defined("WPINC")) {
    die();
}
?>
<div class="bsa-connect">
    <div class="bsa-connect__grid">

        <!-- Left: form -->
        <section class="bsa-reveal bsa-reveal--1">
            <div class="bsa-section-head">
                <h1 class="bsa-h1">
                    <?php _e("Connect your", "botspot-wp"); ?>
                    <span class="bsa-h1__accent"><?php _e("site", "botspot-wp"); ?></span>
                </h1>
                <p class="bsa-description">
                    <?php _e("Paste your access key to connect this WordPress site to bot.spot. Content, review, and publishing are managed there.", "botspot-wp"); ?>
                </p>
            </div>

            <div class="bsa-field">
                <label class="bsa-label" for="bsa-api-key"><?php _e("Access key", "botspot-wp"); ?></label>
                <div class="bsa-input-group" data-bsa-component="api-key-input">
                    <div class="bsa-input-group__prefix" aria-hidden="true">
                        <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="square" d="M15 7a4 4 0 11-5.2 3.8L3 17.5V21h3.5l1-1v-1.5h1.5v-1.5H10l1.2-1.2A4 4 0 1115 7z"/></svg>
                    </div>
                    <input
                        id="bsa-api-key"
                        type="password"
                        name="bspt_api_key"
                        class="bsa-input-group__input bsa-mono bsa-tabular-nums"
                        value=""
                        placeholder="<?php echo $bsa_has_api_key ? "••••••••••••••••••••••••" : "sk_live_xxxxxxxxxxxxxxxxxxxxxxxx"; ?>"
                        autocomplete="off"
                        data-has-value="<?php echo $bsa_has_api_key ? "1" : "0"; ?>"
                    />
                    <button type="button" class="bsa-input-group__affix" data-bsa-action="toggle-key-visibility">
                        <?php _e("Show", "botspot-wp"); ?>
                    </button>
                </div>
                <?php if ($bsa_has_api_key): ?>
                    <p class="bsa-help"><?php _e("Leave empty to keep the current key.", "botspot-wp"); ?></p>
                <?php endif; ?>
            </div>

            <div class="bsa-actions">
                <button type="button" class="bsa-btn bsa-btn--primary" data-bsa-action="test-connection" data-bsa-requires-key="1" data-bsa-is-connected="<?php echo $bsa_is_connected ? '1' : '0'; ?>" <?php echo !$bsa_has_api_key ? 'disabled' : ''; ?>>
                    <?php _e("Connect", "botspot-wp"); ?>
                </button>
                <?php if ($bsa_is_connected): ?>
                    <span class="bsa-status-pill bsa-status-pill--connected" data-bsa-connected-indicator>
                        <span class="bsa-dot bsa-dot--ok bsa-dot--pulse"></span>
                        <span class="bsa-status-pill__label"><?php _e("Connected", "botspot-wp"); ?></span>
                    </span>
                <?php endif; ?>
                <span class="bsa-divider-v"></span>
                <a href="https://platform.bot.spot/integrations" target="_blank" rel="noopener" class="bsa-link">
                    <?php _e("Open bot.spot", "botspot-wp"); ?>
                    <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M7 17L17 7M17 7H9M17 7v8"/></svg>
                </a>
            </div>

            <div class="bsa-result bsa-hidden" data-bsa-result="test-connection"></div>
        </section>

        <!-- Right: setup guide + next step -->
        <aside class="bsa-connect__sidebar bsa-reveal bsa-reveal--2">
            <div class="bsa-card">
                <div class="bsa-card__head">
                    <span class="bsa-card__title"><?php _e("Setup guide", "botspot-wp"); ?></span>
                </div>
                <ol class="bsa-steps">
                    <li class="bsa-step">
                        <span class="bsa-step__num">1</span>
                        <div class="bsa-step__body">
                            <p class="bsa-step__title">
                                <a href="https://platform.bot.spot/integrations" target="_blank" rel="noopener"><?php _e("Sign in to bot.spot", "botspot-wp"); ?></a>
                            </p>
                            <p class="bsa-step__desc"><?php _e("Use your workspace account.", "botspot-wp"); ?></p>
                        </div>
                    </li>
                    <li class="bsa-step">
                        <span class="bsa-step__num">2</span>
                        <div class="bsa-step__body">
                            <p class="bsa-step__title"><?php _e("Go to Integrations", "botspot-wp"); ?></p>
                            <p class="bsa-step__desc"><?php _e("Find the WordPress card in your workspace.", "botspot-wp"); ?></p>
                        </div>
                    </li>
                    <li class="bsa-step">
                        <span class="bsa-step__num">3</span>
                        <div class="bsa-step__body">
                            <p class="bsa-step__title"><?php _e("Generate an access key", "botspot-wp"); ?></p>
                            <p class="bsa-step__desc"><?php _e("Copy it to your clipboard.", "botspot-wp"); ?></p>
                        </div>
                    </li>
                    <li class="bsa-step bsa-step--active">
                        <span class="bsa-step__num">4</span>
                        <div class="bsa-step__body">
                            <p class="bsa-step__title"><?php _e("Paste it here and connect", "botspot-wp"); ?></p>
                            <p class="bsa-step__desc"><?php _e("You're done.", "botspot-wp"); ?></p>
                        </div>
                    </li>
                </ol>
            </div>

            <div class="bsa-card bsa-card--accent">
                <div class="bsa-card__grid-bg"></div>
                <div class="bsa-card__body">
                    <div class="bsa-next-step__head">
                        <div class="bsa-next-step__icon">
                            <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="square" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="bsa-next-step__label"><?php _e("Next step", "botspot-wp"); ?></span>
                    </div>
                    <h3 class="bsa-next-step__title">
                        <?php _e("Open bot.spot to generate, review, and publish your content.", "botspot-wp"); ?>
                    </h3>
                    <p class="bsa-next-step__desc">
                        <?php _e("Your appendix, schema, and styling live in the platform. This plugin handles syncing and injection automatically.", "botspot-wp"); ?>
                    </p>
                    <a href="https://platform.bot.spot/integrations" target="_blank" rel="noopener" class="bsa-btn bsa-btn--ghost">
                        <?php _e("Open bot.spot", "botspot-wp"); ?>
                        <svg class="bsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M7 17L17 7M17 7H9M17 7v8"/></svg>
                    </a>
                </div>
            </div>
        </aside>

    </div>
</div>
