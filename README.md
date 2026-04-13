# BotSpot WP

WordPress plugin for AI discoverability via structured data injection and push-based content sync.

## Description

BotSpot WP connects your WordPress site to the BotSpot platform through two paths:

- **Read path**: Fetches rendered JSON-LD and appendix HTML from locus-core and injects them into your pages server-side.
- **Write path**: Pushes content changes to locus-connectors via webhook on publish, update, or delete.

## Features

- JSON-LD structured data injection into `<head>`
- Appendix HTML injection (bottom of content, above footer, or manual placement)
- Push-based content sync with configurable change-detection thresholds
- Per-page injection control via post meta
- Shortcode (`[botdot_appendix]`), Gutenberg block, WPBakery, and TinyMCE support
- Transient caching with freshness checks and per-request deduplication
- Webhook retry via WP-Cron on failure
- Bulk sync in batches of 100
- Admin settings with 3-tab interface (Connection, Content Sync, Display & Injection)
- Error logging with admin notices on dashboard and post edit screens
- Debug mode for detailed logging

## Requirements

- WordPress 5.0+
- PHP 7.4+

## Installation

1. Upload the `botdot-wp` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to BotSpot in the admin menu to configure

## Configuration

### Connection Tab

| Setting | Description |
|---------|-------------|
| **Locus API URL** | locus-core endpoint for appendix rendering |
| **Connector URL** | locus-connectors endpoint for content push |
| **API Key** | Authentication key for locus-connectors |
| **Botspot Key** | X-Botspot-Key for appendix rendering |
| **Webhook Secret** | HMAC-SHA256 signing secret for webhook payloads |
| **Connection ID** | WordPress connection ID from locus-connectors |

Secret fields (API Key, Botspot Key, Webhook Secret) are never rendered in HTML source. Leave empty when saving to preserve existing values.

### Content Sync Tab

| Setting | Description |
|---------|-------------|
| **Auto-Sync on Publish** | Automatically push content on save |
| **Sync Sensitivity** | High (every save), Medium (10% change), Low (25% change) |
| **Post Types to Sync** | Which post types to push |

### Display & Injection Tab

| Setting | Description |
|---------|-------------|
| **Enable Injection** | Global toggle for JSON-LD + appendix |
| **Injection Position** | Bottom of content, above footer, or manual only |
| **Post Types for Injection** | Which post types receive injected content |
| **Cache TTL** | Transient cache duration (60-86400s) |
| **Debug Mode** | Enable verbose logging |
| **Per-Page Injection** | Toggle injection per page via post meta |

## Architecture

### Read Path (Content Injection)

1. `BotDot_WP_Content_Fetcher::fetch()` retrieves data from locus-core `/api/v1/appendix/render`
2. Results are cached as WordPress transients with freshness checks via `/api/v1/appendix/check`
3. Per-request static cache eliminates duplicate HTTP calls within a single page load
4. `BotDot_WP_Content_Injector` injects JSON-LD into `wp_head` and appendix HTML via `the_content` or `wp_footer`
5. All external HTML is sanitized through `wp_kses` with an extended allowlist
6. JSON-LD is re-encoded through `json_decode`/`wp_json_encode` to prevent script-tag breakout

### Write Path (Content Sync)

1. `BotDot_WP_Sync::on_save_post()` computes content hash and word-count change percentage
2. If change exceeds the configured threshold, sends webhook to locus-connectors
3. On failure, schedules a single WP-Cron retry in 5 minutes
4. `bulk_sync()` processes posts in batches of 100 with per-chunk error tracking

## Developer Hooks

### Filters

| Filter | Args | Description |
|--------|------|-------------|
| `botdot_wp_url_path` | `$path` | Modify the URL path before fetching |
| `botdot_wp_should_inject` | `$should_inject` | Control whether injection happens |
| `botdot_wp_appendix_html` | `$html` | Modify appendix HTML before output |
| `botdot_wp_appendix_jsonld` | `$jsonld` | Modify JSON-LD before output |
| `botdot_wp_should_sync` | `$should_sync, $post_id, $post` | Control whether a post syncs |
| `botdot_wp_sync_payload` | `$payload, $post, $event` | Modify webhook payload |

