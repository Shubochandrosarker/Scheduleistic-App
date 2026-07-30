# Scheduleistic 2.0 — Administrator Guide

For platform operators running Scheduleistic as a SaaS or self-hosted install.

---

## 1. Plans and entitlements

Plans live in `scheduler/app/config/plans.php`. Each declares `limits` and a
full `capabilities` map. There is no database table for plans — changing the
file changes entitlements at the next request, with no data migration.

### Adding a capability

1. Add the key, defaulting to `false`, in the `$capabilities` array.
2. Switch it on in the plans that grant it.
3. Gate the route: `->middleware('capability:your_key')`.
4. Add it to `resources/js/navigation.js` if it has a nav entry.

`PlanCapabilityTest` asserts every plan declares every key, so a capability
added to one plan and forgotten in another fails the suite rather than silently
defaulting to enabled.

### Grandfathering an account

`teams.entitlements` overrides the plan for one organization:

```php
$team->update(['entitlements' => [
    'limits'       => ['workspaces' => 40, 'storage_mb' => 100000],
    'capabilities' => ['white_label' => true, 'custom_domain' => true],
]]);
```

**Overrides only raise and only grant.** A lower limit is ignored; a `false`
capability is ignored. That is deliberate — it means a plan change can never
silently strip an entitlement a customer already had, and it is asserted in the
test suite.

This is also how Enterprise accounts get bespoke limits without inventing a
plan key.

### Stripe

Map each sellable plan to a price id via `STRIPE_PRICE_*`. `free` and
`enterprise` have no self-serve checkout. `Team::syncPlanFromSubscription()`
reconciles the stored plan with the live subscription; an unknown price leaves
the entitlement unchanged rather than downgrading someone because of a config
mismatch.

---

## 2. Terminology

`config/terminology.php` controls customer-facing labels. Changing a value
changes it everywhere — server-side validation messages, notification copy and
the whole UI. Changing a *key* does not, because keys mirror model names.

An organization chooses "Brand" or "Client" for its workspaces via
`branding.workspace_mode`.

---

## 3. Network integrations

`config/provider_capabilities.php` defines what each network supports and what
media it accepts. It is read by the composer, the validator and the publish job.

Adding a network:

1. Implement `App\Social\Contracts\SocialProvider`.
2. Register it in `ProviderManager::$drivers`.
3. Add a capability block — including `notes` explaining anything it cannot do,
   so the UI can say why rather than showing an unexplained disabled control.
4. Add presentation metadata to `resources/js/networks.js`.
5. Add it to `config/scheduleistic.php` `providers` to expose it in connect UI.

Nothing in the composer, planner or validator needs to change.

The definitions are cached. After changing them in production:

```bash
php artisan cache:forget provider_capabilities.v2
```

---

## 4. Media operations

| Setting | Purpose |
| --- | --- |
| `MEDIA_DISK` | `local` or `s3` (any S3-compatible endpoint) |
| `MEDIA_MAX_UPLOAD_MB` | Hard per-upload ceiling, independent of plan quota |
| `MEDIA_AV_COMMAND` | Antivirus hook |

### Antivirus

Set a command that exits non-zero on infection:

```dotenv
MEDIA_AV_COMMAND="clamdscan --no-summary --stdout"
```

`ProcessMediaAssetJob` runs it against the uploaded file before marking the
asset ready. A non-zero exit deletes the stored object, marks the asset failed
with a reason, and logs it. **Unset means uploads are not scanned.**

### Processing load

Derivative generation uses GD and is CPU-bound. On a busy install give it its
own queue and workers. Without `ext-gd`, uploads still succeed and are marked
ready — they render from the original with no thumbnails.

### Storage accounting

Quota is the sum of `media_assets.size` across every workspace in the account,
checked before a byte is written. Recalculate nothing — it is a live sum.

---

## 5. Channel health

`channels:check-health` runs hourly. It re-evaluates every profile's token
expiry and notifies the account owner **only when a profile changes into a bad
state**, which is what keeps it from becoming a daily nag.

