# Social Scheduler — Build Roadmap

Phased plan to go from zero to a sellable, white-label SaaS. Each phase is shippable.
Effort estimates assume one focused developer; treat them as relative sizing, not promises.

---

## Phase 0 — Foundation (scaffold)
**Goal:** a running Laravel app with auth, tenancy, and the deployable shell.

- Laravel 11 + Inertia + Vue 3 + Tailwind scaffold
- Auth (Fortify/Jetstream): register, login, email verify, 2FA, password reset
- Organizations (tenancy) + global scoping + `organization_id` migrations baseline
- Spatie roles/permissions wired (Owner/Admin/Member/Approver/Client)
- Docker Compose (app, worker, scheduler, mysql, redis, reverb)
- CI: Pest + PHPStan

**Done when:** you can sign up, create an org, invite a user, and log in — in Docker.

---

## Phase 1 — MVP Scheduler (single workspace usable)
**Goal:** connect a few networks and schedule real posts reliably.

- Workspaces (clients) CRUD + member assignment + timezone
- Channel OAuth connect flow + encrypted token storage + refresh worker
- **MVP providers:** LinkedIn, X, Facebook, Instagram (others queued for Phase 4)
- Post composer v1: compose, per-network customization, media upload, preview
- Scheduling engine: schedule exact time → cron dispatcher → `PublishPostJob` →
  retries/backoff → rate-limit buckets → failed-state visibility
- Calendar (month/week/list) + drag-to-reschedule
- Media library v1 (upload, reuse, alt text)

**Done when:** a post scheduled in the UI publishes to all 4 MVP networks on time, and a
failure is visible and retryable.

---

## Phase 2 — Agency & Collaboration
**Goal:** the "team members + clients" workflow you described.

- Multi-workspace management at scale, per-workspace assignments
- Approval workflows (submit → approve/reject + comments)
- Client role (approve-only / own-workspace-only) + client portal view
- Internal comments, @mentions, activity feed
- Notifications: in-app + email (+ optional Slack/Telegram)
- Queues & time-slot templates ("best time" auto-fill), recurring posts, bulk CSV import

**Done when:** you can run multiple clients with team members and client approvals end-to-end.

---

## Phase 3 — SaaS & White-Label control plane
**Goal:** turn it into a product you can sell and resell.

- Billing (Cashier + Stripe **or** Paddle): plans, trials, usage limits, invoices, dunning
- Usage metering + plan feature flags enforced via middleware
- Per-tenant branding (logo, colors, app name, email templates)
- Custom domains + automated TLS
- Super-admin panel (Filament): manage orgs/plans/flags, impersonate, suspend, metrics
- White-label license model (managed + self-host license key)

**Done when:** a new customer can self-serve sign up, pay, hit limits, and a reseller can run
a fully branded instance.

---

## Phase 4 — Advanced & Differentiators
**Goal:** match/exceed Mixpost + agency-grade depth.

- More providers: TikTok, YouTube, Pinterest, Google Business, Threads, Mastodon, Bluesky, Medium
- Analytics & reporting + scheduled white-label PDF client reports
- AI Assistant (reuse brand-voice prompts): caption/variant gen, hashtags, rewrite, multi-language
- Media auto-crop per network; first comment; threads/carousels; stories/reels specifics
- **WordPress/RSS/article scheduling** (fold in existing WP plugin) + inbound automation
- Optional Postiz/Ayrshare driver for fast network coverage

**Done when:** feature depth is competitive with Mixpost and the article-scheduling story is live.

---

## Phase 5 — Hardening & GA
- Security review + tenant-isolation tests + audit logging coverage
- Performance/scale testing of the publishing engine
- GDPR export/delete, data retention controls
- Docs: admin guide, white-label setup, API docs, reseller onboarding
- Backups, monitoring, alerting, runbook (evolve the existing `runbook.md`)

---

## Suggested sequencing decisions (need your input — see Architecture §16)
- **Billing:** Stripe vs Paddle (Paddle recommended for solo global selling).
- **MVP networks:** confirm the 4 above or adjust.
- **White-label first:** managed vs self-host license.
- **Default brand name** for the resold skin.

---

## Immediate next step after you approve this plan
Start **Phase 0**: scaffold the Laravel/Inertia app + Docker + auth + tenancy, committed to this
branch in a `scheduler/app/` directory, with the existing WP plugin and prompts wired in as
first-class integrations.
