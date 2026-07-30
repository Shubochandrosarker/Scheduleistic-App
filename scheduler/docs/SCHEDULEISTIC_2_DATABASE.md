# Scheduleistic 2.0 — Database

Every 2.0 migration, the reasoning behind each index, and the compatibility
guarantees. No existing table was renamed or dropped.

Migrations live in `scheduler/app/database/migrations/` and all carry a working
`down()`.

---

## 1. Migrations added

| File | Adds |
| --- | --- |
| `2026_07_30_000001_add_entitlements_to_teams_table` | `teams.entitlements`, `teams.timezone`, `teams.onboarded_at` |
| `2026_07_30_000002_create_content_planning_tables` | `campaigns`, `campaign_user`, `content_pillars`, `tags`, `post_tag`, `ideas`, `idea_tag` |
| `2026_07_30_000003_create_media_library_tables` | `media_folders`, `media_assets`, `media_asset_tag`, `media_asset_post` |
| `2026_07_30_000004_add_planner_columns_to_posts_table` | planner columns + composite indexes on `posts` |
| `2026_07_30_000005_create_operations_tables` | `notification_preferences`, `channel_health_events`, channel health columns, `webhook_endpoints`, `webhook_deliveries`, `audit_logs` |

---

## 2. Schema

### teams (altered)

| Column | Type | Notes |
| --- | --- | --- |
| `entitlements` | json null | Per-organization overrides merged by `PlanService`. **Only ever raises a limit or grants a capability.** Shape: `{"limits":{"workspaces":99},"capabilities":{"white_label":true}}` |
| `timezone` | string, default `UTC` | Account-level default for new brands |
| `onboarded_at` | timestamp null | Drives the onboarding checklist |

### campaigns

Workspace-scoped, not organization-scoped: an agency running "Black Friday" for
two clients must not have them collide, and every authorization check already
resolves through a workspace.

Key columns: `workspace_id`, `owner_id`, `name`, `slug`, `goal`, `status`,
`starts_on`, `ends_on`, `budget_cents`, `currency`, `target_url`, `utm` (json),
`kpi_targets` (json), `color`. Soft-deleted.

| Index | Why |
| --- | --- |
| `(workspace_id, status)` | The campaigns list filters by status within a brand |
| `(workspace_id, starts_on)` | Ordering the list, and the campaign calendar view |
| unique `(workspace_id, slug)` | Slugs are per-brand, not global |

`campaign_user` carries team members with a `role`, unique on
`(campaign_id, user_id)`.

### content_pillars

`workspace_id`, `name`, `slug`, `description`, `color`, `target_share`,
`position`. Unique `(workspace_id, slug)`; index `(workspace_id, position)` for
ordered rendering.

`target_share` is the share of the calendar a pillar *should* occupy, so the
planner can report "40% education against a 25% target".

Every new workspace is seeded with six defaults (`ContentPillar::DEFAULTS`) —
an unseeded brand would show an empty categorisation axis on day one.

### tags + post_tag + idea_tag + media_asset_tag

`tags` is workspace-scoped with unique `(workspace_id, slug)`, so the same tag
name may exist in two brands without colliding. `Tag::resolve()` is
find-or-create by slug, which is why submitting an existing tag name is a no-op
rather than a duplicate.

Each pivot is unique on its pair and indexes the far side for reverse lookups.

### ideas

`workspace_id`, `campaign_id`, `content_pillar_id`, `author_id`, `assignee_id`,
`title`, `notes`, `stage`, `priority`, `source_url`, `attachments` (json),
`due_on`, `position`. Soft-deleted.

| Index | Why |
| --- | --- |
| `(workspace_id, stage, position)` | The Kanban board's exact query — group by stage, order by position |
| `(workspace_id, assignee_id)` | "Assigned to me" |

Stages: `inbox → researching → drafting → ready → scheduled → published →
archived`.

### media_folders

`workspace_id`, `parent_id` (self-referencing, cascade), `name`. Index
`(workspace_id, parent_id)` for tree rendering.

### media_assets

| Column | Notes |
| --- | --- |
| `path` | The **original**, never overwritten |
| `variants` | json map of derivatives: `{"thumb":{"path":…,"width":…,"height":…}}` |
| `checksum` | SHA-256 of the uploaded bytes |
| `processing_status` | `pending` → `processing` → `ready` \| `failed` |
| `alt_text` | Published as accessibility metadata |
| `notes` | Internal, never published |
| `usage_count`, `last_used_at`, `is_favorite`, `archived_at` | Library UX |

| Index | Why |
| --- | --- |
| unique `(workspace_id, checksum)` | Duplicate detection **and** idempotent re-upload — the same mechanism |
| `(workspace_id, kind)` | "Images only" |
| `(workspace_id, media_folder_id)` | Folder browsing |
| `(workspace_id, archived_at)` | The default view excludes archived |
| `(workspace_id, last_used_at)` | "Recently used" |

`media_asset_post` links assets to posts with a nullable `provider` (null = the
base post, otherwise a per-network override) and a `position`. **Explicit, not
polymorphic** — the brief asks for that, and it is right here: a polymorphic
`mediable_type/id` pair cannot be foreign-keyed, indexes worse, and forces
authorization to branch on a string.