### Examples

```php
// Skip injection on category archives
add_filter('botdot_wp_should_inject', function($should_inject) {
    return is_category() ? false : $should_inject;
});

// Modify appendix HTML
add_filter('botdot_wp_appendix_html', function($html) {
    return '<div class="my-wrapper">' . $html . '</div>';
});

// Skip syncing drafts converted to publish for a specific category
add_filter('botdot_wp_should_sync', function($should_sync, $post_id, $post) {
    if (has_category('internal', $post)) {
        return false;
    }
    return $should_sync;
}, 10, 3);
```

## Manual Placement

### Shortcode

```
[botdot_appendix]
```

### Gutenberg Block

Search for "BotDot Appendix" in the block inserter.

### WPBakery

The "BotSpot Appendix" element is available under the Content category.

## Building

```bash
./build.sh
```

Creates a distributable zip in `dist/botdot-wp-{version}.zip`.

## Debugging

Enable Debug Mode in the Display & Injection tab. Errors are always logged to PHP `error_log` regardless of `WP_DEBUG`. Recent errors display as admin notices on the dashboard, settings page, and post edit screens.

To enable WordPress debug logging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Changelog

### 2.5.0
- **Change (visible)**: BotSpot JSON-LD is now emitted as a **separate `<script type="application/ld+json">` tag** alongside any SEO plugin's output, instead of being merged into Yoast's or RankMath's `@graph`. This is the "peer schema" model: BotSpot and the SEO plugin are independent publishers of structured data. `locus-core` coordinates emission so BotSpot no longer emits `Organization` or `BreadcrumbList` when the SEO plugin already provides them, avoiding `@id` collisions.
- **Change**: The `jsonld_conflict_mode` option's `"merge"` and `"replace"` values now behave identically in 2.5.0 — both cause BotSpot to emit its peer tag. `"off"` still disables injection entirely. Legacy option is scheduled for removal in a future major release.
- **Fix**: Pre-capture sanitizer in `extract_page_jsonld()` now strips Yoast replacement tokens (`%%name%%`, `%%sitename%%`, etc.) and unresolved Mustache-style placeholders (`{search_term_string}`) from source JSON-LD **before** it's sent to `locus-core`. Addresses the live `forus.fi` `SearchAction` leak flagged on BOT-237 #1. Language-agnostic — matches on shape, not token vocabulary.
- **New**: Polylang + WPML per-post language detection via new `BotDot_WP_Language` helper. Resolution order is Polylang → WPML → WordPress `get_locale()`. Sites without a multilingual plugin behave identically to 2.4.0; sites with Polylang or WPML now send the correct post-specific language to `locus-core` (previously all posts shared the site-wide locale). Affects ingest payloads and the three frontend cache-key / API-fetch sites.
- **Compat**: Requires `locus-core` with peer-schema Stream A deployed (core-side supplement-only JSON-LD + `sanitize_jsonld()`). On older `locus-core`, the plugin side still works but `locus-core` will still merge — net effect is harmless duplication, not breakage.

