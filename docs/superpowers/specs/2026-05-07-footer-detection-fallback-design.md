# Footer Detection Fallback Design

**Ticket:** BOT-264  
**Date:** 2026-05-07  
**Status:** Approved

## Problem

The BotSpot WordPress plugin's `above_footer` and `bottom_of_page` placement options rely on JS footer detection with 10 CSS selectors. Sites without semantic footer elements (e.g., forus.fi using Elementor without Theme Builder footer) fail detection silently, leaving the appendix wherever `the_content` placed it. No feedback is given to site admins.

## Solution

Graceful fallback with admin guidance:
1. Expand selector list with high-value patterns
2. Explicit fallback to `bottom_of_content` when detection fails
3. Debug output via `?bsa-debug=1` query param
4. Settings page warning when `above_footer` or `bottom_of_page` selected

## Design

### JS Changes

File: `includes/class-botdot-wp-content-injector.php` → `inject_placement_script()`

**Updated selector list:**
```js
var SELECTORS = [
    "[data-botspot-footer]",        // explicit (highest priority)
    "footer",
    "[role=contentinfo]",
    ".site-footer",
    "#colophon",
    ".footer",
    ".page-footer",
    "#footer",
    "#site-footer",                 // new
    ".elementor-location-footer",
    ".fl-builder-footer",
    "#main-footer",
    ".wp-block-template-part[data-area=footer]"  // new: FSE themes
];
```

**Fallback behavior:**
- Both `above_footer` and `bottom_of_page` require footer detection (`above_footer` inserts before footer, `bottom_of_page` inserts after footer)
- When `findFooter()` returns null, both fall back identically: leave appendix in its `the_content` position
- If `bsa-debug=1` query param present, inject HTML comment: `<!-- bsa-footer-detection: failed, fallback to bottom_of_content -->`

### PHP Changes

File: `admin/partials/tab-settings.php` (lines ~45-65, injection_position radio group)

**Settings page warning:**
When `injection_position` is `above_footer` or `bottom_of_page`, show inline notice below the dropdown:

> **Note:** Footer detection relies on common HTML patterns (`<footer>`, `role="contentinfo"`, etc.). If your theme uses a non-standard footer, add the attribute `data-botspot-footer` to your footer element for reliable placement.

Styling: WordPress `.description` class or light info box. Static text, no dismiss.

## Testing

1. **forus.fi (no semantic footer):**
   - Set position to `above_footer`
   - Verify appendix stays at bottom of content
   - Add `?bsa-debug=1` — verify HTML comment shows detection failure

2. **With `data-botspot-footer` attribute:**
   - Add attribute to forus footer in Elementor
   - Verify appendix moves above footer

3. **Regression (site with semantic `<footer>`):**
   - Verify existing behavior unchanged

## Out of Scope

- Server-side footer detection
- Automated tests (JS embedded in PHP)
- Dashboard-wide admin notices
