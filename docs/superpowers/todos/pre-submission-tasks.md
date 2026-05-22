WordPress.org Pre-Submission Checklist for BotSpot WordPress
============================================================

Purpose
-------

This checklist is for the WordPress plugin expert preparing `bot.wordpress` for WordPress.org Plugin Directory submission.

Primary goal: make the plugin acceptable under the WordPress.org Detailed Plugin Guidelines and reduce review back-and-forth.

Reference guideline:

https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

Current plugin facts to verify before starting:

- Main plugin file: `botdot-wp.php`
- WordPress.org readme: `readme.txt`
- Build script: `build.sh`
- Current version: `2.9.2`
- Text domain: `botdot-wp`
- Plugin slug/build folder: `botdot-wp`
- SaaS/API service: BotSpot / locus API


Must Fix Before Submission
--------------------------

- [ ] Include `readme.txt` in the generated plugin ZIP.
  - Current risk: `build.sh` copies `README.md` and `THEME-INTEGRATION.md`, but does not copy `readme.txt`.
  - Acceptance criteria:
    - Running the production build includes `botdot-wp/readme.txt` at the root of the plugin folder inside the ZIP.
    - The packaged readme is the WordPress.org readme, not only the GitHub-style `README.md`.
    - The readme passes the official WordPress.org readme validator.

- [ ] Resolve public BotSpot attribution/link behavior.
  - Current risk: plugin code adds BotSpot `sdPublisher` attribution and a BotSpot organization node to public JSON-LD by default in `includes/class-botdot-wp-content-injector.php`.
  - Guideline concern: public-facing credits or links included by plugin code must be optional and default off.
  - Decide with product/legal whether attribution is required by the external service.
  - Preferred acceptance criteria:
    - Plugin code does not add public BotSpot credits/links by default.
    - If attribution remains, add an explicit admin opt-in setting that defaults off and is clearly described.
    - The setting must not be required for plugin functionality.
    - `readme.txt` and settings copy disclose what enabling attribution does.
  - Alternative acceptance criteria if attribution is service-generated:
    - The plugin does not add the attribution itself.
    - The service controls branding in its returned output.
    - `readme.txt` documents that service-rendered output may contain BotSpot branding or attribution if configured in BotSpot.

- [ ] Remove external Google Fonts load from the admin UI or replace with local/system fonts.
  - Current risk: `admin/class-botdot-wp-admin.php` enqueues `https://fonts.googleapis.com/...`.
  - Guideline concern: external asset loading can be flagged, especially if unrelated to the service’s core function or privacy disclosure.
  - Preferred acceptance criteria:
    - Admin UI uses system fonts or bundled local font files.
    - No `fonts.googleapis.com` or `fonts.gstatic.com` request is made by the plugin.
    - If local font files are bundled, their license is GPL-compatible and included/documented.

- [ ] Update the build and packaging process for WordPress.org.
  - Acceptance criteria:
    - Production build uses production API endpoints.
    - ZIP root contains exactly one `botdot-wp/` directory.
    - ZIP does not contain `.git`, tests, fixtures, local dev files, temporary build files, shell-only testing helpers, or private docs that are not intended for users.
    - ZIP includes all runtime dependencies needed by the plugin.
    - ZIP includes all required source files or a clear public source/build-tools link in `readme.txt`.

- [ ] Confirm GPL-compatible licensing for all shipped code and assets.
  - Current status: project license declarations were changed to GPLv2 or later / GPL-2.0-or-later.
  - Acceptance criteria:
    - `botdot-wp.php`, `readme.txt`, `README.md`, and `composer.json` agree on GPL-compatible licensing.
    - All bundled PHP, JS, CSS, images, fonts, and vendored libraries are GPL-compatible.
    - Third-party licenses are preserved in the built package where required.
    - Confirm licenses for:
      - `jaybizzle/crawler-detect`
      - `monperrus/crawler-user-agents`
      - Any files generated or copied by Strauss
      - Any bundled icons, SVGs, or fonts


SaaS and Privacy Review
-----------------------

- [ ] Validate the external service disclosure in `readme.txt`.
  - Acceptance criteria:
    - It clearly states that the plugin connects to BotSpot.
    - It identifies the service provider.
    - It lists the production service URLs.
    - It explains what data is sent and when.
    - It links to Terms of Service and Privacy Policy.
    - It says that an administrator must connect with an access key before sync/rendering.

- [ ] Confirm `https://bot.spot/terms` is live and accurate.
  - Acceptance criteria:
    - Publicly accessible without authentication.
    - Describes BotSpot SaaS terms.
    - No redirect to an unrelated or missing page.

- [ ] Confirm `https://bot.spot/privacy` is live and accurate.
  - Acceptance criteria:
    - Publicly accessible without authentication.
    - Covers content sync, metadata, API requests, webhooks, and analytics/impression data.
    - Matches what the plugin actually collects and sends.

