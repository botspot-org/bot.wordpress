BotDot to BotSpot Safe Migration Procedure
==========================================

Purpose
-------

This document describes a safe procedure for renaming inconsistent `botdot` identifiers to `botspot` in the WordPress plugin.

Do not do a blind project-wide replacement such as:

```bash
botdot_wp -> botspot_wp
BotDot_WP -> BotSpot_WP
BOTDOT_WP -> BOTSPOT_WP
botdot-wp -> botspot-wp
```

That is unsafe because the current names are used in persisted WordPress data, public hooks, AJAX actions, nonces, REST routes, shortcodes, block names, file names, class names, constants, and generated package paths.

The safe path depends on whether the plugin has already shipped to real users.


Decision Point
--------------

- [ ] Confirm whether any real customer/site has installed a version using `botdot`.

If the plugin has not shipped:

- A clean rename is acceptable.
- You can rename options, meta keys, hooks, routes, shortcodes, files, classes, constants, and slug in one coordinated change.
- You still need a full regression test because this is a broad rename.

If the plugin has shipped:

- Treat this as a backwards-compatible migration.
- Add `botspot` names while preserving `botdot` aliases.
- Migrate persisted data on upgrade.
- Keep old public identifiers for at least one major release, preferably indefinitely for content-facing identifiers such as shortcodes and blocks.

Assume "has shipped" unless explicitly proven otherwise.


Identifier Inventory
--------------------

Before editing code, create a complete inventory with exact counts and call sites.

- [ ] Search PHP/JS/docs/build files for:
  - `botdot_wp`
  - `BotDot_WP`
  - `BOTDOT_WP`
  - `botdot-wp`
  - `botdot_`
  - `_botdot`
  - `botdot`

- [ ] Search existing `botspot` usage to avoid collisions:
  - `botspot_wp`
  - `BotSpot_WP`
  - `BOTSPOT_WP`
  - `botspot-wp`
  - `botspot_`
  - `_botspot`

- [ ] Categorize every match into one of these buckets:
  - Persisted option names
  - Persisted post meta names
  - Transient names
  - Scheduled cron event names
  - AJAX action names
  - Nonce action names
  - REST namespace/route names
  - WordPress hooks and filters
  - Shortcodes
  - Block names
  - PHP classes
  - PHP constants
  - PHP functions
  - Text domain
  - Plugin slug / folder / main file
  - JS globals and DOM data attributes
  - CSS classes/selectors
  - Build/package names
  - Documentation-only references


Recommended Naming Policy
-------------------------

Use `botspot` for new public-facing names.

Recommended new names:

- PHP class prefix: `BotSpot_WP_*`
- PHP constant prefix: `BOTSPOT_WP_*`
- Function prefix: `botspot_wp_*`
- Option prefix: `botspot_wp_`
- Post meta prefix: `_botspot_`
- AJAX action prefix: `botspot_wp_`
- Nonce action prefix: `botspot_wp_`
- REST namespace: `botspot-wp/v1`
- Primary shortcode: `[botspot_appendix]`
- Block name: `botspot-wp/appendix`
- Text domain: decide carefully; see Text Domain section.
- Plugin slug: decide carefully; see Slug section.

Legacy names to preserve for shipped installs:

- `botdot_wp_*` options
- `_botdot_*` post meta
- `botdot_wp_*` AJAX actions/nonces as aliases
- `botdot-wp/v1` REST route as an alias
- `[botdot_appendix]` shortcode as an alias
- `botdot-wp/appendix` block name as a deprecated/legacy block if already used in content
- Existing hooks/filters with `botdot_wp_*` aliases


Slug and Text Domain Decision
-----------------------------

Do not change slug or text domain casually.

- [ ] Decide whether the WordPress.org slug should remain `botdot-wp` or become `botspot-wp`.

If the plugin has not shipped:

- Prefer aligning slug, folder, main file, text domain, block namespace, and readme with `botspot-wp`.

If the plugin has shipped:

- Keeping the existing slug/folder/main file may be safer.
- WordPress.org plugin slugs are stable identifiers; changing them can create update and discovery issues.
- Text domain convention usually follows the plugin slug. If the slug remains `botdot-wp`, changing the text domain to `botspot-wp` may hurt translation consistency.

Acceptance criteria:

- [ ] The final slug decision is documented.
- [ ] The final text-domain decision is documented.
- [ ] The readme, main plugin header, build script, and package folder agree with the final slug decision.


Phase 1: Add Compatibility Helpers
----------------------------------

Goal: allow the plugin to read old data and write new data safely.

