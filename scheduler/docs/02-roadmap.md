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

## What's next (genuinely open)

Nothing on this list blocks running the product; it's the difference between "feature-complete"
and "polished for scale":

- **Go-live operational work** — real OAuth app credentials per network, a live Stripe account,
  and a production deploy. See [`04-build-deploy-maintain-guide.md`](04-build-deploy-maintain-guide.md)
  and [`../app/DEPLOYMENT_HOSTINGER.md`](../app/DEPLOYMENT_HOSTINGER.md).
- **GDPR data export/delete** — noted as an open follow-up in `03-security.md` for organizations
  operating in the EU.
- **Edge-level rate limiting (WAF)** — app-level throttles exist; nothing sits in front of them
  at the network edge yet.
- **White-label license-key model** — the *managed* white-label path (branding + custom domain,
  you host, you bill) is fully built; a *self-hosted license-key* resale model is not.
- **Scheduled client-facing PDF reports** — analytics are viewable in-app; there's no automated
  report export yet.
