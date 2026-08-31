=== BotSpot ===
Contributors: haavardmk
Tags: structured-data, schema, ai, content, seo
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.7.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress content to BotSpot and render BotSpot appendix content and JSON-LD structured data on your site.

== Description ==

BotSpot connects your WordPress site to the BotSpot platform. It syncs selected published content to BotSpot and renders the resulting appendix HTML and JSON-LD structured data on your public pages.

The plugin is designed for sites that use BotSpot as a software-as-a-service platform for content enrichment and structured data generation. A BotSpot account and access key are required before content can be synced or rendered.

Features include:

* Sync selected post types, including posts and pages.
* Fetch and render appendix HTML from BotSpot.
* Emit JSON-LD structured data in the page head.
* Place the appendix at the end of the content, at the end of the page, or manually with a shortcode.
* Add appendix placement with the `[botspot_appendix]` shortcode or the BotSpot Appendix block.
* Cache rendered content with WordPress transients.
* Retry failed syncs with WP-Cron.
* View connection, sync, runtime, and diagnostic information in the WordPress admin.

This plugin does not include premium-only WordPress plugin code. Any paid plan or account requirement applies to the external BotSpot service, not to locked code inside the plugin.

== External Services ==

This plugin connects to BotSpot services in order to provide its core functionality. The plugin will not sync site content until a site administrator enters a BotSpot access key and connects the site.

Service provider: BotSpot

Service URLs used by production builds:

* `https://platform.bot.spot` - BotSpot dashboard used to create and manage access keys.
* `https://locus-api.bot.spot` - BotSpot API used for site registration, content sync, appendix rendering, JSON-LD rendering, and enrichment webhooks.

Terms of service: https://bot.spot/terms

Privacy policy: https://bot.spot/privacy

Data sent to BotSpot may include:

* Site URL, site name, and webhook URL during connection.
* Selected published content, including title, content, excerpt, permalink, post type, status, author display name, language, publish/update dates, categories, tags, featured image URL, and existing source JSON-LD during sync.
* The current page path, site origin, and API authentication headers when fetching rendered appendix content or JSON-LD.
* On each frontend page view: the requested path, the visitor's user agent string, and the referring URL. This measures how often AI and search crawlers reach the site. No cookies are set or read, and no IP address is stored.

BotSpot may send webhook requests back to the WordPress site to update enrichment status, clear local caches, and push configuration changes. These requests are authenticated with an HMAC signature.

== Privacy ==

Content sync and rendering require an administrator to connect the site with a BotSpot access key. After connection, the plugin can automatically send selected published content to BotSpot when that content is published or updated.

After connection, the BotSpot platform may push configuration changes to the plugin via webhook, altering which post types sync or display enriched content. Administrators can review current settings in the Settings tab. These settings are managed in the BotSpot dashboard.

Site administrators can view which post types are synced and which post types receive injected output from the plugin settings screen. Disconnecting or removing the access key stops new authenticated API calls from succeeding.

The plugin stores BotSpot access credentials and sync metadata in the WordPress database. On uninstall, plugin options and plugin-owned post metadata are removed.

The plugin does not add BotSpot attribution links or credits to public pages by default. Service-rendered appendix output may include BotSpot-managed branding or attribution only if that behavior is configured in BotSpot.

== Source and Build ==

The plugin source is distributed as human-readable PHP, JavaScript, and CSS. Runtime PHP dependencies are installed with Composer and prefixed during release builds with Strauss to avoid dependency conflicts in WordPress environments.

Production release archives are generated with `./build.sh` or `TARGET=production ./build.sh`. The generated ZIP includes the WordPress.org `readme.txt`, the plugin license, third-party license notices, and the Strauss-prefixed runtime dependency files needed by the plugin.

== Third-Party Licenses ==

This plugin bundles or generates runtime code from the following GPL-compatible packages:

* `jaybizzle/crawler-detect` - MIT License.
* `monperrus/crawler-user-agents` - MIT License.
* Composer autoload/runtime metadata and Strauss-prefixed generated dependency files - see `THIRD-PARTY-LICENSES.txt` in the plugin package.

== Installation ==

1. Upload the `botspot` folder to the `/wp-content/plugins/` directory, or install the plugin from the WordPress Plugin Directory.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open the BotSpot menu in the WordPress admin.
4. Sign in to BotSpot and create an access key.
5. Paste the access key into the Connect tab and click Connect.
6. Review the Settings tab to choose which post types should sync and where output should be rendered.

== Frequently Asked Questions ==

= Do I need a BotSpot account? =

Yes. BotSpot is an interface to the BotSpot software-as-a-service platform. An access key is required before content can be synced or rendered.

= Does the plugin send data to an external service? =

Yes. After an administrator connects the plugin, selected published content and related metadata are sent to BotSpot so the service can process and render appendix content and structured data. See the External Services section for details.

= Can I choose what content is synced? =

Yes. Content sync settings are managed in the BotSpot dashboard and sync automatically to the plugin. The Settings tab displays the current configuration. By default, posts and pages are selected.

= Can I disable automatic output? =