- [ ] Add a migration/version option.
  - Suggested option: `botspot_wp_schema_version`
  - Legacy option can be read if needed: `botdot_wp_schema_version`
  - Store an integer migration version, not the plugin version string.

- [ ] Add a centralized option helper that understands aliases.
  - Preferred file: `includes/class-botdot-wp-options.php` or renamed equivalent.
  - New writes should go to `botspot_wp_*`.
  - Reads should check `botspot_wp_*` first, then fall back to `botdot_wp_*`.
  - Secret-preserving save logic must work for both old and new keys during migration.

Suggested behavior:

```php
get('api_key'):
1. read botspot_wp_api_key
2. if empty/missing, read botdot_wp_api_key
3. return cast/sanitized value

set('api_key'):
1. write botspot_wp_api_key
2. do not write botdot_wp_api_key unless alias compatibility requires it
```

- [ ] Add a centralized post-meta helper if meta usage is broad.
  - New writes should go to `_botspot_*`.
  - Reads should check `_botspot_*` first, then `_botdot_*`.
  - Avoid duplicating meta forever if a migration will move values.

- [ ] Add transient helper functions if transient keys are renamed.
  - Either keep transient names as-is until cache naturally expires, or clear old transients during migration.
  - Do not rely on old transients for critical data.

Acceptance criteria:

- [ ] Existing installs with only `botdot_wp_*` options still load settings.
- [ ] New installs create only `botspot_wp_*` options unless intentionally dual-writing.
- [ ] Existing post sync status still displays after upgrade.
- [ ] New sync metadata writes to `_botspot_*`.


Phase 2: Persisted Data Migration
---------------------------------

Goal: copy or move old stored data to new names without losing settings.

Run migrations on `admin_init` or plugin load after dependencies are available. Keep the migration idempotent.

- [ ] Create a migration method, for example:
  - `BotSpot_WP_Migrations::run()`
  - or `BotSpot_WP_Options::migrate_botdot_to_botspot()`

- [ ] Migrate options.

Suggested option mapping:

- `botdot_wp_api_key` -> `botspot_wp_api_key`
- `botdot_wp_webhook_secret` -> `botspot_wp_webhook_secret`
- `botdot_wp_webhook_id` -> `botspot_wp_webhook_id`
- `botdot_wp_connection_id` -> `botspot_wp_connection_id`
- `botdot_wp_tenant_id` -> `botspot_wp_tenant_id`
- `botdot_wp_auto_sync_enabled` -> `botspot_wp_auto_sync_enabled`
- `botdot_wp_sync_sensitivity` -> `botspot_wp_sync_sensitivity`
- `botdot_wp_sync_post_types` -> `botspot_wp_sync_post_types`
- `botdot_wp_appendix_enabled` -> `botspot_wp_appendix_enabled`
- `botdot_wp_jsonld_enabled` -> `botspot_wp_jsonld_enabled`
- `botdot_wp_jsonld_conflict_mode` -> `botspot_wp_jsonld_conflict_mode`
- `botdot_wp_injection_position` -> `botspot_wp_injection_position`
- `botdot_wp_inject_on_post_types` -> `botspot_wp_inject_on_post_types`
- `botdot_wp_cache_ttl` -> `botspot_wp_cache_ttl`
- `botdot_wp_debug_mode` -> `botspot_wp_debug_mode`
- `botdot_wp_last_flush_at` -> `botspot_wp_last_flush_at`
- `botdot_wp_last_flush_id` -> `botspot_wp_last_flush_id`
- `botdot_wp_fatal_errors` -> `botspot_wp_fatal_errors`

Legacy cleanup candidates:

- `botdot_wp_botspot_key`
- `botdot_wp_injection_enabled`
- `botdot_wp_page_injection_status`

Migration rules:

- [ ] If new option exists and is non-empty, do not overwrite it.
- [ ] If new option is missing/empty and old option exists, copy old value to new option.
- [ ] Keep old options for one release if you want rollback safety.
- [ ] Delete old options only after a deliberate cleanup release, or on uninstall.

- [ ] Migrate post meta.

Suggested post meta mapping:

- `_botdot_sync_hash` -> `_botspot_sync_hash`
- `_botdot_last_synced_at` -> `_botspot_last_synced_at`
- `_botdot_sync_status` -> `_botspot_sync_status`
- `_botdot_sync_word_count` -> `_botspot_sync_word_count`
- `_botdot_inject_enabled` -> `_botspot_inject_enabled`
- `_botdot_artifact_id` -> `_botspot_artifact_id`
- `_botdot_enrichment_tier` -> `_botspot_enrichment_tier`
- `_botdot_enrichment_status` -> `_botspot_enrichment_status`

