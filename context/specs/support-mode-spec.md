# Support Mode Specification

**Version**: 1.2  
**Date**: 2026-05-24  
**Status**: Ready for implementation  

---

## Overview

Support Mode is a time-limited, opt-in feature that sends diagnostic data from the WordPress plugin to BotSpot's backend every 15 minutes while active. It auto-expires after 24 hours.

**Why**: Early-stage product needs visibility into client-side issues without SSH access or manual log collection.

**Compliance**: WordPress.org Guideline §7 requires explicit user consent for any external data transmission.

---

## Design Decisions

### Self-Issued vs Support-Code Model

| Model | How it works | Pros | Cons |
|-------|--------------|------|------|
| **Self-issued** (recommended for v1) | Admin enables directly in Developer tab | Simple, no operational overhead | Less audit trail |
| **Support-code** | Staff issues code tied to ticket, admin enters it | Ties activation to ticket, auditable | Adds friction, requires issuance UI |

**Decision**: Self-issued for v1. Revisit support-code model if abuse or compliance concerns arise.

---

## Known Limitations

| Limitation | Impact | Mitigation |
|------------|--------|------------|
| **Logger 1hr TTL** | Logs use transient with 1hr expiry — only see last hour, not full 24h session | Document; consider separate buffer in Phase 2 |
| **WP-Cron unreliability** | Low-traffic sites may not fire cron | Document system cron recommendation |
| **Multisite** | Not addressed in v1 | Options are per-site; network admin can enable on any site |

---

## Hard Blockers (Must Address Before Shipping)

| ID | Risk | Mitigation |
|----|------|------------|
| C1 | WP.org rejection: external call without explicit consent | Two-step consent modal with timestamp stored |
| C3 | API keys in diagnostic payload | Explicit allowlist in `collect_payload()`; show only masked prefix |
| T1 | Unauthenticated endpoint | Require `X-API-Key` (WorkOS), same as all other endpoints |
| T4 | Support mode stuck on after deactivation | Deactivator + uninstall.php must clear cron + options |

---

## Data Collection

### Allowlist (what we collect)

| Category | Fields |
|----------|--------|
| **Environment** | WP version, PHP version, plugin version, active theme (slug only), multisite flag, HTTPS flag, PHP memory/exec limits, WP cron enabled |
| **Connection** | API key prefix (masked: `sk_live***`), connection_id, tenant_id, connector reachability |
| **Plugin Options** | Safe subset: auto_sync_enabled, sync_sensitivity, sync_post_types, appendix_enabled, jsonld_enabled, injection_position, cache_ttl, debug_mode |
| **Sync Health** | Post counts by status (synced, pending, error, never) |
| **Cron Status** | Next run timestamps for analytics flush and support mode report |
| **Logs** | Last 50 entries from `BotSpot_WP_Logger`, stripped of post titles/author names |

### Denylist (NEVER collect)

- API key value, webhook_secret
- Post content, titles, URLs, slugs
- User emails, display names (except activating admin stored at activation)
- $_POST, $_GET, $_COOKIE, $_SERVER
- WooCommerce orders, customer data
- Data from third-party plugins

---

## Plugin Architecture

### New Options

| Option | Type | Default | Purpose |
|--------|------|---------|---------|
| `support_mode` | bool | false | Master toggle |
| `support_mode_expires` | int | 0 | Unix timestamp of expiry |
| `support_mode_activated_at` | int | 0 | Activation timestamp |
| `support_mode_activated_by` | string | "" | Activating admin's display name |
| `support_mode_last_report_at` | int | 0 | Last successful send |
| `support_mode_last_report_id` | string | "" | UUID of last report |
| `support_mode_prompt_dismissed_at` | int | 0 | Phase 2: when error prompt was dismissed |

### Options Class Integration

Add to `BotSpot_WP_Options::$defaults`:
```php
'support_mode' => false,
'support_mode_expires' => 0,
'support_mode_activated_at' => 0,
'support_mode_activated_by' => '',
'support_mode_last_report_at' => 0,
'support_mode_last_report_id' => '',
```

Add to `cast_option_value()`:
```php
case 'support_mode':
    return (bool) $value;
case 'support_mode_expires':
case 'support_mode_activated_at':
case 'support_mode_last_report_at':
    return (int) $value;
case 'support_mode_activated_by':
case 'support_mode_last_report_id':
    return is_string($value) ? trim($value) : '';
```