Yes. Output settings including appendix HTML, JSON-LD, placement, and post types are managed in the BotSpot dashboard. Changes made there sync automatically to this plugin.

= How do I place the appendix manually? =

Set placement to Manual placement and add the `[botspot_appendix]` shortcode where the appendix should appear. You can also use the BotSpot Appendix block in the block editor.

= Does this replace my SEO plugin? =

No. The plugin emits BotSpot JSON-LD as a separate structured data script and is designed to work alongside common SEO plugins.

= What happens if BotSpot is temporarily unavailable? =

Previously fetched content can be served from WordPress transients while cache entries are still available. Failed sync attempts are logged, and some sync failures are retried with WP-Cron.

= Does this work on WordPress Multisite? =

Yes. Each subsite connects to BotSpot independently with its own access key. Network administrators cannot configure BotSpot network-wide; each subsite administrator manages their own connection and settings.

== Screenshots ==

1. Connect tab for entering a BotSpot access key.
2. Settings tab for content sync and display placement.
3. Developer tab with connection, sync, cache, and diagnostic tools.

== Changelog ==

= 3.7.8 =

* New installs now place the appendix at the end of the page instead of the end of the content. The end of the page survives page builders and themes that wrap the post text. Sites installed before this release keep the placement they already have.

= 3.7.7 =

* The connection status indicators now agree. The header pill and the Connect tab pill both check the same state, so one cannot show connected while the other shows disconnected.

= 3.7.6 =

* Crawler measurement now recognises many more AI assistants and search crawlers, including ChatGPT search, Claude, Perplexity, Gemini, and Meta AI, and reports each one separately instead of grouping them.
* Visits that arrive with campaign tags in the address (utm_source and friends) now report those tags, so you can see when an AI assistant sends a reader to your page. No cookies are set or read, and no IP address is stored.

= 3.7.5 =

* BotSpot now measures how often AI and search crawlers reach your pages. Each frontend page view sends the path, the user agent, and the referrer to BotSpot in a background request that does not delay the page. No cookies are set or read, and no IP address is stored.

= 3.7.4 =

* The plugin now reports its own version to BotSpot, so support can see which build a site runs without asking. No settings change and no new data about your content.

= 3.7.3 =

* Placement values now match the names the BotSpot dashboard shows, so a placement chosen there no longer reverts on the next save. Existing settings migrate on read and need no action.

= 3.7.2 =

* The placement settings no longer ask for a CSS selector. Placement is at the end of the content, at the end of the page, or a manual shortcode.
* "In content" is now called "End of the content", and each placement option describes where the appendix lands when you hover it.

= 3.7.0 =

* Connecting now registers the site with bot.spot straight away. Sites with no published posts appear as connected instead of waiting for the first content sync.
* Connecting to a domain another integration already owns now reports the clash instead of failing silently.

= 3.6.0 =

* Placement now has three settings: in the content, at the end of the page, or manual shortcode. Older settings migrate automatically.
* Sites set to "above footer" now render the appendix below the footer.
* The appendix renders at the chosen position on the server. JavaScript no longer moves it after the page loads.
* A new optional CSS selector places the appendix next to a specific element. The appendix stays at the end of the page if the selector matches nothing.
* Placement and post-type changes made in the BotSpot dashboard now reach the site right away.
* Placement and post-type changes made in WordPress now push back to the dashboard. The most recent change wins.
* The settings screen no longer locks itself after a dashboard change.

= 3.5.15 =

* Page-builder pages now sync their real content. Divi and WPBakery were shipping raw shortcode markup, and Elementor, Bricks and Beaver Builder only had a dozen widget types read by a hardcoded allowlist.
* Content is now harvested by value shape rather than a per-widget allowlist, so third-party and newly added widgets are picked up without a plugin update.
* Fixed main-content extraction truncating an article at the first nested closing tag; the page is now parsed with DOM instead of a regex.
* Script, style and code-widget bodies are no longer sent as page copy.
* The extraction path used for each page is now reported alongside the content.

= 3.5.14 =

* Escaped all output at the point of output, including the appendix HTML, JSON-LD, diagnostic comments, and admin notices.
* Appendix HTML is now filtered before it is escaped, so output added by the `bspt_appendix_html` filter is escaped too.
* Replaced the legacy `[botdot_appendix]` shortcode registration with an automatic rewrite to `[bspt_appendix]`, so existing pages keep working without the plugin claiming a shortcode name that lacks its prefix.
* Fixed page cache purging for WP Rocket, which was never being triggered, and routed W3 Total Cache purges through its public API so all of its cache modules are cleared.
* Fixed a bug where a page using the `[bspt_appendix]` shortcode or BotSpot Appendix block could receive a second, automatically injected appendix.

= 3.5.13 =

* Fixed two admin notice links that still pointed at the old settings page slug and produced a permissions error.
* Synced version metadata across the plugin header, version constant, and readme.

= 3.5.12 =

* Removed the Plugin URI header so the plugin and author URIs are no longer identical.

= 3.5.11 =

* Fixed a fatal error on activation caused by a call to a removed webhook secret method.
* Guarded the settings tab against a null post type list.

= 3.5.9 =

* Settings now lock only when the BotSpot dashboard explicitly pushes configuration, not on initial connect.

