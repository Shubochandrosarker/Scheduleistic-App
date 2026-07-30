# Scheduleistic

**The agency-grade social media scheduling platform — self-hosted, white-label, and built to be sold.**

Scheduleistic is a production Laravel SaaS, not a weekend scheduler script. One workspace lets
an agency (or a solo operator scaling into one) plan, approve, and publish content across 13
social networks for as many clients as their plan allows — with per-tenant branding, custom
domains, Stripe billing, and an AI assistant grounded in each team's own brand voice.

- **Marketing site:** https://scheduleistic.com
- **App:** https://app.scheduleistic.com
- **License:** GNU GPL v2 — see [`LICENSE`](LICENSE)

---

## Status

The original 7 build phases — auth/tenancy, the publishing engine, agency/approvals,
billing/white-label, AI/analytics, security hardening, and custom-domain TLS — are shipped.

**Scheduleistic 2.0** builds on that with capability-based entitlements, a real content planner,
a media library, campaigns/pillars/ideas, channel health, signed outbound webhooks, a
notification centre, and a rebuilt navigation and design system. It is an incremental upgrade:
nothing was rewritten, no table was renamed, and no existing feature was removed.

2.0 does **not** deliver everything in the 2.0 brief. What landed and what did not is listed
explicitly in
[`scheduler/docs/SCHEDULEISTIC_2_RELEASE_NOTES.md`](scheduler/docs/SCHEDULEISTIC_2_RELEASE_NOTES.md).

Last verified locally (PHP 8.4.19, Node 22.22, npm 10.9.7):

| Check | Result |
| --- | --- |
| `composer install` | clean (`ext-bcmath` required — see the deployment doc) |
| `npm install && npm run build` | clean, ~1.9 s |
| `php artisan migrate:fresh --seed` | clean |
| `php artisan test` | **280/287 passing**, 7 skipped by design, 0 failing, 1,000 assertions |
| `composer audit` | 4 medium (`guzzlehttp/guzzle`, transitive) |
| `npm audit` | 2 high (`axios`, `postcss` — build-time) |

Neither advisory set is introduced by 2.0; both clear with a lock refresh (`composer update
guzzlehttp/guzzle`, `npm audit fix`) and should be cleared before a public launch.

---

## Why it's not "just a scheduler"

| | |
| --- | --- |
| **Multi-tenant by design** | `Organization → Workspace → Channel`. One agency account isolates every client's accounts, content, and approvals from every other client — enforced at the query layer, not by convention. |
| **A real publishing engine** | Posts are never published inline. A cron dispatcher claims due posts and a queue worker publishes each target independently, with retries, backoff, and per-channel rate limiting — one failing network never blocks the rest of a post. |
| **Agency workflow, not just a queue** | Draft → submit → client approves/requests changes → publish, with threaded comments, @mentions, and a client-portal role that sees only its own workspace. |
| **Sellable out of the box** | Stripe billing (Cashier) with four metered plans, per-organization white-label branding, tenant custom domains with *automatic* HTTPS, and a super-admin control plane to run the whole platform. |
| **AI that knows your brand** | Caption generation, rewrite, hashtag, and quality-check agents — optionally grounded in a team's own brand knowledge via the external Brain Gateway, with real usage reporting. Fully optional; the app works without it. |
| **Demoable with zero API keys** | `SOCIAL_FAKE=true` and `AI_FAKE=true` swap in in-memory fakes so you can register, connect "channels," and publish end-to-end on a laptop with no OAuth apps and no LLM key. |

## Repository layout

| Path | What it is |
| --- | --- |
| [`scheduler/app/`](scheduler/app) | The Laravel 13 + Jetstream + Inertia/Vue 3 + Tailwind application — the product. |
| [`scheduler/docs/`](scheduler/docs) | Architecture, security posture, the full build/deploy/maintain guide (also as a PDF), and the `SCHEDULEISTIC_2_*` documentation set. |
| [`marketing/`](marketing) | The static marketing website (`scheduleistic.com`) — no build step, no framework. |
| [`automation/`](automation) | **Legacy.** The pre-app WordPress → n8n → Postiz pipeline this product replaces. Kept for history/reference only; nothing in `scheduler/app` depends on it. |
| [`tools/`](tools) | `md2pdf.py` — a dependency-free Markdown → PDF renderer used to produce `Scheduleistic-Guide.pdf`. |
| [`INSTALL.md`](INSTALL.md) | Full local installation guide (one-command, manual, and Docker). |
| [`USER_GUIDE.md`](USER_GUIDE.md) | End-user walkthrough: onboarding, composing, scheduling, approvals, billing, AI, admin. |

