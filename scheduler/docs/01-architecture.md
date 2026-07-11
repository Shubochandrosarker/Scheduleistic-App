# Scheduleistic — Architecture & System Design

**Type:** Multi-tenant, white-label, resellable SaaS for social media scheduling & management.
**Inspiration:** Mixpost (self-hostable Laravel scheduler) + agency-grade client/team management.
**Status:** Current — this document describes the shipped, deployed application, not a proposal.
For what changed release-by-release, see [`02-roadmap.md`](02-roadmap.md).

---

## 1. What this is

Scheduleistic is a full SaaS platform, not "just a scheduler":

1. **Use it yourself** to schedule every kind of social post across 13 networks.
2. **Run it as an agency** — invite team members, assign them to clients, manage each client's
   own connected social accounts in isolation.
3. **Sell it as a SaaS** — paying customers on metered plans with Stripe billing.
4. **License it white-label** — a tenant can run the dashboard on their own branded domain.

It ships as one Laravel monolith: auth, multi-tenancy, billing, role-based access, a post
composer, a reliable publishing engine, analytics, client approval workflows, AI assistance, and
a super-admin control plane.

---

## 2. Tech stack (as built)

| Layer | What's actually running |
|---|---|
| **Backend framework** | Laravel 13, PHP 8.4. |
| **Frontend** | Inertia.js + Vue 3 + Tailwind CSS, built with Vite — one repo, SPA feel, no separate API/frontend split. |
| **Database** | MySQL 8 in Docker/production; SQLite for local dev and CI. |
| **Cache / Queue / Sessions** | Redis in production (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`); the Laravel `database` driver locally, so local dev needs no Redis. |
| **Background jobs** | Plain Laravel Queues — a `queue:work` worker process (no Horizon). |
| **Scheduler** | Laravel's task scheduler via `schedule:work`, dispatching `posts:dispatch-due` every minute plus the other console commands below. |
| **Auth + teams** | Laravel Jetstream (Fortify under the hood): email/password, 2FA, email verification, teams. Team = **Organization**. |
| **Roles & permissions** | Jetstream's own role/permission system (`Jetstream::role(...)` in `app/Providers/JetstreamServiceProvider.php`) — **not** a separate ACL package. Workspace-level roles are a plain `role` column on the `workspace_user` pivot. |
| **Billing** | Laravel Cashier (Stripe only — no Paddle driver). |
| **Media storage** | Local disk by default (`FILESYSTEM_DISK=local`); swappable to S3-compatible storage via Laravel's filesystem config. |
| **AI** | Pluggable OpenAI-compatible chat endpoint (`config/ai.php`), defaults to OpenRouter. No realtime/websocket layer is implemented. |
| **Deployment** | Docker Compose: `caddy` (edge/TLS), `app`, `worker` (queue), `scheduler` (cron), `mysql`, `redis`. |

---

## 3. High-Level Architecture

```
                          ┌────────────────────────────────────────────┐
                          │            Browser (Vue 3 SPA)               │
                          │  Dashboard · Composer · Calendar · Analytics │
                          └───────────────────────┬──────────────────────┘
                                                  │ Inertia (HTTPS)
                          ┌───────────────────────▼──────────────────────┐
                          │              Laravel Application               │
                          │  Auth · Tenancy · Composer · Billing · Admin   │
                          └───┬───────────────┬───────────────────┬───────┘
                              │               │                   │
                  ┌───────────▼───┐   ┌───────▼────────┐   ┌──────▼───────────┐
                  │     MySQL     │   │     Redis      │   │  Local / S3      │
                  │ (tenant data) │   │ queue · cache  │   │  media storage   │
                  └───────────────┘   │  · sessions    │   └──────────────────┘
                                      └───────┬────────┘
                                              │ jobs
                          ┌───────────────────▼──────────────────────────┐
                          │   Queue Worker + Scheduler (Docker services)   │
                          │  PublishPostJob · FetchMetricsJob · PollFeeds  │
                          └───────────────────┬──────────────────────────┘
                                              │ OAuth2 / platform APIs
   ┌──────────┬──────────┬──────────┬─────────▼────────┬──────────┬──────────┬──────────┐
   │ LinkedIn │ Facebook │Instagram │  Google Business  │Pinterest │  TikTok  │ YouTube  │ +6 more
   └──────────┴──────────┴──────────┴──────────────────┴──────────┴──────────┴──────────┘
```

The web app never publishes inline. It only enqueues jobs. The `worker` service owns all outbound
platform calls so publishing is retryable, rate-limited, and observable; the `scheduler` service
only decides *what's due* and hands off to the queue.

---

## 4. Multi-Tenancy Model

Three nested levels — this is what makes it agency- and resale-ready.

```
Team ("teams" table — a Jetstream team = an Organization / tenant)
│   └─ Cashier Billable: stripe_id, subscription, plan, branding json, custom_domain, suspended_at
│
├── Members (Users with an org-level Jetstream role: admin, member, approver, client)
│
└── Workspaces  ("workspaces" table — clients / brands)
        │   team_id → teams.id
        │
        ├── Channels (connected social accounts — provider, encrypted tokens)
        ├── Posts → PostVersions → PostTargets
        ├── Approvals, Comments
        ├── TimeSlots (queue templates)
        └── Feeds (RSS/WordPress ingestion)
        └── workspace_user pivot: which users can see this workspace, and their
            workspace-level role (member | approver | client)
```

- **Tenancy strategy:** single database, row-level scoping by `team_id` (workspaces) and
  `workspace_id` (everything under a workspace), enforced in controllers via the
  `AuthorizesWorkspaceAccess` concern (`guardWorkspace()` / `guardPost()`) rather than a global
  Eloquent scope.
- A **User** can belong to multiple teams (Jetstream's standard multi-team support).
- A **client** is modeled as a `workspace_user` row with `role = 'client'`, scoped to exactly one
  workspace — never assigned org-wide.

---

## 5. Roles & Permissions

| Capability | Owner | Admin | Team Member | Approver | Client |
|---|:--:|:--:|:--:|:--:|:--:|
| Manage billing & plan | ✅ | – | – | – | – |
| Manage organization settings / branding | ✅ | ✅ | – | – | – |
| Invite/remove users & set roles | ✅ | ✅ | – | – | – |
| Create/delete workspaces (clients) | ✅ | ✅ | – | – | – |
| Connect/disconnect social channels | ✅ | ✅ | assigned only | – | – |
| Create & schedule posts | ✅ | ✅ | assigned only | – | assigned only |
| Approve / reject posts | ✅ | ✅ | – | assigned only | assigned only |
| View analytics | ✅ | ✅ | assigned only | assigned only | assigned only |
| Access all workspaces | ✅ | ✅ | assigned only | assigned only | own only |

The Owner is implicitly the team creator and has every permission. `admin`, `member`, `approver`,
and `client` are Jetstream org-level roles defined in `JetstreamServiceProvider`; a user's actual
reach into a given workspace is additionally gated by their `workspace_user.role` (or plain
membership) and enforced server-side by `AuthorizesWorkspaceAccess` on every workspace-scoped
controller. Billing checkout/portal is owner-only.

---

## 6. Core Data Model (entities)

Reflects the actual migrations under `database/migrations/`. All tenant tables trace back to a
`team_id` (directly, or via `workspace_id → workspaces.team_id`).

- **teams** (Jetstream) — name, `stripe_id`/subscription columns (Cashier), `branding` json,
  `custom_domain` (+ verification token/verified_at), `suspended_at`.
- **users** — name, email, password, 2FA secret, `is_platform_admin` (not mass-assignable).
- **team_user** (Jetstream) — org membership + org-level role.
- **workspaces** — `team_id`, name, `client_name`, timezone, color, logo.
- **workspace_user** — `workspace_id`, `user_id`, `role` (member/approver/client).
- **channels** — `workspace_id`, provider, `provider_account_id`, name, encrypted access/refresh
  tokens, scopes, meta, status.
- **posts** — `workspace_id`, author, status (draft/pending_approval/scheduled/publishing/
  published/partially_failed/failed), `scheduled_at`, `recurring_rule`, `parent_post_id`,
  `group_id` (bulk/recurring linkage).
- **post_versions** — per-provider content variant: content, media, options (arrays).
- **post_targets** — one `post` × one `channel`: status, `provider_post_id`, error, attempts,
  `published_at`.
- **approvals** — post, requester, approver, state (pending/approved/rejected/changes_requested).
- **comments** — post, user, body, mentions.
- **time_slots** — workspace, day_of_week, time (queue templates).
- **feeds** — workspace, RSS/WordPress source, `seen_guids`, `last_polled_at`.
- **analytics_metrics** — post_target/channel, metric, value, captured_at.
- **team_invitations** (Jetstream) — pending invites.

There is **no** separate `plans`/`subscriptions` table — plans and their limits live in
`config/plans.php`, and subscriptions are Cashier's own `subscriptions`/`subscription_items`
tables against the `teams` billable.

---

## 7. Core Modules (feature areas)

### 7.1 Onboarding & Auth
Register, login, email verification, 2FA, team creation/invitations — stock Jetstream, themed.

### 7.2 Workspaces / Client Management
Create a workspace per client, set timezone/branding, assign team members, connect that client's
own social accounts. Full isolation between clients (`WorkspaceController`).

### 7.3 Channel Connections
Per-provider OAuth2 connect flow (`ChannelController`), or manual token entry for token-based
networks (Mastodon, Bluesky, Medium, WordPress). Tokens encrypted at rest; state validated on
OAuth callback.

### 7.4 Post Composer
Compose once, customize per network, attach media, schedule or save as draft
(`PostComposer` service). Models: `Post`, `PostVersion`, `PostTarget`.

### 7.5 Scheduling & Publishing Engine
`posts:dispatch-due` runs every minute, atomically claims due posts, and dispatches one queued
`PublishPostJob` per target — with retries/backoff and independent per-target success/failure
that rolls up into the post's overall status (`published` / `partially_failed` / `failed`).

### 7.6 Calendar & Queues
Time-slot templates (`TimeSlot` + `QueueScheduler`) so "add to queue" fills the next free posting
slot per workspace, skipping collisions.

### 7.7 Approval Workflows
`ApprovalService`: submit → pending → approve / reject / request-changes, with an audit trail and
`post.approval_state` kept in sync. Clients only ever act on their own workspace.

### 7.8 Collaboration
Per-post `Comment` thread with @mentions; mail + database notifications on submit/decision/
publish-failure.

### 7.9 Recurring posts & bulk import
`RecurrenceService` creates the next occurrence automatically (idempotent) after a recurring post
publishes. `BulkImporter` turns a CSV of `content,scheduled_at,providers` rows into many posts.

### 7.10 Analytics
`AnalyticsService` + the `analytics_metrics` table; an hourly `analytics:fetch` job captures
engagement per published target via each provider's `fetchMetrics()`.

### 7.11 Media auto-crop
`MediaCropService` computes centered per-network crop specs from a source image.

### 7.12 RSS / WordPress → social
`RssIngestService` + the `feeds` table: `feeds:poll` ingests new articles and auto-drafts social
posts, de-duplicated by GUID, with an SSRF guard on the feed URL.

### 7.13 AI Assistant
`AiAssistant` (caption generation) and `PostAiAgents` (Pro+ agents: rewrite/cleanup, hashtag
optimization, quality check) against a pluggable LLM. Optionally grounded in a team's own brand
knowledge via `BrainGatewayClient`, which also reports real usage back to the external
`wpistic-ai-gateway` — both disabled by default and fully optional.

---

## 8. White-Label & SaaS Control Plane

### 8.1 Branding per tenant
Name, tagline, colors, logo stored on the `teams` row (`BrandingController`), merged over
platform defaults and shared to the whole UI via an Inertia `branding` prop.

### 8.2 Billing & Plans (Laravel Cashier + Stripe)
Four plans in `config/plans.php` — **Free, Pro, Agency, Scale** — each with limits (workspaces,
channels, members, monthly posts) and feature flags (`client_approval`, `white_label`,
`analytics` tier, `ai_captions`, `ai_agents`). `PlanService` + `UsageService` enforce limits when
creating workspaces, connecting channels, and scheduling posts; `BillingController` drives Stripe
Checkout and the billing portal. The organization (`Team`) is the billable entity.

### 8.3 Custom domains + automatic TLS
An org owner adds a domain; the app stores it with a verification token. `domains:verify`
(`DomainVerificationService`) checks a DNS `TXT` record every 5 minutes. Once verified, Caddy's
on-demand TLS asks the app's `/tls/check` endpoint before issuing a Let's Encrypt certificate —
so only verified tenant domains (and the platform's own) ever get a cert. `ResolveTenantDomain`
middleware then serves that tenant's branding by host, including the guest login page.

### 8.4 Super-admin control plane
A plain Inertia/Vue panel under `/admin` (`Admin/OrganizationController`, gated by the
`platform.admin` middleware / `EnsurePlatformAdmin` and the non-mass-assignable
`is_platform_admin` flag): list every organization, suspend/unsuspend, and impersonate an owner
for support — every impersonation is audit-logged, and nested impersonation is rejected.

---

## 9. Platform Integration Matrix

Built as pluggable provider drivers, one class per network implementing the common
`SocialProvider` contract (`app/Social/Contracts/SocialProvider.php`), resolved through
`ProviderManager` (`app/Social/ProviderManager.php`). OAuth-based drivers share
`AbstractOAuthProvider`; token-based drivers share `AbstractTokenProvider`.

| Provider | Auth | Class |
|---|---|---|
| LinkedIn (personal) | OAuth2 | `LinkedInProvider` |
| LinkedIn (company page) | OAuth2 | `LinkedInCompanyProvider` |
| Facebook Pages | OAuth2 (Meta) | `FacebookProvider` |
| Instagram | OAuth2 (Meta Graph) | `InstagramProvider` |
| Google Business Profile | OAuth2 (Google) | `GoogleBusinessProvider` |
| Pinterest | OAuth2 | `PinterestProvider` |
| Threads | OAuth2 (Meta) | `ThreadsProvider` |
| TikTok | OAuth2 | `TikTokProvider` |
| YouTube | OAuth2 (Google) | `YouTubeProvider` |
| Mastodon | App password / token | `MastodonProvider` |
| Bluesky | App password / token | `BlueskyProvider` |
| Medium | API token | `MediumProvider` |
| WordPress | App password | `WordPressProvider` |
| — (local/dev only) | none | `FakeProvider` — every provider resolves to this when `SOCIAL_FAKE=true`, so the full connect → compose → publish flow works with zero OAuth credentials. |

---

## 10. Scheduling/Publishing Engine Internals

1. **Cron (every minute):** the `posts:dispatch-due` command (`DispatchDuePosts`) selects posts
   where `scheduled_at <= now` and `status = scheduled`, atomically marks them `publishing`, and
   dispatches one `PublishPostJob` per `post_target`.
2. **Per-target job:** calls the provider driver's `publish()`; on success stores
   `provider_post_id` + `published_at`; on failure records the error and retries with backoff,
   then surfaces a `failed` target.
3. **Rollup:** a post's overall status becomes `published`, `partially_failed`, or `failed`
   depending on how its targets resolved — one bad channel never blocks the others.
4. **Analytics:** `analytics:fetch` (`FetchMetricsJob`) runs hourly against targets published in
   the last 30 days.
5. **Feeds:** `feeds:poll` runs every 15 minutes against active RSS/WordPress feeds.
6. **Domains:** `domains:verify` runs every 5 minutes against pending custom domains.

Observability: structured logs plus an in-app failed-jobs view (`queue:failed`); no external APM
is wired in by default.

---

## 11. Security & Compliance

See [`03-security.md`](03-security.md) for the full threat model and the hardening pass applied
(mass-assignment defense, SSRF guard, suspension enforcement, AI throttling, impersonation audit
logging). In short: OAuth tokens are encrypted at rest and hidden from the frontend, every
workspace-scoped action is guarded server-side, and 2FA/CSRF/session security come from Jetstream
and Laravel's defaults.

---

## 12. Infrastructure & Deployment

**Self-host package:** `docker-compose.yml` with `caddy`, `app`, `worker`, `scheduler`, `mysql`,
`redis`. `.env`-driven config. This is the exact stack documented in
[`04-build-deploy-maintain-guide.md`](04-build-deploy-maintain-guide.md) and
[`../app/DEPLOYMENT_HOSTINGER.md`](../app/DEPLOYMENT_HOSTINGER.md) and running at
`app.scheduleistic.com`.

CI (`.github/workflows/ci.yml`): PHP 8.4 + Node 22, installs dependencies, builds the front end,
and runs the full test suite against SQLite on every push/PR.

---

## 13. Non-Functional Requirements

- **Reliability:** no missed/duplicate posts; failed posts always visible & retryable
  (`queue:failed`).
- **Security:** encrypted tokens, strict tenant isolation (tested), audit trail for sensitive
  actions.
- **Extensibility:** a new network is one driver class implementing `SocialProvider`; a new AI
  provider is a config change to `config/ai.php`.
- **White-label:** zero code changes to rebrand a tenant — branding and domain are data, not code.
- **Self-host friendly:** one `docker compose up -d --build` brings up the whole stack.

---

## 14. How the legacy `automation/` assets folded in

| Legacy asset | Became |
|---|---|
| `automation/wp-plugin/wpistic-content-automation.php` | The native **WordPress provider** (`app/Social/Providers/WordPressProvider.php`) and inbound RSS ingestion (`app/Services/RssIngestService.php`). |
| `automation/prompts/brand-voice-prompts.md` | Seed prompts for the AI Assistant (`config/ai.php`) — the file is still credited in a code comment there. |
| n8n workflow logic (stagger, idempotency, fan-out, retry/recovery) | Ported natively into the publishing engine (§10) — no n8n dependency remains. |
| Platform list, image-dimension table, rate-limit notes | Direct inputs to the provider drivers and `MediaCropService`. |

The application fully absorbs and replaces the n8n/ClickUp/Postiz stack described in
`automation/`; that directory is kept only for historical reference (see
[`../README.md`](../README.md)).
