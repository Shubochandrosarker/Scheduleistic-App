# Social Scheduler — Architecture & System Design

**Product codename:** Wpistic Social (working title — fully rebrandable, see White-Label section)
**Type:** Multi-tenant, white-label, resellable SaaS for social media scheduling & management
**Inspiration:** Mixpost (self-hostable Laravel scheduler) + agency-grade client/team management
**Status:** Architecture proposal — _no application code written yet. Approve this before build._

---

## 1. Product Vision

A social media management platform you can:

1. **Use yourself** to schedule every kind of social post across many networks.
2. **Run as an agency** — invite team members, assign them to clients, manage each client's
   own connected social accounts in isolation.
3. **Sell as a SaaS** — sign up paying customers with plans, usage limits, and billing.
4. **License white-label** — resell the whole product under a buyer's brand, logo, and domain
   (the "white-label deal" you mentioned), either self-hosted or managed by you.

This is **not** "just a scheduler." It is a full SaaS platform: auth, multi-tenancy, billing,
role-based access, an advanced post composer, a reliable publishing engine, analytics,
client approval workflows, AI assistance, and a super-admin control plane.

---

## 2. Why this is bigger than the current repo

The existing `automation/` folder is **glue code** (a WordPress plugin + n8n workflows) that
pushes blog posts into **Postiz** and **ClickUp**. It has no app of its own — no database, UI,
users, teams, or billing. This new product is the **application layer** those tools stand in for.

The good news: several existing assets fold directly into the new product (see §15).

---

## 3. Tech Stack Decision

| Layer | Choice | Why |
|---|---|---|
| **Backend framework** | **Laravel 11 (PHP 8.3)** | Matches Mixpost; mature SaaS ecosystem; self-hostable as one package (key for white-label resale). |
| **Frontend** | **Inertia.js + Vue 3 + Tailwind CSS** | SPA feel without a separate API/frontend repo; same stack Mixpost uses; fast to build. |
| **Database** | **MySQL 8 / MariaDB** (PostgreSQL supported) | Ubiquitous on shared & VPS hosting buyers will use. |
| **Cache / Queue / Locks** | **Redis** | Drives the scheduling engine, rate-limit buckets, idempotency. |
| **Background jobs** | **Laravel Queues + Horizon** | The publishing engine runs entirely on workers. |
| **Scheduler** | **Laravel Scheduler (cron)** | Dispatches due posts every minute. |
| **Auth + teams** | **Laravel Fortify/Jetstream + Spatie Laravel-Permission** | Email/password, 2FA, social login, RBAC. |
| **Billing** | **Laravel Cashier (Stripe)** + optional Paddle driver | Subscriptions, plans, usage metering, invoices. |
| **Media storage** | **S3-compatible** (AWS S3 / Cloudflare R2 / MinIO) | Cheap, scalable; local disk fallback for self-host. |
| **Realtime** | **Laravel Reverb / Pusher** | Live calendar, notifications, collaboration. |
| **AI** | **OpenRouter / OpenAI / Anthropic** (pluggable) | Reuse existing brand-voice prompts for caption generation. |
| **Deployment** | **Docker Compose** (app + worker + redis + db + reverb) | One-command self-host; the unit you license to white-label buyers. |

### Alternatives considered
- **Next.js + Supabase** (also available in this session): excellent for a *cloud-only* SaaS, but
  harder to package as a self-hostable white-label unit and diverges from the PHP/Mixpost model
  you prefer. Kept as a fallback if you decide cloud-only.
- **Extending Postiz/n8n** (your option C): fastest, but you'd never own the core scheduler UI or
  the billing/white-label layer — a dead end for a product you want to sell.

> **Decision:** Laravel monolith (modular). Revisit only if you explicitly want cloud-only SaaS.

---

## 4. High-Level Architecture

