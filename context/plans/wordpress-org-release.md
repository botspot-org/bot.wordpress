# WordPress.org Release Preparation Plan

## Context

The `botdot-wp` plugin needs to be prepared for WordPress.org directory submission. Currently it's distributed privately with a proprietary license. The rebrand from BotDot to BotSpot is partially complete (plugin header says "BotSpot WordPress") but ~950 internal references still use BotDot naming.

**Scope:**
1. WordPress.org compliance (license, readme.txt, assets, security, privacy disclosure)
2. Full BotDot → BotSpot rename (plugin slug: `botspot-wp`)
3. Cross-repo BotDot cleanup (~330 references in 6 other repos)

---

## Phase 1: WordPress.org Compliance (botdot-wp repo)

### 1.1 License Change
- Change header `License: Proprietary` → `License: GPL v2 or later`
- Change `License URI` to `https://www.gnu.org/licenses/gpl-2.0.html`
- Add `LICENSE` file (GPL v2 text) to repo root

### 1.2 Create readme.txt
Standard WP.org format with:
- Required headers (Contributors, Tags, Requires at least, Tested up to, Stable tag, Requires PHP, License)
- Sections: Description, Installation, FAQ, Changelog, Screenshots
- **External service disclosure** (required): Document that plugin sends data to bot.spot APIs

Content can be adapted from existing README.md.

### 1.3 Create assets/ Directory
For WordPress.org SVN (not in plugin ZIP):
- `icon-128x128.png` and `icon-256x256.png`
- `banner-772x250.png` and `banner-1544x500.png`
- Screenshots referenced in readme.txt

### 1.4 Create languages/ Directory
- Generate `botspot-wp.pot` file using WP-CLI or similar
- Text domain will change from `botdot-wp` to `botspot-wp`

### 1.5 Privacy Policy Hook
Add `wp_add_privacy_policy_content()` call to disclose:
- Data sent to bot.spot APIs (content, URLs)
- Purpose (AI discoverability, structured data)
- Link to bot.spot privacy policy

### 1.6 Security Fixes (2 coding standards issues)

**Required for WP.org: `$wpdb->prepare()` in `admin/class-botdot-wp-admin.php`**

Both queries use hardcoded meta_key without `$wpdb->prepare()`. Not a true vulnerability (no user input), but WP.org reviewers flag this as bad practice:

1. **`handle_get_sync_health()`** (lines 1237-1243):
   ```php
   // BEFORE (vulnerable)
   $rows = $wpdb->get_results(
       "SELECT meta_value AS status, COUNT(*) AS n
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_botdot_sync_status'
        GROUP BY meta_value",
       ARRAY_A
   );
   
   // AFTER (fixed)
   $rows = $wpdb->get_results(
       $wpdb->prepare(
           "SELECT meta_value AS status, COUNT(*) AS n
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
            GROUP BY meta_value",
           '_botdot_sync_status'
       ),
       ARRAY_A
   );
   ```

2. **`handle_get_enrichment_lifecycle()`** (lines 1265-1271):
   Same pattern — wrap in `$wpdb->prepare()` with `%s` placeholder for `'_botdot_enrichment_tier'`.

**Audit Summary:**
- Nonces: Compliant (all AJAX handlers use `check_ajax_referer()`)
- Capability checks: Compliant (all admin handlers check `manage_options`)
- Input sanitization: Compliant
- Output escaping: Compliant
- SQL: **2 fixes required** (above)

### 1.7 Version Update
Update both `Version:` header and `@version` to `3.0.0` (currently mismatched at 2.9.2 / 2.8.2).

---

## Phase 2: BotDot → BotSpot Rename (botdot-wp repo)

### 2.1 Directory and File Rename
- Rename folder `botdot-wp/` → `botspot-wp/`
- Rename main file `botdot-wp.php` → `botspot-wp.php`
- Rename all `class-botdot-wp-*.php` files → `class-botspot-wp-*.php`

### 2.2 Code Rename (952 references)
**Classes:** `BotDot_WP_*` → `BotSpot_WP_*`
**Functions:** `botdot_wp_*` → `botspot_wp_*`  
**Constants:** `BOTDOT_WP_*` → `BOTSPOT_WP_*`
**Text domain:** `botdot-wp` → `botspot-wp`
**Options prefix:** `botdot_wp_` → `botspot_wp_`

### 2.3 Data Migration Path

Existing installs have `botdot_wp_*` data in the database. On `plugins_loaded` (not just activation, to handle folder renames):