## Feature tour

- **13 social network drivers** behind one `SocialProvider` contract: LinkedIn (personal +
  company page), Facebook, Instagram, Google Business Profile, Pinterest, Threads, TikTok,
  YouTube, Mastodon, Bluesky, Medium, and WordPress.
- **Composer** — write once, customize per network, attach media, schedule exact time, add to a
  time-slot queue, set up recurring posts, or bulk-import from CSV.
- **Calendar & queues** — see everything scheduled per workspace, with configurable posting-time
  templates so "add to queue" always lands in the next open slot.
- **Approvals & client portal** — clients are scoped to their own workspace and can review,
  comment, and approve without ever seeing another client's content.
- **Analytics** — per-post and per-channel engagement metrics captured on a schedule.
- **Media auto-crop** — computes the right crop for each network's dimensions from one source
  image.
- **RSS / WordPress → social** — point a feed at the app and it drafts social posts from new
  articles automatically, de-duplicated by GUID.
- **Content planner (2.0)** — month, week, agenda, list and Instagram-grid views over one
  URL-persisted filter set, with drag-and-drop rescheduling, bulk actions, and a post detail
  drawer that opens over the calendar instead of navigating away.
- **Media library (2.0)** — tenant-isolated assets with folders, tags, alt text and a plan
  storage quota. Uploads are validated by content type, deduplicated by checksum, and processed
  off the request; the original is never modified.
- **Campaigns, content pillars, tags and ideas (2.0)** — the axes the planner and reporting
  slice content by, plus a Kanban pipeline that converts an idea straight into drafts.
- **Channel health (2.0)** — token expiry, last publish and last sync per profile, checked
  hourly, with the publish job refusing a dead connection up front instead of burning retries.
- **Signed outbound webhooks (2.0)** — HMAC-SHA256 payloads, secret rotation, delivery history,
  replay, exponential backoff, and automatic disable after repeated failures.
- **Billing & plans** — Free, Solo, Pro, Agency, Scale and Enterprise tiers (`config/plans.php`)
  with capability-based entitlements and enforced limits on brands, profiles, members, monthly
  posts and storage — no code change needed to reprice, and per-account overrides that can only
  ever raise a limit or grant a capability.
- **White-label** — per-organization name, colors, logo, and a custom domain with Caddy-issued,
  on-demand Let's Encrypt certificates.
- **Super-admin control plane** — list every organization, suspend/reactivate, and impersonate an
  owner for support, all audit-logged.

See [`USER_GUIDE.md`](USER_GUIDE.md) for how each of these actually feels to use, and
[`scheduler/docs/01-architecture.md`](scheduler/docs/01-architecture.md) for how it's built.

## Quick start (local, no API keys required)

```bash
cd scheduler/app
./dev.sh          # macOS / Linux / WSL2 — or dev.ps1 on native Windows PowerShell
```

This installs dependencies, creates a local SQLite `.env` with `SOCIAL_FAKE=true` and
`AI_FAKE=true`, generates the app key, migrates, builds the front end, and starts the web
server and queue worker together. Open http://localhost:8000, register, and make yourself a
platform admin:

```bash
php artisan tinker
>>> App\Models\User::first()->forceFill(['is_platform_admin' => true])->save();
```

For the full manual/Docker setup, environment reference, and troubleshooting, see
**[`INSTALL.md`](INSTALL.md)**.

### Running the tests

```bash
cd scheduler/app
composer install          # add --ignore-platform-req=ext-bcmath if bcmath is unavailable
npm install
cp .env.example .env
php artisan key:generate  # required — the suite 500s without an APP_KEY
npm run build             # required — Inertia pages need the Vite manifest
php artisan test
```

The `key:generate` and `npm run build` steps are genuine prerequisites, not optional: without
them every HTTP test fails with "No application encryption key" and "Vite manifest not found".

