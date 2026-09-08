# Security Policy

## Reporting a vulnerability

Report suspected vulnerabilities to **security@bot.spot**. Do not open a public
GitHub issue, a WordPress.org support topic, or a forum post for a security
issue.

Include what you have:

- Plugin version (`Version:` in `botspot.php`, or the version shown on the
  Plugins screen).
- WordPress and PHP versions.
- Steps to reproduce, and the request or payload if one is involved.
- What an attacker gains: read access, write access, privilege escalation,
  content injection.

You do not need a proof-of-concept exploit. A precise description of the flaw
is enough.

## What to expect

| Stage | Target |
|---|---|
| Acknowledgement of your report | 3 business days |
| Initial assessment and severity | 10 business days |
| Fix released for a confirmed critical or high issue | 30 days |
| Fix released for a confirmed medium or low issue | Next scheduled release |

We will tell you when the fix ships and credit you in the changelog unless you
ask us not to. We ask that you hold public disclosure until a fixed release is
on WordPress.org, or 90 days after your report, whichever comes first.

## Supported versions

| Version | Supported |
|---|---|
| 3.7.x | Yes |
| < 3.7 | No |

Security fixes ship on the latest 3.7.x release. Upgrade before reporting an
issue against an older version.

## Scope

In scope: this plugin's code, its REST endpoints, its admin screens, and the
data it transmits.

Out of scope: WordPress core, other plugins, themes, the hosting environment,
and the BotSpot platform API. Report platform issues to the same address; they
follow a separate remediation path.

## Attack surface

The plugin registers four unauthenticated REST routes:

- `POST /wp-json/bspt/v1/webhook`
- `POST /wp-json/botspot/v1/webhook`
- `POST /wp-json/botspot-wp/v1/webhook`
- `POST /wp-json/bspt/v1/trigger-resync`

Their `permission_callback` is `__return_true` by design. Authentication is an
HMAC-SHA256 signature over the raw request body, sent as
`X-Webhook-Signature: sha256=<hex>` and compared with `hash_equals()`. The key
is the per-site webhook secret the platform issues at registration. A request
without a valid signature is rejected before any handler logic runs.

Every admin AJAX action verifies a nonce with `check_ajax_referer()` and
requires the `manage_options` capability.

Appendix HTML returned by the platform passes through an allowlist sanitizer
before injection. `<script>` elements are removed, `on*` event attributes are
stripped, and `javascript:` and `data:text/html` URLs are blocked in `href` and
`src`.

## Data handling

For what the plugin sends, where it goes, and how long it is kept, see
[SECURITY-OVERVIEW.md](SECURITY-OVERVIEW.md).