Existing `_botspot_*` keys:

- `_botspot_pre_enrich_jsonld`
- `_botspot_impressions_pending`
- `_botspot_impressions_inflight`
- `_botspot_impressions_inflight_batch`
- `_botspot_impressions_inflight_at`

These are already `botspot` and should not be renamed.

Post meta migration rules:

- [ ] Prefer SQL `INSERT ... SELECT` or batched PHP migration for large sites.
- [ ] Do not overwrite existing new meta.
- [ ] Do not delete old meta immediately unless rollback is not needed.
- [ ] Add uninstall cleanup for both old and new meta names.

- [ ] Migrate transients.

Current transient/key families to review:

- `botdot_content_*`
- `botdot_jsonld_*`
- `botdot_wp_status_snapshot`
- `botdot_wp_recent_errors`
- `botdot_wp_activation_notice`
- `botspot_impressions_*`
- `botspot_flush_lock`

Recommended transient approach:

- [ ] Do not try to preserve old content/jsonld caches.
- [ ] Clear old `botdot_content_*` and `botdot_jsonld_*` transients during migration.
- [ ] Start writing new cache keys using `botspot_content_*` and `botspot_jsonld_*` if renaming.
- [ ] Keep existing `botspot_impressions_*` and `botspot_flush_lock` keys.

- [ ] Migrate scheduled cron events if names change.

Potential old events:

- `botspot_flush_analytics` already uses `botspot`; keep it.
- `botdot_wp_retry_sync`
- `botdot_wp_force_resync_run`

Recommended approach:

- [ ] Register both old and new cron handlers for one release.
- [ ] Unschedule old events after scheduling/using new names.
- [ ] Ensure queued old retry events still execute after upgrade.

Acceptance criteria:

- [ ] Upgrade from a database containing only old options/meta preserves settings and sync status.
- [ ] New values are written under new keys.
- [ ] Old values are not deleted until rollback strategy is approved.
- [ ] Migration can run multiple times without duplicating data or changing results.


Phase 3: Public API Aliases
---------------------------

Goal: add `botspot` public names while preserving old `botdot` names.

- [ ] Shortcodes.
  - Register `[botspot_appendix]` as primary.
  - Keep `[botdot_appendix]` as legacy alias.
  - Update docs/admin UI to show `[botspot_appendix]`.
  - Do not remove `[botdot_appendix]`; old post content can contain it forever.

- [ ] Blocks.
  - Register `botspot-wp/appendix` as the primary block.
  - If `botdot-wp/appendix` has ever been used in saved content, keep it registered as a deprecated/legacy block pointing to the same render callback.
  - Update editor JS to insert/use the new block name.
  - Verify existing posts with old block comments still render.

- [ ] REST routes.
  - Register new namespace: `botspot-wp/v1`.
  - Keep old namespace: `botdot-wp/v1`.
  - Make both call the same handler.
  - If webhooks are registered with BotSpot using old route URLs, either:
    - keep old route indefinitely, or
    - re-register webhooks during migration with the new URL.

- [ ] AJAX actions and nonces.
  - Add new actions such as `botspot_wp_save_settings`.
  - Keep old actions such as `botdot_wp_save_settings` as aliases for one or more releases.
  - Update JS to call new actions and use new nonce keys.
  - Consider accepting both old and new nonce action strings during transition.

- [ ] WordPress hooks and filters.
  - Add new filters/actions:
    - `botspot_wp_url_path`
    - `botspot_wp_should_inject`
    - `botspot_wp_appendix_html`
    - `botspot_wp_appendix_jsonld`
    - `botspot_wp_should_sync`
    - `botspot_wp_sync_payload`
    - cache purge hooks with `botspot_wp_*`
  - Keep old `botdot_wp_*` hooks for compatibility.
  - If both old and new filters can mutate data, define order clearly:
    - apply old filter first, then new filter; or
    - apply new filter first, then old filter.
  - Document both during transition.

Acceptance criteria:

- [ ] Existing shortcodes render.
- [ ] Existing blocks render.
- [ ] Existing webhook URLs still work.
- [ ] Existing integrations using old filters still work.
- [ ] New docs and UI show `botspot` names.


Phase 4: Internal Rename
------------------------

Goal: rename internal code identifiers once compatibility layers exist.

Recommended order:

