# Scheduleistic 2.0 — Routes, Endpoints and Outbound Webhooks

Every route added or changed in 2.0, plus the outbound webhook contract that
receivers implement.

All application routes are session-authenticated (`auth:sanctum` + Jetstream
session) and sit behind `verified`. Feature routes additionally require
`org.active`, so a suspended organization keeps billing access but loses the
publishing tooling.

Capability-gated routes answer **402 Payment Required** with the name of the
plan that unlocks them — distinct from **403 Forbidden**, which means "you are
not allowed", so a client can tell an upsell from a permission error.

---

## 1. Planner

| Method | Path | Name | Gate |
| --- | --- | --- | --- |
| GET | `/planner` | `planner.index` | — |
| GET | `/planner/posts/{post}` | `planner.posts.show` | policy `view` |
| PATCH | `/planner/posts/{post}/schedule` | `planner.posts.reschedule` | policy `reschedule` |
| POST | `/planner/bulk` | `planner.bulk` | per-post policy |

### `GET /planner`

Query parameters (all validated; unknown ids are rejected, not ignored):

| Parameter | Type | Notes |
| --- | --- | --- |
| `view` | enum | `month` (default), `week`, `agenda`, `list`, `feed`, `grid`, `campaign` |
| `from`, `to` | date | `from` anchors the window; bounded views derive their own span |
| `workspace_ids[]` | int[] | Intersected with the caller's visible set |
| `providers[]` | string[] | |
| `statuses[]` | enum[] | `draft`, `pending_approval`, `scheduled`, `publishing`, `published`, `partially_failed`, `failed` |
| `campaign_ids[]`, `pillar_ids[]`, `tag_ids[]` | int[] | Must exist within the caller's workspaces |
| `assignee_ids[]` | int[] | |
| `search` | string | Caption + title; LIKE wildcards are escaped |

Renders `Planner/Index` with `posts` (card projection), `view`, `window`,
`filters` (echoed for URL round-tripping) and `options` (facet sources).

The date window is derived from `view`, so a week view genuinely transfers less
data than a month view. Drafts with no `scheduled_at` are always included.

### `GET /planner/posts/{post}` → JSON

Full post detail for the drawer, plus a `can` object (`update`, `delete`,
`approve`, `reschedule`) so the drawer renders the right actions rather than
guessing from a role.

### `PATCH /planner/posts/{post}/schedule` → JSON

```json
{ "scheduled_at": "2026-08-04T09:00:00Z", "timezone": "Europe/London" }
```

`scheduled_at: null` returns a scheduled post to draft. Scheduling a draft
promotes it. Any other status is left alone. Refused (403) for a post that is
`publishing` or `published`.

### `POST /planner/bulk`

```json
{ "action": "reschedule", "post_ids": [1,2,3], "shift_minutes": 1440 }
```

| Action | Additional fields |
| --- | --- |
| `reschedule` | `scheduled_at` (required) or `shift_minutes` for a relative move |
| `delete` | — |
| `submit` | — |
| `approve` | `decision`: `approved` \| `changes_requested`, optional `note` |
| `assign` | `assignee_id` |
| `tag` | `tag_ids[]` |
| `campaign` | `campaign_id` |

Maximum 200 ids. If **any** id is outside the caller's tenancy the whole
request fails validation — bulk actions never apply partially. Recorded in
`audit_logs` as `planner.bulk_action`.

---

## 2. Campaigns, pillars and tags

| Method | Path | Name | Capability |
| --- | --- | --- | --- |
| GET | `/campaigns` | `campaigns.index` | `campaigns` |
| POST | `/campaigns` | `campaigns.store` | `campaigns` |
| PUT | `/campaigns/{campaign}` | `campaigns.update` | `campaigns` |
| DELETE | `/campaigns/{campaign}` | `campaigns.destroy` | `campaigns` |
| POST | `/pillars` | `pillars.store` | — |
| PUT | `/pillars/{pillar}` | `pillars.update` | — |
| DELETE | `/pillars/{pillar}` | `pillars.destroy` | — |
| POST | `/tags` | `tags.store` | — |
| DELETE | `/tags/{tag}` | `tags.destroy` | — |

Pillars and tags are ungated on purpose: they are how content gets organised at
all, and gating them would make the planner useless on the lower tiers.

Deleting a campaign detaches its posts and ideas rather than cascading —
published content outlives the campaign that produced it.

---

## 3. Ideas

| Method | Path | Name | Capability |
| --- | --- | --- | --- |
| GET | `/ideas` | `ideas.index` | `ideas` |
| POST | `/ideas` | `ideas.store` | `ideas` |
| PUT | `/ideas/{idea}` | `ideas.update` | `ideas` |
| DELETE | `/ideas/{idea}` | `ideas.destroy` | `ideas` |
| POST | `/ideas/{idea}/convert` | `ideas.convert` | `ideas` |

`convert` takes `count` (1–10) and produces that many drafts, carrying the
idea's campaign, pillar and assignee across. The idea is **not** consumed — it
moves to `drafting` and keeps a link to the posts it produced.

---

## 4. Media library

