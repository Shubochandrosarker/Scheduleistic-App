# Scheduleistic 2.0 — Release Notes

**Honest scope statement first.** The 2.0 brief specified 23 features, 10 UI
improvements and 8 implementation phases. This release delivers **phases 1–3
in full plus the operational groundwork from phases 4–7**. It does not deliver
everything in the brief, and this document says exactly which parts landed and
which did not, so nothing is planned around a feature that is not there.

- **Tests:** 169 → **287** (280 passing, 7 skipped, 0 failing)
- **Assertions:** 480 → **1,000**
- **Migrations added:** 5 (all reversible)
- **Existing functionality changed or removed:** none

---

## Shipped

### Capability-based entitlements

`config/plans.php` now declares six plans — `free`, `solo`, `pro`, `agency`,
`scale`, `enterprise` — each with an explicit capability map alongside its
limits. `PlanService` resolves entitlements from the plan plus per-organization
overrides stored in the new `teams.entitlements` column, and **overrides only
ever raise a limit or grant a capability**. A plan change cannot strip
something a customer already had.

Nothing compares plan names any more. Backend routes carry
`capability:<key>` middleware answering **402** with the plan that unlocks the
feature; Vue asks `useCapabilities()`. A locked navigation item says *"Campaigns
— Pro plan and above"* instead of being a silently greyed-out box.

Every 2.0 plan limit is equal to or higher than its 1.x counterpart, so nobody
loses anything on upgrade.

### Terminology layer

`config/terminology.php` maps internal model names to customer-facing labels —
Organization → **Account**, Workspace → **Brand** or **Client**, Channel →
**Social profile**, PostTarget → **Destination**. Agencies and in-house teams
pick which noun their workspaces use. No table was renamed.

### The planner

A real content calendar replacing the single flat list:

- **Month, week, agenda, list and Instagram-grid views.** The *server* derives
  the date window from the view, so a week view genuinely transfers less data.
- **Drag-and-drop rescheduling** — with the policy refusing any post that is
  publishing or already published, so a card cannot be dragged out from under
  the queue.
- **Bulk actions**: reschedule (absolute or a relative shift that preserves the
  gaps), delete, submit for approval, approve, assign, tag, move to campaign.
  A batch containing one foreign id fails wholesale.
- **A post detail drawer** that opens over the calendar instead of navigating
  away, with focus trapping, Escape-to-close and focus return.
- **Filters in the URL** — by brand, network, status, campaign, pillar, tag,
  assignee and caption search — so a filtered calendar is a shareable link.
  Locally saved views on top.
- **Errors on the card**, not hidden behind a click.

This also fixes the audit's largest performance finding: the old page shipped
up to 200 whole post records with every comment on every visit.

### Campaigns, content pillars, tags and ideas

Campaigns with goals, dates, budget, target URL, UTM defaults and KPI targets.
Six default content pillars seeded into every new brand. Workspace-scoped tags
that find-or-create rather than duplicate. An ideas pipeline with a Kanban
board and a list view, converting an idea into up to ten drafts while keeping
the idea and its link to what it produced.

### Media library

Tenant-isolated assets with folders, tags, favourites, archiving, search, alt
text and internal notes. Uploads are validated by **content type** against an
allow-list (SVG deliberately excluded), checked against the plan's storage
quota before a byte is written, and deduplicated by per-workspace checksum —
which makes the endpoint idempotent under a retried request.

Processing is queued: probe dimensions, run the optional antivirus hook,
generate thumb/preview/web derivatives. **The original is never modified.**
Storage runs through Laravel's filesystem abstraction, so local and
S3-compatible object storage are a config change.

### Provider capabilities and media validation

`config/provider_capabilities.php` is now the single source of truth for what
each of the 13 networks supports — caption limits, first comment, alt text,
threads, boards, privacy, per-kind media rules — plus a `notes` map explaining
*why* an option is unavailable.

`MediaValidator` enforces it in the composer **and again inside
`PublishPostJob`**, because a post can be edited after it was scheduled.
Errors block; warnings carry an actionable suggestion.

### Channel health

`channel_health_events` plus health columns on `channels`, an hourly
`channels:check-health` command, and a health page showing state, token expiry,
last publish, last sync and a reconnect action. The publish job refuses a
blocked channel up front rather than burning three retries on a guaranteed 401,
records failures against the channel, and clears prior problems on success.

Notifications fire **on the state transition only**, which is what stops this
becoming a daily nag.

### Signed outbound webhooks

Endpoints scoped to an account or a single brand, with HMAC-SHA256 payload
signing over `{timestamp}.{body}` (the timestamp is inside the signed string,
so a captured payload cannot be replayed), secret rotation, delivery history,
manual replay, exponential backoff, immediate give-up on non-retryable 4xx, and
automatic disable after 10 consecutive failures. Destinations are re-checked
against `SsrfGuard` on **every** attempt, not just at creation.

### Notification centre

An in-app inbox plus per-user, per-event delivery preferences across 14 event
types with in-app/email/digest control. Deliberately reachable by a suspended
organization, which must be able to read why it was suspended.

### Interface

- **One icon system.** 24×24 stroked SVGs behind `SIcon`, which enforces an
  accessible name on icon-only controls. Emoji and typographic glyphs
  (`↩ ☼ ▾ ✕ ◌`) are gone as functional icons.
