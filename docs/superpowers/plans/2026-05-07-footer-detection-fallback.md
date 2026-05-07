# Footer Detection Fallback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make appendix placement gracefully fall back to `bottom_of_content` when footer detection fails, with debug output and admin guidance.

**Architecture:** Expand JS selector list, add HTML debug comment on detection failure, add PHP warning on settings page when footer-dependent placement is selected.

**Tech Stack:** PHP, inline JS, WordPress admin partials

**Spec:** `docs/superpowers/specs/2026-05-07-footer-detection-fallback-design.md`

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `includes/class-botdot-wp-content-injector.php` | Modify | Update JS selector list + add debug output |
| `admin/partials/tab-settings.php` | Modify | Add warning notice for footer-dependent placements |

---

### Task 1: Update JS Selector List

**Files:**
- Modify: `includes/class-botdot-wp-content-injector.php:613-624`

- [ ] **Step 1: Update SELECTORS array**

Replace lines 613-624:

```js
    var SELECTORS = [
        "[data-botspot-footer]",
        "footer",
        "[role=contentinfo]",
        ".site-footer",
        "#colophon",
        ".footer",
        ".page-footer",
        "#footer",
        "#site-footer",
        ".elementor-location-footer",
        ".fl-builder-footer",
        "#main-footer",
        ".wp-block-template-part[data-area=footer]"
    ];
```

- [ ] **Step 2: Verify syntax**

Run: `php -l includes/class-botdot-wp-content-injector.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add includes/class-botdot-wp-content-injector.php
git commit -m "feat(placement): expand footer selector list (BOT-264)"
```

---

### Task 2: Add Debug Output on Detection Failure

**Files:**
- Modify: `includes/class-botdot-wp-content-injector.php:638-643`

- [ ] **Step 1: Update findFooter failure handler**

Replace lines 638-643 (the `if (!footer)` block inside `place()`):

```js
        if (!footer) {
            if (window.console && console.warn) {
                console.warn("[BotSpot] footer not detected, appendix left in-content");
            }
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get("bsa-debug") === "1") {
                var comment = document.createComment(" bsa-footer-detection: failed, fallback to bottom_of_content ");
                node.parentNode.insertBefore(comment, node);
            }
            return;
        }
```

- [ ] **Step 2: Verify syntax**

Run: `php -l includes/class-botdot-wp-content-injector.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add includes/class-botdot-wp-content-injector.php
git commit -m "feat(placement): add debug comment on footer detection failure (BOT-264)"
```

---

### Task 3: Add Settings Page Warning

**Files:**
- Modify: `admin/partials/tab-settings.php:119-121`

- [ ] **Step 1: Add warning notice after placement radio group**

Insert after line 119 (after the closing `</div>` of `bsa-check-list`, before the closing `</div>` of `bsa-settings-row__body`):

```php
            <p class="bsa-settings-row__note" id="bsa-footer-detection-note" style="display: none; margin-top: 12px; padding: 10px; background: #fff8e5; border-left: 3px solid #ffb900; font-size: 13px;">
                <strong><?php _e("Note:", "botdot-wp"); ?></strong>
                <?php _e("Footer detection relies on common HTML patterns (<code>&lt;footer&gt;</code>, <code>role=\"contentinfo\"</code>, etc.). If your theme uses a non-standard footer, add the attribute <code>data-botspot-footer</code> to your footer element for reliable placement.", "botdot-wp"); ?>
            </p>
            <script>
            (function() {
                var radios = document.querySelectorAll('input[name="botdot_wp_injection_position"]');
                var note = document.getElementById('bsa-footer-detection-note');
                function toggle() {
                    var val = document.querySelector('input[name="botdot_wp_injection_position"]:checked');
                    note.style.display = (val && (val.value === 'above_footer' || val.value === 'bottom_of_page')) ? 'block' : 'none';
                }
                radios.forEach(function(r) { r.addEventListener('change', toggle); });
                toggle();
            })();
            </script>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l admin/partials/tab-settings.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add admin/partials/tab-settings.php
git commit -m "feat(admin): show footer detection warning for above_footer/bottom_of_page (BOT-264)"
```

---

### Task 4: Manual QA

- [ ] **Step 1: Test on forus.fi (no semantic footer)**

1. Set injection position to `above_footer` in plugin settings
2. Verify warning notice appears on settings page
3. Visit a page on forus.fi
4. Verify appendix appears at bottom of content (fallback behavior)
5. Add `?bsa-debug=1` to URL
6. View page source, search for `bsa-footer-detection`
7. Verify HTML comment is present: `<!-- bsa-footer-detection: failed, fallback to bottom_of_content -->`

- [ ] **Step 2: Test with data-botspot-footer attribute**

1. In Elementor, add `data-botspot-footer` attribute to footer section
2. Reload page
3. Verify appendix now appears above footer

- [ ] **Step 3: Regression test (site with semantic footer)**

1. Test on a site with standard `<footer>` element
2. Set position to `above_footer`
3. Verify appendix appears above footer (existing behavior works)

- [ ] **Step 4: Final commit (if any fixups needed)**

```bash
git add -A
git commit -m "fix(placement): address QA findings (BOT-264)"
```

---

## Completion

After all tasks pass:
1. Push branch
2. Update Linear ticket BOT-264 status to "Done"
