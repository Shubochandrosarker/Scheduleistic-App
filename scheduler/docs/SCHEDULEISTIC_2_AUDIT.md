# Scheduleistic 2.0 — Pre-flight Repository Audit

Audit performed against `main` before any 2.0 code was written, so every number
below describes the codebase *as found*.

- **Repository:** `Shubochandrosarker/Scheduleistic-App`
- **Application root:** `scheduler/app`
- **Audit date:** 2026-07-30
- **Branch under development:** `claude/scheduleistic-2-0-upgrade-ntzrpq`

---

## 1. Environment and toolchain

| Item | Value |
| --- | --- |
| PHP | 8.4.19 (CLI) |
| Composer | 2.x |
| Node | v22.22.2 |
| npm | 10.9.7 |
| Laravel | ^13.8 |
| Jetstream / Cashier / Inertia | ^5.5 / ^16.5 / ^2.0 |
| Vue / Tailwind / Vite | ^3.3 / ^3.4 / ^8.0 |
| Default DB in `.env.example` | SQLite (MySQL/Postgres supported via config) |

### Environment blockers found

1. **`ext-bcmath` is not installed and cannot be installed in this container.**
   `laravel/cashier` requires it. Composer only completes with
   `--ignore-platform-req=ext-bcmath`. The test suite passes without it because
   no test exercises Cashier's money maths, but **production installs must have
   `ext-bcmath` enabled** — this is now called out in the deployment docs.
2. **GitHub dist archives are unreachable through the container proxy**
   (`codeload.github.com` and `api.github.com` both return 403), so Composer
   must fall back to `--prefer-source`. This is a sandbox constraint, not a
   repository defect.
3. `php artisan test` fails wholesale with *"No application encryption key has
   been specified"* until `php artisan key:generate` is run, and then fails again
   with *"Vite manifest not found"* until `npm run build` is run. Neither step is
   currently mentioned as a prerequisite in the test instructions — fixed in the
   README as part of 2.0.

---

## 2. Baseline verification results

All commands run from `scheduler/app`.

| Command | Result |
| --- | --- |
| `composer install --ignore-platform-req=ext-bcmath` | ✅ success (from source) |
| `npm install` | ✅ 197 packages |
| `php artisan key:generate` | ✅ |
| `npm run build` | ✅ built in 1.80s, 285.69 kB entry bundle (98.13 kB gzip) |
| `php artisan test` | ✅ **169 tests, 162 passed, 7 skipped, 0 failed, 480 assertions** (8.4s) |
| `composer audit` | ⚠️ **4 medium advisories**, all in `guzzlehttp/guzzle` |
| `npm audit` | ⚠️ **2 high advisories** (`axios`, `postcss`) |

### Baseline test inventory (169 tests)

- 47 feature test files, 3 unit test files.
- The 7 skipped tests are all Jetstream feature-flag guards
  (`markTestSkipped('… is not enabled.')`) in `DeleteApiTokenTest`,
  `ApiTokenPermissionsTest`, `CreateApiTokenTest`, `RegistrationTest`,
  `EmailVerificationTest`, `PasswordResetTest`, `DeleteAccountTest` — they skip
  when the corresponding Jetstream/Fortify feature is off in config. Not
  failures, and not regressions.
- **Unit coverage is thin:** only `MediaCropTest` and `SsrfGuardTest` plus the
  scaffold `ExampleTest`. Everything else is an HTTP-level feature test.

### Dependency advisories (detail)

| Package | Severity | Advisory | Affected |
| --- | --- | --- | --- |
| `guzzlehttp/guzzle` | medium | URI fragments disclosed in redirect `Referer` headers | `<7.15.1` |
| `guzzlehttp/guzzle` | medium | Host-only cookie scope not preserved | `<7.15.1` |
| `guzzlehttp/guzzle` | medium | Unbounded response cookies risk DoS | `<7.15.1` |
| `guzzlehttp/guzzle` | medium | `Proxy-Authorization` header leaked to origin servers | `<7.14.2` |
| `axios` | high | prototype pollution via nested option objects | transitive |
| `postcss` | high | path traversal via `sourceMappingURL` auto-loading | `<=8.5.17` |

None are *critical*. Guzzle is a transitive dependency of the framework; it
resolves on `composer update` once the lock file is refreshed. `postcss` and
`axios` are build-time/dev dependencies fixed by `npm audit fix`.

---

## 3. Architecture as found

The existing architecture is sound and is **kept** for 2.0. Nothing was
rewritten; everything below was extended.

