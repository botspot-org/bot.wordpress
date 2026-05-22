=== BotSpot WordPress ===
Contributors: botspot
Tags: structured-data, schema, ai, content, seo
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.9.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress content to BotSpot and render BotSpot appendix content and JSON-LD structured data on your site.

== Description ==

BotSpot WordPress connects your WordPress site to the BotSpot platform. It syncs selected published content to BotSpot and renders the resulting appendix HTML and JSON-LD structured data on your public pages.

The plugin is designed for sites that use BotSpot as a software-as-a-service platform for content enrichment and structured data generation. A BotSpot account and access key are required before content can be synced or rendered.

Features include:

* Sync selected post types, including posts and pages.
* Fetch and render appendix HTML from BotSpot.
* Emit JSON-LD structured data in the page head.
* Choose automatic placement, footer-aware placement, or manual placement with a shortcode.
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
* `https://locus-api.bot.spot` - BotSpot API used for site registration, content sync, appendix rendering, JSON-LD rendering, enrichment webhooks, and analytics reporting.

Terms of service: https://bot.spot/terms

Privacy policy: https://bot.spot/privacy

Data sent to BotSpot may include:

* Site URL, site name, and webhook URL during connection.
* Selected published content, including title, content, excerpt, permalink, post type, status, author display name, language, publish/update dates, categories, tags, featured image URL, and existing source JSON-LD during sync.
* The current page path, site origin, and API authentication headers when fetching rendered appendix content or JSON-LD.
* Aggregated impression counters for synced content, grouped by bot or human class. The plugin classifies visitors locally and sends aggregate counts, artifact identifiers, and first-hit timestamps. It does not send raw visitor IP addresses or raw user-agent strings in the analytics batch.

BotSpot may send webhook requests back to the WordPress site to update enrichment status and clear local caches. These requests are authenticated with an HMAC signature.

== Privacy ==

Content sync and rendering require an administrator to connect the site with a BotSpot access key. After connection, the plugin can automatically send selected published content to BotSpot when that content is published or updated.

Site administrators can choose which post types are synced and which post types receive injected output from the plugin settings screen. Disconnecting or removing the access key stops new authenticated API calls from succeeding.

The plugin stores BotSpot access credentials and sync metadata in the WordPress database. On uninstall, plugin options and plugin-owned post metadata are removed.

== Installation ==

1. Upload the `botdot-wp` folder to the `/wp-content/plugins/` directory, or install the plugin from the WordPress Plugin Directory.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open the BotSpot menu in the WordPress admin.
4. Sign in to BotSpot and create an access key.
5. Paste the access key into the Connect tab and click Connect.
6. Review the Settings tab to choose which post types should sync and where output should be rendered.

== Frequently Asked Questions ==

= Do I need a BotSpot account? =

Yes. BotSpot WordPress is an interface to the BotSpot software-as-a-service platform. An access key is required before content can be synced or rendered.

= Does the plugin send data to an external service? =

Yes. After an administrator connects the plugin, selected published content and related metadata are sent to BotSpot so the service can process and render appendix content and structured data. See the External Services section for details.

= Can I choose what content is synced? =

Yes. The Settings tab lets administrators choose the post types that are synced. By default, posts and pages are selected.

= Can I disable automatic output? =

Yes. The Settings tab includes controls for appendix HTML output, JSON-LD output, output placement, and post types that receive injected output.

= How do I place the appendix manually? =

Set placement to Manual placement and add the `[botspot_appendix]` shortcode where the appendix should appear. You can also use the BotSpot Appendix block in the block editor.

= Does this replace my SEO plugin? =

No. The plugin emits BotSpot JSON-LD as a separate structured data script and is designed to work alongside common SEO plugins.

= What happens if BotSpot is temporarily unavailable? =

Previously fetched content can be served from WordPress transients while cache entries are still available. Failed sync attempts are logged, and some sync failures are retried with WP-Cron.

== Screenshots ==

1. Connect tab for entering a BotSpot access key.
2. Settings tab for content sync and display placement.
3. Developer tab with connection, sync, cache, and diagnostic tools.

== Changelog ==

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

= 2.9.2 =

Update to the latest stable release for current sync, rendering, cache, and diagnostic behavior.