| Method | Path | Name | Capability |
| --- | --- | --- | --- |
| GET | `/media` | `media.index` | `media_library` |
| POST | `/media` | `media.store` | `media_library` |
| GET | `/media/{asset}/status` | `media.status` | `media_library` |
| PUT | `/media/{asset}` | `media.update` | `media_library` |
| DELETE | `/media/{asset}` | `media.destroy` | `media_library` |
| POST | `/media/folders` | `media.folders.store` | `media_library` |
| DELETE | `/media/folders/{folder}` | `media.folders.destroy` | `media_library` |

`POST /media` is `multipart/form-data`: `workspace_id`, `file`, optional
`media_folder_id`, `alt_text`, `notes`, `tags[]`.

Accepted content types (validated by content, not extension):
`image/jpeg`, `image/png`, `image/webp`, `image/gif`, `video/mp4`,
`video/quicktime`, `video/webm`. **SVG is rejected.**

The response returns as soon as the original is written; probing, scanning and
derivative generation happen in `ProcessMediaAssetJob`. Poll
`GET /media/{asset}/status` for `pending → processing → ready | failed`.

Re-uploading identical bytes into the same workspace returns the existing
asset — the endpoint is idempotent under a retried request.

---

## 5. Channel health

| Method | Path | Name |
| --- | --- | --- |
| GET | `/social-profiles/health` | `channels.health` |
| POST | `/social-profiles/health/refresh` | `channels.health.refresh` |

Also runs hourly as `php artisan channels:check-health`, which notifies the
account owner **on a state transition only**.

---

## 6. Notifications

| Method | Path | Name |
| --- | --- | --- |
| GET | `/notifications` | `notifications.index` |
| POST | `/notifications/{notification}/read` | `notifications.read` |
| POST | `/notifications/read-all` | `notifications.read-all` |
| PUT | `/notifications/preferences` | `notifications.preferences` |

Deliberately outside `org.active`: a suspended organization must still be able
to read why it was suspended.

---

## 7. Outbound webhooks

| Method | Path | Name | Capability |
| --- | --- | --- | --- |
| GET | `/webhooks` | `webhooks.index` | `webhooks` |
| POST | `/webhooks` | `webhooks.store` | `webhooks` |
| PUT | `/webhooks/{endpoint}` | `webhooks.update` | `webhooks` |
| POST | `/webhooks/{endpoint}/rotate` | `webhooks.rotate` | `webhooks` |
| DELETE | `/webhooks/{endpoint}` | `webhooks.destroy` | `webhooks` |
| POST | `/webhook-deliveries/{delivery}/replay` | `webhooks.replay` | `webhooks` |

Management is restricted to the organization owner and administrators.

### Events

`post.created` · `post.scheduled` · `approval.requested` · `post.approved` ·
`publish.succeeded` · `publish.failed` · `comment.received` ·
`report.generated`

> **Implemented and firing today:** `publish.succeeded` and `publish.failed`.
> The rest are accepted as subscriptions and defined in the contract, but are
> not yet dispatched — see `SCHEDULEISTIC_2_RELEASE_NOTES.md`.

### Request

`POST` to your URL (HTTPS only) with `Content-Type: application/json`:

| Header | Meaning |
| --- | --- |
| `X-Scheduleistic-Event` | The event name |
| `X-Scheduleistic-Delivery` | UUID — the **idempotency key** |
| `X-Scheduleistic-Timestamp` | Unix seconds, part of the signed string |
| `X-Scheduleistic-Signature` | `sha256=<hex>` |
| `User-Agent` | `Scheduleistic-Webhooks/2.0` |

```json
{
  "event": "publish.succeeded",
  "created_at": "2026-07-30T09:00:00+00:00",
  "account_id": 12,
  "data": {
    "post_id": 8412,
    "target_id": 19233,
    "workspace_id": 44,
    "provider": "linkedin",
    "status": "published",
    "error": null
  }
}
```

### Verifying

```
signature == "sha256=" + hmac_sha256(secret, timestamp + "." + raw_body)
```

Compare in constant time, and reject a timestamp older than ~5 minutes. The
timestamp is inside the signed string specifically so a captured payload cannot
be replayed later against the same signature.

```php
$expected = 'sha256='.hash_hmac(
    'sha256',
    $request->header('X-Scheduleistic-Timestamp').'.'.$request->getContent(),
    $secret,
);

abort_unless(hash_equals($expected, $request->header('X-Scheduleistic-Signature')), 400);
```

### Retries and failure handling

- 5 attempts, backoff **10s → 1m → 5m → 30m**.
- A 4xx other than 408/429 is **not** retried — it will not start working.
- 10 consecutive failures disables the endpoint with a stated reason;
  re-enabling clears the streak.
- Any success resets the streak.
- The destination is re-checked against `SsrfGuard` on **every** attempt.
- **De-duplicate on `X-Scheduleistic-Delivery`.** A manual replay deliberately
  carries a *new* id, because a replay is a new intent, not a duplicate.

---

## 8. Shared Inertia props (2.0 additions)

| Prop | Purpose |
| --- | --- |
| `capabilities` | Resolved capability map — the only thing Vue may gate on |
| `upgradeFor` | Capability → cheapest plan name that grants it |
| `terms` | Customer-facing labels for internal model names |
| `unreadNotifications` | Badge count for the rail and bottom bar |

`planName`, `planUsage`, `branding`, `isPlatformAdmin`, `isImpersonating` and
`status` are unchanged from 1.x.