```
Organization (Jetstream Team)     — plan, branding, custom domain, Cashier billable
└── Workspace                     — a client/brand
    ├── Channel                   — a connected social account (encrypted tokens)
    ├── TimeSlot                  — queue posting-time template
    ├── Feed                      — RSS/WordPress ingestion
    └── Post
        ├── PostVersion           — per-provider caption/options override
        ├── PostTarget            — one publish attempt per channel
        ├── Approval
        └── Comment
```

**Publishing engine.** `DispatchDuePosts` (scheduler command) → `PublishPostJob`
(queued, per target) → `ProviderManager` → a `SocialProvider` driver.
`FetchMetricsJob` collects analytics. `SOCIAL_FAKE` swaps every driver for
`FakeProvider`. This is correct and was preserved verbatim.

**Provider drivers present (13):** linkedin, linkedin_company, facebook,
instagram, google_business, pinterest, threads, tiktok, youtube, mastodon,
bluesky, medium, wordpress — plus `FakeProvider`.

---

## 4. Findings

Severity key: **H** = must fix for a public SaaS launch, **M** = should fix,
**L** = polish.

### 4.1 Security

| # | Sev | Finding |
| --- | --- | --- |
| S1 | — | ✅ **Good:** `Channel::$casts` encrypts `access_token`/`refresh_token`, and `$hidden` keeps them out of JSON. Verified by `WorkspaceTest::test_channel_oauth_tokens_are_encrypted_at_rest`. |
| S2 | — | ✅ **Good:** `SsrfGuard` blocks private-network URLs for RSS ingestion, with unit tests. |
| S3 | — | ✅ **Good:** OAuth `state` is verified on callback (`ChannelConnectTest::test_callback_rejects_mismatched_state`). |
| S4 | **H** | **No Form Request classes exist.** All 17 controllers validate inline with `$request->validate()`. Rule 4 of the 2.0 brief requires Form Requests; more importantly, inline validation makes authorization and validation easy to drift apart. |
| S5 | **H** | **Only one policy exists** (`TeamPolicy`). Workspace/post authorization is a hand-rolled trait (`AuthorizesWorkspaceAccess`) using `abort_unless`. It is *correct* where used, but it is opt-in — a new controller that forgets the trait has no tenancy guard at all. |
| S6 | **M** | `PostController::store` re-fetches `Workspace::findOrFail($validated['workspace_id'])` and checks `team_id` inline instead of going through the shared guard, so the client-portal assignment path is not honoured there. |
| S7 | **M** | No audit log table. Impersonation shows a banner and the brief requires "every administrative action must be logged", but nothing is persisted. |
| S8 | **M** | No storage quota, MIME verification, or SVG handling — because there is no upload feature yet. Becomes live the moment a media library exists. |
| S9 | **L** | No formula-injection escaping on CSV output (`BulkImporter` reads CSV; nothing writes it yet). |

### 4.2 Performance

| # | Sev | Finding |
| --- | --- | --- |
| P1 | **H** | `PostController::index` sends **up to 200 full post records** — including `comments.user` — through Inertia on every calendar visit, with no pagination, no filtering, and no date windowing. This is the single largest payload in the app and directly violates 2.0 perf rules 1–4. |
| P2 | **M** | `PostController::workspaceMembers()` eager-loads `team.owner`, `team.users` and `users` for every visible workspace on every calendar render, then merges in PHP. For an agency with 20 workspaces this is a large fan-out for data that changes rarely. |
| P3 | **M** | `UsageService::snapshot()` runs one `COUNT` query per metered key **on every request**, because `HandleInertiaRequests` shares it globally. It is behind a lazy closure, so it only costs on full page loads — but it is still 4–5 uncached counts per navigation. |
| P4 | **M** | Plan capabilities and provider metadata are re-read from config on every call with no caching. |
| P5 | **L** | The Vite entry bundle is 285.69 kB (98.13 kB gzip). Pages are code-split already, which is good, but there is no lazy-loading strategy for chart libraries (none exist yet). |
| P6 | **M** | Missing indexes for the filtering the 2.0 planner needs: `posts` has no composite index on `(workspace_id, scheduled_at)` or `(workspace_id, status)`. |

### 4.3 Product / UX gaps versus the 2.0 target

