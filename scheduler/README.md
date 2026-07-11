# Scheduleistic — the platform

This directory holds the actual product: a multi-tenant, white-label, resellable social media
scheduling platform, built in the spirit of [Mixpost](https://mixpost.app) (self-hostable
Laravel) with agency-grade team and client management layered on top.

> **Status:** Shipped and deployed. Every phase in the original build plan — auth/tenancy,
> the publishing engine, the agency/approval layer, billing/white-label, the advanced feature
> set, and a security hardening pass — is complete and covered by the test suite. What's left is
> operational: real OAuth/Stripe credentials and a go-live runbook, not application code.

## What it does today

- Schedules posts across **13 social networks** from one composer, with per-network overrides.
- Lets an agency invite **team members** and assign them to specific clients.
- Isolates each **client's** own connected social accounts, content, and approvals in their own
  workspace.
- Sells itself as a **SaaS** — Stripe billing, four plans, enforced usage limits.
- Supports **white-label** resale — per-tenant branding and a custom domain with automatic HTTPS.

## Layout

| Path | What's in it |
| --- | --- |
| [`app/`](app) | The Laravel application. See [`app/README.md`](app/README.md) for the full, phase-by-phase feature breakdown and local dev instructions. |
| [`docs/`](docs) | Reference documentation (below). |

## Docs

- [`docs/01-architecture.md`](docs/01-architecture.md) — current system design: multi-tenancy,
  data model, roles, the provider/publishing engine, white-label & SaaS layer.
- [`docs/02-roadmap.md`](docs/02-roadmap.md) — what shipped in each phase, and what's next.
- [`docs/03-security.md`](docs/03-security.md) — threat model and the hardening pass applied.
- [`docs/04-build-deploy-maintain-guide.md`](docs/04-build-deploy-maintain-guide.md) — the
  complete build/deploy/maintain manual (also as a PDF: [`docs/Scheduleistic-Guide.pdf`](docs/Scheduleistic-Guide.pdf)).
  The PDF is a pre-rendered copy, not built by CI — regenerate it after editing the guide with
  `python3 ../../tools/md2pdf.py docs/04-build-deploy-maintain-guide.md docs/Scheduleistic-Guide.pdf`.
- [`app/DEPLOYMENT_HOSTINGER.md`](app/DEPLOYMENT_HOSTINGER.md) — the step-by-step production
  go-live runbook.

For installation and the end-user guide, see the repository root:
[`../INSTALL.md`](../INSTALL.md) and [`../USER_GUIDE.md`](../USER_GUIDE.md).

## Relationship to `../automation/`

`../automation/` is the **legacy** pipeline (WordPress plugin + n8n + Postiz) this product
replaces. It has no database, users, or billing of its own — it was glue code standing in for the
application layer that now lives here. The WordPress integration and brand-voice AI prompts it
pioneered were absorbed as first-class, native features (`app/Social/Providers/WordPressProvider.php`,
`app/Services/RssIngestService.php`, and `config/ai.php`) — see architecture doc §15. Nothing in
`app/` calls out to `automation/`; it's kept in the repo for historical reference only.