- [ ] Review data minimization.
  - Acceptance criteria:
    - The plugin only sends data needed for BotSpot functionality.
    - The plugin does not send raw visitor IP addresses.
    - The plugin does not send raw user-agent strings in analytics flush payloads.
    - If any personal data is included in synced content or author metadata, the readme/privacy policy acknowledges that site content may contain personal data.

- [ ] Review consent flow.
  - Acceptance criteria:
    - External data transfer does not begin until an administrator enters an access key and clicks Connect or saves settings.
    - Auto-sync behavior is visible and configurable.
    - Default synced post types are clearly shown in the settings UI.
    - The first connection flow should not surprise the administrator by silently syncing all content without clear UI copy.

- [ ] Confirm analytics behavior is acceptable.
  - Acceptance criteria:
    - Analytics are aggregate counts, not individual visitor tracking.
    - Analytics collection is documented in `readme.txt`.
    - If the expert believes guideline 7 requires a separate opt-in for analytics, add a setting that defaults off or is explicitly enabled during connect.
    - The admin UI clearly explains what the analytics flush does.


Readme and Metadata
-------------------

- [ ] Validate `readme.txt` with the WordPress.org readme validator.
  - Acceptance criteria:
    - No parser errors.
    - `Stable tag` matches the plugin version or the intended SVN tag.
    - Tags are five or fewer.
    - Short description is concise and non-spammy.

- [ ] Update `Tested up to`.
  - Current value: `6.4`.
  - Acceptance criteria:
    - Test against the current WordPress version targeted for submission.
    - Update both:
      - `botdot-wp.php`
      - `readme.txt`

- [ ] Confirm `Requires at least` and `Requires PHP`.
  - Current values:
    - WordPress: `5.0`
    - PHP: `7.4`
  - Acceptance criteria:
    - Values reflect actual tested support.
    - No code uses APIs unavailable in WordPress 5.0 unless guarded.
    - PHP 7.4 compatibility is verified if that remains the minimum.

- [ ] Confirm contributors field.
  - Current value: `botspot`.
  - Acceptance criteria:
    - The WordPress.org username exists.
    - The account has correct email/contact details.
    - The account is the intended plugin owner.

- [ ] Confirm plugin name, slug, and trademark risk.
  - Acceptance criteria:
    - Proposed slug does not begin with another project’s trademark.
    - Name and slug accurately represent BotSpot.
    - If the slug will be `botdot-wp`, ensure that is intentional and matches the submitted ZIP folder.

- [ ] Review changelog for public suitability.
  - Acceptance criteria:
    - No internal-only names or customer-specific incidents.
    - No private issue references.
    - No claims that imply guaranteed legal/SEO/compliance outcomes.
    - First submitted version can have a concise changelog if preferred.

- [ ] Add screenshots only if assets will be submitted.
  - Acceptance criteria:
    - If `readme.txt` has a Screenshots section, matching assets are prepared for WordPress.org SVN assets if desired.
    - Screenshot descriptions match actual admin screens.


Security Review
---------------

- [ ] Run WordPress Coding Standards and Plugin Check.
  - Suggested tools:
    - WordPress Plugin Check plugin
    - PHP_CodeSniffer with WordPress Coding Standards
    - PHPCompatibilityWP if available
  - Acceptance criteria:
    - No critical security findings.
    - Any warnings are triaged and either fixed or documented as false positives.

- [ ] Review all AJAX handlers.
  - Files to inspect:
    - `admin/class-botdot-wp-admin.php`
  - Acceptance criteria:
    - Every state-changing AJAX handler verifies a nonce.
    - Every admin AJAX handler checks capabilities.
    - Input is sanitized.
    - Output is escaped or returned as JSON.

- [ ] Review REST webhook endpoint.
  - Files to inspect:
    - `includes/class-botdot-wp-sync.php`
    - `includes/class-botdot-wp-webhook-handler.php`
  - Acceptance criteria:
    - Public route is justified.
    - HMAC signature verification is correct.
    - Missing/invalid secrets fail closed.
    - Webhook body parsing is safe.
    - Webhook updates only plugin-owned metadata/cache.

- [ ] Review output escaping in admin partials.
  - Files to inspect:
    - `admin/partials/*.php`
  - Acceptance criteria:
    - Dynamic text uses `esc_html`, `esc_attr`, `esc_url`, or appropriate escaping.
    - Translation output is escaped where needed.
    - URLs opened in new windows use `rel="noopener"`.

- [ ] Review public HTML injection.
  - Files to inspect:
    - `includes/class-botdot-wp-content-injector.php`
  - Acceptance criteria:
    - Remote HTML is passed through `wp_kses` before output.
    - Allowed tags/attributes are narrow enough.
    - Script tags from remote HTML are not allowed.
    - Inline `style` allowances are intentional and reviewed.

