# SOCIALISTIC — Application

**Complete Social Automated System**
_Powered by WPistic — A product of the WORDPRESSISTIC Ecosystem_

Phase 0 scaffold of the white-label, multi-tenant social scheduling platform described in
[`../docs/01-architecture.md`](../docs/01-architecture.md). Built as a Laravel monolith with
**native integrations only** (no third-party publishers).

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
- Branding config (`config/socialistic.php`) + white-label env switches
- Docker stack: `app`, `worker` (queue), `scheduler` (cron), `mysql`, `redis`
- CI (`../../.github/workflows/ci.yml`) running the test suite — **46 tests green**

## Local development
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

## Roadmap
See [`../docs/02-roadmap.md`](../docs/02-roadmap.md). Next up: **Phase 1** — channel OAuth
connect flows (LinkedIn personal + company, Facebook, Instagram, Google Business Profile),
the post composer, and the scheduling/publishing engine.
