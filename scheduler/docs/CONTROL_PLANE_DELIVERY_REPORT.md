# Control Plane, Platform Activation & Schedule History — Delivery Report

**Companion to:** [`CONTROL_PLANE_ACTIVATION_AUDIT.md`](CONTROL_PLANE_ACTIVATION_AUDIT.md) (the audit and
phased plan this report closes out). Written at the end of Phase 8, against branch
`claude/repo-audit-update-workflow-9bo55j`.

---

## 1. Outcome

Every phase in the audit's plan (§7) shipped: Phases 1–7 (the platform-admin control plane, per the
uploaded master prompt), Phase A (platform activation — all 13 social provider drivers), and this
Phase 8 closeout. Nothing was descoped except what the audit itself explicitly deferred up front —
see §6 (Known limitations) below.

| # | Phase | Commit | What it delivered |
| --- | --- | --- | --- |
| 1 | Trust & correctness fixes | `a276189` | Real audit-logged impersonation, billing bug fixes, dependency security bump |
| 2 | Control-plane foundations | `d641402` | Suspension + plan-override schema, effective-plan resolver, audit-context redactor |
| 3 | Platform overview + paginated admin | `cd74f41` | `/admin` dashboard, paginated users/organizations |
| 4 | Organization detail + entitlements | `89dbb0b` | Full org profile page, plan overrides, entitlement grants |
| 5 | Schedule history (all 3 forms) | `aedfb60` | Admin audit-log viewer, user-facing post history, channel-health timeline |
| 6 | Impersonation hardening completion | `df3a1e1` | High-risk action blocking + audited break-glass |
| 7 | Owner dashboard improvements | `4d45aa7` | Settings hub, effective-plan visibility, seat-limit enforcement |
| A | Platform activation | `43b9bbb` | All 13 provider drivers fixed at the code level |
| 8 | This report | — | Final regression, doc corrections, this document |

**Test count progression:** 287 (baseline) → 298 → 316 → 334 → 347 → 372 → 383 → 392 → 402 → **443**
tests, **436 passing**, 7 skipped by design, **0 failing**, at every single checkpoint along the way —
no phase was accepted with a regression. Final numbers in §5.

**Schema footprint:** two additive migrations, both in Phase 2, described in §3. Nothing else in this
entire engagement touched the database schema — every other phase's persistence needs were already
covered by existing tables (`meta`/`entitlements` JSON columns, the append-only `audit_logs` table).

---

## 2. Architecture decisions

Decisions worth understanding if you're extending this work, not just reviewing it:

- **Session-authoritative impersonation, not guard-derived.** `ImpersonationService` stores
  `impersonator_id`/`impersonated_user_id`/`impersonating_team_id`/`impersonation_started_at`
  directly in the session rather than inferring them from `$request->user()`. This sidesteps a real
  Sanctum `RequestGuard` caching quirk (its resolved user is cached for the guard instance's
  lifetime — invisible in production, where every request boots fresh, but very visible when a test
  method makes several requests against the same instance) and is more defensively correct
  regardless: the impersonation lifecycle no longer depends on which guard resolved which user.
- **Effective plan is the only thing anything compares.** `PlanService::planKey()`/`plan()` resolve
  a strict precedence — active plan override, else the Stripe-synced base plan — and every caller
  goes through it. Phase 7 found and fixed three places that had quietly bypassed this
  (`HandleInertiaRequests`'s shared sidebar prop, the owner dashboard, and the external AI-gateway
  tier mapping) by reading `team.plan` directly. There is no fourth: `grep -rn "team->plan\b"` across
  `app/` was re-run after the fix and the only remaining hits are `PlanService::basePlanKey()` itself
  (by design — it *is* the base-plan resolver) and the AI-gateway file now routed through
  `PlanService`.
- **Grant-only/raise-only entitlement overrides.** Pre-existing in `PlanService`, verified correct
  and extended, never weakened: a capability override can only turn a capability on, never off; a
  limit override can only raise a limit, via `maxLimit()`'s `max($base, $override)`, never lower one.
  A plan downgrade can therefore never silently revoke something a customer was explicitly granted.
- **Audit log stays append-only and auto-redacting.** `AuditLog::record()` is the single writer
  (`UPDATED_AT = null`, no update/delete route exists anywhere, tested explicitly). Every context
  array passes through `AuditContextRedactor` before it's written, so a password/token/secret can
  never end up readable in the admin audit-log viewer even if some future caller passes one in by
  mistake.
- **Break-glass, not silent override, for impersonation restrictions.** `BlockHighRiskActionsDuringImpersonation`
  denies a fixed route list outright, but a `break_glass_reason` field lets the request through —
  writing its own `impersonation.break_glass` audit row naming the admin, the impersonated user, the
  route, and the stated reason. The restriction is real, but not a dead end for the one legitimate
  case (a tenant explicitly asks support to do something on their behalf).