### 2.4.0
- **New**: Analytics tab in the admin UI with three cards: sync health, enrichment lifecycle, and impressions (including per-bot breakdown for AI crawlers like GPTBot, ClaudeBot, PerplexityBot, etc.).
- **New**: Visitor impressions (including AI crawler hits) are counted in post meta on the hot path via `BotDot_WP_Bot_Classifier`. Classification uses an explicit `monperrus/crawler-user-agents`–derived pattern map for named AI bots and falls back to `jaybizzle/crawler-detect` for any other crawler. Human visitors classify as `human`.
- **New**: Hourly `wp-cron` event `botspot_flush_analytics` flushes pending counters to `locus-core` `/api/v1/analytics/impressions/batch` with a UUID batch_id, single-flight locked, with orphan recovery for crashed flush runs.
- **New**: "Flush now" button and opportunistic flush (fires when Analytics tab opens and `last_flush_at` is older than 2h).
- **Internal**: Added Composer + [Strauss](https://github.com/BrianHenryIE/strauss) to vendor and namespace-scope `jaybizzle/crawler-detect` and `monperrus/crawler-user-agents` under `BotSpot\Vendor\` so they cannot collide with other plugins on the same site.
- **Build**: `build.sh` now runs `composer install --no-dev --optimize-autoloader` and `composer run strauss`, and the zip includes `vendor/botspot-prefixed/`.

### Analytics & system cron

The Analytics tab relies on an hourly wp-cron event (`botspot_flush_analytics`)
to push impression counts to BotSpot. If wp-cron is disabled on your site,
configure a system cron job:

```
*/15 * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron > /dev/null
```

### 2.3.0
- **Fix**: Cache freshness check against `/api/v1/appendix/check` was silently broken. The plugin read a `content_hash` field from the response, but locus-core returned `hash`. The plugin fell through to "treat cache as fresh" on every call, masking the bug. Fixed on the core side by renaming the field; the plugin already reads the correct name.
- **Fix**: Pass `lang` query parameter to `/api/v1/appendix/check` for consistency with the render and jsonld fetchers. Uses `substr(get_locale(), 0, 2)` to match the existing pattern.
- **New**: Plugin reads `cache_ttl` from render/jsonld responses when provided, falling back to the local `cache_ttl` option otherwise. locus-core now suggests a default TTL of 3600 seconds.
- **Change**: Deprecated the plugin's dependency on the `locus-connectors` service. The Connect tab now registers webhooks directly with `locus-core` via `POST /api/v1/webhooks`, which returns `id`, `secret`, and `org_id` in a single call. Two old call sites removed:
  - `handle_register_connection()` no longer POSTs to `{connector_url}/api/v1/connections/register`
  - `handle_test_connection()` no longer probes `{connector_url}/health`
- **Internal**: `connection_id` option is now an alias for `webhook_id` (same UUID), preserved for backwards-compat with call sites that check `connection_id` as the "is connected" signal. Both options are set by `handle_register_connection()`.
- **Note**: The `get_connector_url()` helper and `BOTDOT_WP_CONNECTOR_URL` constant are retained in this release for backwards-compat but are no longer called by the plugin. Plan to remove in a future major release.
- **Compat**: This release requires locus-core with the corresponding `content_hash` / `cache_ttl` / tenant-self-service-webhooks fix. Upgrading the plugin against an older core will leave the cache freshness path still broken (same as before).

### 2.2.2
- **Fix**: Admin UI was still visually off-center on wide WP content areas (the 1200px container was centered in a wider content area, appearing shifted right due to the WP sidebar on the left).
- **Change**: The dark chrome (header bar, tab nav, panel backgrounds) now fills the entire WP content area edge-to-edge. Inner content is centered via `padding-inline: max(24px, calc((100% - 1200px) / 2))` — a single-selector CSS trick that expands padding symmetrically on wide screens without adding HTML wrappers.
- **Change**: Developer tab content now centers at `max-width: 1400px` (wider than the other tabs) to give logs more horizontal space before wrapping.

### 2.2.1
- **Fix**: Layout centering — header, tabs, and form panels now share a single max-width and auto-center within WP's content area (was flush-left / right-pushed depending on tab).
- **Fix**: Settings tab body was left-aligned within its 1200px container; now centered at `max-width: 760px` with `margin-inline: auto`.
- **Fix**: Developer tab log viewer no longer appears pushed to the right on wider screens.
- **Change**: Removed hard `min-width: 900px` on header / tabs / form so the UI now adapts to narrower WP content areas (collapsed sidebar, secondary monitors, etc.).
- **New**: Proper responsive breakpoints for screen adaptation:
  - ≥ 1120px — default 2-column layout
  - < 1120px — tightened Connect sidebar (320px) + Settings label column (160px)
  - < 960px — stack Connect and Developer grids to single column
  - < 820px — stack Settings row labels above controls; wrap header to two rows
  - < 782px — WP mobile breakpoint tweaks (reduced padding, narrower margins)
  - < 640px — hide status-pill text labels (dots only), wrap action rows

### 2.2.0
- **New admin UI**: Dark-themed 3-tab interface (Connect / Settings / Developer) matching the BotSpot brand. Ports the bot.optimizer design to WordPress's admin shell while preserving the sidebar and admin bar.
- **New**: Header status indicators for Connection / Sync / Runtime, fetched live via `wp_ajax_botdot_wp_get_status`. Cached for 5 minutes per admin page load.
- **New**: Developer tab with live log viewer (filterable by level, refreshable, downloadable) backed by `BotDot_WP_Logger::get_logs_for_viewer()`.
- **New**: Developer actions — Test connection, Force re-sync, Clear cache, Copy diagnostics (collects environment + recent logs to clipboard).
- **New**: WooCommerce detection — the `product` checkbox is auto-labeled "Products · WooCommerce" when WC is active, disabled otherwise.
- **New**: "Advanced" collapsible in the Settings tab exposes sync sensitivity, auto-sync toggle, inject-on post type list, and output toggles for power users.
- **New**: AJAX handlers — `botdot_wp_get_logs`, `botdot_wp_get_status`, `botdot_wp_force_resync`, `botdot_wp_clear_cache`.
- **New**: Scoped admin CSS (`admin/css/botspot-admin.css`) and vanilla JS module (`admin/js/botspot-admin.js`) with no build step and no external JS framework.
- **Change**: Note — the new UI hard-overrides to a dark theme regardless of the user's WP admin color scheme. This is an intentional brand-consistency choice.

### 1.0.1
- **Security**: Sanitize all external HTML through `wp_kses` with extended allowlist (S1)
- **Security**: Prevent JSON-LD script-tag breakout via re-encoding (S2)
- **Security**: Remove API keys/secrets from HTML source (S3)
- **Security**: Truncate response bodies in error logs to 500 chars (S4)
- **Security**: Remove raw `$_POST` access from sanitize callbacks (S5)
- **Security**: Add `wp_http_validate_url()` SSRF check for configured URLs (S7)
- **Security**: Use `$wpdb->prepare()` for all LIKE queries (S9)
- **Security**: Escape `paginate_links()` output with `wp_kses_post()` (S10)
- **Fix**: Eliminate dual Content_Injector instances causing double injection (L2)
- **Fix**: `check_freshness()` treats errors as fresh to prevent unnecessary re-fetches (E1)
- **Fix**: Add per-request static cache to eliminate duplicate HTTP calls (E1)
- **Fix**: Add `return` after all `wp_send_json_error()` calls to prevent double-send (E2)
- **Fix**: Show admin notices on post/page edit screens (E3)
- **Fix**: Schedule WP-Cron retry on webhook failure (E4)
- **Fix**: Initialize word count on first sync for accurate change detection (L4/A7)
- **Fix**: Migrate `page_injection_status` from single option to per-post meta (E5/P4)
- **Fix**: Replace deprecated `current_time('timestamp')` with `time()` (E8)
- **Performance**: Bulk sync processes in batches of 100 (P2/P3)
- **Performance**: Consolidate sync stats into single SQL query (P5)
- **Architecture**: Guard admin hooks with `is_admin()` (A4)
- **Architecture**: Remove unused shortcode attributes from TinyMCE and Gutenberg editors (A2/A5)
- **Architecture**: Remove debug-check.sh from production build (A6)

### 1.0.0
- Complete rewrite: replace mirror-domain model with push-based ingestion
- Unified content injector for JSON-LD and appendix HTML
- Push-based content sync via locus-connectors webhooks
- 3-tab admin interface (Connection, Content Sync, Display & Injection)
- Shortcode, Gutenberg block, WPBakery, and TinyMCE support

### 0.7.0
- Fix homepage appendix injection + comprehensive logging

### 0.6.5
- Hotfix release

### 0.6
- Display improvements

### 0.1.0
- Initial release with basic JSON-LD fetching and injection

## License

Proprietary - All rights reserved by BotSpot Team