```
                          ┌────────────────────────────────────────────┐
                          │            Browser (Vue 3 SPA)               │
                          │  Dashboard · Composer · Calendar · Analytics │
                          └───────────────────────┬──────────────────────┘
                                                  │ Inertia (HTTPS)
                          ┌───────────────────────▼──────────────────────┐
                          │              Laravel Application               │
                          │  Auth · RBAC · Tenancy · Composer · Billing    │
                          │  REST/Internal API · Webhooks · Admin panel    │
                          └───┬───────────────┬───────────────────┬───────┘
                              │               │                   │
                  ┌───────────▼───┐   ┌───────▼────────┐   ┌──────▼───────────┐
                  │  MySQL/Maria  │   │     Redis      │   │  S3 / R2 media   │
                  │ (tenant data) │   │ queue·cache·   │   │  (images/video)  │
                  └───────────────┘   │ locks·ratelim  │   └──────────────────┘
                                      └───────┬────────┘
                                              │ jobs
                          ┌───────────────────▼──────────────────────────┐
                          │      Queue Workers (Horizon) + Scheduler       │
                          │  PublishPostJob · RefreshTokenJob · Analytics  │
                          └───────────────────┬──────────────────────────┘
                                              │ OAuth2 / platform APIs
   ┌──────────┬──────────┬──────────┬─────────▼────────┬──────────┬──────────┬──────────┐
   │ LinkedIn │    X     │ Facebook │    Instagram     │ Pinterest│  TikTok  │ YouTube  │ ...
   └──────────┴──────────┴──────────┴──────────────────┴──────────┴──────────┴──────────┘
```

The web app **never** publishes inline. It only enqueues jobs. Workers own all outbound
platform calls so publishing is retryable, rate-limited, and observable.

---

## 5. Multi-Tenancy Model

Three nested levels — this is what makes it agency- and resale-ready.

```
Organization (tenant / a paying account, e.g. an agency or a white-label buyer's customer)
│   └─ has a subscription, plan limits, branding, billing
│
├── Members (Users with org-level roles: Owner, Admin, Member)
│
└── Workspaces  ("clients" / brands)               ← your "add my client's platforms"
        │   └─ isolates one client's channels, posts, media, approvals
        │
        ├── Channels (connected social accounts: LinkedIn page, IG account, etc.)
        ├── Posts / Drafts / Scheduled / Published
        ├── Media library
        ├── Approval flows
        └── Workspace memberships (which team members & clients can see this workspace)
```

- **Tenancy strategy:** single database, **row-level scoping** by `organization_id` enforced via a
  global Eloquent scope + middleware (simple, cheap, proven). Optional DB-per-tenant later for
  enterprise/self-host buyers.
- A **User** can belong to multiple organizations (for your own super-admin + reseller scenarios).
- A **client** is modeled as a Workspace plus User(s) with the `client` role limited to that workspace.

---

## 6. Roles & Permissions

You asked for Owner/Admin, Team members, and Clients. Full matrix:

| Capability | Owner | Admin | Team Member | Approver | Client |
|---|:--:|:--:|:--:|:--:|:--:|
| Manage billing & plan | ✅ | – | – | – | – |
| Manage organization settings / branding | ✅ | ✅ | – | – | – |
| Invite/remove users & set roles | ✅ | ✅ | – | – | – |
| Create/delete workspaces (clients) | ✅ | ✅ | – | – | – |
| Connect/disconnect social channels | ✅ | ✅ | ✅* | – | ✅* |
| Create & schedule posts | ✅ | ✅ | ✅ | – | ✅* |
| Approve / reject posts | ✅ | ✅ | ✅* | ✅ | ✅* |
| View analytics | ✅ | ✅ | ✅ | ✅ | ✅* |
| Access all workspaces | ✅ | ✅ | assigned only | assigned only | own only |

`*` = only within workspaces they're assigned to. Built on **Spatie Permission** with
workspace-scoped role assignment. Roles are customizable per organization (advanced plans).

---

## 7. Core Data Model (entities)

> Field lists are representative, not exhaustive. All tenant tables carry `organization_id`.

