# Scheduleistic — Application

**Complete Social Automated System**
_Powered by WPistic — A product of the WORDPRESSISTIC Ecosystem_

The shipped application: a white-label, multi-tenant social scheduling platform, architecture
described in full in [`../docs/01-architecture.md`](../docs/01-architecture.md). Built as a
Laravel monolith with **native integrations only** (no third-party publishers). The sections
below trace how it was built, phase by phase — see [`../docs/02-roadmap.md`](../docs/02-roadmap.md)
for the delivery history and what's still genuinely open.

## Stack
- **Laravel 13** (PHP 8.4+)
- **Jetstream + Inertia + Vue 3 + Tailwind** — auth, 2FA, passkeys, email verification,
  organizations (Jetstream "teams") with invitations and roles
- **MySQL** + **Redis** (queue / cache / sessions) in Docker
- **Stripe** (Cashier) for billing — wired in Phase 3

## What Phase 0 delivers
- Authentication: register, login, password reset, email verification, 2FA, passkeys
- **Organizations** (Jetstream teams) = an agency / tenant / white-label account
- **Roles**: Owner, Administrator, Team Member, Approver, Client (see
  `app/Providers/JetstreamServiceProvider.php`)
- **Tenancy layer**: `Workspace` (a client/brand) → `Channel` (a connected social account),
  with encrypted-at-rest OAuth tokens
- Branding config (`config/scheduleistic.php`) + white-label env switches
- Docker stack: `app`, `worker` (queue), `scheduler` (cron), `mysql`, `redis`
- CI (`../../.github/workflows/ci.yml`) running the test suite on every push/PR — **154 of 161
  tests passing** (7 skipped by design for Jetstream features this app disables, e.g. API tokens
  and account self-deletion; 0 failing)

## Local development

### Quick start (one command)
```bash
cd scheduler/app
./dev.sh            # macOS / Linux / WSL2   (PHP 8.3+, Composer, Node 20+)
# or on native Windows PowerShell:
.\dev.ps1
```
This installs deps, creates a local **sqlite** `.env` with `SOCIAL_FAKE=true` + `AI_FAKE=true`
(so you can connect channels and publish end-to-end with **no real API keys**), generates the app
key, migrates, builds the front-end, and runs the web server **and** queue worker together.
Then open http://localhost:8000, register, and make yourself admin:
```bash
php artisan tinker
>>> App\Models\User::first()->forceFill(['is_platform_admin' => true])->save();
```
Use `./dev.sh setup` to do setup only (no servers). Local queue/cache/session use the database, so
**no Redis is required**.

### Manual
```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
touch database/database.sqlite      # or configure MySQL in .env
php artisan migrate
npm run dev                          # in one terminal
php artisan serve                    # in another → http://localhost:8000
```

## Docker (self-host / managed)
```bash
cp .env.example .env
php artisan key:generate             # APP_KEY must be set before building
docker compose up -d --build
docker compose exec app php artisan migrate
# App on http://localhost:8000  ·  worker + scheduler run automatically
```

## Tests
```bash
php artisan test
```

## Domain model (Phase 0)
```
Team (Organization / tenant)
 └── Workspace (client / brand)         team_id → teams
       ├── Channel (social account)     workspace_id → workspaces  (tokens encrypted)
       └── workspace_user (assignment + role: member|approver|client)
```

## What Phase 1 adds
- **Pluggable provider drivers** (`app/Social/`): a `SocialProvider` contract + `ProviderManager`
  registry. Drivers for **LinkedIn (personal + company), Facebook, Instagram, Google Business
  Profile**, plus a `FakeProvider` for local/dev (`SOCIAL_FAKE=true`).
- **OAuth connect/disconnect flows** per workspace (`ChannelController`), with CSRF-style state
  validation and tokens encrypted at rest.
- **Post composer**: compose once, pick channels, per-network overrides, schedule or save draft
  (`PostComposer` service + composer UI). Models: `Post`, `PostVersion`, `PostTarget`.
- **Scheduling/publishing engine**: `posts:dispatch-due` (scheduled every minute) atomically
  claims due posts and dispatches one queued `PublishPostJob` per target — with retries/backoff,
  per-channel rate limiting, and independent per-target success/failure that rolls up into the
  post status (`published` / `partially_failed` / `failed`).
- **UI**: Workspaces, Channels, Composer, and Posts (calendar/list) pages + nav.

### Turn on live posting
Add the OAuth app credentials to `.env` (`LINKEDIN_CLIENT_ID`, `FACEBOOK_CLIENT_ID`,
`GOOGLE_CLIENT_ID`, …). Until then, set `SOCIAL_FAKE=true` to exercise the full flow with no
network calls.