### Cron Schedule Registration

Add to `class-botspot-wp-activator.php` or via filter in main class:
```php
add_filter('cron_schedules', function($schedules) {
    $schedules['botspot_15min'] = [
        'interval' => 900,
        'display' => __('Every 15 minutes', 'botspot-wp')
    ];
    return $schedules;
});
```

### New Files

**`includes/class-botspot-wp-support-mode.php`**
```php
class BotSpot_WP_Support_Mode {
    const DURATION_SECONDS = 86400;  // 24h
    const CRON_HOOK = 'botspot_support_mode_report';
    const CRON_INTERVAL = 'botspot_15min';  // 900s

    public static function is_active(): bool;
    public static function activate(): bool;    // sets options, schedules cron, sends immediate report
    public static function deactivate(): void;  // clears options, unschedules cron
    public static function maybe_expire(): void;
    public static function time_remaining(): int;
    public static function status(): array;
}
```

**`includes/class-botspot-wp-diagnostics-reporter.php`**
```php
class BotSpot_WP_Diagnostics_Reporter {
    const LOCK_TRANSIENT = 'botspot_diag_lock';
    const LOCK_TTL = 300;

    public static function send(): array;  // main entry, called by cron
    private static function collect_payload(): array;  // allowlist enforced here
    private static function post_report(string $report_id, array $payload): bool;
}
```

### AJAX Handlers (in BotSpot_WP_Admin)

| Action | Handler | Purpose |
|--------|---------|---------|
| `botspot_wp_toggle_support_mode` | `handle_toggle_support_mode()` | Enable/disable support mode |
| `botspot_wp_send_diag_report` | `handle_send_diag_report()` | Manual "send now" |

### Network Call Configuration

```php
$response = wp_remote_post($endpoint, [
    'headers' => $headers,
    'body' => $json_body,
    'timeout' => 10,
    'blocking' => true,  // Need response to store report_id; cron runs in background anyway
]);
```

Note: `blocking: true` is required because we need the response to store `report_id`. This is safe because the cron callback runs in a background process, not during user page loads.

### Cleanup on Deactivation

In `class-botdot-wp-deactivator.php`:
```php
BotSpot_WP_Support_Mode::deactivate();
```

In `uninstall.php`:
```php
delete_option('botspot_wp_support_mode');
delete_option('botspot_wp_support_mode_expires');
delete_option('botspot_wp_support_mode_activated_at');
delete_option('botspot_wp_support_mode_activated_by');
delete_option('botspot_wp_support_mode_last_report_at');
delete_option('botspot_wp_support_mode_last_report_id');
delete_option('botspot_wp_support_mode_prompt_dismissed_at');
delete_transient('botspot_diag_lock');
wp_clear_scheduled_hook('botspot_support_mode_report');
```

---

## Backend Architecture

### Endpoint

```
POST /api/v1/diagnostics/report
Authorization: X-API-Key sk_*
Content-Type: application/json
```

**File**: `app/api/v1/diagnostics.py`

**Auth**: ServiceKeyAuth (WorkOS API key, same as ingest). `org_id` derived from key.

**Validation**:
- Reject if `support_mode_expires <= time.time()` (server-enforced expiry, with 60s tolerance for clock skew)
- Reject if payload `tenant_id` doesn't match auth `org_id`
- Rate limit: 1 request per 10 min per org_id (enforced regardless of session — prevents disable/re-enable bypass)

### Storage Model

```python
# app/services/diagnostics/models.py
class DiagnosticsReport(SQLModel, table=True):
    __tablename__ = "diagnostics_reports"

    id: UUID = Field(default_factory=uuid4, primary_key=True)
    report_id: UUID = Field(index=True, unique=True)  # client-assigned, idempotency
    org_id: str = Field(index=True)
    received_at: datetime = Field(default_factory=lambda: datetime.now(UTC))
    plugin_version: str
    wp_version: str
    php_version: str
    support_mode_expires: int
    payload: dict = Field(sa_column=Column(JSONB))
```

### Retention

Dagster nightly asset `diagnostics_reports_prune`:
```python
DELETE FROM diagnostics_reports WHERE received_at < now() - interval '30 days'
```

