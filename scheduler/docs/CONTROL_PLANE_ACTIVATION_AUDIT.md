# Control Plane, Platform Activation & Schedule History — Audit and Delivery Plan

**Audited against:** branch `claude/repo-audit-update-workflow-9bo55j`, commit at time of writing `ecf986f`.
**Instruction file audited:** `Scheduleistic_Admin_Control_Plane_Master_Prompt.md` (uploaded by the operator; a detailed
platform-admin/organization/subscription control-plane spec — referred to below as "the master prompt").
**Also in scope, from the operator's direct request (not covered by the master prompt):** a secure "schedule
history" feature with advanced capabilities, and activating Facebook plus the platform's other social networks
for real users.

This document is the Phase 1 deliverable the master prompt itself asks for: *"Repository audit and baseline
results."* Nothing in `app/`, `resources/js/`, `routes/`, `config/`, or `database/` has been changed to produce
it — this is a read-only audit plus a proposed plan. See §6 for the baseline test/build results and §7 for the
phased delivery plan, which needs one decision from the operator before Phase 2 can start (§7.0).

---

## 1. Top findings, if you read nothing else

1. **The master prompt is accurate.** Every file, method, and behavior it references was independently verified
   against this exact codebase (not a generic template) — see §2. Its gap list ("Audit notes for the
   implementer") matches what this audit independently found.
2. **A live false security claim is shipping today.** The impersonation banner tells every user *"Everything you
   do is audit-logged,"* and two docs (`docs/03-security.md`, `docs/SCHEDULEISTIC_2_SECURITY.md`) both state
   impersonation is audit-logged. It is not — `Admin/OrganizationController::impersonate()` only writes to the
   application log (`Log::warning`), never to the `audit_logs` table. Organization suspension isn't logged at
   all. This is worse than "a gap," it's an incorrect claim in front of users — see §3.
3. **Facebook, Instagram, Threads, and 10 other networks are already fully wired into the connect UI today** —
   nothing needs to be "turned on" there. The real blockers are (a) real OAuth app credentials per network, which
   is an external business process, and (b) a set of genuine code bugs and missing pieces that will make several
   networks fail or behave wrongly even once credentials exist — see §4.
4. **"Schedule history" doesn't exist as a named feature yet**, and the app's own admin guide already admits the
   closest thing to it (the audit log) has "no admin UI yet — query it directly [in SQL]." There are several
   different, legitimate things this phrase could mean; §5 lays out the concrete options and asks which one(s)
   you want.
5. **Everything above is additive.** Nothing proposed here requires rewriting a working feature, renaming a
   column, or changing an established route contract.

---

## 2. Master-prompt accuracy check

Every repository fact the master prompt asserts in its §1 ("Repository facts you must preserve") and its closing
"Audit notes for the implementer" was checked directly against the code. All of it held up:

| Master prompt claim | Verified against |
| --- | --- |
| Laravel 13, Jetstream/Fortify, Sanctum, Inertia 2, Vue 3, Tailwind, Vite, Cashier 16, Stripe, queues, Caddy | `composer.json`, `package.json`, `caddy/Caddyfile` |
| `Team` = organization, `Workspace` = brand/client, `Channel` = connected profile | `app/Models/Team.php`, `Workspace.php`, `Channel.php` |
| `User::visibleWorkspaceIds()` is the tenancy boundary | `app/Models/User.php:101-107` |
| `users.is_platform_admin` guarded, non-mass-assignable, `platform.admin` middleware | `User.php:30-34`, `app/Http/Middleware/EnsurePlatformAdmin.php` |
| Org roles owner/admin/member/approver/client | Jetstream permissions + `Membership`/workspace roles |
| `Team` is `Billable`; `teams.stripe_id`, `subscriptions.team_id` | `Team.php:23`, migrations `create_customer_columns`, `create_subscriptions_table` |
| Plans `free/solo/pro/agency/scale/enterprise` in `config/plans.php` | confirmed, all 6 present |
| `PlanService`, capability middleware, `teams.entitlements`, grant-only/raise-only | `PlanService.php`, `EnsureCapability.php`, `Team.php:34` (fillable), verified raise-only logic at `PlanService.php:96-109` |
| Basic admin page: list + suspend + impersonate only | `Admin/OrganizationController.php` — literally only those 3 actions exist |
| One nav source, `resources/js/navigation.js` | confirmed, single `NAV_GROUPS` export, admin section has exactly one item today |
| `latest()->limit(500)->get()` unbounded org query | `Admin/OrganizationController.php:25` — literal match |
| Impersonation logged to app log, not audit table; stop not audited | `OrganizationController.php:60-64` (`Log::warning`), `stopImpersonating()` has no audit call at all |
| `teams.entitlements` already grant-only/raise-only | Confirmed correct and already safe — nothing to fix here |
| `STRIPE_PRICE_SOLO`/`STRIPE_PRICE_ENTERPRISE` referenced but env docs incomplete | `config/plans.php` references both via `env()`; `.env.example:87-89` only documents `PRO`/`AGENCY`/`SCALE` |
| Billing controller sends legacy `features` field | `BillingController.php:32` sends `$p['features'] ?? []` — and plans.php has **no** `features` key anymore (only `capabilities`), so this is worse than "legacy": it's a **dead field that always renders empty** |
| No configured JS test runner / e2e harness | Confirmed — `package.json` has no test script; PHPUnit is the only automated suite |

**One place the master prompt is stale, not wrong:** it describes the admin surface as "the basic admin page" as
if that were the *starting point* for a fresh 2.0 build. In fact this repository already shipped a full "2.0"
initiative (`docs/02-roadmap.md`, phases 0–7, all ✅) — campaigns, media library, planner, AI agents, webhooks,
white-label TLS, channel health, etc. — and the admin surface is the **one area that initiative did not touch**.
That doesn't change anything the master prompt asks for; it just means "the whole app is basic" is inaccurate —
only the platform-admin control plane is.

### 2.1 A real conflict you should know about

The master prompt's §10 says: *"Avoid changes to publishing, social providers, planner, media, queues,
automation, marketing output, or WordPress integration unless needed for compatibility."* Your direct request
("activate Facebook and other platforms") requires going straight into `app/Social/*` — exactly what that clause
excludes.

**I'm treating your spoken request as the more specific, more recent instruction and building it as a parallel,
clearly-separated workstream** (§4, §7 Phase A), not folding it into the control-plane work the master prompt
describes. The two won't interfere with each other — the control-plane phases touch `app/Http/Controllers/Admin`,
new migrations, and admin Vue pages; the activation phase touches `app/Social/Providers/*` and their tests. Say
so if you'd rather I hold off on the social-provider changes until the control-plane work ships first.

---

## 3. The false-claim bug, in full

- **What ships today:** `resources/js/Layouts/AppLayout.vue:143` — *"You are impersonating this organization.
  Everything you do is audit-logged."*
- **What the code does:** `app/Http/Controllers/Admin/OrganizationController.php:56-64` — `impersonate()` calls
  `Log::warning(...)` (the application log file, not a database table anyone but a server admin with shell
  access can read) and never touches `AuditLog::record()`. `stopImpersonating()` (`:72-80`) records nothing at
  all. `suspend()` (`:43-48`) records nothing at all.
- **Two docs repeat the false claim:** `docs/03-security.md:45-46` ("every impersonation is written to the audit
  log") and `docs/SCHEDULEISTIC_2_SECURITY.md:41-42` ("Impersonation ... is audit-logged").
- **Additional hardening gaps found, matching the master prompt §8 checklist exactly:** no password
  confirmation before impersonating; no session-ID regeneration on start or stop; `stopImpersonating()` never
  re-verifies the original admin still exists and is still a platform admin before restoring them; no maximum
  impersonation lifetime; nested impersonation *is* correctly rejected (`:57`, tested).
- **Fix is small and goes in Phase 1** (§7): make `impersonate()`/`stopImpersonating()`/`suspend()` write real
  `AuditLog::record()` rows, add password confirmation + session regeneration + admin-still-valid check + a max
  lifetime, and only then can the banner and docs keep making the claim — otherwise, reword them to be accurate.

---

## 4. "Activate Facebook and other platforms" — full findings

### 4.1 What's already true, before any code changes

All 13 real drivers (`linkedin`, `linkedin_company`, `facebook`, `instagram`, `google_business`, `pinterest`,
`threads`, `tiktok`, `youtube`, `mastodon`, `bluesky`, `medium`, `wordpress`) are registered in
`app/Social/ProviderManager.php` and **all 13 already appear as cards on the channel-connect screen**
(`ChannelController::index`, `app/Http/Controllers/ChannelController.php:32-44` iterates
`$this->providers->keys()` — every registered driver, unconditionally). The 7-network list in
`config/scheduleistic.php`'s `providers` array does **not** gate this — it is dead configuration, confirmed by
grepping every consumer in the codebase. The one doc that tells a future engineer to edit that array to "make a
network appear in the connect UI" (`docs/SCHEDULEISTIC_2_PROVIDER_CAPABILITIES.md` §7, step 5) is itself stale
and should be corrected.

So "activating a platform for users" is not a matter of flipping a switch in code — that switch doesn't exist,
because everything is already switched on. What's actually gating each network is below.

### 4.2 Real code bugs found — these block a network even with perfect credentials

| Network | Bug | Where |
| --- | --- | --- |
| **TikTok** | OAuth sends `client_id`; TikTok's v2 API requires the parameter to be named `client_key`. The authorize URL and every token exchange/refresh call will be rejected by TikTok regardless of how correct the credential values are. | `TikTokProvider` inherits `AbstractOAuthProvider`'s generic param names unmodified — needs an override, same pattern `GoogleBusinessProvider` already uses to customize its OAuth URL |
| **YouTube** | `publish()` never uploads a video file — it POSTs only `snippet`/`status` JSON with no resumable upload session and no video bytes. The method's own comment says the upload happens "out of band" in a worker, but no such job/command exists anywhere in the codebase. YouTube cannot publish a working video today, independent of credentials. | `app/Social/Providers/YouTubeProvider.php` |
| **All OAuth networks** | `refreshToken()` is fully implemented on the base class and on every OAuth driver, but it is **never called by anything** — no scheduled command, no job. Grepped the whole tree; zero call sites outside the method declarations. Every OAuth channel (LinkedIn, Google Business, YouTube, Pinterest, TikTok included) will eventually expire and require the user to fully reconnect from scratch — there is no silent renewal despite refresh tokens being stored in the database for exactly that purpose. | Engine-level (`PublishPostJob`, `channels:check-health`) |
| **Facebook, Instagram, Threads** | No long-lived-token exchange step (Meta's `fb_exchange_token` grant) after the initial code exchange, and `token_expires_at` is never populated for these three. Combined with the point above, these three networks get **zero proactive expiry protection** from the hourly health check — failures only surface reactively, after a real scheduled post fails against the live API. | `FacebookProvider`, `InstagramProvider`, `ThreadsProvider::mapAccount()` |
| **Facebook, Instagram** | `mapAccount()` unconditionally takes the *first* Facebook Page returned (`data.0`), with a comment admitting "the UI lets the user choose in Phase 2." No page picker exists. A user managing the wrong page first, or zero pages, gets a channel created silently with no way to pick correctly or be warned. | `FacebookProvider.php:50-55`, `InstagramProvider.php:50-59` |
| **Google Business, Pinterest** | Neither `mapAccount()` ever resolves or stores a location (`meta.location_name`) / board (`meta.board_id`). **Every publish attempt on a freshly-connected channel for either network will always throw** ("no location configured" / "requires a board") — there is no step anywhere that lets the user pick one after connecting. | `GoogleBusinessProvider.php`, `PinterestProvider.php` |
| **LinkedIn Company Page** | Same shape of gap: nothing resolves which LinkedIn organization the user administers; posting falls back to treating `provider_account_id` as the org id, which it usually isn't. | `LinkedInCompanyProvider.php:24-34` |
| **Facebook, LinkedIn (both), Threads, Google Business, Mastodon, Bluesky, Medium, WordPress** | `publish()` never references `$payload->media` at all — every post through these 8 drivers goes out **text-only**, even though `config/provider_capabilities.php` advertises image/video support for all of them and the composer lets a user attach media. Only Instagram, Pinterest, and TikTok actually transmit media today (each only sends the first item — no multi-image/carousel anywhere). | listed drivers' `publish()` methods |
| **WordPress** | The outbound publish call posts directly to a user-supplied `site_url` with no visible use of the app's own `SsrfGuard` (which the codebase already has and uses for RSS feed ingestion). Worth a deliberate check before this network handles real tenant traffic. | `WordPressProvider.php:52-73` |
| **Pinterest** | Possible OAuth-shape mismatch: Pinterest's v5 API documents HTTP Basic client authentication, not client_id/secret as body params (the generic shape every other driver uses). Flagged as "needs verification against a real Pinterest app," not a certainty. | `AbstractOAuthProvider` generic flow |
| **`website` provider key** | Present in `config/scheduleistic.php` and `resources/js/networks.js`, but has no driver class, no route, no controller — it cannot be "activated" through the channel-connect mechanism because nothing implements it. (Distinct from, and not to be confused with, the unrelated RSS/WordPress-feed-ingestion feature.) | grep across `app/`, `resources/js/` |

### 4.3 What's a pure external/business-process blocker (no code fix will unlock these)

| Network(s) | What the operator must do |
| --- | --- |
| LinkedIn (personal + company) | Register a LinkedIn app with "Sign In with LinkedIn" + "Share on LinkedIn" products; set `LINKEDIN_CLIENT_ID`/`SECRET`. No app review required for these scopes at normal volume. |
| Facebook, Instagram, Threads | One Meta Business app covering all three (`config/services.php` already shares one client id/secret across them by default). Requires **Meta App Review** for `pages_manage_posts`, `business_management`, `instagram_content_publish` etc. — and **Meta Business Verification** for Instagram/Threads content publishing. This is a multi-week process, not a same-day signup, and the current deployment doc (`docs/04-build-deploy-maintain-guide.md` §5.1) understates this — it will be corrected in Phase A (§7). |
| Google Business Profile, YouTube | One Google Cloud OAuth client. Business Profile API access requires a separate Google approval/allow-listing request beyond "enable the API." An unverified OAuth consent screen caps at 100 test users and shows an "unverified app" warning to real customers — production needs Google's OAuth verification process, which is more involved for sensitive scopes like `business.manage` and `youtube.upload`. |
| Pinterest | Developer app + standard API access tier approval. |
| TikTok | Content Posting API approval, and a **separate** audit specifically to post publicly (unaudited apps are restricted to private/self-only posting — which is exactly why `TikTokProvider` currently hard-codes `SELF_ONLY`). |
| Medium | Should be verified independently: Medium is reported to have closed public registration for new integration tokens. If true, "activating Medium" may be a dead end for *new* customers regardless of what's coded — worth a 10-minute check before promising it. |
| Mastodon, Bluesky, WordPress | None — users self-serve their own instance URL/handle/app password. Nothing for the operator to register. |

### 4.4 Test coverage gap

Of all 13 drivers, only **LinkedIn (personal), Pinterest, and Mastodon** have a test that actually invokes
`publish()` against a mocked HTTP response. **Facebook, Instagram, and Threads — the exact networks named in
the request — have zero test coverage of their driver code.** The generic connect/callback plumbing (state
validation, tenant isolation, unconfigured-provider rejection) is well tested via `FakeProvider`, but that
proves nothing about any specific network's real API-calling code. This gets closed in Phase A.

---

## 5. "Schedule history" — landscape and the decision needed

Nothing in the codebase is named "schedule history" or "post history" today. Here is everything already
history-shaped, and how complete each one is:

| Existing mechanism | Model | Is it a real history? | Is there a UI for it? |
| --- | --- | --- | --- |
| Post publish outcome | `PostTarget` | **No** — one row per (post, channel) forever; each retry *overwrites* the error message and increments a counter. Individual past attempts are not preserved. | No dedicated view; only current status shown in the planner |
| Channel health | `ChannelHealthEvent` | **Yes** — genuinely append-only; a recovered problem gets `resolved_at` stamped rather than being deleted or rewritten | **Partially** — the health page (`Channels/Health.vue`) only ever loads *open* issues (`ChannelHealthController::index` calls `openHealthEvents()` only). Resolved events are stored but never shown anywhere, despite the admin guide explicitly documenting the intent to "use it as the history when a tenant asks why a post failed three weeks ago." |
| Outbound webhook delivery | `WebhookDelivery` | **Yes** — a true per-attempt log; replay creates a new row rather than mutating the old one | Yes, on the Webhooks page |
| Approval decisions | `Approval` | **Yes** — one row per submit/decision cycle, naturally an append-log per post | Yes — the post drawer's "Approval history" section |
| Analytics | `AnalyticsMetric` | Yes, a true time series | Only read in aggregate today (lifetime totals) |
| Platform admin audit trail | `AuditLog` | **Yes** — append-only, `UPDATED_AT = null`, but only 4 controllers / 9 action types ever write to it (campaign and idea actions, webhook actions, planner bulk actions) — **not** suspension or impersonation (see §3) | **No admin UI exists.** `docs/SCHEDULEISTIC_2_ADMIN_GUIDE.md` §7 literally says: *"There is no admin UI for it yet — query it directly"* and gives a raw SQL example. |

None of the app's own docs describe a single, unified "history" page anywhere — each of the above is discussed
as its own isolated mechanism.

### 5.1 Candidate designs (grounded in what already exists — no invented architecture)

**A. Admin audit-log viewer.** Exactly master prompt §5.5. Closes the self-documented "query SQL directly" gap.
Filterable by actor/org/action/date using `AuditLog`'s existing indexes. Read-only, platform-admin only. This is
needed regardless of what else you choose, because the master prompt requires it and it's the one gap the app's
own docs already admit in writing.

**B. User-facing post/schedule history.** A new, permanent, paginated, filterable page (per organization) listing
every past scheduled/published post with its per-channel outcome — built the way the 2.0 planner was built
(`PlannerQuery`/`PlannerFilterRequest`: validated multi-facet filters, workspace-tenancy-safe, URL-persisted,
reusing the existing `FilterBar` component), but using real server-side `paginate()` instead of the planner's
windowed `limit(500)`, since history is expected to grow unboundedly rather than being date-window-bound like a
calendar. This is the natural home for "advanced features" — retry/error detail per channel, CSV export (there's
already a CSV-reading precedent in `BulkImporter` to mirror for writing), date-range filters, per-campaign/tag
breakdowns.

**C. Channel-health timeline.** A small, contained addition: extend the existing health page with a per-channel
"view full history" that lists *all* `channel_health_events` including resolved ones. The data and indexes
already support this — only the read path and a page section are missing. Closes another gap the app's own guide
already promised but never shipped.

**D. A unified activity feed** merging `AuditLog` + `ChannelHealthEvent` + `WebhookDelivery` + `Approval` into one
chronological view — conceptually similar to what `PlannerController::show()` already assembles for a single
post (targets + versions + comments + approvals in one response), generalized into a standalone page.

**My recommendation:** build **A** regardless (it's required, cheap relative to the rest, and the infrastructure
it needs — a filterable, paginated, admin-only table view — is reusable). Then build **B**, since "advanced
features" fits it best and it's the one a paying customer (not just you as the operator) will actually use.
Treat **C** as a small bonus once B's list/filter infrastructure exists (it can reuse the same components). Treat
**D** as a possible follow-up once A/B/C exist independently, rather than a first attempt at "everything at once."

I'm asking you which of these you want, rather than guessing, because building the wrong one is real wasted
work — B and C in particular serve different audiences (your customers vs. you as the platform operator).

---

## 6. Baseline (per master prompt §11 — established before any code changes)

Command sequence run from `scheduler/app` on this exact branch, before any edits:
`composer install`, `key:generate`, `migrate:fresh`, `npm install`, `npm run build`, `php artisan test`,
`composer audit`, `npm audit`, `vendor/bin/pint --test`.

> **Environment note (matches the prior 2.0 audit's finding exactly):** this container's outbound proxy blocks
> `codeload.github.com`/`api.github.com` dist downloads. Composer's own per-package fallback silently re-clones
> each one from source instead — several minutes slower, not a repository defect, and not something that needed
> the explicit `--prefer-source` retry this run had ready as a backstop.

| Command | Result |
| --- | --- |
| `composer install --ignore-platform-req=ext-bcmath` | ✅ success — 138 packages, all via source fallback (see note above) |
| `php artisan key:generate` | ✅ |
| `php artisan migrate:fresh` | ✅ clean — all 34 migrations, no errors |
| `npm install` | ✅ clean |
| `npm run build` | ✅ built in 1.95s, 287.07 kB entry bundle (98.52 kB gzip) |
| `php artisan test` | ✅ **287 tests, 280 passed, 7 skipped, 0 failed, 1,000 assertions** (8.18s) — exactly matches the README's last-recorded numbers; **no regression exists on this branch right now** |
| `composer audit` | ⚠️ **12 advisories across 2 packages** — see below, worse than the README's recorded "4 medium" |
| `npm audit` | ⚠️ 2 high (`axios`, `postcss`, both build-time/dev) — unchanged from the README |
| `vendor/bin/pint --test` | ❌ fails across a broad swath of the existing codebase — see note below |

**The dependency-advisory picture has gotten worse since the README was last updated, through no fault of any
app code:**
- `guzzlehttp/guzzle` now has **6** advisories, including a new **high**-severity one
  (`CVE-2026-69246`, reported 2026-08-03: noncanonical host can bypass host-based checks) — previously only medium
  ones were known.
- `league/commonmark` (a transitive Markdown-rendering dependency, not something Scheduleistic calls directly) now
  has **6** advisories, **four of them high**, all reported 2026-08-06 — the day before this audit. None of this
  existed when the 2.0 baseline was last recorded.
- Both are transitive dependencies pulled in by the framework/Jetstream stack, not code this app owns, and both
  clear with a lock-file-only `composer update` — no application code changes. This goes into Phase 1 (§7) since
  it's cheap, safe, and newly time-sensitive.

**`vendor/bin/pint --test` failing broadly is a pre-existing condition, not something this audit introduced** —
confirmed `.github/workflows/ci.yml` does not run Pint at all today, only `php artisan test`, so style drift has
never been enforced or caught. Running a blanket `pint` auto-fix across the whole repository would touch nearly
every file in one unrelated diff, which is exactly the kind of "uncontrolled rewrite" the master prompt warns
against (§13) and would make every phase's real diff harder to review. **Plan: apply Pint only to files a given
phase actually touches, not the whole tree**, and leave the pre-existing drift alone unless you'd rather I clean
it up as its own, separate, zero-behavior-change commit.

---

## 7. Proposed phased delivery plan

**How I'll guarantee nothing existing breaks**, applied to every phase below:
- Every schema change is a new, additive migration (new nullable columns/tables) — never an edit to a shipped
  migration, per master prompt §10.
- The full test suite re-runs after every phase; a phase isn't "done" if it drops the pass count.
- New admin capability = new routes/controllers/policies, never a rewrite of an existing customer-facing route,
  except the specific, deliberately-tested fixes called out below.
- `vendor/bin/pint` runs scoped to the files each phase actually touches (not a blanket repo-wide reformat —
  see §6, the existing tree doesn't pass `pint --test` today and CI has never enforced it, so a full reformat
  would bury every real diff in style noise). CI stays green throughout.
- One branch, one draft PR, updated continuously — but structured as small, individually reviewable commits (one
  per phase, per master prompt §13's "small, reviewable phases, do not attempt a single uncontrolled rewrite"),
  so you can review incrementally without me opening a new PR per phase. Say the word if you'd rather I pause
  for your review after specific phases instead of continuing straight through.

### 7.0 — Decision needed before Phase 2

Two questions are open (asked separately, alongside this document) — which "schedule history" option(s) from §5.1,
and how to sequence platform activation from §4. Phase 1 does not depend on either answer and can start
immediately.

### Phase 1 — Trust & correctness fixes (small, independent, high value, start immediately)
- Impersonation: real `AuditLog::record()` on start/stop, password confirmation, session regeneration, verify
  the restored admin is still valid, add a max lifetime. Fix or earn the banner/docs claim (§3).
- Organization suspend/reactivate: add the missing audit row.
- Fix `BillingController`'s dead `features` field to use resolved `capabilities` (per master prompt's own note).
- Add a duplicate-subscription guard to `BillingController::checkout()`.
- Document `STRIPE_PRICE_SOLO`/`STRIPE_PRICE_ENTERPRISE` in `.env.example`.
- Correct the stale `config/scheduleistic.php` `providers` array and the stale instruction in
  `SCHEDULEISTIC_2_PROVIDER_CAPABILITIES.md` §7 (§4.1).
- `composer update guzzlehttp/guzzle league/commonmark` to clear the newly-reported high-severity advisories
  (§6) — lock-file only, no application code change.
- Tests for every fix above; nothing here touches provider publishing behavior.

### Phase 2 — Control-plane foundations (master prompt §2–4, §7)
Additive migrations for user suspension (`suspended_at`/`suspension_reason`/`suspended_by`) and plan overrides
(`plan_override`, `plan_override_expires_at`, `plan_override_reason`, `plan_override_set_by`); the canonical
effective-plan resolver; Form Request + Policy scaffolding for the new admin surfaces; an audit-context redactor
helper. Unit-tested in isolation; nothing user-visible changes yet.

### Phase 3 — Platform overview + paginated Users + Organizations (master prompt §5.1–5.3)
`GET /admin` overview, server-paginated user index with search/filter/actions, server-paginated organization
index replacing the `limit(500)` query — additive routes, new Vue pages, `navigation.js` gains the new admin
nav items (single source, per master prompt §9).

### Phase 4 — Organization detail + subscription/entitlement admin (master prompt §5.4)
Detail page with the 8 tabs the master prompt specifies; plan-override grant/change/remove with password
confirmation and audit trail; entitlement grant/clear UI on top of the already-correct raise-only service.

### Phase 5 — Audit-log admin UI (master prompt §5.5 + your §5.1-A answer)
Closes the "query SQL directly" gap. If you also want option B/C from §5.1, their list/filter/pagination
infrastructure is built here once and reused.

### Phase 6 — Impersonation hardening completion (master prompt §8)
Anything from Phase 1's fixes not already sufficient: block high-risk actions while impersonating (billing,
credentials, ownership transfer) unless explicitly break-glass audited.

### Phase 7 — Owner dashboard improvements (master prompt §6)
Settings hub linking existing Team/Billing/Branding/Notifications/API/Webhook pages; surface base vs. effective
plan; member-limit enforcement at invite time (not just UI).

### Phase A — Platform activation, tier 1 (§4.2, the code bugs) — independent of Phases 2–7, can run in parallel
Fix TikTok's `client_key` bug; wire `refreshToken()` into a real scheduled job so every OAuth network actually
renews; add the Meta long-lived-token exchange for Facebook/Instagram/Threads; add page/location/board/organization
resolution (a post-connect picker step) for Facebook, Instagram, Google Business, Pinterest, and LinkedIn Company;
wire already-declared media capabilities into `publish()` for the networks in §4.2's table. Add real
`Http::fake()`-backed tests for Facebook, Instagram, and Threads specifically, closing §4.4's gap. Rewrite
`docs/04-build-deploy-maintain-guide.md` §5.1 to state the real Meta App Review / Business Verification / Google
verification / TikTok audit timelines accurately instead of the current understated version.

### Phase B — Platform activation, tier 2 (§4.2, larger builds — likely a follow-up initiative, not this pass)
A real YouTube resumable video upload implementation; Instagram carousel/video support; media wiring for the
remaining text-only drivers (Mastodon, Bluesky, Medium, WordPress, LinkedIn). Flagged separately because each of
these is a self-contained feature-sized piece of work, not a bug fix.

### Phase C — Schedule/post history feature (per your §5.1 answer)
Built on Phase 5's infrastructure if B and/or C from §5.1 were chosen.

### Phase 8 — Full regression, docs, and final report (master prompt §11–14)
Complete suite, `npm run build`, `vendor/bin/pint`, updated docs (including correcting `03-security.md` and
`SCHEDULEISTIC_2_SECURITY.md`'s impersonation claims once Phase 1 makes them true), manual checklist, and the
final report format the master prompt specifies in §14 (outcome, architecture decisions, migrations, route
inventory, authorization matrix, test/build results, risks, deployment/rollback steps).

---

## 8. What I'm explicitly *not* doing without further sign-off

- Not building a database-driven plan editor (master prompt is explicit that this is a separate initiative).
- Not touching the planner, media library, campaigns, AI agents, or WordPress *ingestion* (the RSS-feed feature,
  distinct from the WordPress *publishing* driver) — out of scope for both the master prompt and your request.
- Not implementing Phase B (YouTube real upload, etc.) in the same pass as everything else unless you say so —
  it's large enough to deserve its own review cycle.
- Not modifying marketing site HTML/content (master prompt §10 is explicit, and it's outside your request too).
