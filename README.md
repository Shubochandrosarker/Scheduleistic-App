# Scheduleistic

A production-ready, multi-tenant **social media scheduling SaaS** for agencies,
creators, and small businesses — plan, approve, and publish social content from
one clean workspace.

- **Marketing site:** https://scheduleistic.com
- **App:** https://app.scheduleistic.com

## Repository layout

| Path                | What it is                                                                 |
| ------------------- | ------------------------------------------------------------------------- |
| `scheduler/app/`    | The Laravel 13 + Inertia/Vue 3 + Tailwind SaaS application                 |
| `scheduler/docs/`   | Architecture, security, and build/deploy/maintain guides                  |
| `marketing/`        | The static marketing website (`scheduleistic.com`) — plain HTML/CSS       |
| `automation/`       | Companion n8n workflows + WordPress plugin + prompt library               |

## Quick start (the app)

```bash
cd scheduler/app
cp .env.example .env
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate
php artisan test          # 131 passing
composer dev              # serve + queue + vite + logs
```

Set `SOCIAL_FAKE=true` in `.env` to connect channels and publish end-to-end with
**no** OAuth credentials (uses an in-memory fake provider) — ideal for local demos.

## Production deployment

Docker Compose (`caddy`, `app`, `worker`, `scheduler`, `mysql`, `redis`) on a
Hostinger VPS. See **[`scheduler/app/DEPLOYMENT_HOSTINGER.md`](scheduler/app/DEPLOYMENT_HOSTINGER.md)**
for the full two-domain (marketing + app) runbook, and
`scheduler/app/.env.production.example` for the production environment template.

## Marketing site

Static, dependency-free, SEO/AEO-optimised. Preview locally:

```bash
cd marketing && python3 -m http.server 8080
```

See [`marketing/README.md`](marketing/README.md) for deployment and editing notes.

## License

See [`LICENSE`](LICENSE).
