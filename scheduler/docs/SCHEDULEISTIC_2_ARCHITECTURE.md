# Scheduleistic 2.0 — Architecture

How the application is put together after the 2.0 pass. Read
`SCHEDULEISTIC_2_AUDIT.md` first for what it looked like before, and
`SCHEDULEISTIC_2_RELEASE_NOTES.md` for exactly what shipped.

---

## 1. Stack

Unchanged from 1.x, deliberately. Laravel 13 · Jetstream · Cashier · Inertia 2 ·
Vue 3 (Composition API) · Tailwind · Redis queues · Caddy on-demand TLS.

---

## 2. Tenancy

```
Team  (an Organization / "Account" in the UI)
├── plan + entitlements   ← what the account may do
├── branding + custom domain
├── Cashier subscription
└── Workspace  (a "Brand" or "Client")
    ├── Channel            → a "Social profile"
    ├── Campaign / ContentPillar / Tag
    ├── Idea
    ├── MediaFolder / MediaAsset
    ├── TimeSlot / Feed
    └── Post
        ├── PostVersion    per-network caption + options
        ├── PostTarget     → a "Destination", one publish attempt per channel
        ├── Approval / Comment
        └── tags, campaign, pillar, assignee
```

**The tenancy boundary is one method.** `User::visibleWorkspaceIds()` returns
every workspace the user may act on: those owned by their current organization,
plus any they are individually assigned to (the client portal). Everything —
the planner, the media library, every 2.0 form request, every policy — resolves
it through that single method. Before 2.0 the same logic was reimplemented per
controller, which is exactly how a new controller ends up with no guard at all.

**IDs from the client are never trusted.** `ScopesToWorkspace` (form requests)
validates `workspace_id` against `Rule::in($user->visibleWorkspaceIds())`, and
every related id — campaign, pillar, tag, asset, assignee — must belong to that
same workspace. A request naming another tenant's campaign fails validation; it
does not silently return nothing, because an empty result still confirms the id
exists.

---

## 3. Entitlements

Three layers, resolved by `PlanService`:

1. **Plan** — `config/plans.php` declares limits and a full capability map for
   each of `free`, `solo`, `pro`, `agency`, `scale`, `enterprise`.
2. **Overrides** — `teams.entitlements` (JSON). Merged over the plan, and
   **only ever raises a limit or grants a capability**. This is how a legacy
   customer keeps something their new tier would not include, and how an
   Enterprise account gets bespoke limits without inventing a plan key.
3. Nothing else. No code compares plan names.

```php
$plans->can($team, 'media_library');     // capability
$plans->limit($team, 'workspaces');      // limit (-1 = unlimited)
$plans->upgradePlanFor('white_label');   // "Agency"
```

**Enforcement is server-side.** Capability-gated routes carry
`middleware('capability:media_library')`, which answers **402 Payment Required**
(not 403) with the name of the plan that unlocks the feature. Hiding a
navigation item is presentation, not access control — both exist.

**The frontend never decides.** `HandleInertiaRequests` shares the resolved
`capabilities` map plus `upgradeFor`, and `useCapabilities()` is the only way
Vue asks. A locked navigation item renders with the plan that unlocks it rather
than being a silently greyed-out box.

### Adding a capability

1. Add the key (defaulting to `false`) to the `$capabilities` array in
   `config/plans.php`.
2. Switch it on in the plans that grant it.
3. Gate the route with `capability:<key>`.
4. Reference it in `navigation.js` if it has a nav entry.

`PlanCapabilityTest` asserts every plan declares every key, so a capability
added to one plan and forgotten in another fails the suite rather than
defaulting to "on".

---

## 4. Terminology

Model and table names stay engineering-shaped. `config/terminology.php` maps
each concept to its customer-facing label; `App\Support\Terminology` reads it
server-side and it is shared to Vue as `terms`.

| Model | UI label |
| --- | --- |
| `Team` | Account |
| `Workspace` | Brand *or* Client (per account) |
| `Channel` | Social profile |
| `PostTarget` | Destination |
| provider driver | Network integration |

Agencies say "client", in-house teams say "brand" — the organization picks via
`branding.workspace_mode` and the whole UI follows.

---

## 5. Publishing engine

Unchanged in shape, extended at the edges.

```
Schedule (every minute)
  └── posts:dispatch-due
        └── PublishPostJob   (one per PostTarget, queued)
              ├── channel health check    ← 2.0
              ├── MediaValidator re-check ← 2.0
              ├── per-channel rate limit
              ├── ProviderManager → SocialProvider driver
              ├── ChannelHealthService    ← 2.0
              └── WebhookDispatcher       ← 2.0
```

The two new pre-flight checks matter:

- **Health.** A profile whose token has expired fails immediately with a
  readable reason instead of burning three retries on a guaranteed 401.
- **Re-validation.** A post can be edited after it was scheduled, and a
  network's rules can change under it. The composer's validation is advice; the
  job's is authoritative.

`SOCIAL_FAKE=true` still swaps every driver for `FakeProvider`, so the whole
chain — schedule → publish → metrics → report — is demonstrable without a
single OAuth app.

---

## 6. Provider capabilities

`config/provider_capabilities.php` is the single source of truth for what each
network supports: character limit, first comment, alt text, threads, board,
privacy, per-kind media rules, and a `notes` map explaining *why* an option is
unavailable.

`ProviderCapabilityService` reads it (cached); `MediaValidator` enforces it.
Nothing in Vue hard-codes a provider rule — the composer asks for the
definitions and renders what it is told. Adding a network is a config block
plus a driver class.