- [ ] Review JSON-LD injection.
  - Files to inspect:
    - `includes/class-botdot-wp-content-injector.php`
  - Acceptance criteria:
    - JSON-LD is decoded and re-encoded with `wp_json_encode`.
    - `</script>` breakout is neutralized.
    - Filters that can alter JSON-LD are documented and safe for trusted site code.

- [ ] Review remote requests.
  - Files to inspect:
    - `includes/class-botdot-wp-content-fetcher.php`
    - `includes/class-botdot-wp-sync.php`
    - `includes/class-botdot-wp-analytics-flusher.php`
    - `admin/class-botdot-wp-admin.php`
  - Acceptance criteria:
    - Requests use WordPress HTTP APIs.
    - Requests use HTTPS production endpoints.
    - Timeouts are set.
    - API keys are sent only to BotSpot endpoints.
    - Error logs do not expose secrets or full sensitive response bodies.

- [ ] Review direct database queries.
  - Acceptance criteria:
    - Dynamic SQL uses `$wpdb->prepare`.
    - LIKE queries use `$wpdb->esc_like`.
    - Table names are WordPress-provided properties.

- [ ] Review secret storage and rendering.
  - Acceptance criteria:
    - API keys/webhook secrets are not rendered back into HTML.
    - Secret inputs preserve existing values when left empty.
    - Diagnostics and copied debug output do not include full secrets.

- [ ] Review uninstall behavior.
  - File to inspect:
    - `uninstall.php`
  - Acceptance criteria:
    - Plugin-owned options are removed.
    - Plugin-owned post meta is removed.
    - Plugin-owned transients are removed.
    - Analytics metadata and all current transient prefixes are included.
    - No unrelated site data is removed.


Code and Runtime Quality
------------------------

- [ ] Confirm activation/deactivation behavior.
  - Acceptance criteria:
    - Activation does not make unexpected external calls.
    - Activation does not fatal on supported PHP/WordPress versions.
    - Deactivation unschedules plugin cron events.
    - Uninstall cleanup is separate from deactivation.

- [ ] Confirm cron behavior.
  - Acceptance criteria:
    - Scheduled events are registered and unscheduled correctly.
    - No high-frequency cron events.
    - Failed sync retry is bounded and does not create duplicate events.
    - Analytics flush lock prevents overlapping flushes.

- [ ] Confirm admin notices do not hijack the dashboard.
  - Acceptance criteria:
    - Notices are scoped to relevant screens or are dismissible.
    - Error notices include useful remediation.
    - No persistent marketing nags.

- [ ] Confirm no trialware behavior.
  - Acceptance criteria:
    - The plugin does not contain locally bundled functionality locked behind payment.
    - Any paid limitations are tied to the BotSpot service and are disclosed.
    - The plugin remains a complete interface to the SaaS.

- [ ] Confirm no external executable code loading.
  - Acceptance criteria:
    - No remote JavaScript or CSS except any approved/reviewed font decision.
    - No remote plugin/theme update system.
    - No iframe-based admin app.
    - All plugin JS/CSS needed for operation ships locally.

- [ ] Confirm human-readable code.
  - Acceptance criteria:
    - No minified-only source without source links.
    - Generated/prefixed dependencies have source/build instructions.
    - No obfuscation, eval, packed JS, or unclear generated blobs.

- [ ] Confirm translation readiness.
  - Acceptance criteria:
    - Text domain is consistent: `botdot-wp`.
    - User-facing strings are translation-ready.
    - Escaped translation helpers are used where appropriate.
    - The plugin does not need to ship compiled translation files for initial submission.

- [ ] Confirm WordPress default library usage.
  - Acceptance criteria:
    - If jQuery is used, it relies on WordPress-bundled jQuery.
    - No bundled copies of WordPress core libraries.


Functional Test Plan
--------------------

- [ ] Test clean install on a fresh WordPress site.
  - Acceptance criteria:
    - Plugin activates without fatal errors.
    - BotSpot admin menu appears.
    - No external content sync occurs before connect.
    - No PHP warnings in debug log.

- [ ] Test connection flow.
  - Acceptance criteria:
    - Empty key shows a clear error.
    - Invalid key shows a clear error.
    - Valid key registers successfully.
    - Key is not shown in page source after save/connect.
    - Webhook ID and tenant ID display only to authorized admins.

- [ ] Test settings save flow.
  - Acceptance criteria:
    - Post type selections persist.
    - Placement selection persists.
    - Output toggles persist.
    - Cache TTL is bounded.
    - Reset behavior is expected.