```bash
php artisan test --filter=TenantIsolationTest   # one suite
composer dev                                    # serve + queue + vite + logs in one terminal
```

## Production deployment

Docker Compose (`caddy`, `app`, `worker`, `scheduler`, `mysql`, `redis`) on a single VPS is the
supported, documented path — this is the exact stack running at `app.scheduleistic.com`. See:

- **[`scheduler/app/DEPLOYMENT_HOSTINGER.md`](scheduler/app/DEPLOYMENT_HOSTINGER.md)** — the
  step-by-step go-live runbook (DNS, server hardening, launch, backups, monitoring).
- **[`scheduler/docs/04-build-deploy-maintain-guide.md`](scheduler/docs/04-build-deploy-maintain-guide.md)**
  (also shipped as [`scheduler/docs/Scheduleistic-Guide.pdf`](scheduler/docs/Scheduleistic-Guide.pdf))
  for the deeper build history, white-label custom-domain TLS design, and day-to-day maintenance
  from VS Code Remote-SSH.
- **[`scheduler/app/.env.production.example`](scheduler/app/.env.production.example)** for the
  production environment template.

## Marketing site

Static, dependency-free, SEO/AEO-optimized — no build step. Preview locally:

```bash
cd marketing && python3 -m http.server 8080
```

See [`marketing/README.md`](marketing/README.md) for the current design system, content map, and
deployment notes.

## Scheduleistic 2.0 documentation

| Document | Covers |
| --- | --- |
| [`SCHEDULEISTIC_2_AUDIT.md`](scheduler/docs/SCHEDULEISTIC_2_AUDIT.md) | The pre-flight audit: baseline metrics, findings, and what 2.0 set out to change |
| [`SCHEDULEISTIC_2_ARCHITECTURE.md`](scheduler/docs/SCHEDULEISTIC_2_ARCHITECTURE.md) | Tenancy, entitlements, the publishing engine, the planner, and where things deliberately do not live |
| [`SCHEDULEISTIC_2_DATABASE.md`](scheduler/docs/SCHEDULEISTIC_2_DATABASE.md) | Every migration, the reasoning behind each index, and the compatibility guarantees |
| [`SCHEDULEISTIC_2_API.md`](scheduler/docs/SCHEDULEISTIC_2_API.md) | Routes, request shapes, and the outbound webhook contract |
| [`SCHEDULEISTIC_2_PROVIDER_CAPABILITIES.md`](scheduler/docs/SCHEDULEISTIC_2_PROVIDER_CAPABILITIES.md) | What each network supports — and what the platform does not claim to do |
| [`SCHEDULEISTIC_2_SECURITY.md`](scheduler/docs/SCHEDULEISTIC_2_SECURITY.md) | Tenant isolation, credentials, uploads, SSRF, idempotency, and what is not yet covered |
| [`SCHEDULEISTIC_2_TEST_PLAN.md`](scheduler/docs/SCHEDULEISTIC_2_TEST_PLAN.md) | Every suite, what it proves, and coverage against the brief |
| [`SCHEDULEISTIC_2_DEPLOYMENT.md`](scheduler/docs/SCHEDULEISTIC_2_DEPLOYMENT.md) | Requirements, environment variables, deploy steps, verification and rollback |
| [`SCHEDULEISTIC_2_RELEASE_NOTES.md`](scheduler/docs/SCHEDULEISTIC_2_RELEASE_NOTES.md) | What shipped, what did not, known risks, and the recommended next release |
| [`SCHEDULEISTIC_2_USER_GUIDE.md`](scheduler/docs/SCHEDULEISTIC_2_USER_GUIDE.md) | End-user walkthrough of the 2.0 surfaces |
| [`SCHEDULEISTIC_2_ADMIN_GUIDE.md`](scheduler/docs/SCHEDULEISTIC_2_ADMIN_GUIDE.md) | Operating the platform: plans, entitlements, media, health, webhooks, audit |

## Security

Threat model, tenant-isolation guarantees, and the hardening pass applied to the platform are in
[`scheduler/docs/03-security.md`](scheduler/docs/03-security.md), updated for 2.0 in
[`scheduler/docs/SCHEDULEISTIC_2_SECURITY.md`](scheduler/docs/SCHEDULEISTIC_2_SECURITY.md).

## License

[GNU General Public License v2.0](LICENSE).
