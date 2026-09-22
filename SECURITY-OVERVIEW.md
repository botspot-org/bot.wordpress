# Security and data handling overview

This document describes what the BotSpot WordPress plugin sends off your site,
what it stores locally, how the two ends authenticate to each other, and what
an IT review needs to know before the plugin goes on a production site. It
covers plugin version 3.7.8.

To report a vulnerability, see [SECURITY.md](SECURITY.md).

## What the plugin is

BotSpot is a hosted service that reads your published pages, enriches them, and
returns an appendix of generated HTML plus JSON-LD structured data that the
plugin renders back into those pages for search engines and AI crawlers. The
plugin is the connector between your WordPress site and that service. It does
nothing on its own: with no API key configured it makes no outbound requests at
all.

## What leaves your site

Three things go outbound, on three different triggers.

### Published content, on save and on manual sync

When you save a published post whose type is in the sync list, the plugin sends
the page to `POST /api/v1/connector/ingest`. A bulk sync sends the same records
in batches to `/api/v1/connector/ingest/batch`. The payload for each page
carries the title, the author's display name, the language, the published and
modified timestamps, the tags and categories, the permalink, the rendered HTML
body, the excerpt, the featured image URL, the WordPress post ID, post type and
status, any JSON-LD an SEO plugin already emits for the page, and the name of
the page builder in use.

Only posts with status `publish` and a post type you selected are eligible.
Drafts, pending, private, trashed, and password-protected posts are never sent.
The plugin re-sends a page only when its content hash changes and the word
count moves past the sync sensitivity threshold, so ordinary metadata edits do
not generate traffic.

Password protection is enforced on every path: the per-post eligibility check,
the bulk sync query, and the page registration query all exclude protected
posts, and the content fetcher refuses to read one even if a custom
integration reaches it directly. Releases before 3.7.9 excluded protected posts
on none of those paths; upgrade if you rely on this.

The author display name is the one field in this payload that is personal data.
The setting "Send author names", under the advanced settings, controls it. It
is on by default, because the platform republishes the name as schema.org
authorship in the appendix, which is an SEO signal. Turn it off if your site
hides bylines or treats author names as confidential.

### Page view telemetry, on every front-end request

On each front-end page view the plugin fires a non-blocking `POST /api/v1/t`
carrying the request path with the query string removed, the user agent
truncated to 512 characters, the referrer, and any `utm_*` campaign parameters
truncated to 128 characters each. That is the whole payload.

It does not send the visitor's IP address, any cookie, any session or visitor
identifier, or the identity of a logged-in user. The plugin sets no cookies and
reads none. Because the call is made by your web server rather than by the
visitor's browser, the BotSpot API observes your server's IP address, not the
visitor's. Admin screens, AJAX, cron, REST, and WP-CLI requests are excluded.

The purpose is bot traffic measurement. LLM and search crawlers do not run
JavaScript, so a browser pixel would miss exactly the traffic this exists to
count. The `utm_source` parameter is what attributes a human click back to the
assistant that cited the page.

### Appendix requests, on render

To render a page the plugin requests the appendix for that path, sending the
path and the content language. No page content travels in that direction.

Every outbound call goes over HTTPS to the API host you configure, which
defaults to the BotSpot production API.

## What comes back, and how it is handled

The platform returns appendix HTML and JSON-LD. The plugin runs the HTML
through an allowlist sanitizer before injecting it. Tags outside the allowlist
are unwrapped, `<script>` elements are removed outright, `on*` event handler
attributes are stripped regardless of tag, and `javascript:` and
`data:text/html` URLs are removed from `href` and `src`. `<style>` elements and
`style` attributes are deliberately preserved, because the appendix ships its
own scoped CSS and cannot render without them; that CSS is confined to the
appendix subtree.

## Inbound requests from the platform

The plugin exposes four REST routes that the platform calls: three aliases of a
fan-in webhook endpoint (`bspt/v1/webhook`, `botspot/v1/webhook`,
`botspot-wp/v1/webhook`) and a resync trigger (`bspt/v1/trigger-resync`). All
four accept unauthenticated HTTP at the WordPress level and authenticate in the
handler instead, by verifying an HMAC-SHA256 signature computed over the raw
request body with the site's webhook secret. The signature arrives as
`X-Webhook-Signature: sha256=<hex>` and is compared in constant time with
`hash_equals()`. Requests with a missing or wrong signature are rejected before
anything else happens. If no webhook secret is configured, every request to
these routes is refused.

These endpoints trigger a content resync, apply platform-pushed display
settings, invalidate cached appendix entries, and record enrichment status.
None of them write post content, create users, or execute site code.

## Credentials on your site

Two secrets are stored: the API key (`bspt_api_key`) and the webhook secret
(`bspt_webhook_secret`), both in the WordPress options table. They are stored in
plaintext, with the same protection WordPress gives any other option, which
means anyone with database read access or with the `manage_options` capability
can read them. This is the standard model for WordPress plugin credentials, and
it is worth stating plainly: treat the API key as equivalent in sensitivity to
a read-write credential for your BotSpot tenant, and rotate it from the BotSpot
dashboard if the database is ever exposed.

The key is scoped to one BotSpot organization. It cannot read another tenant's
data.

## Access control inside WordPress

The settings screen and every AJAX action the plugin registers require the
`manage_options` capability and a valid WordPress nonce, verified with
`check_ajax_referer()`. An editor or author account cannot change the
connection, trigger a sync, or read the stored credentials.

## Where the data goes

The BotSpot platform runs on Google Cloud. The API, the databases and the
content store run in `europe-north1` (Finland). The enrichment workers and the
AI models run in `europe-west4` (Netherlands). Your content and its backups
stay in those two regions.

Google's generative AI service writes the appendix. It runs on a global
endpoint. That endpoint gives no guarantee about the processing location. The
model that indexes your content runs in `europe-west4`.

BotSpot hosts the content stores itself, in the same project. No third-party
database vendor holds your content. WorkOS validates API keys and holds
organization identity. It receives no page content.

All tenant data is scoped by organization ID at every layer: separate Qdrant
collections per tenant, tenant tags on every graph node, and organization
filters on every database query.

## Removal

Deactivating the plugin stops all outbound traffic. Deleting it runs an
uninstall routine that removes every plugin option, every transient, and every
piece of plugin-written post metadata from your database. To have the content
already held by the platform deleted, ask through your BotSpot account.

## Open questions for your review

If your assessment needs anything not covered here, including a subprocessor
list on letterhead, a penetration test report, a data processing agreement, or
answers to a specific questionnaire, raise it with your BotSpot contact.