| Area | State as found |
| --- | --- |
| Calendar | A single list view (`Posts/Index.vue`) rendering `PostRow` — no month/week/agenda/grid views, no drag-and-drop, no filters, no bulk actions, no detail drawer. |
| Composer | Single-column `Posts/Composer.vue`. Has per-provider overrides in the data model (`PostVersion`) but the UI exposes only a basic override. No three-panel layout, no native previews, no validation panel. |
| Media | **No media library at all.** `Post::$media` is a JSON array; `MediaCropService` computes crop maths but nothing stores assets. |
| Campaigns / pillars / tags | Do not exist. |
| Ideas | Do not exist. |
| Social inbox | Does not exist. |
| Approvals | Single-stage only (`Approval` + `approval_state` string). No multi-level, parallel, sequential, deadlines, guest links or version comparison. |
| Analytics | `AnalyticsService::totalsForTeam()` returns **organization-lifetime totals only** — no date range, no comparison, no per-profile/campaign breakdown. |
| Reports | Do not exist. |
| Notifications | Three Laravel notifications exist; there is no in-app notification centre and no per-user preferences. |
| Channel health | `Channel::$status` exists but there is no health page, no token-expiry warning, no reconnect flow surfaced. |
| Webhooks / public API | `routes/api.php` exists; there are no outbound webhooks. |
| Link management / link-in-bio | Do not exist. |
| Competitor tracking | Does not exist. |
| Onboarding | No wizard, no checklist. |
| PWA | No manifest, no service worker, no bottom navigation. |

### 4.4 Frontend quality

| # | Sev | Finding |
| --- | --- | --- |
| F1 | **H** | **Emoji and text glyphs are used as functional icons**: `↩ Sign out`, `☼`/`☾` theme toggle, `▾` dropdown caret, `+` new post, `→` on buttons. These are not accessible and are inconsistent with the inline SVGs used elsewhere. |
| F2 | **H** | **Navigation is a flat 7-item list** hard-coded in `SidebarNav.vue` with no grouping, no compact mode, no plan awareness and no notification badges. |
| F3 | **H** | Nav icons are inline `<path d="…">` strings embedded in the nav config — there is no icon system, so every new icon means another magic string. |
| F4 | **M** | Typography runs small: `text-[10px]`, `text-[10.5px]`, `text-[11px]`, `text-[11.5px]` appear throughout for metadata and controls, below the 12px floor the 2.0 brief sets. Uppercase tracking (`sc-eyebrow`) is used liberally. |
| F5 | **M** | No skeleton loaders, no toast system, no standard filter component, no chart components. |
| F6 | **M** | Design tokens are strong (a full `--sc-*` accent ramp, dark/light parity, `useBrand()` runtime retinting) but lean heavily on gradients and translucent glass surfaces for *ordinary* cards (`--card-bg`, `--panel-bg`, `--nav-bg` are all gradients). |
| F7 | **M** | Tenant accent colours are applied without any contrast validation, so a low-contrast brand colour produces unreadable accent text. |
| F8 | **L** | `Posts/Index.vue` embeds its own filtering/formatting logic that will not survive the new planner. |
| F9 | **L** | No `prefers-reduced-motion` handling. |

### 4.5 Terminology

The UI mixes internal and customer-facing vocabulary: the sidebar says
"Clients" but pages, routes and props say `workspaces`; the top bar says
"Organization"; channels are called "Channels" in some places and "social
accounts" in comments. There is no presentation-label layer, so renaming means
touching every template.

### 4.6 Dead code / duplication

- `resources/js/Components/` still carries the full Jetstream scaffold
  (`ActionMessage`, `ActionSection`, `FormSection`, `SectionBorder`,
  `SectionTitle`, `NavLink`, `ResponsiveNavLink`, `Welcome.vue`) alongside the
  newer `Components/UI/*` design-system components. Both sets are in the bundle.
- `config/scheduleistic.php` declares a `providers` list of **7** networks while
  `ProviderManager` registers **13** drivers, and `resources/js/networks.js`
  knows **15** keys. Three separate, drifting sources of provider truth.
- `Workspace` model imports `App\Models\Team` while already being in that
  namespace.

### 4.7 Accessibility

- Icon-only buttons (nav toggle, theme toggle) have `aria-label` in some places
  and not others.
- Status is communicated by colour alone in several badges.
- No focus-visible styling beyond browser defaults on custom `.sc-btn` classes.
- Modals do not trap focus or restore focus to the trigger.
- No `prefers-reduced-motion` media query anywhere in `app.css`.
- Several interactive controls fall below the 44px touch target on mobile.

---

## 5. What 2.0 changes, and what it deliberately does not

**Kept exactly as found:** the tenancy model, the queued publishing engine, the
provider driver contract, `SOCIAL_FAKE`/`AI_FAKE`, Cashier billing, the
custom-domain + Caddy TLS flow, and the `--sc-*` design-token architecture.

**Replaced:** flat navigation, hard-coded plan checks, the untyped `features`
map, the unfiltered calendar payload, emoji icons.

**Added:** capability entitlements, a terminology layer, campaigns/pillars/tags,
ideas, a media library with queued processing, provider capability definitions
and media validation, the planner, the notification centre, channel health, and
signed outbound webhooks.

See `SCHEDULEISTIC_2_RELEASE_NOTES.md` for exactly what shipped in this pass and
what remains on the roadmap.
