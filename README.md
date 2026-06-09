# BotSpot

WordPress plugin for AI discoverability via structured data injection and push-based content sync.

## Description

BotSpot connects your WordPress site to the BotSpot platform through two paths:

- **Read path**: Fetches rendered JSON-LD and appendix HTML from BotSpot and injects them into your pages server-side.
- **Write path**: Pushes selected published content changes to BotSpot on publish or update.

## Features

- JSON-LD structured data injection into `<head>`
- Appendix HTML injection (bottom of content, above footer, or manual placement)
- Push-based content sync with configurable change-detection thresholds
- Per-page injection control via post meta
- Shortcode (`[botspot_appendix]`, with `[botdot_appendix]` retained as a legacy alias), Gutenberg block, WPBakery, and TinyMCE support
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

1. Upload the `botspot` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to BotSpot in the admin menu to configure

## Configuration

### Connection Tab

| Setting | Description |
|---------|-------------|
| **Access Key** | BotSpot access key used for connection, content sync, rendering, webhooks, and analytics |
| **Webhook Secret** | HMAC-SHA256 signing secret returned by BotSpot for webhook payloads |
| **Connection ID** | BotSpot webhook/connection identifier |

Secret fields are never rendered in HTML source. Leave empty when saving to preserve existing values.

### Content Sync Tab

| Setting | Description |
|---------|-------------|
| **Auto-Sync on Publish** | Automatically push content on save |
| **Sync Sensitivity** | High (every save), Medium (10% change), Low (25% change) |
| **Post Types to Sync** | Which post types to push |

### Display & Injection Tab

| Setting | Description |
|---------|-------------|
| **Enable Appendix** | Toggle appendix HTML output |
| **Enable JSON-LD** | Toggle structured data output |
| **Injection Position** | Bottom of content, above footer, or manual only |
| **Post Types for Injection** | Which post types receive injected content |
| **Cache TTL** | Transient cache duration (60-86400s) |
| **Debug Mode** | Enable verbose logging |

## Architecture

### Read Path (Content Injection)

1. `BotSpot_WP_Content_Fetcher::fetch()` retrieves data from BotSpot `/api/v1/appendix/render`
2. Results are cached as WordPress transients with freshness checks via `/api/v1/appendix/check`
3. Per-request static cache eliminates duplicate HTTP calls within a single page load
4. `BotSpot_WP_Content_Injector` injects JSON-LD into `wp_head` and appendix HTML via `the_content` or `wp_footer`
5. All external HTML is sanitized through `wp_kses` with an extended allowlist
6. JSON-LD is re-encoded through `json_decode`/`wp_json_encode` to prevent script-tag breakout

### Write Path (Content Sync)

1. `BotSpot_WP_Sync::on_save_post()` computes content hash and word-count change percentage
2. If change exceeds the configured threshold, sends selected published content to BotSpot
3. On failure, schedules a single WP-Cron retry in 5 minutes
4. `bulk_sync()` processes posts in batches of 100 with per-chunk error tracking

## Developer Hooks

### Filters

| Filter | Args | Description |
|--------|------|-------------|
| `botspot_wp_url_path` | `$path` | Modify the URL path before fetching |
| `botspot_wp_should_inject` | `$should_inject` | Control whether injection happens |
| `botspot_wp_appendix_html` | `$html` | Modify appendix HTML before output |
| `botspot_wp_appendix_jsonld` | `$jsonld` | Modify JSON-LD before output |
| `botspot_wp_should_sync` | `$should_sync, $post_id, $post` | Control whether a post syncs |
| `botspot_wp_sync_payload` | `$payload, $post, $event` | Modify webhook payload |

### Examples

```php
// Skip injection on category archives
add_filter('botspot_wp_should_inject', function($should_inject) {
    return is_category() ? false : $should_inject;
});

// Modify appendix HTML
add_filter('botspot_wp_appendix_html', function($html) {
    return '<div class="my-wrapper">' . $html . '</div>';
});

// Skip syncing drafts converted to publish for a specific category
add_filter('botspot_wp_should_sync', function($should_sync, $post_id, $post) {
    if (has_category('internal', $post)) {
        return false;
    }
    return $should_sync;
}, 10, 3);
```

## Manual Placement

### Shortcode

```
[botspot_appendix]
```

The legacy `[botdot_appendix]` shortcode remains available for upgraded sites.

### Gutenberg Block

Search for "BotSpot Appendix" in the block inserter.

### WPBakery

The "BotSpot Appendix" element is available under the Content category.

## Building

```bash
./build.sh
```

Creates a production distributable zip in `dist/botspot-{version}.zip`. Use `TARGET=staging ./build.sh` or `./build.sh --staging` only for internal staging archives.

The package includes `readme.txt`, `LICENSE.txt`, `THIRD-PARTY-LICENSES.txt`, and Strauss-prefixed Composer runtime dependencies.

## Debugging

Enable Debug Mode in the Display & Injection tab. Errors are always logged to PHP `error_log` regardless of `WP_DEBUG`. Recent errors display as admin notices on the dashboard, settings page, and post edit screens.

To enable WordPress debug logging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Changelog

### 3.0.8
- **Submission readiness**: Hardened production packaging, metadata, third-party license notices, JSON-LD encoding, sync authorization, SSL verification, analytics rendering, lifecycle remote-call behavior, and uninstall cleanup for WordPress.org review.

See `readme.txt` for WordPress.org release notes.

## License

GPLv2 or later. See https://www.gnu.org/licenses/gpl-2.0.html.