- **Grouped, collapsible, plan-aware navigation** driven by one declarative
  tree shared by the desktop rail, mobile drawer and bottom bar, with a
  persisted compact mode and notification badges.
- **Global brand switcher** with search, favourites, recents, connected-profile
  counts and health.
- **Mobile bottom navigation** with a raised compose action and safe-area
  support.
- **Standard filter component** with multi-select facets, debounced search,
  clear-all with an active count, URL persistence and saved views.
- **Flatter surfaces** — gradients reserved for primary actions — higher
  dark-mode text contrast, a 12px metadata floor, visible `:focus-visible`
  rings, a skip link, 44px touch targets and full `prefers-reduced-motion`
  support.

### Security and correctness

Form Request classes (the codebase had none), policies for every
workspace-scoped model via a shared `WorkspaceScopedPolicy`, a single
definition of the tenancy boundary, an append-only audit log, and a
15-test isolation suite.

Two real bugs were found and fixed by the new tests:

1. **LIKE wildcards were not escaped portably.** Searching for `50%` matched
   every row on SQLite and Postgres. Fixed with an explicit `ESCAPE` clause.
2. **`Carbon::diffInDays` is signed.** A token six months from expiry read as
   `-181` and was flagged as expiring. Fixed by comparing instants.

---

## Not shipped

Declared plainly. These are from the brief and are **not** in this release:

| Feature | Status |
| --- | --- |
| Three-panel composer with native previews | Not built. The per-network data model (`PostVersion`) and validation exist and are tested; the composer UI is still 1.x single-column. |
| Social inbox, saved replies, reviews | Not built. |
| Approval workflow 2.0 (multi-level, parallel, guest links, version compare) | Not built. Single-stage approvals from 1.x are unchanged and working. |
| Advanced analytics workspace | Not built. 1.x organization-lifetime totals are unchanged. |
| Report builder, scheduled reports, PDF export | Not built. |
| Best-time-to-post recommendations | Not built. |
| AI campaign copilot, brand knowledge grounding, AI usage accounting | Not built. 1.x AI caption/rewrite/hashtag/quality tools are unchanged. |
| Link management, UTM presets, short links, click tracking | Partially: campaign UTM defaults exist and are tested. Short links and click tracking are not built. |
| Link-in-bio builder | Not built. |
| Integration hub, Zapier/Make/n8n/Slack/MCP | Not built. Outbound webhooks and the existing API tokens are the automation surface today. |
| WordPress/WooCommerce automation module | Not built. The `WordPressProvider` publishing driver is unchanged. |
| Competitor tracking | Not built. |
| Guided onboarding wizard | Not built. `teams.onboarded_at` exists for it. |
| PWA (manifest, service worker, offline drafts, push) | Not built. Mobile navigation and touch targets are; push is a stored preference only. |
| Platform admin operations centre | Not built. The 1.x super-admin organization list is unchanged. |
| Evergreen recycling library | Not built. 1.x recurring posts are unchanged. |
| Content templates, hashtag groups | Not built. |
| Frontend (Vitest) and end-to-end tests | No JS test runner or browser harness is configured in this repository. |

Four webhook events (`post.created`, `post.scheduled`, `approval.requested`,
`post.approved`, `comment.received`, `report.generated`) are defined in the
contract and accepted as subscriptions but are **not yet dispatched**. Only
`publish.succeeded` and `publish.failed` fire today.

---

## Upgrade notes

1. Run `php artisan migrate` (5 migrations; see the deployment doc for the
   index-creation caveat on large `posts` tables).
2. Set `MEDIA_DISK` if you want media on S3 rather than the local disk.
3. Add `STRIPE_PRICE_SOLO` / `STRIPE_PRICE_ENTERPRISE` if you sell those tiers.
4. `php artisan queue:restart` — there are two new job classes.
5. Nothing else. No data backfill, no config rewrite, no breaking route change.

One existing test assertion changed: `PlanLimitsTest` moved the Agency
workspace limit from 15 to 20 to match the new ladder. The limit went up.

---

## Known risks

- **Media derivatives depend on `ext-gd`.** Without it, uploads succeed and are
  marked ready but render from the original with no thumbnails.
- **Video validation is metadata-only.** No ffprobe, so duration and codec are
  checked only when the uploader supplies them.
- **The planner caps at 500 posts per window.** A very dense month on a large
  agency account will truncate. List virtualisation is not implemented.
- **Saved filter views and brand favourites live in `localStorage`**, so they
  do not follow a user between devices.
- **Antivirus is a hook, not a bundled scanner.** Unset means unscanned.
- 4 medium (`guzzle`) and 2 high (`axios`, `postcss`) dependency advisories
  remain outstanding; none introduced by this change.

---

## Recommended next release

In priority order, on the reasoning that composer quality gates content quality
and analytics gates renewals:

1. **The three-panel composer** — the data model, provider capabilities and
   validator are already in place, so this is largely UI work against
   infrastructure that exists and is tested.
2. **Approval workflow 2.0** — multi-stage, guest links, version comparison.
   Agencies buy the plan for this.
3. **The analytics workspace** — date ranges, comparisons, per-campaign and
   per-pillar breakdowns on a metrics-normalisation layer.
4. **Report builder** on top of that, queued, with branded PDF export.
5. **Social inbox**, starting with the networks whose APIs genuinely support it
   and saying so plainly for the ones that do not.