- **organizations** — name, slug, plan, branding json, custom_domain, trial_ends_at
- **users** — name, email, password, 2fa, avatar
- **organization_user** — org membership + role
- **workspaces** — organization_id, name, client_name, timezone, color, logo
- **workspace_user** — assignment + workspace role (member/approver/client)
- **channels** — workspace_id, provider (linkedin/x/…), provider_account_id, name, avatar,
  access_token (encrypted), refresh_token (encrypted), token_expires_at, scopes, status
- **posts** — workspace_id, author_id, status (draft/pending_approval/scheduled/publishing/
  published/failed), scheduled_at, timezone, recurring_rule, approval_state, source (manual/
  rss/wordpress/ai), group_id
- **post_versions** — post_id, channel_id (or provider), content, media[], options json
  (per-network customization: first comment, thread, alt text, link, location, board, etc.)
- **post_targets** — post_id + channel_id + per-target status, provider_post_id, error, published_at
- **media** — workspace_id, disk path, mime, width/height, alt, variants (auto-cropped per network)
- **approvals** — post_id, requested_by, approver_id, state, comment, decided_at
- **comments** — post_id, user_id, body (internal collaboration + @mentions)
- **calendar_slots / queue_categories** — per-channel time-slot templates for "best time" queues
- **analytics_metrics** — post_target_id/channel_id, metric, value, captured_at
- **plans / subscriptions / usage_counters** — billing & limits
- **audit_logs** — actor, action, subject, ip, meta (security/compliance)
- **integrations** — RSS feeds, WordPress sites, webhooks
- **notifications** — in-app + email queue

---

## 8. Core Modules (feature areas)

### 8.1 Onboarding & Auth
Email/password, social login, email verification, 2FA, invite acceptance, org creation wizard.

### 8.2 Workspaces / Client Management  ← _"add my clients' platforms"_
Create a workspace per client, set timezone/branding, assign team members, connect that client's
own social accounts. Full isolation between clients.

### 8.3 Channel Connections (OAuth)
Per-provider OAuth2 connect flow, token storage (encrypted at rest), auto-refresh worker,
reconnect prompts on expiry, health status badges. See §10 for the provider matrix.

### 8.4 Post Composer (the heart)
- Compose once, **customize per network** (different text/media per platform from one editor).
- Media attach with drag-drop; auto-validation against each network's limits.
- **Threads** (X/Threads/LinkedIn carousels), **first comment**, hashtag groups, mentions,
  link shortening, emoji, UTM tagging.
- Network-specific options: IG reels/stories, Pinterest board+link, YouTube title/visibility,
  TikTok privacy, GBP CTA button, location tagging, alt text.
- Live previews per network. Save as draft / template.

### 8.5 Scheduling Engine
- Schedule at exact time, add to a **queue** (auto-fills next best slot from time-slot templates),
  **recurring** posts, **bulk CSV import**, and **smart stagger** to avoid spam flags
  (carried over from the existing n8n logic).
- Per-workspace timezone handling. Calendar drag-to-reschedule.
- Reliability: due-dispatcher cron → `PublishPostJob` per target → retries w/ backoff →
  rate-limit buckets per channel → failures surfaced + optional auto-retry.

### 8.6 Calendar & Queue Views
Month/week/list calendar, color-coded by workspace/channel/status, filterable, drag-reschedule.

### 8.7 Approval Workflows  ← _team + client review_
Submit → pending → approver/client approves or requests changes (with comments) → schedules.
Clients can be limited to approve-only on their own workspace. Email + in-app notifications.