States: `connected`, `token_expiring` (≤7 days), `token_expired`,
`permission_missing`, `provider_unavailable`, `rate_limited`, `publish_failed`.

`token_expired` and `permission_missing` block publishing — the publish job
refuses up front rather than burning retries.

`channel_health_events` is append-only. Recovery stamps `resolved_at`; nothing
deletes a row. Use it as the history when a tenant asks why a post failed three
weeks ago.

Run it on demand:

```bash
php artisan channels:check-health
```

---

## 6. Webhooks

Endpoints are per-account, optionally scoped to one brand. Secrets are
encrypted at rest and shown in plaintext exactly once. There is no recovery
path — a customer who loses a secret must rotate it.

An endpoint disables itself after 10 consecutive failures with a stated reason.
Re-enabling clears the streak.

Destinations are validated against `SsrfGuard` at creation **and on every
delivery attempt**, because DNS can be re-pointed after the endpoint is saved
and the worker sits inside your network perimeter.

To investigate a customer's delivery problem, `webhook_deliveries` holds the
response status, a truncated body, attempt count and duration for every attempt.

---

## 7. Audit log

`audit_logs` is append-only and records the acting user, the account, the
subject, contextual JSON and the IP.

Currently written for: campaign create/update/delete, idea conversion, bulk
planner actions, webhook create/update/delete/secret-rotation.

There is no admin UI for it yet — query it directly:

```sql
SELECT created_at, action, user_id, subject_type, subject_id, context
FROM audit_logs
WHERE team_id = ?
ORDER BY created_at DESC
LIMIT 200;
```

---

## 8. Impersonation

Unchanged from 1.x. A platform admin can impersonate an account owner; the
session shows a permanent amber banner and the action is audit-logged. The
impersonated session can always stop it.

Platform administrators see the control plane but **never** tenant credentials —
tokens and webhook secrets are encrypted and hidden from serialisation
regardless of who is looking.

---

## 9. Suspension

`org.active` middleware blocks a suspended organization from the publishing
tooling while keeping billing reachable so they can reactivate. The
notification centre is deliberately outside that gate — a suspended account
must be able to read why.

---

## 10. Queues and scheduling

| Job | Trigger | Notes |
| --- | --- | --- |
| `PublishPostJob` | `posts:dispatch-due`, every minute | One per destination; 3 tries, 10/30/60s backoff |
| `FetchMetricsJob` | `analytics:fetch`, hourly | |
| `ProcessMediaAssetJob` | On upload | 3 tries, 10/60/300s backoff; idempotent |
| `DeliverWebhookJob` | On event | 5 tries, 10s/1m/5m/30m backoff |

Everything is idempotent: a published target returns early, a delivered webhook
is never re-sent, and media processing recomputes from the untouched original.

---

## 11. Health checks for your own monitoring

```bash
php artisan about
php artisan migrate:status
php artisan queue:monitor redis:default --max=100
php artisan channels:check-health
```

Useful queries:

```sql
-- Publishing success rate, last 24h
SELECT status, COUNT(*) FROM post_targets
WHERE updated_at > NOW() - INTERVAL 1 DAY GROUP BY status;

-- Profiles that cannot publish
SELECT COUNT(*) FROM channels
WHERE health_state IN ('token_expired','permission_missing');

-- Webhook endpoints auto-disabled
SELECT id, team_id, url, disabled_reason FROM webhook_endpoints
WHERE disabled_at IS NOT NULL;

-- Media stuck processing
SELECT COUNT(*) FROM media_assets
WHERE processing_status IN ('pending','processing')
  AND created_at < NOW() - INTERVAL 1 HOUR;
```

A rising count in the last query means media workers are not keeping up (or are
not running).

---

## 12. Demo and development modes

- `SOCIAL_FAKE=true` — every driver resolves to `FakeProvider`. The whole chain
  works end to end with no OAuth apps, which is what makes a demo environment
  possible.
- `AI_FAKE=true` — deterministic AI responses with no paid API calls.

Both are preserved from 1.x and are covered by the test suite.
