# Scheduleistic — Build Roadmap

Every phase below shipped as a focused, tested pull request. This is the actual delivery history,
not a forward-looking plan — see [`01-architecture.md`](01-architecture.md) for how the shipped
system works, and §7 below for what's genuinely still open.

---

## Phase 0 — Foundation ✅
**Delivered:** a running Laravel app with auth, tenancy, and a deployable shell.

- Laravel 13 + Jetstream (Fortify) + Inertia + Vue 3 + Tailwind
- Auth: register, login, email verify, 2FA, password reset
- Organizations (Jetstream teams) + `team_id`-scoped tenancy
- Jetstream roles/permissions (Owner/Admin/Member/Approver/Client)
- Docker Compose (app, worker, scheduler, mysql, redis)
- CI running the test suite on every push/PR

---

## Phase 1 — MVP Scheduler ✅
**Delivered:** connect networks and schedule real posts reliably.

- Workspaces (clients) CRUD + member assignment + timezone
- Channel OAuth connect flow + encrypted token storage
- Launch providers: LinkedIn, Facebook, Instagram, Google Business Profile
- Post composer v1: compose, per-network customization, media, preview
- Scheduling engine: cron dispatcher → `PublishPostJob` → retries/backoff → per-target
  failure visibility
- `FakeProvider` + `SOCIAL_FAKE=true` for a zero-credential local/demo flow

---

## Phase 2 — Agency & Collaboration ✅
**Delivered:** the team-members-plus-clients workflow.

- Approval workflows (submit → approve/reject + comments), `Approval` audit trail
- Client role, scoped to exactly one workspace (`workspace_user.role = client`)
- Internal comments with @mentions
- Mail + database notifications (submit, decision, publish failure)
- Time-slot queues, recurring posts, bulk CSV import

---

## Phase 3 — SaaS & White-Label control plane ✅
**Delivered:** turned the app into something sellable and resellable.

- Stripe billing (Cashier): the **organization** is the billable entity
- Four plans (`config/plans.php`) — Free, Pro, Agency, Scale — with usage limits and feature
  flags enforced by `PlanService` / `UsageService`
- Per-tenant branding (name, colors, logo)
- Custom-domain storage + verification
- Super-admin panel (`/admin`): list organizations, suspend/unsuspend, impersonate

---

## Phase 4 — Advanced & Differentiators ✅
**Delivered:** feature depth beyond a bare scheduler.

- 8 more provider drivers: Pinterest, Threads, TikTok, YouTube, Mastodon, Bluesky, Medium,
  WordPress — 13 networks total
- AI caption assistant (`AiAssistant`) + Pro+ AI agents (`PostAiAgents`: rewrite, hashtags,
  quality check) against a pluggable LLM, with `AI_FAKE` for offline dev
- Analytics (`AnalyticsService`, hourly `analytics:fetch`)
- Media auto-crop (`MediaCropService`)
- RSS/WordPress → social ingestion (`RssIngestService`, `feeds:poll`)

---

## Phase 5 — Security Hardening ✅
**Delivered:** a full audit pass and the fixes it produced.

- Mass-assignment defense on `is_platform_admin`
- SSRF guard on feed URLs (`SsrfGuard`), including DNS resolution and private-range checks
- Organization suspension enforcement (`EnsureOrganizationActive`) that still allows billing
- AI endpoint throttling (20/min) requiring an organization context
- Impersonation hardening: nested impersonation rejected, every impersonation audit-logged

Full writeup: [`03-security.md`](03-security.md).

---

## Phase 6 — Custom-domain white-label TLS ✅
**Delivered:** tenants can run the dashboard on their own domain with automatic HTTPS.

- DNS `TXT` ownership verification (`domains:verify`, every 5 minutes)
- Caddy on-demand TLS gated by the app's `/tls/check` endpoint
- `ResolveTenantDomain` middleware serving each tenant's branding by host

---

## Phase 7 — AI Brain Gateway grounding ✅
**Delivered:** the AI assistant and post agents can optionally ground their output in a team's
own brand knowledge, and report real usage back to a shared dashboard.