1. Rename classes.
2. Rename constants.
3. Rename functions.
4. Rename hook/action strings with aliases.
5. Rename JS globals and nonce keys.
6. Rename docs/user-facing copy.
7. Rename files only if autoload/require paths are updated.

- [ ] Rename PHP classes from `BotDot_WP_*` to `BotSpot_WP_*`.
  - Update every `require_once`, static call, constructor call, type reference, and test reference.
  - If concerned about plugin extensions referencing classes directly, provide `class_alias()` for old class names.

Suggested compatibility aliases:

```php
if (!class_exists('BotDot_WP_Options') && class_exists('BotSpot_WP_Options')) {
    class_alias('BotSpot_WP_Options', 'BotDot_WP_Options');
}
```

Use aliases carefully. Prefer placing them after new class definitions are loaded.

- [ ] Rename constants from `BOTDOT_WP_*` to `BOTSPOT_WP_*`.
  - Keep old constants defined as aliases for one release if third-party code may use them.

Suggested compatibility constants:

```php
define('BOTSPOT_WP_VERSION', '2.9.2');
if (!defined('BOTDOT_WP_VERSION')) {
    define('BOTDOT_WP_VERSION', BOTSPOT_WP_VERSION);
}
```

- [ ] Rename functions from `botdot_wp_*` to `botspot_wp_*`.
  - Keep old function wrappers if activation/deactivation hooks or external calls may reference them.

- [ ] Rename JS globals.
  - Example: `botspotAdmin` can remain as-is if already correct.
  - If any `botdotWP` global exists, add `botspotWP` and keep `botdotWP` alias if editor scripts expect it.

- [ ] Rename CSS/DOM classes only if necessary.
  - CSS class names are not usually important for WordPress.org review.
  - Avoid churn unless user-facing or clearly inconsistent.
  - If changed, update all templates, JS selectors, and CSS together.

Acceptance criteria:

- [ ] Plugin activates after internal rename.
- [ ] No fatal class/function/constant errors.
- [ ] Tests and plugin smoke checks pass.
- [ ] Backward-compatible aliases cover shipped public interfaces.


Phase 5: Build, Slug, and File Names
------------------------------------

Only perform this phase if the slug decision allows it.

- [ ] If changing slug/folder/main file to `botspot-wp`:
  - Rename `botdot-wp.php` to the chosen main file name.
  - Update plugin basename constants.
  - Update build script `PLUGIN_SLUG`.
  - Update readme stable metadata if needed.
  - Update generated ZIP folder name.
  - Update documentation.
  - Confirm WordPress.org submission uses the same slug/folder.

- [ ] If keeping slug/folder/main file as `botdot-wp`:
  - Do not rename main file/folder.
  - Keep text domain aligned with slug unless the expert intentionally chooses otherwise.
  - User-facing brand copy can still say BotSpot.

Acceptance criteria:

- [ ] Build produces the intended folder name.
- [ ] Plugin activation/deactivation hooks still work.
- [ ] Upgrade from previous ZIP works in WordPress.
- [ ] WordPress.org metadata matches the chosen slug.


Phase 6: Documentation Updates
------------------------------

- [ ] Update `readme.txt`.
  - Show `BotSpot` branding.
  - Use `[botspot_appendix]` as primary shortcode.
  - Mention `[botdot_appendix]` as a legacy alias if shipped.
  - If block namespace changes, explain old blocks continue to render.
  - If REST webhook URL changes, no need to expose this unless useful for debugging.

- [ ] Update `README.md`.
  - Same shortcode and naming updates.
  - Add migration notes for site owners if needed.

- [ ] Update admin UI copy.
  - Replace "BotDot" with "BotSpot" where user-facing.
  - Keep old identifiers only in technical/developer compatibility notes.

- [ ] Update tests and fixtures.

- [ ] Update `pre-submission-tasks.md` if the slug/package decision changes.

Acceptance criteria:

- [ ] New docs consistently use BotSpot.
- [ ] Legacy names are only mentioned as compatibility aliases.
- [ ] No docs recommend new usage of `botdot` names.


Phase 7: Testing Plan
---------------------

Test both a clean install and an upgrade install.

Clean install tests:

- [ ] Install fresh plugin ZIP with no old data.
- [ ] Activate plugin.
- [ ] Confirm only new `botspot_wp_*` options are created, except intentional compatibility constants/routes/hooks.
- [ ] Connect with valid key.
- [ ] Save settings.
- [ ] Publish/update a post.
- [ ] Confirm new `_botspot_*` meta is written.
- [ ] Confirm public rendering works.
- [ ] Confirm `[botspot_appendix]` works.
- [ ] Confirm `botspot-wp/appendix` block works if implemented.