### 8.8 Media Library
Per-workspace asset library, auto-crop variants per network dimension (the n8n "future
enhancement" becomes built-in), alt text, reuse across posts, optional stock/Canva integration.

### 8.9 Analytics & Reporting
Per-post and per-channel metrics (reach, engagement, clicks, follower growth), workspace
dashboards, scheduled **white-label PDF reports** for clients, best-time recommendations.

### 8.10 Article / RSS / WordPress Scheduling  ← _Mixpost-like + your existing automation_
- RSS feed ingestion → auto-draft social posts.
- **WordPress integration** via the existing plugin (publish → auto-create social drafts).
- "Article scheduling": queue website articles and auto-syndicate to social.

### 8.11 AI Assistant
Caption/variant generation per network using the **existing brand-voice prompts**, hashtag
suggestions, rewrite/shorten, multi-language. Pluggable provider; usage metered per plan.

### 8.12 Collaboration & Notifications
Internal comments, @mentions, activity feed, email + in-app + optional Slack/Telegram alerts.

---

## 9. White-Label & SaaS Control Plane

This is what turns it into a sellable product.

### 9.1 Branding per tenant
Logo, favicon, color theme, app name, email "from" name/templates, login page, **custom domain**
(CNAME + automated TLS). All driven by `organizations.branding` + a theming layer.

### 9.2 Billing & Plans (Laravel Cashier + Stripe)
- Plans with **feature flags & usage limits**: # workspaces, # channels, # team members,
  # scheduled posts/month, AI credits, analytics retention.
- Trials, coupons, proration, dunning, invoices, tax. Paddle driver as alt (better for global
  digital sales / MoR).
- **Usage metering** enforced via middleware + counters; graceful "upgrade" prompts.

### 9.3 Reseller / White-Label deal models
1. **Managed white-label:** you host; reseller gets a branded org + custom domain, you bill them.
2. **Self-hosted license:** ship the Docker package + a license key check; reseller runs it on
   their own server under their brand. (License server is a small add-on module.)

### 9.4 Super-Admin panel
A separate `/admin` (Filament-based) for **you** as platform owner: manage organizations,
plans, feature flags, impersonate for support, view global metrics, suspend tenants, manage
white-label licenses, broadcast announcements.

---

## 10. Platform Integration Matrix

Built as **pluggable provider drivers** (one class per network implementing a common
`SocialProvider` contract: `connect`, `refresh`, `publish`, `fetchMetrics`, `limits`).

| Provider | Auth | Notable build notes |
|---|---|---|
| **LinkedIn** | OAuth2 | Personal + Org pages; doc-share for articles. |
| **X (Twitter)** | OAuth2 | Threads; paid API tier limits — handle 429 + tier detection. |
| **Facebook Pages** | OAuth2 (Meta) | Page tokens, long-lived token refresh. |
| **Instagram** | OAuth2 (Meta Graph) | Business/Creator only; feed/reels/stories; 2-step media container publish. |
| **Pinterest** | OAuth2 | Board required; pin + destination link. |
| **TikTok** | OAuth2 | Content Posting API; video processing/polling. |
| **YouTube** | OAuth2 (Google) | Resumable video upload; title/visibility/schedule. |
| **Google Business Profile** | OAuth2 (Google) | Location-scoped; CTA buttons; ~1 post/day norm. |
| **Threads** | OAuth2 (Meta) | Newer API; similar to IG container flow. |
| **Mastodon / Bluesky** | OAuth2 / app-pw | Easy wins, popular with agencies. |
| **Medium** | API token | Article cross-posting (carried from current setup). |
| **WordPress** | App password / plugin | Inbound (RSS/plugin) + outbound article publish. |

Optional escape hatch: a **Postiz/Ayrshare driver** so you can ship faster on networks whose
direct APIs aren't built yet, then replace with native drivers over time. (Reuses your existing
Postiz knowledge from this repo.)

---

## 11. Scheduling/Publishing Engine Internals

1. **Cron (every minute):** `PublishDuePosts` selects posts where `scheduled_at <= now` and
   `status=scheduled`, marks `publishing`, dispatches one `PublishPostJob` per `post_target`.
2. **Per-target job:** acquires a Redis rate-limit token for that channel → calls the provider
   driver → on success stores `provider_post_id` + `published_at`; on failure records error and
   retries with exponential backoff (configurable), then routes to a **failed** state + alert.