- `BrainGatewayClient` — HMAC-signed integration with the external `wpistic-ai-gateway`
- `/v1/brain/search` grounding for generations; `reportUsage()` for tokens/provider/model/refusals
- Disabled by default (`BRAIN_GATEWAY_ENABLED=false`), fails soft — AI features work fully
  ungrounded with no gateway configured

---

## Phase 8 — Platform admin control plane, schedule history & platform activation ✅
**Delivered:** the operator-facing control plane the product never had, a real "schedule
history" feature, and the code-level fixes blocking every social network from actually working.
Full writeup, including architecture decisions, the route/authorization inventory, and known
follow-ups: [`CONTROL_PLANE_ACTIVATION_AUDIT.md`](CONTROL_PLANE_ACTIVATION_AUDIT.md) (the audit
and plan) and [`CONTROL_PLANE_DELIVERY_REPORT.md`](CONTROL_PLANE_DELIVERY_REPORT.md) (the report).

- Impersonation hardening: password confirmation, real audit-log rows (start/stop/expiry/
  break-glass), session regeneration, a maximum lifetime, and a fixed set of billing/credential/
  destructive actions blocked outright while impersonating
- Platform admin surface: an overview dashboard, paginated/filterable user and organization
  management, an organization detail page (plan overrides, entitlement grants, subscription and
  branding summary), and a read-only audit-log viewer — all additive to `users`/`teams` via two
  new migrations, nothing renamed or restructured
- Schedule history, all three forms: the admin audit-log viewer above, a user-facing paginated
  post/publish history with CSV export and per-campaign/tag breakdowns, and a channel
  health timeline showing resolved (not just open) connection problems
- Owner-side gaps closed: a settings hub linking every settings destination, the base-vs-
  effective plan bug fixed everywhere it was silently wrong (sidebar, dashboard, an external AI
  integration), and server-side seat-limit enforcement at invite time
- All 13 social provider drivers fixed at the code level: TikTok's OAuth parameter bug, token
  auto-renewal wired into the hourly health check, Meta's long-lived-token exchange for
  Facebook/Instagram/Threads, real page/location/board/organization resolution for five
  networks that previously picked blindly or never resolved one at all, media wiring for three
  of the eight text-only drivers, an SSRF guard on WordPress publishing, and new test coverage
  for the three networks (Facebook, Instagram, Threads) that had none

## What's next (genuinely open)

Nothing on this list blocks running the product; it's the difference between "feature-complete"
and "polished for scale":

- **Go-live operational work** — real OAuth app credentials and the underlying platform
  approvals per network (Meta App Review + Business Verification, Google OAuth verification,
  a TikTok content-posting audit — see `04-build-deploy-maintain-guide.md` §5.1 for real
  timelines), a live Stripe account, and a production deploy. The code-level blockers are
  resolved as of Phase 8 above; what remains is external and cannot be sped up by more code.
  See [`04-build-deploy-maintain-guide.md`](04-build-deploy-maintain-guide.md)
  and [`../app/DEPLOYMENT_HOSTINGER.md`](../app/DEPLOYMENT_HOSTINGER.md).
- **Follow-up platform-activation work** (Phase B, deliberately deferred as feature-sized, not
  bug-sized): a real YouTube resumable video upload, Instagram carousel/video support, media
  wiring for the remaining five text-only drivers, and a reconnect-free account switcher for
  Facebook/Instagram (Google Business, Pinterest, and LinkedIn Company already have one).
- **GDPR data export/delete** — noted as an open follow-up in `03-security.md` for organizations
  operating in the EU.
- **Edge-level rate limiting (WAF)** — app-level throttles exist; nothing sits in front of them
  at the network edge yet.
- **White-label license-key model** — the *managed* white-label path (branding + custom domain,
  you host, you bill) is fully built; a *self-hosted license-key* resale model is not.
- **Scheduled client-facing PDF reports** — analytics are viewable in-app; there's no automated
  report export yet.