---

## Payload Schema

```json
{
  "report_id": "uuid-v4",
  "plugin_version": "2.9.2",
  "wp_version": "6.4.2",
  "php_version": "8.2.15",
  "support_mode_expires": 1748123456,
  "tenant_id": "org_xxx",

  "activated_at": 1748037056,

  "environment": {
    "site_domain": "example.com",
    "api_key_prefix": "sk_live***",
    "connection_id": "wh_uuid",
    "is_multisite": false,
    "wp_debug": false,
    "is_https": true,
    "php_memory_limit": "256M",
    "php_max_execution_time": 30,
    "wp_cron_enabled": true,
    "is_wp_cron_disabled": false,
    "is_object_cache_active": false,
    "server_software": "Apache/2.4.51",
    "active_plugins": [
      {"slug": "woocommerce/woocommerce.php", "version": "8.5.0"}
    ],
    "active_plugins_count": 12
  },

  "options_snapshot": {
    "auto_sync_enabled": true,
    "sync_sensitivity": "medium",
    "sync_post_types": ["post", "page"],
    "appendix_enabled": true,
    "jsonld_enabled": true,
    "injection_position": "bottom_of_content",
    "cache_ttl": 3600,
    "debug_mode": false
  },

  "sync_health": {
    "synced": 142,
    "pending": 3,
    "error": 1,
    "never": 0
  },

  "cron_status": {
    "flush_analytics_next_run": 1748120000,
    "support_mode_report_next_run": 1748120900
  },

  "analytics_status": {
    "last_flush_at": 1748119600,
    "last_flush_id": "uuid",
    "pending_impressions_count": 5
  },

  "log_entries": [
    {
      "timestamp": "2026-05-24T10:00:00Z",
      "level": "error",
      "source": "sync",
      "message": "HTTP 502 from locus-core"
    }
  ]
}
```

**Size estimate**: ~10KB max per report. 96 reports max per 24h session = ~1MB per site.

---

## UI Design

### Developer Tab Section

```
┌─────────────────────────────────────────────────────┐
│ Support Mode                                        │
├─────────────────────────────────────────────────────┤
│ Sends diagnostics to bot.spot every 15 minutes.    │
│ Auto-expires after 24 hours.                       │
│                                                     │
│ Status: ● Active — expires in 23h 42m              │
│                                                     │
│ [Disable now]  [Send report now]                   │
│                                                     │
│ Last report: 2 minutes ago                         │
└─────────────────────────────────────────────────────┘
```

When inactive:
```
│ Status: ○ Inactive                                 │
│                                                     │
│ [Enable support mode]                              │
```

### Consent Modal (on Enable)

```
┌─────────────────────────────────────────────────────┐
│ Enable Support Mode?                                │
├─────────────────────────────────────────────────────┤
│ When enabled, the following is sent to bot.spot    │
│ every 15 minutes for 24 hours:                     │
│                                                     │
│ • WordPress & PHP versions                         │
│ • Plugin settings (not API keys)                   │
│ • Sync status and error counts                     │
│ • Recent log messages                              │
│                                                     │
│ No page content or visitor data is collected.      │
│                                                     │
│ Privacy policy: https://bot.spot/privacy           │
│                                                     │
│ [Cancel]  [Enable for 24 hours]                    │
└─────────────────────────────────────────────────────┘
```

### Persistent Admin Banner (while active)

Non-dismissible, shown on all admin pages:

```
┌─────────────────────────────────────────────────────────────────────┐
│ BotSpot Support Mode is active — diagnostics sent every 15 min     │
│ Expires in 23h 42m  [Disable now]                                  │
└─────────────────────────────────────────────────────────────────────┘
```

### Error-Triggered Prompt (Phase 2)

When `error_count >= 3` and support mode inactive and not dismissed in last 48h:

```
┌─────────────────────────────────────────────────────┐
│ BotSpot encountered repeated errors                 │
│                                                     │
│ Enable Support Mode to help us investigate?        │
│ (Auto-expires after 24 hours)                      │
│                                                     │
│ [Enable Support Mode]  [Dismiss for 48h]           │
└─────────────────────────────────────────────────────┘
```

---

## Implementation Phases

### Phase 1: Core (MVP)