## What Phase 2 adds (agency layer)
- **Approval workflows** (`ApprovalService`): submit → pending → approve / reject /
  request-changes, with an `Approval` audit trail and `post.approval_state` kept in sync.
- **Client portal**: clients are assigned to a workspace (`workspace_user` role) and see/act on
  **only** their workspace; org owners/admins see everything. Enforced by
  `AuthorizesWorkspaceAccess`.
- **Collaboration**: per-post `Comment` thread with @mentions.
- **Notifications** (mail + database): approver on submit, author on decision, author on publish
  failure.
- **Queues & best-time slots** (`TimeSlot` + `QueueScheduler`): "add to queue" fills the next
  free posting slot, skipping collisions.
- **Recurring posts** (`RecurrenceService`): daily/weekly/monthly — the next occurrence is created
  automatically after a recurring post publishes (idempotent).
- **Bulk CSV import** (`BulkImporter`): upload `content,scheduled_at,providers` rows to create
  many posts at once.

## What Phase 3 adds (monetize + white-label)
- **Stripe billing** (Laravel Cashier): the **organization (team) is the billable entity** —
  `Team` is `Billable`, customer columns + subscriptions live on `teams`/`team_id`. Checkout +
  billing-portal routes (`BillingController`).
- **Plans, usage limits & feature gating** (`config/plans.php`, `PlanService`, `UsageService`):
  free / pro / agency tiers with per-resource limits (workspaces, channels, members,
  monthly posts). Enforced when creating workspaces, connecting channels, and scheduling posts.
- **Per-tenant branding** (`BrandingController`): app name, tagline, colors, logo stored on the
  organization and shared to the whole UI via Inertia (`branding` prop), merged over platform
  defaults.
- **Custom domains** (CNAME): set a domain + auto-issued verification token on the organization.
- **Super-admin control plane** (`/admin`, `platform.admin` middleware): list every organization,
  suspend/unsuspend, and impersonate an owner for support.

### Billing env
Set `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, and the plan price ids
`STRIPE_PRICE_PRO` / `STRIPE_PRICE_AGENCY`. Mark yourself as a platform operator by setting
`users.is_platform_admin = true`.

## What Phase 4 adds (advanced features)
- **More networks**: Pinterest, Threads, TikTok, YouTube, Mastodon, Bluesky, Medium, WordPress —
  13 providers total, all behind the same `SocialProvider` contract.
- **AI caption assistant** (`AiAssistant` + `config/ai.php`): per-network, brand-voice copy from a
  pluggable LLM (OpenRouter/OpenAI-compatible). `AI_FAKE=true` for a local stub. Composer has a
  "✨ Generate" button; `POST /ai/generate` returns captions.
- **Analytics & reporting** (`AnalyticsService`, `analytics_metrics`): providers expose
  `fetchMetrics()`; an hourly `analytics:fetch` job captures engagement per published target; the
  Analytics dashboard shows org-wide totals.
- **Media auto-crop** (`MediaCropService`): computes centered per-network crop specs from a source
  image (geometry unit-tested; pixel crop performed by the worker).
- **WordPress / RSS article scheduling** (`RssIngestService`, `feeds` table, `feeds:poll`):
  feeds auto-draft social posts from new articles, de-duplicated by guid — the article→social
  pipeline, folding in the WORDPRESSISTIC ecosystem.

### AI env
`AI_FAKE`, `AI_ENDPOINT`, `AI_API_KEY`, `AI_MODEL`.

## Security
See [`../docs/03-security.md`](../docs/03-security.md) for the threat model, controls, and the
Phase 5 hardening pass (mass-assignment defense, SSRF guard, suspension enforcement, AI throttle,
impersonation audit logging).

## Deploying & operating
See [`../docs/04-build-deploy-maintain-guide.md`](../docs/04-build-deploy-maintain-guide.md)
(also available as a PDF in `scheduler/docs/Scheduleistic-Guide.pdf`) for the full go-live runbook
(Hostinger VPS), OAuth/Stripe/AI setup, custom-domain white-label TLS, and VS Code maintenance.

## Custom-domain white-label TLS
Tenants can run the dashboard on their own domain with automatic HTTPS. They add a `TXT`
ownership record (`_scheduleistic.<domain>`) plus a `CNAME` to the platform domain; the scheduler
verifies it (`domains:verify`), and Caddy issues a Let's Encrypt certificate on demand — gated by
the app's `/tls/check` endpoint so only verified domains get certs. `ResolveTenantDomain` serves
each tenant's branding by host (including the guest login page).

## Roadmap
See [`../docs/02-roadmap.md`](../docs/02-roadmap.md). All roadmap phases plus security hardening and
custom-domain TLS are complete. Remaining work is operational: obtaining real OAuth/Stripe
credentials and running the go-live runbook.
