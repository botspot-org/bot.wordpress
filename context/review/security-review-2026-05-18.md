# Security Review - 2026-05-18

**Mode**: security-focused (full codebase)
**Plugin version**: 2.9.1 (commit `d327e1a`)
**Scope**: All PHP in `botdot-wp.php`, `uninstall.php`, `admin/`, `includes/`, `public/`. Excluded `vendor/`, `tests/`, `testing/`.
**Method**: Traced untrusted inputs (webhook payloads, REST params, AJAX POSTs, HTTP request data) to sensitive sinks (DB, output, HTTP, file ops, signature checks). Findings reported only at ≥80% exploitability confidence.

## Executive Summary

**No HIGH or MEDIUM severity vulnerabilities found at the required confidence threshold.**

The plugin correctly applies WordPress security primitives across the board.

## Verified Secure

| Area | Location | Status |
| ---- | -------- | ------ |
| REST webhook auth | `class-botdot-wp-webhook-handler.php:42-115`, `class-botdot-wp-sync.php:37-119` | `permission_callback => __return_true` is intentional; auth enforced via HMAC-SHA256 with constant-time `hash_equals()`; empty secret/signature rejected before comparison. No bypass. |
| AJAX handlers (13) | `class-botdot-wp-admin.php` | All consistently `check_ajax_referer()` + `current_user_can("manage_options")`. No missing CSRF/capability checks. |
| SQL (`$wpdb`) | webhook-handler.php:137-151, content-fetcher.php:386-392, admin.php:998-1003, analytics-flusher.php:142-289, uninstall.php:57-63 | All raw queries use `$wpdb->prepare()` + `esc_like()`; analytics `GROUP BY` (admin.php:1237,1265) uses hardcoded meta keys. No injection. |
| Output / XSS | admin partials; content-injector.php:320-325,372-379 | `esc_html`/`esc_attr`/`esc_url` used; JSON-LD output neutralizes `</script>` breakout. |
| SSRF | content-fetcher / sync HTTP calls | Targets built from compile-time constant `BOTDOT_WP_LOCUS_API_URL`; host/protocol not user-controllable (only path/lang query args appended to fixed host). |
| Deserialization | webhook/sync payload handling | No `unserialize`/`maybe_unserialize` on untrusted input; payloads use `json_decode`. |
| Inline API key save | admin.php:553 (`maybe_save_inline_api_key`) | Both callers gate on nonce + `manage_options` first. |

## Informational (not findings — no action required)

| ID | Location | Note |
| -- | -------- | ---- |
| I1 | options storage | `botdot_wp_api_key` / `botdot_wp_webhook_secret` stored as plaintext WP options. Standard WP practice; DB-read access exposes them. Out of scope (secrets at rest). |
| I2 | `class-botdot-wp-sync.php:103` | Unvalidated `$new_tier` from webhook payload written to post meta — only reachable after HMAC verification, so requires the shared secret. Not independently exploitable. |
| I3 | `admin.php:1310` | `handle_get_impressions` reads a `botdot_wp_api_url` option never written anywhere in the codebase — effectively dead code. |
| I4 | `content-injector.php:1119-1141` (`sanitize_html`) | `wp_kses()` allowlist includes `<style>` and `style=`. HTML source is locus-core over authenticated API (`X-API-Key`), so only exploitable by compromised/MITM'd upstream — below threshold. |

## Regression Status

First security-focused review. Prior `codebase-review-2026-02-21.md` flagged 10 security items (1 P0, 1 P1) against an earlier version (~v1.0.0); current v2.9.1 codebase shows the standard WP vuln classes are now properly mitigated.