- **Global route-name-keyed middleware for vendor-registered routes.** Both
  `EnforceImpersonationLifetime`/`BlockHighRiskActionsDuringImpersonation` (Phase 1/6) and the
  reasoning behind them apply again in Phase A's provider work: several blocked/gated routes
  (password, 2FA, passkeys, account deletion) are registered by Fortify/Jetstream in vendor route
  files this app cannot fork just to attach one middleware. Matching on `$request->route()->getName()`
  from a globally-appended middleware avoids that fork entirely.
- **Post-connect account resolution stores everything a switch needs up front.** Facebook,
  Instagram, Google Business, Pinterest, and LinkedIn Company all resolve *every* available
  page/location/board/organization at connect time (one or two extra list calls), not just the one
  auto-selected. For Google Business, Pinterest, and LinkedIn Company this made a real "switch
  without reconnecting" feature possible with no schema change, because those three drivers'
  `channel.access_token` is already at the right level to re-list options on demand. Facebook and
  Instagram's stored token is Page/IG-scoped, not user-scoped, so switching there would need a new
  encrypted column to hold the user-level token — deliberately not built this pass (see §6).
- **`vendor/bin/pint` scoped to touched files only, every phase.** The pre-existing codebase does not
  pass `pint --test` (confirmed again in §5 below — unchanged from the Phase 1 baseline) and CI never
  enforced it. Reformatting the whole tree in this engagement would have buried every real diff in
  style noise, which is exactly what the master prompt's §13 warns against. Every file this
  engagement created is itself clean; nothing pre-existing was touched for style alone.

---

## 3. Migrations

Both additive, both from Phase 2, neither edits a previously-shipped migration:

| Migration | Table | Adds |
| --- | --- | --- |
| `2026_08_07_000001_add_suspension_to_users_table` | `users` | `suspended_at`, `suspension_reason`, `suspended_by` (FK → `users.id`, null on delete) |
| `2026_08_07_000002_add_plan_override_to_teams_table` | `teams` | `plan_override`, `plan_override_expires_at`, `plan_override_reason`, `plan_override_set_by` (FK → `users.id`, null on delete) |

Both were verified with a full `migrate:fresh` → `migrate:rollback --step=2` → `migrate` cycle during
Phase 2, not just a forward `migrate:fresh` — each `down()` explicitly drops its own index before the
column it's on (SQLite rebuilds the table to drop a column, and fails if an index on that column is
still attached), which a `migrate:fresh`-only check would never have caught.

---

## 4. Route inventory (everything added this engagement)

All routes below sit behind `auth:sanctum` + the app's session guard + `verified` at minimum (the
outer authenticated group in `routes/web.php`); additional gates are called out per row.

### Platform admin (`platform.admin` middleware — `EnsurePlatformAdmin`, on every row below)

| Method | URI | Name | Extra gate |
| --- | --- | --- | --- |
| GET | `/admin` | `admin.overview` | — |
| GET | `/admin/organizations` | `admin.organizations.index` | — |
| GET | `/admin/organizations/{organization}` | `admin.organizations.show` | — |
| POST | `/admin/organizations/{organization}/suspend` | `admin.organizations.suspend` | — |
| POST | `/admin/organizations/{organization}/impersonate` | `admin.organizations.impersonate` | `password.confirm` |
| POST | `/admin/organizations/{organization}/plan-override` | `admin.organizations.plan-override.update` | `password.confirm` |
| DELETE | `/admin/organizations/{organization}/plan-override` | `admin.organizations.plan-override.destroy` | `password.confirm` |
| POST | `/admin/organizations/{organization}/resync-plan` | `admin.organizations.resync-plan` | — |
| POST | `/admin/organizations/{organization}/entitlements` | `admin.organizations.entitlements.grant` | — |
| DELETE | `/admin/organizations/{organization}/entitlements` | `admin.organizations.entitlements.clear` | — |
| GET | `/admin/users` | `admin.users.index` | — |
| POST | `/admin/users/{user}/suspend` | `admin.users.suspend` | — |
| POST | `/admin/users/{user}/reset-link` | `admin.users.reset-link` | — |
| POST | `/admin/users/{user}/revoke-sessions` | `admin.users.revoke-sessions` | — |
| GET | `/admin/audit-logs` | `admin.audit-logs.index` | — (read-only; no update/delete route exists) |

