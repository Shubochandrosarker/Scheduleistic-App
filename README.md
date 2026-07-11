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
| [`scheduler/docs/`](scheduler/docs) | Architecture, security posture, and the full build/deploy/maintain guide (also as a PDF). |
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
- **Billing & plans** — Free, Pro, Agency, and Scale tiers (`config/plans.php`) with enforced
  limits on workspaces, channels, members, and monthly posts — no code change needed to reprice.
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

```bash
php artisan test    # run the suite
composer dev        # serve + queue + vite + logs, all in one terminal
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

## Security

Threat model, tenant-isolation guarantees, and the hardening pass applied to the platform are in
[`scheduler/docs/03-security.md`](scheduler/docs/03-security.md).

## License

[GNU General Public License v2.0](LICENSE).