Errors block publishing; warnings do not and carry an actionable suggestion
("Crop to 1080×566 to reach 1.91:1").

---

## 7. Media pipeline

```
POST /media
  ├── StoreMediaAssetRequest   content-type allow-list + plan storage quota
  ├── MediaLibrary::storeUpload  checksum → dedupe → write original
  └── ProcessMediaAssetJob (queued)
        ├── probe dimensions
        ├── antivirus hook (optional, config-driven)
        └── generate thumb / preview / web variants
```

- The original at `path` is **never modified**. Every derivative is a new
  object in the same per-asset directory, so deleting an asset is one prefix
  removal.
- `(workspace_id, checksum)` is unique, which makes duplicate detection and
  idempotent re-upload the same mechanism.
- Storage goes through Laravel's filesystem abstraction — `MEDIA_DISK=s3` in
  production, `local` in development, with no code change.
- SVG is absent from the allow-list on purpose: it is an executable document,
  and there is no safe way to serve an untrusted one same-origin with the
  dashboard.

---

## 8. The planner

`PlannerController` + `PlannerQuery`.

The audit's largest performance finding was that the old calendar shipped up to
200 whole post records — comments included — on every visit, regardless of what
was on screen. 2.0 fixes that structurally:

- The **view determines the date window** server-side. Month loads six weeks;
  week loads seven days; agenda loads fourteen days. Switching view is one
  request that returns proportionally less data.
- `PlannerQuery::card()` returns a **narrow projection** — time, status,
  networks, brand colour, campaign, thumbnail, errors. The drawer fetches full
  detail on demand.
- Drafts (no `scheduled_at`) are always included regardless of window,
  otherwise they would be invisible on every view.
- Filters live in the URL. A filtered calendar is a shareable link, a bookmark
  that survives reload, and a back button that behaves.

Writes re-authorize per post inside the transaction. `BulkPostActionRequest`
proves every id is in the caller's tenancy; the controller then proves the
caller's *role* permits the specific verb on each one. A bulk action containing
one foreign id fails wholesale — half-applied bulk edits are worse than none.

---

## 9. Authorization

`WorkspaceScopedPolicy` is the base for every workspace-scoped model. Two
questions, always in this order:

1. **Tenancy** — is this record in a workspace the user can see? Absolute; no
   permission overrides it.
2. **Permission** — does their Jetstream organization role allow the verb?

Subclasses name the permission for each verb and never re-implement the tenancy
check. `PostPolicy` adds `approve` (separate from `update`, so an Approver or
Client can sign off on content they cannot edit) and `reschedule` (refuses a
post that is publishing or already published, so a drag cannot move a post out
from under the queue).

---

## 10. Operational surfaces

**Channel health.** `ChannelHealthService` is the only writer of
`channel_health_events`. Events are append-only: re-observing an open problem
refreshes it rather than stacking duplicates, and recovery stamps `resolved_at`
instead of deleting the row. `channels:check-health` runs hourly and notifies
**on the transition only**, which is what stops it becoming a daily nag.

**Webhooks.** `WebhookDispatcher` fans an event out to subscribed endpoints —
one delivery row and one job each, so a slow receiver cannot delay the others.
The row is created *before* the job is queued, so an attempt is on record even
if the worker dies. `DeliverWebhookJob` signs with HMAC-SHA256 over
`{timestamp}.{body}` (the timestamp is inside the signed string, so a captured
payload cannot be replayed), retries with exponential backoff, gives up
immediately on a non-retryable 4xx, disables an endpoint after 10 consecutive
failures, and re-checks the destination against `SsrfGuard` on **every**
attempt — DNS can be re-pointed at a private address after the endpoint is
saved, and the worker sits inside the network perimeter.

**Audit log.** `AuditLog::record()` is the only writer; nothing updates or
deletes a row once written.

---

## 11. Frontend

```
resources/js/
├── icons.js              the icon set (24×24 stroked grid)
├── navigation.js         the IA — one tree, three renderers
├── networks.js           per-network presentation metadata
├── composables/
│   ├── useCapabilities   entitlements + terminology
│   ├── useSidebar        persisted compact/expanded preference
│   ├── useBrand / useTheme
├── Components/UI/        SIcon, FilterBar, EmptyState, Skeleton, …
├── Layouts/Partials/     SidebarNav, TopBar, BrandSwitcher, MobileNav
└── Pages/                Planner, Media, Campaigns, Ideas, Notifications, …
```

- **One icon system.** `SIcon` enforces an accessible name on icon-only
  controls and marks decorative icons hidden. Emoji and typographic glyphs
  (`↩ ☼ ▾ ✕ ◌`) are gone as functional icons.
- **One navigation tree.** `NAV_GROUPS` drives the desktop rail, the mobile
  drawer and the bottom bar, so they cannot disagree about what exists or what
  is active. Items are filtered by capability and by whether their route
  actually exists in the build.
- **One filter component.** `FilterBar` writes to the URL, supports
  multi-select facets, debounced search, clear-all with an active count, and
  locally saved views.

---

## 12. Where things deliberately do not live

- **WordPress logic** does not belong in the publishing engine. It is a driver
  (`WordPressProvider`) plus, in future, a modular integration — never a
  special case inside `PublishPostJob`.
- **Provider rules** do not belong in Vue. They come from config.
- **Plan checks** do not belong anywhere except `PlanService`.
- **Tenancy** does not belong in controllers. It belongs in
  `User::visibleWorkspaceIds()` and the policies that call it.