- [ ] Test content sync.
  - Acceptance criteria:
    - Publishing a selected post type syncs content.
    - Updating a selected post type syncs according to sensitivity rules.
    - Unselected post types do not sync.
    - Manual sync works.
    - Bulk/force resync works on small and larger sites.

- [ ] Test public rendering.
  - Acceptance criteria:
    - JSON-LD renders when enabled.
    - Appendix HTML renders when enabled.
    - Output does not render on admin, search, 404, or unsupported post types.
    - Manual shortcode placement works.
    - Block editor placement works.
    - Footer-aware placement works with common footer selectors.
    - Page-builder fallback does not duplicate output.

- [ ] Test caching.
  - Acceptance criteria:
    - Render responses are cached.
    - Freshness checks behave as expected.
    - Clear cache button removes current transient prefixes.
    - Enrichment webhook purges relevant post cache.

- [ ] Test analytics.
  - Acceptance criteria:
    - Impression counters increment only when appendix is rendered.
    - Bot classification works for known crawlers and humans.
    - Scheduled flush sends aggregate counters.
    - Failed flush merges inflight counters back to pending.
    - Admin analytics cards handle empty/error states.

- [ ] Test multisite expectations.
  - Current plugin header says `Network: false`.
  - Acceptance criteria:
    - Plugin behaves correctly when activated per-site on multisite, or multisite is explicitly unsupported.
    - No network activation assumption leaks into code.

- [ ] Test PHP/WordPress version matrix.
  - Minimum matrix if keeping current requirements:
    - PHP 7.4 with minimum supported WordPress version if feasible.
    - PHP 8.1 or 8.2 with current WordPress.
    - Current WordPress with default theme.
  - Acceptance criteria:
    - No fatal errors.
    - No deprecation warnings that would concern review.


Build and Submission Dry Run
----------------------------

- [ ] Run dependency install and build from a clean checkout.
  - Acceptance criteria:
    - `composer install` succeeds.
    - Strauss prefixing succeeds.
    - Production build succeeds.
    - Build does not rely on uncommitted local files except intended generated vendor output.

- [ ] Inspect the generated ZIP manually.
  - Acceptance criteria:
    - Contains `botdot-wp/botdot-wp.php`.
    - Contains `botdot-wp/readme.txt`.
    - Contains required `admin/`, `includes/`, `public/`, `vendor/botspot-prefixed/`, and `languages/` paths.
    - Does not contain `.git`, `tests/`, `testing/`, `docs/superpowers/`, `context/review/`, `dist/`, `build/`, or local fixtures unless intentionally included.

- [ ] Run Plugin Check against the exact generated ZIP contents.
  - Acceptance criteria:
    - No blocker-level issues.
    - Warnings are triaged.
    - External service warnings are backed by readme disclosure.

- [ ] Install the generated ZIP through WordPress admin.
  - Acceptance criteria:
    - Upload install succeeds.
    - Activation succeeds.
    - Admin pages load.
    - Basic connect/settings/render smoke tests pass.

- [ ] Verify no staging endpoints in production ZIP.
  - Acceptance criteria:
    - No `locus-staging-api.bot.spot`.
    - No `staging-locus-connectors.bot.spot`.
    - Production constants point to production endpoints.


Nice to Have Before Submission
------------------------------

- [ ] Add a `Third-Party Licenses` section or file.
  - Include runtime libraries and any local assets.

- [ ] Add a short `Source and Build` section to `readme.txt`.
  - Explain where source lives and how generated vendor files are produced.
  - Useful because the package includes Strauss-prefixed dependencies.

- [ ] Add a privacy/settings explainer in the Connect tab.
  - One short note near the Connect button can reduce consent ambiguity:
    - connecting sends selected published content to BotSpot;
    - post types can be changed in Settings;
    - links to privacy/terms. (bot.spot/privacy and bot.spot/terms)

- [ ] Add explicit analytics toggle if review expert recommends it.
  - Default can be off or enabled during connect with clear explanation.

- [ ] Add a local development submission script.
  - Example target:
    - clean checkout
    - install dependencies
    - build production ZIP
    - run plugin check
    - inspect ZIP contents


Expert Sign-Off
---------------

Do not submit until the WordPress plugin expert can answer "yes" to all of these:

- [ ] The submitted plugin ZIP is GPL-compatible.
- [ ] The submitted plugin ZIP includes `readme.txt`.
- [ ] External service usage is clearly disclosed.
- [ ] Public credits/links are opt-in or service-controlled, not plugin-forced by default.
- [ ] No non-service external assets are loaded from third-party domains.
- [ ] The production ZIP contains no staging endpoints.
- [ ] Plugin Check has no unresolved blocker issues.
- [ ] A clean install/connect/render smoke test passes.
- [ ] The WordPress.org account in `Contributors` is correct and reachable.