### posts (altered)

Added `campaign_id`, `content_pillar_id`, `assignee_id`, `idea_id`, `title`.
All foreign keys `nullOnDelete`, so deleting a campaign never destroys
published content.

| Index | Why |
| --- | --- |
| `(workspace_id, scheduled_at)` | The planner's default query, and the dispatcher's due-post scan |
| `(workspace_id, status)` | Status filters and dashboard counts |
| `(workspace_id, campaign_id)` | Campaign filtering and campaign analytics |
| `(workspace_id, assignee_id)` | "Assigned to me" |

`workspace_id` leads every one of them because it is the first predicate in
every query the application issues — tenancy is not an afterthought in the
index design either.

### notification_preferences

`user_id`, `event`, `in_app`, `email`, `push`, `digest`. Unique
`(user_id, event)`.

The event catalogue is `NotificationPreference::EVENTS` — a constant, validated
against, so a typo in a dispatch call fails loudly instead of quietly
notifying nobody.

### channel_health_events

`channel_id`, `state`, `severity`, `message`, `context` (json), `detected_at`,
`resolved_at`. Indexes `(channel_id, detected_at)` and
`(channel_id, resolved_at)`.

**Append-only by convention**: nothing in the codebase deletes a row, and
recovery stamps `resolved_at` rather than removing the record, so the timeline
stays intact as evidence.

### channels (altered)

`health_state` (default `connected`), `last_published_at`,
`last_metrics_sync_at`, `last_health_check_at`. The model mirrors the column
default via `$attributes` so a freshly created channel reports its health
without a round trip.

### webhook_endpoints

`team_id`, `workspace_id` (null = whole account), `name`, `url`, `secret`
(**encrypted cast**, `$hidden`), `secret_rotated_at`, `events` (json),
`is_active`, `consecutive_failures`, `disabled_at`, `disabled_reason`.

### webhook_deliveries

`webhook_endpoint_id`, `event_id` (UUID — the idempotency key sent to the
receiver), `event`, `payload`, `status`, `response_status`, `response_body`,
`attempts`, `duration_ms`, `delivered_at`. Indexed on
`(webhook_endpoint_id, status)` and `event_id`.

### audit_logs

`team_id`, `user_id`, `action`, `subject_type`, `subject_id`, `context`, `ip`,
`created_at` (no `updated_at` — `AuditLog::UPDATED_AT = null`). Indexed on
`(team_id, created_at)`, `(subject_type, subject_id)`, `action`.

---

## 3. Conventions

- **Foreign keys everywhere**, with an explicit delete behaviour:
  `cascadeOnDelete` when the child is meaningless without the parent (a tag
  link), `nullOnDelete` when the child outlives it (a post's campaign).
- **Composite indexes lead with `workspace_id`** — see above.
- **Soft deletes** on `campaigns`, `ideas`, `media_assets`: all three are
  recoverable user content. Not on pivots or log tables.
- **Immutable records**: `audit_logs` and `channel_health_events`.
- **No polymorphic relationships** where an explicit one works.
- **Unique provider identifiers** where they exist: `media_assets.checksum` per
  workspace, `webhook_deliveries.event_id` globally.

---

## 4. Backward compatibility

- No table renamed, no column dropped, no column made non-nullable.
- Every added column is nullable or has a default, so existing rows are valid
  the moment the migration finishes.
- `plans.free|pro|agency|scale` keep their keys. `solo` and `enterprise` are
  new; nothing is stranded.
- Every 2.0 plan limit is **equal to or higher** than its 1.x counterpart, so
  the plan change alone cannot reduce anyone's entitlement:

  | Plan | workspaces | channels | members | monthly posts |
  | --- | --- | --- | --- | --- |
  | free | 1 → 1 | 3 → 3 | 1 → 1 | 30 → 30 |
  | pro | 3 → **5** | 10 → **25** | 3 → **5** | 300 → **2,000** |
  | agency | 15 → **20** | 50 → **75** | 10 → **15** | 2,000 → **10,000** |
  | scale | 50 → 50 | 150 → **200** | 50 → 50 | 10,000 → **50,000** |

- The legacy `features` map (`client_approval`, `white_label`, `analytics`,
  `ai_captions`, `ai_agents`) still resolves through
  `PlanService::feature()`, now derived from capabilities so there is one
  source of truth. Pre-2.0 call sites keep working.

### Grandfathering a specific account

```php
$team->update(['entitlements' => [
    'limits'       => ['workspaces' => 40],
    'capabilities' => ['white_label' => true, 'custom_domain' => true],
]]);
```

Overrides only raise and only grant. A `false` in `capabilities` is ignored and
a lower number in `limits` is ignored — both are asserted in
`PlanCapabilityTest`.

---

## 5. Running the migrations

```bash
cd scheduler/app
php artisan migrate            # production
php artisan migrate:fresh --seed   # local reset
```

All 2.0 migrations are reversible: `php artisan migrate:rollback --step=5`
returns the schema to its 1.x state. Rolling back **does** drop the 2.0 tables
and their data — see `SCHEDULEISTIC_2_DEPLOYMENT.md` for the backup step.
