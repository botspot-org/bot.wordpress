# Theme Integration Guide

## How Appendix Content Works

BotSpot WP fetches pre-rendered HTML from locus-core and injects it into your pages. The HTML uses semantic elements (`<details>`, `<summary>`, `<dl>`, `<dt>`, `<dd>`) styled via the bundled `botdot-wp-appendix.css`.

## Injection Positions

1. **Bottom of Content** (default) - appended to `the_content` filter output
2. **Above Footer** - output via `wp_footer` hook
3. **Manual Placement** - use the `[botdot_appendix]` shortcode or the Gutenberg block

Configure in BotSpot > Display & Injection > Injection Position.

## CSS Custom Properties

The appendix stylesheet uses CSS custom properties that inherit from WordPress `theme.json` values:

```css
/* Override in your theme's custom CSS */
.botdot-appendix {
    --wp--preset--color--primary: #your-color;
    --wp--preset--color--contrast: #your-text-color;
}
```

## Customizing Appendix HTML

Use the `botdot_wp_appendix_html` filter (1 argument: the HTML string) to modify or replace the appendix output:

```php
// Wrap appendix in a custom container
add_filter('botdot_wp_appendix_html', function($html) {
    if (empty($html)) {
        return $html;
    }
    return '<section class="my-appendix-wrapper">' . $html . '</section>';
});
```

## Shortcode Placement

Insert the shortcode anywhere in your content:

```
[botdot_appendix]
```

When manual placement is detected (shortcode or Gutenberg block), automatic injection is skipped to prevent duplicates.

## Gutenberg Block

Search for "BotDot Appendix" in the block inserter. The block renders server-side, so you'll see a placeholder in the editor and the actual content on the front end.

## Per-Page Control

Disable injection for specific pages via:
- The toggle switches in BotSpot > Display & Injection > Per-Page Injection
- Or programmatically: `update_post_meta($post_id, '_botdot_inject_enabled', '0')`

## Controlling Injection Programmatically

```php
// Disable injection on specific templates
add_filter('botdot_wp_should_inject', function($should_inject) {
    if (is_page_template('landing-page.php')) {
        return false;
    }
    return $should_inject;
});
```

## Troubleshooting

**Appendix not appearing?**
1. Check that injection is enabled in BotSpot > Display & Injection
2. Verify the current post type is in the "Post Types for Injection" list
3. Check per-page override isn't set to disabled
4. Enable Debug Mode and check `wp-content/debug.log` for fetch errors

**Appendix appearing twice?**
- Ensure you're not using both automatic injection and manual placement on the same page
- The plugin prevents double injection, but some themes may call `the_content` multiple times

**Styling conflicts?**
- The appendix CSS has low specificity by design
- Override with your theme's stylesheet using more specific selectors

## Smart Footer Placement (v2.7.0+)

When `injection_position` is `above_footer` or `below_footer`, the plugin uses a small client-side script to find the real footer element and position the appendix relative to it. Selectors checked in order:

1. `<footer>` (semantic)
2. `[role=contentinfo]`
3. `.site-footer`, `#colophon`, `.footer`, `.page-footer`, `#footer`
4. `.elementor-location-footer`, `.fl-builder-footer`, `#main-footer`

First match wins. If none match, the appendix stays where the `the_content` filter rendered it (in article body) and the script logs a console warning (`[BotSpot] footer not detected`).

Multi-footer pages use the first match in document order. If your theme has an unusual structure, choose `bottom` or use the `[bsa_appendix]` shortcode for manual placement.

**Known limitation:** the appendix briefly appears in the article body before the script repositions it (visible reflow on page load). This is intentional for now — the alternative (`display:none` until positioned) silently hides the appendix if the script errors. Revisit if reported as a real problem.