**Options (`wp_options`):**
1. Check for any `botdot_wp_*` options
2. Copy values to corresponding `botspot_wp_*` options
3. Preserve old options (don't delete — user may need rollback)

**Post meta (`wp_postmeta`):**
1. Migrate `_botdot_*` meta keys to `_botspot_*`:
   - `_botdot_sync_status` → `_botspot_sync_status`
   - `_botdot_artifact_id` → `_botspot_artifact_id`
   - `_botdot_enrichment_tier` → `_botspot_enrichment_tier`
   - `_botdot_appendix_enabled` → `_botspot_appendix_enabled`
   - etc.
2. Run migration in batches to avoid timeout on large sites

**Transients:**
1. `botdot_content_*` and `botdot_wp_appendix_*` transients will naturally expire
2. No migration needed — they'll be recreated with new prefix

**Version tracking:**
- Add `botspot_wp_db_version` option to track which migrations have run
- Prevents re-migration on every activation

Add migration function in `class-botspot-wp-activator.php`, called from `plugins_loaded`.

### 2.4 Update Hook/Filter Names
All hooks use `botdot_wp_*` prefix — update to `botspot_wp_*`:
- `botdot_wp_url_path` → `botspot_wp_url_path`
- `botdot_wp_should_inject` → `botspot_wp_should_inject`
- etc.

Clean break — no deprecation layer per user decision.

### 2.5 Update uninstall.php
Must clean up BOTH old and new prefixes for users who upgrade and later uninstall:
- `botdot_wp_*` AND `botspot_wp_*` options
- `_botdot_*` AND `_botspot_*` post meta

### 2.6 Update build.sh
- Update paths/references to old naming
- Ensure `/tests`, `/testing`, `/vendor` dev files excluded from ZIP

---

## Phase 3: Cross-Repo BotDot Cleanup

| Repo | References | Action |
|------|------------|--------|
| locus-core | 281 | Update references to WP plugin, docs |
| developer-docs | 34 | Update documentation |
| bot.dashboard | 6 | Read-only reference per user feedback |
| locus-connectors | 5 | Update WP connector path references |
| locus-mcp | 2 | Minor cleanup |
| botspot-drupal | 1 | Minor cleanup |

**Note:** Per user preference, bot.dashboard is another team's domain — log mismatches but don't modify.

---

## Phase 4: Verification

### Pre-Submission Checks
1. Run [Plugin Check (PCP)](https://wordpress.org/plugins/plugin-check/) WordPress plugin
2. Validate readme.txt with [WP readme validator](https://wordpress.org/plugins/developers/readme-validator/)
3. Test fresh install + upgrade from current version (options + post meta migration)
4. Test on WP 6.4+ with PHP 8.x
5. Verify all external requests are disclosed
6. Verify vendor libraries are prefixed (`botspot-prefixed/`) — unprefixed libs cause rejection
7. Verify external requests use `wp_remote_*` (not direct cURL) with SSL verification enabled

### Upgrade UX Note
Renaming `botdot-wp/` → `botspot-wp/` will deactivate the plugin (WP tracks by path). Users must manually reactivate. Migration logic should run on `plugins_loaded`, not just activation hook, to handle folder renames without reactivation.

### Functional Tests
- Activation/deactivation cycle
- Settings save/load
- Content sync webhook fires
- Appendix injection renders
- JSON-LD injection works

---

## Decisions

- **Version:** 3.0.0 (major bump for rebrand, continues from 2.9.x)
- **Hook compatibility:** Clean break — only `botspot_wp_*` hooks, no deprecation layer
- **Assets:** Placeholder files; user provides final images later
- **WordPress.org username:** TBD (needed for Contributors field in readme.txt)

---

## Files to Modify (botdot-wp)

**New files:**
- `LICENSE` (GPL v2 text)
- `readme.txt`
- `assets/` (banners, icons)
- `languages/botspot-wp.pot`

**Renamed files:**
- `botdot-wp.php` → `botspot-wp.php`
- All `class-botdot-wp-*.php` → `class-botspot-wp-*.php`

**Modified files (all PHP):**
- Main plugin file (license header, constants, functions)
- `includes/class-botdot-wp-options.php` (options prefix, migration)
- `includes/class-botdot-wp-activator.php` (migration function)
- All other class files (class names, references)
- Admin partials (text domain, any BotDot strings)

---

## Execution Order

1. **License change** — GPL v2 header + LICENSE file (no code references to fix later)
2. **Privacy hook** — `wp_add_privacy_policy_content()` for external API disclosure
3. **Full rename** — files, code, data migration logic (botdot → botspot)
4. **SQL fixes** — `$wpdb->prepare()` in renamed files (avoids double-edit)
5. **Create readme.txt** — WP.org standard format with external service disclosure + upgrade notice for 3.0.0
6. **Create languages/botspot-wp.pot** — i18n template
7. **Asset placeholders** — banner/icon with "TODO" text
8. **First Plugin Check** — catch issues early
9. **Test upgrade path** — existing `botdot_wp_*` options + `_botdot_*` meta migrate correctly
10. **Cross-repo cleanup** — locus-core, developer-docs, locus-connectors, etc.
11. **Final Plugin Check** — validation after all changes
12. **Submit to WordPress.org** — after assets finalized
