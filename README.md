# BotDot WP

Server-side JSON-LD injection for WordPress from a configurable mirror domain.

## Description

BotDot WP is a WordPress plugin that fetches JSON-LD structured data from a mirror domain and injects it into your WordPress pages before they are rendered. This enables AI discoverability by providing structured data that can be queried by AI agents.

## Features

- Fetches JSON-LD from a configurable mirror domain
- Server-side injection into page headers
- Configurable post type filtering
- Individual page exclusion by ID
- Error logging and admin notices
- Test connection functionality
- Debug mode for troubleshooting
- No caching (always fetches fresh data)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- PHP cURL extension
- PHP JSON extension

## Installation

1. Upload the `botdot-wp` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > BotDot WP to configure the plugin

## Configuration

### General Settings

- **Mirror Domain**: The domain to fetch JSON-LD from (e.g., `ai.example.com`)
- **Enable Plugin**: Toggle to enable/disable JSON-LD injection

### Injection Settings

- **Inject on Post Types**: Select which post types should receive JSON-LD injection
- **Exclude Page IDs**: Comma-separated list of page/post IDs to exclude

### Advanced Settings

- **Fetch Timeout**: Maximum time to wait for JSON-LD fetch (1-60 seconds)
- **Debug Mode**: Enable debug logging to WordPress debug log

## How It Works

1. When a page is rendered, the plugin checks if injection is enabled
2. It verifies the current page type is in the allowed list
3. It constructs the mirror domain URL by appending `.json` to the path
4. Fetches JSON-LD from the mirror domain using WordPress HTTP API
5. Validates the response is valid JSON-LD
6. Injects the JSON-LD into the page header as a script tag

### Example

For a page at `https://example.com/blog/my-post`, the plugin will:
- Fetch JSON-LD from `https://ai.example.com/blog/my-post.json`
- Inject it into the page head as:
```html
<script type="application/ld+json">
{JSON-LD content here}
</script>
```

## Error Handling

- Fetch failures are logged and displayed in admin notices
- Pages render normally even if JSON-LD fetch fails
- Recent errors are displayed on the settings page
- Errors can be cleared from the settings page

## Developer Hooks

### Filters

- `botdot_wp_url_path` - Modify the URL path before fetching
- `botdot_wp_should_inject` - Control injection logic
- `botdot_wp_inject_on_archives` - Enable injection on archive pages

### Example Usage

```php
// Modify URL path before fetching
add_filter('botdot_wp_url_path', function($path) {
    return '/custom' . $path;
});

// Custom injection logic
add_filter('botdot_wp_should_inject', function($should_inject) {
    if (is_category()) {
        return false;
    }
    return $should_inject;
});
```

## Changelog

### 0.1.0
- Initial release
- Basic JSON-LD fetching and injection
- Admin settings page
- Error logging and notices
- Post type filtering
- Page exclusion

## License

GPL v2 or later

## Support

For issues and questions, please use the GitHub issue tracker.

## Credits

Developed by the BotDot Team