Also added, outside the `admin` prefix (available to the impersonated session, not gated by
`platform.admin`, deliberately exempt from `user.active` — see the route's own comment):

| Method | URI | Name |
| --- | --- | --- |
| POST | `/admin/stop-impersonating` | `admin.stop-impersonating` |

### Schedule history

| Method | URI | Name | Gate |
| --- | --- | --- | --- |
| GET | `/history` | `history.index` | `org.active` |
| GET | `/history/export` | `history.export` | `org.active` |
| GET | `/social-profiles/health/{channel}/history` | `channels.health.history` | `org.active` + tenant check (`abort_unless` on `visibleWorkspaceIds()`) |

### Owner-side

| Method | URI | Name | Gate |
| --- | --- | --- | --- |
| GET | `/settings` | `settings.index` | none beyond auth (deliberately outside `org.active`, matching billing) |
| PUT | `/workspaces/{workspace}/channels/{channel}/select-account` | `workspaces.channels.select-account` | `org.active` + tenant check |

**Total new routes: 19.** None replace or change the signature of an existing route; the two
pre-existing organization-admin routes (`index`, `suspend`, `impersonate`) that Phase 1 hardened kept
their original names and URIs.

---

## 5. Authorization matrix

| Actor | Can do | Cannot do |
| --- | --- | --- |
| **Guest** | Nothing under `/admin`, `/settings`, `/history`, or any workspace route — all redirect to login. |
| **Org member (non-owner)** | Everything their Jetstream role permits inside their own organization; sees `isOwner: false` on the settings hub, hiding owner-only actions. | Billing (`isOwner` check in `BillingController`), impersonate anyone, see another organization's data (`visibleWorkspaceIds()` boundary, checked on every workspace-scoped read). |
| **Org owner** | Everything a member can, plus billing/checkout, plan-override *visibility* (not granting), inviting members up to the plan's seat limit (now enforced server-side, not just hidden in the UI). | Grant themselves a plan override or entitlement (platform-admin only), see another organization. |
| **Platform admin** | Everything under `/admin`; suspend/reactivate an organization or user; grant/remove a plan override or entitlement (both `password.confirm`-gated); start an impersonation session (`password.confirm`-gated, rejected if the target is another platform admin or an impersonation is already active); read the full audit log. | Impersonate another platform admin (`403`); restore a demoted/deleted admin on `stop()` (`403`, session left pointing at the impersonated user rather than silently promoting anyone); update or delete an audit-log row (no such route exists). |
| **Platform admin, while impersonating** | Ordinary content management as the impersonated user (campaigns, ideas, media, channels) — the entire point of impersonating someone to fix their account. | Billing checkout/portal, password/profile/2FA/passkey changes, other-device session revocation, account/organization/workspace deletion — all blocked by `BlockHighRiskActionsDuringImpersonation` unless `break_glass_reason` is supplied, which is then itself audited. Session force-ends after `ImpersonationService::MAX_MINUTES` (60) regardless. |

---

## 6. Known limitations (deliberately not fixed this pass, and why)

Each of these was named explicitly in the audit or a phase's own commit — nothing here is a
surprise found after the fact:

- **Facebook/Instagram account switching still needs a reconnect.** Every available page/IG account
  is recorded (name/id only) at connect time for visibility, but switching to a different one
  without a fresh OAuth round-trip would need a new encrypted column to hold the user-level token
  (the stored `access_token` is Page/IG-scoped, not user-scoped, unlike Google Business/Pinterest/
  LinkedIn Company where the same token already works for both). A real, scoped follow-up, not a bug.
- **YouTube real video upload, Instagram carousel/video, and media wiring for the remaining five
  text-only drivers** (Mastodon, Bluesky, Medium, WordPress, LinkedIn personal + company) — the
  audit's own Phase B, explicitly deferred as feature-sized work, not a bug fix.
- **Pinterest's OAuth shape (HTTP Basic auth) and the Google Business/LinkedIn Company account-
  listing endpoints are implemented against each platform's documented API shape, not verified
  against a live registered app** (this sandboxed environment cannot reach the real APIs). Same
  epistemic position the original driver authors were in — flagged here rather than silently assumed
  correct, the same way the audit itself flagged Pinterest's OAuth shape as "needs verification."
- **Medium's registration status is unverified.** Reported to have closed public integration-token
  registration for new customers; worth a 10-minute manual check before promising it in a sales
  conversation. Nothing to fix in code either way.
- **`npm audit`: 0 vulnerabilities as of this report** (`axios`/`nanoid`/`postcss` — all build-time/dev
  dependencies — cleared via `npm audit fix`, lock-file only, no `package.json` range changed).
  **`composer audit`: 0 advisories** (the 12 advisories on the original baseline, later found to have
  grown worse mid-audit, were cleared in Phase 1 via a lock-file-only `guzzlehttp/guzzle`/
  `league/commonmark` update). Re-run both before every future release — dependency advisories are a
  moving target, as this engagement's own baseline getting worse mid-audit demonstrated.
- **`vendor/bin/pint --test` still fails across the pre-existing codebase** — unchanged from the
  Phase 1 baseline, confirmed again in this final regression pass. `.github/workflows/ci.yml` does
  not run Pint, so this has never been enforced; every file this engagement touched is itself clean
  (verified with a scoped `pint` run after every phase), and no pre-existing file was reformatted
  for style alone, per the master prompt's own §13 guidance against uncontrolled rewrites.

---

## 7. Test & build results (final)

Run from `scheduler/app` on this branch at the head commit:

| Command | Result |
| --- | --- |
| `php artisan test` | ✅ **443 tests, 436 passed, 7 skipped by design, 0 failed**, 1,844 assertions |
| `npm run build` | ✅ built cleanly, no warnings |
| `composer audit` | ✅ **no security vulnerability advisories found** |
| `npm audit` | ✅ **0 vulnerabilities** (was 2 high at the original baseline, briefly 3 before this report's fix) |
| `vendor/bin/pint --test` | ⚠️ fails — pre-existing drift, unchanged from the Phase 1 baseline; see §6 |

The 7 skipped tests are pre-existing and unrelated to this engagement (skipped by explicit design in
the code they live in, not a new gap introduced here).

---

## 8. Deployment & rollback

**Deploying this work:**
1. Merge/deploy as normal — `php artisan migrate` picks up the two Phase 2 migrations automatically;
   nothing else in this engagement requires a manual step.
2. No new environment variables are *required* — `STRIPE_PRICE_SOLO`/`STRIPE_PRICE_ENTERPRISE` were
   already optional (only consumed if you sell those plans) and are now documented in `.env.example`.
3. If you intend to use the platform-admin surface, at least one user needs `is_platform_admin = true`
   set directly in the database (there is deliberately no self-service or UI path to grant this —
   unchanged from before this engagement).
4. Re-run `npm run build` as part of your normal deploy step (new Vue pages: `Admin/*`,
   `Settings/Index`, `History/Index`, `Channels/HealthHistory`) — nothing here changes the build
   process itself.

**Rolling back:**
- Every phase is its own commit on this branch, in the order listed in §1 — revert from the top down
  if you need to peel back specific phases rather than everything.
- The two migrations are safe to roll back (`php artisan migrate:rollback --step=2` from a fresh
  state, or target them specifically) — both were verified round-trip in Phase 2, and rolling them
  back only removes nullable columns nothing else was made to depend on being present. A team with
  an active plan override or a user with a suspension recorded would lose that state on rollback,
  same as any additive-column rollback; nothing else in the schema references these columns as a
  foreign key or a not-null constraint.
- No pre-existing route, controller, or Vue page was renamed, removed, or had its contract changed —
  a rollback of the new code alone (without touching migrations) leaves every pre-existing feature
  exactly as it was.

---

## 9. Manual verification checklist

The automated suite covers all of this already (§7); walk through it by hand once before treating
this as production-ready, since a human clicking through a real browser session catches presentation
issues no assertion does:

- [ ] Log in as a non-admin user — confirm the sidebar shows no "Platform admin" section at all.
- [ ] Set `is_platform_admin = true` on a test user directly in the database, log in as them —
      confirm `/admin` renders the overview, and `/admin/organizations`/`/admin/users`/
      `/admin/audit-logs` all load with working search/filter/sort/pagination.
- [ ] From `/admin/organizations/{id}`, grant a plan override (requires re-confirming your
      password) — confirm the target organization's sidebar, dashboard, and billing page all show
      the overridden plan, not the base one, and that the billing page explicitly says it's an
      override with the reason and expiry you set.
- [ ] Impersonate that same organization's owner (requires password re-confirmation) — confirm the
      persistent warning banner appears, try a blocked action (e.g. visit Billing and attempt
      checkout) and confirm it's refused, then click "Stop impersonating" and confirm you're back
      in your own admin session.
- [ ] As a regular owner, visit `/settings` — confirm every card links somewhere real, and that
      webhooks shows as locked with an upgrade hint if your test org is on a plan without it.
- [ ] Visit `/history` as an owner with some published/failed posts — confirm filters, sorting, and
      the CSV export download all work, and that the per-campaign breakdown reflects what's
      currently filtered.
- [ ] Connect a real (or `SOCIAL_FAKE=true` fake) social channel, then visit its workspace's
      channels page — confirm the "Posting to" switcher appears only when more than one
      page/location/board/organization was found, and that switching updates immediately without
      a redirect to the provider.
- [ ] From a workspace's social-profile health page, click "History" on any channel — confirm
      resolved (not just open) events appear in the timeline.
- [ ] Invite a team member on a plan already at its member limit — confirm the invite is rejected
      with a clear message, not silently accepted.