Upgrade tests:

- [ ] Seed database with old `botdot_wp_*` options and `_botdot_*` post meta.
- [ ] Install/activate upgraded plugin.
- [ ] Confirm settings are preserved.
- [ ] Confirm connection still works.
- [ ] Confirm sync status still displays.
- [ ] Confirm new keys are created by migration.
- [ ] Confirm old shortcodes still render.
- [ ] Confirm old blocks still render if applicable.
- [ ] Confirm old webhook route still accepts signed webhooks.
- [ ] Confirm old AJAX aliases work if called.
- [ ] Confirm new AJAX actions work from updated JS.
- [ ] Confirm old cron retry events still execute.

Regression tests:

- [ ] Run PHP unit tests if available.
- [ ] Run Plugin Check.
- [ ] Run WordPress Coding Standards if available.
- [ ] Run build.
- [ ] Inspect generated ZIP.
- [ ] Install generated ZIP via WordPress admin.

Search-based verification:

- [ ] Search for `BotDot` after migration.
  - Remaining matches should be compatibility aliases or documented legacy mentions.
- [ ] Search for `botdot_wp`.
  - Remaining matches should be migration maps, compatibility aliases, uninstall cleanup, or legacy hook/route registrations.
- [ ] Search for `_botdot`.
  - Remaining matches should be migration maps, legacy reads, or uninstall cleanup.
- [ ] Search for `botdot-wp`.
  - Remaining matches should be slug/text-domain if intentionally kept, legacy block/REST aliases, file names if intentionally kept, or docs explaining compatibility.


Rollback Strategy
-----------------

Before deleting old keys or old aliases, define rollback behavior.

- [ ] For the first migration release, keep old options and meta after copying to new names.
- [ ] Keep old shortcodes and REST routes.
- [ ] Keep old AJAX actions for at least one release.
- [ ] Keep old filters/actions for third-party integrations.
- [ ] Keep old cron handlers until all queued old events are expected to have run.

Rollback acceptance criteria:

- [ ] If the previous plugin version is reinstalled, old `botdot_*` data still exists.
- [ ] No migration step destructively deletes required settings in the first migration release.
- [ ] Uninstall still removes both old and new plugin-owned data.


Cleanup Release
---------------

Only after the migration release has been deployed and proven stable:

- [ ] Consider deleting old `botdot_wp_*` options during a later major release.
- [ ] Consider deleting old `_botdot_*` post meta during a later major release.
- [ ] Keep content-facing aliases forever if they may exist in post content:
  - `[botdot_appendix]`
  - `botdot-wp/appendix`
- [ ] Keep old REST webhook route unless all registered webhooks are known to have been updated.
- [ ] Keep old hooks/filters if third-party integrations may rely on them.


Implementation Checklist
------------------------

- [ ] Make slug/text-domain decision.
- [ ] Add migration/version option.
- [ ] Add alias-aware option reads.
- [ ] Add option copy migration.
- [ ] Add alias-aware post meta reads or migrate post meta.
- [ ] Add post meta copy migration.
- [ ] Clear or migrate old transients.
- [ ] Add new shortcode and keep old shortcode.
- [ ] Add new REST namespace and keep old REST namespace.
- [ ] Add new AJAX actions and keep old aliases.
- [ ] Add new hooks/filters and keep old aliases.
- [ ] Rename internal classes/constants/functions.
- [ ] Add `class_alias`/constant/function wrappers if needed.
- [ ] Update JS to new names.
- [ ] Update admin UI copy.
- [ ] Update readme/docs.
- [ ] Update uninstall cleanup for both old and new keys.
- [ ] Update tests.
- [ ] Run clean install tests.
- [ ] Run upgrade tests.
- [ ] Run Plugin Check.
- [ ] Run production build and inspect ZIP.


Definition of Done
------------------

The migration is complete when:

- [ ] New installs use `botspot` names for new persisted data.
- [ ] Existing installs using `botdot` data upgrade without losing settings or status.
- [ ] Existing shortcodes, blocks, routes, hooks, and AJAX calls remain functional where needed.
- [ ] User-facing copy consistently says BotSpot.
- [ ] Remaining `botdot` references are intentional compatibility aliases, migration maps, cleanup code, or slug/text-domain references that were explicitly approved.
- [ ] The production ZIP installs and activates cleanly.
- [ ] Public rendering, sync, webhook, cache, and analytics smoke tests pass.