3. **Idempotency:** `group_id` + per-target unique lock prevents double-posting on retries/restart
   (same principle as the current WP plugin's idempotency meta).
4. **Token refresh:** scheduled `RefreshExpiringTokensJob`; reconnect notifications on hard failures.
5. **Analytics:** periodic `FetchMetricsJob` per published target.

Observability: Horizon dashboard, structured logs, an in-app "Activity/Failures" view replacing
the old Google-Sheet + ClickUp "Failed Posts" lists.

---

## 12. Security & Compliance

- **OAuth tokens encrypted at rest** (Laravel encryption / KMS), never exposed to frontend.
- **RBAC** enforced server-side on every action; workspace scoping via global query scopes.
- **Audit log** for sensitive actions (connect/disconnect, publish, role changes, billing).
- **Tenant isolation** verified by automated tests (no cross-org data leakage).
- **GDPR:** data export + delete per org; DPA-ready; configurable data retention.
- Webhook signature verification (reuse the HMAC pattern from the WP plugin).
- 2FA, rate limiting on auth, password policies, session management.

---

## 13. Infrastructure & Deployment

**Self-host package (the white-label unit):** `docker-compose.yml` with services:
`app` (php-fpm + nginx) · `worker` (Horizon) · `scheduler` (cron) · `mysql` · `redis` ·
`reverb` (websockets). Object storage via S3/R2 or MinIO. `.env`-driven config; install wizard.

**Managed multi-tenant SaaS (you host):** same image, horizontally scaled app + worker tiers,
managed MySQL + Redis, S3/R2, queue autoscaling, custom-domain TLS automation.

CI: tests + static analysis (Pest + PHPStan) + build → image registry.

---

## 14. Non-Functional Requirements

- **Reliability:** no missed/duplicate posts; failed posts always visible & retryable.
- **Scale target (initial):** thousands of scheduled posts/day across hundreds of channels.
- **Security:** encrypted tokens, strict tenant isolation, audit trail.
- **Extensibility:** new network = one driver class. New AI provider = one adapter.
- **White-label:** zero code changes to rebrand a tenant.
- **Self-host friendly:** one-command Docker bring-up.

---

## 15. How existing repo assets fold in

| Existing asset | Reused as |
|---|---|
| `wp-plugin/wpistic-content-automation.php` | Official **WordPress integration** (inbound article → social drafts; HMAC pattern → webhook security). |
| `prompts/brand-voice-prompts.md` | Seed prompts for the **AI Assistant** (§8.11). |
| n8n workflow logic (stagger, idempotency, fan-out, retry/recovery) | Ported natively into the **publishing engine** (§11) — no n8n dependency. |
| `docs/postiz-setup.md` | Basis for an optional **Postiz/aggregator driver** (§10) for fast network coverage. |
| Platform list, image-dimension table, rate-limit table | Direct inputs to provider drivers + media auto-crop. |

The new product **absorbs and replaces** the n8n/ClickUp/Postiz stack with owned, sellable code.

---

## 16. Open Questions (for you)

1. **Billing processor:** Stripe (great if you have a company/entity) vs **Paddle** (Merchant of
   Record — handles global tax for you; better for solo selling worldwide). Recommendation: Paddle
   if selling globally solo, else Stripe.
2. **Launch networks:** which of the §10 platforms must be in the **MVP** vs later? (Building all
   12 natively is a lot — recommend an MVP set, e.g. LinkedIn, X, Facebook, Instagram.)
3. **White-label primary model:** managed (you host) vs self-hosted license — which first?
4. **Cloud vs self-host emphasis:** confirms Laravel monolith (recommended) vs pivot to
   Next.js+Supabase cloud-only.
5. **Branding:** keep "Wpistic" as the default skin, or a neutral name since it's resold?

These don't block starting Phase 0/1 — see `02-roadmap.md`.