**Plugin:**
1. Add 6 options to `BotSpot_WP_Options`
2. Create `class-botspot-wp-support-mode.php`
3. Create `class-botspot-wp-diagnostics-reporter.php`
4. Add AJAX handlers to `BotSpot_WP_Admin`
5. Register hooks + 15-min cron schedule
6. Add deactivation cleanup
7. Add Developer tab UI section
8. Add consent modal + admin banner

**Backend:**
1. Create `app/api/v1/diagnostics.py` router
2. Create `app/schemas/diagnostics.py`
3. Create `app/services/diagnostics/` with models + service
4. Alembic migration for `diagnostics_reports`
5. Dagster nightly prune asset

### Phase 2: Error-Triggered Prompt

1. Add `support_mode_prompt_dismissed_at` option
2. Compute `should_prompt` flag
3. Add modal HTML + JS handlers

### Phase 3: Dashboard Read Endpoint

1. `GET /api/v1/diagnostics/reports` list endpoint
2. `GET /api/v1/diagnostics/reports/{report_id}` detail endpoint
3. OpenAPI spec for dashboard team

---

## File Map

### New Plugin Files
- `includes/class-botspot-wp-support-mode.php`
- `includes/class-botspot-wp-diagnostics-reporter.php`

### Modified Plugin Files
- `includes/class-botspot-wp-options.php` — 6 new options in $defaults, cast_option_value, sanitize_option_value
- `includes/class-botspot-wp.php` — hook registrations, requires
- `includes/class-botspot-wp-activator.php` — register botspot_15min cron schedule
- `includes/class-botspot-wp-deactivator.php` — cleanup call
- `uninstall.php` — delete options + transient + clear cron
- `admin/class-botspot-wp-admin.php` — AJAX handlers, localization with nonces
- `admin/partials/tab-developer.php` — support mode section
- `admin/js/botspot-admin.js` — JS handlers

### New Backend Files
- `app/api/v1/diagnostics.py`
- `app/schemas/diagnostics.py`
- `app/services/diagnostics/__init__.py`
- `app/services/diagnostics/service.py`
- `app/services/diagnostics/models.py`
- Alembic migration

### Modified Backend Files
- `app/api/v1/__init__.py` — register router

---

## Open Questions

| # | Question | Options | Recommendation |
|---|----------|---------|----------------|
| 1 | Session duration | Fixed 24h vs configurable | Fixed 24h for v1 |
| 2 | Diagnostic retention | 30 days vs 90 days | 30 days (document in privacy policy) |
| 3 | Event-triggered immediate send on error? | Yes/No | No for v1 — risk of burst traffic; 15-min interval is short enough |
| 4 | Delta-only storage (skip identical reports)? | Yes/No | No for v1 (simplicity), revisit if storage grows |

---

## Privacy Policy Update Required

Add to bot.spot privacy policy before shipping:

> **Support Mode (WordPress Plugin)**
> 
> When a WordPress administrator enables Support Mode, the BotSpot plugin sends diagnostic information to BotSpot servers every 15 minutes for up to 24 hours. This includes:
> - WordPress, PHP, and plugin version information
> - Plugin configuration settings (not API keys)
> - Content sync status and error counts
> - Recent plugin log messages
>
> No page content, visitor data, or personally identifiable information is transmitted. Diagnostic data is retained for 30 days and used solely for troubleshooting purposes.

---

## Watch Out For (Future Concerns)

| Concern | Risk | Mitigation |
|---------|------|------------|
| **active_plugins list size** | Sites with 50+ plugins inflate payload | Include count + truncate to 20; only log names, not settings |
| **Clock skew** | Server rejects valid requests if clocks differ | 60s tolerance window in expiry check |
| **Rate limit bypass** | User could disable/re-enable for immediate sends | Rate limit by org_id regardless of session |
| **Memory pressure** | Large log collection on constrained hosts | Keep log limit at 50; truncate message length |
| **Consent timestamp audit** | Backend should know when consent was given | Include `activated_at` in payload |

---

## Checklist Before Shipping

- [ ] Privacy policy updated with Support Mode section
- [ ] WP.org readme.txt updated with external connection disclosure
- [ ] Consent modal text reviewed by legal
- [ ] Rate limiting tested
- [ ] Deactivation cleanup tested
- [ ] Multisite behavior documented (per-site toggle)