= 3.5.7 =

* Fixed hidden settings controls caused by a stale checkbox override.
* Webhooks are now always re-registered instead of trusting a cached webhook id.

= 3.5.6 =

* Fixed the settings page hook and admin CSS selectors after the plugin slug was renamed to "botspot".

= 3.5.5 =

* Prefixed all global template variables (bspt_) to satisfy WordPress.org global-namespace guidelines.

= 3.5.4 =

* Corrected the plugin text domain to match the plugin slug ("botspot") across all strings.
* Renamed the plugin to "BotSpot" and removed the invalid "Network" header for WordPress.org compliance.
* Removed the manual load_plugin_textdomain() call (handled automatically for hosted plugins) and updated the "Tested up to" version.

= 3.5.3 =

* Hardened for WordPress.org review: output escaping, translation text domain, input sanitization, and strict comparisons across admin and public code.
* Documented plugin-owned direct database queries and removed stale analytics references from the readme.
* Uninstall now removes all plugin options and transients, including platform settings and migration flags.

= 3.5.2 =

* Version bump for WordPress.org plugin review submission.

= 3.5.1 =

* Fixed Developer tab badge status checks (Sync timezone, Runtime transient prefix).
* Fixed Settings form field selector prefixes.
* Removed incomplete server-side analytics feature.
* Updated WordPress tested-up-to version to 6.8.

= 3.4.8 =

* Synced version metadata across plugin header and readme.
* Upgraded test harness dependencies to resolve security advisories.

= 3.4.7 =

* Continued WordPress.org Plugin Directory compliance refinements.
* Rebranded user-visible strings from "BotSpot" to "bot.spot".
* Auto-detect WooCommerce and include product post type in sync options.
* Relocated analytics section to Developer tab.
* Simplified UX by hiding sensitivity setting and defaulting to high.
* Settings now only lock when dashboard pushes config, not on initial connect.

= 3.1.1 =

* Hardened release packaging, metadata, third-party license notices, analytics rendering, JSON-LD output encoding, sync authorization, SSL verification, lifecycle behavior, and uninstall cleanup for WordPress.org submission readiness.

= 3.0.0 =

* Renamed internal code from botdot-wp to botspot-wp to match BotSpot branding.
* File names, class names, constants, hooks, and options now use botspot prefix.
* Migration code automatically preserves settings from v2.x installations.
* Legacy hook names (botdot_wp_*) remain as aliases for backwards compatibility.
* Legacy shortcode [botdot_appendix] remains as alias for [botspot_appendix].

= 2.9.2 =

* Current stable release.
* Improves push-based content sync and appendix rendering.
* Supports JSON-LD output, appendix HTML output, manual placement, block editor placement, caching, diagnostics, and enrichment status updates.

= 2.6.4 =

* Fixed cache clearing so plugin-owned appendix and JSON-LD transients are removed correctly.
* Added external page-cache purge hooks when enrichment updates are received.
* Added cache helper actions for site-specific integrations.

= 2.6.3 =

* Improved force resync behavior for large sites.

= 2.6.2 =

* Improved Connect flow so access keys can be saved inline.

= 2.5.0 =

* Changed JSON-LD output to emit BotSpot structured data as a separate peer script.
* Improved multilingual language detection for Polylang and WPML.
* Improved source JSON-LD cleanup before sync.

= 2.4.0 =

* Added admin analytics cards for sync health, enrichment lifecycle, and aggregate impressions.
* Added local bot classification and scheduled analytics flushing.

= 1.0.1 =

* Improved sanitization for external HTML.
* Re-encoded JSON-LD output before rendering.
* Improved error logging and cache handling.

= 1.0.0 =

* Initial push-based sync and appendix injection release.

== Upgrade Notice ==

= 3.7.8 =

Changes the appendix placement that new installs start with, from the end of the content to the end of the page. Your existing placement is untouched.

= 3.7.7 =

Fixes the connection status shown on the settings screen. The header and the Connect tab could disagree, one reporting connected while the other reported disconnected. No settings change.

= 3.7.6 =

Recognises many more AI crawlers by name and reports campaign tags on incoming links, so you can tell which assistant sent a reader. No cookies are used and no IP address is stored.

= 3.7.5 =

Adds crawler visit measurement so you can see which AI and search bots read your pages. Page load speed is unaffected, no cookies are used, and no IP address is stored.

= 3.7.4 =

The plugin now tells BotSpot which version it is running, so support can answer version questions without asking you. Nothing else changes.

= 3.7.0 =

WordPress now appears as a connected integration as soon as you connect, even with no published posts. Reconnect once after updating if your site is missing from the dashboard.

= 3.6.0 =

Placement settings are renamed and now render server-side. Sites previously set to "above footer" will show the appendix below the footer instead; re-check placement after updating.

= 3.5.13 =

Fixes broken admin notice links and header compliance issues found during WordPress.org review. Recommended for all users.

= 3.5.2 =

Version bump for WordPress.org plugin review submission. No code changes from 3.5.1.

= 3.5.1 =

Bug fixes for Developer tab badges (Sync, Runtime status) and Settings form fields.
