# Social Scheduler (planning)

A multi-tenant, white-label, resellable social media scheduling platform — built in the spirit
of [Mixpost](https://mixpost.app) (self-hostable Laravel), with agency-grade team and client
management on top.

> **Status:** Design phase. No application code yet — these are the approved-design docs.
> Build begins after sign-off (see Phase 0 in the roadmap).

## What it will do
- Schedule every kind of social post across many networks
- Invite **team members** and assign them to clients
- Manage each **client's** own connected social accounts in isolation
- Sell as a **SaaS** (plans, billing, usage limits) and license **white-label**

## Docs
- [`docs/01-architecture.md`](docs/01-architecture.md) — full system design, data model,
  multi-tenancy, roles, integrations, white-label & SaaS layer, security, infra.
- [`docs/02-roadmap.md`](docs/02-roadmap.md) — phased build plan (Phase 0 → GA).

## Relationship to `../automation/`
The existing `automation/` pipeline (WordPress plugin + n8n + Postiz + AI prompts) is **glue
code** with no app layer. This product is the owned application that absorbs and replaces that
stack — the WordPress plugin and brand-voice prompts fold in as first-class integrations.
See Architecture §15.
