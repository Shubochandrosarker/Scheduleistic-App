# Installing Scheduleistic

This is the complete local installation guide for the Scheduleistic application
(`scheduler/app/`). For putting it live on a server, see
[`scheduler/app/DEPLOYMENT_HOSTINGER.md`](scheduler/app/DEPLOYMENT_HOSTINGER.md) instead — this
guide is about running it on your own machine.

Every method below gets you to the same place: a working app at `http://localhost:8000` with
**no OAuth apps, no Stripe account, and no LLM key required** — `SOCIAL_FAKE` and `AI_FAKE` swap
in in-memory fakes so you can register, connect "channels," compose, and publish end-to-end.

---

## 1. Prerequisites

| Tool | Version | Why |
| --- | --- | --- |
| PHP | 8.3+ (repo is tested on 8.4) | Runs the Laravel app. |
| PHP extensions | `bcmath`, `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `gd`, `zip` | Cashier math, SQLite driver, media handling. |
| Composer | 2.x | PHP dependency manager. |
| Node.js | 20+ (repo is tested on 22) | Builds the Vue/Inertia front end via Vite. |
| npm | bundled with Node | JS dependency manager. |

Optional, only if you want the full production-shaped stack locally:

| Tool | Why |
| --- | --- |
| Docker + Docker Compose | Runs `caddy` + `app` + `worker` + `scheduler` + `mysql` + `redis` exactly as in production. |

You do **not** need MySQL, Redis, or Docker for local development — the one-command and manual
paths below use SQLite and the Laravel `database` queue/cache driver.

---

## 2. Fastest path: one-command setup

```bash
cd scheduler/app
./dev.sh            # macOS / Linux / WSL2
# or, on native Windows PowerShell:
.\dev.ps1
```

What it does, step by step:

1. Checks for `php`, `composer`, `node`, `npm` and the required PHP extensions; stops with a
   clear message if any are missing.
2. `composer install` + `npm install`.
3. Creates `.env` from `.env.example` if one doesn't already exist, and forces
   `SOCIAL_FAKE=true`, `AI_FAKE=true`, `APP_URL=http://localhost:8000` in it. If `.env` already
   exists, it's left untouched.
4. Generates `APP_KEY` if not already set.
5. Creates `database/database.sqlite` and runs migrations.
6. Builds the front end (`npm run build`).
7. Starts `php artisan serve` **and** `php artisan queue:work` together (Ctrl+C stops both).

Run `./dev.sh setup` (or `.\dev.ps1 setup`) to do steps 1–6 only, without starting the servers —
useful if you'd rather run `composer dev` yourself (see below).

Once it's running, open **http://localhost:8000**, register an account, then promote yourself to
platform admin so you can see the `/admin` super-admin panel:

```bash
php artisan tinker
>>> App\Models\User::first()->forceFill(['is_platform_admin' => true])->save();
>>> exit
```

---

## 3. Manual setup

If you'd rather run each step yourself (or `dev.sh`/`dev.ps1` doesn't fit your shell):

```bash
cd scheduler/app
cp .env.example .env
composer install
npm install

php artisan key:generate
mkdir -p database && touch database/database.sqlite
php artisan migrate

npm run build              # one-time build, or `npm run dev` for hot-reload while you work
php artisan serve           # http://localhost:8000
```

In `.env`, set `SOCIAL_FAKE=true` and `AI_FAKE=true` if you want the zero-credential demo
experience described above. In a second terminal, run the queue worker so scheduled/queued posts
actually get "published" (to the fake provider):

```bash
php artisan queue:work --tries=3
```

Or run web server + queue worker + Vite + log tailing together in one terminal:

```bash
composer dev
```

---

## 4. Docker setup (local, production-shaped)

Use this if you want to test the exact stack that runs in production — Caddy, MySQL, Redis, a
separate worker and scheduler container:

```bash
cd scheduler/app
cp .env.example .env
php artisan key:generate         # APP_KEY must be set before the image is built
docker compose up -d --build
docker compose exec app php artisan migrate
```

The app is reachable at `http://localhost:8000` (the `app` service also publishes port 8000
directly; `caddy` fronts it on 80/443 using `APP_DOMAIN`/`MARKETING_DOMAIN`, which default to
`localhost`/`scheduleistic.localhost` for local use). The `worker` and `scheduler` containers
start automatically — no separate `queue:work`/`schedule:work` command needed.

```bash
docker compose ps                 # confirm all six services are up
docker compose logs -f worker      # watch the publishing engine
docker compose exec app php artisan tinker   # promote yourself to platform admin, same as above
```

---

## 5. Environment variables reference

`.env.example` is the source of truth; this groups the settings you're most likely to touch.

### Core
```
APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL
DB_CONNECTION (sqlite locally, mysql in Docker/production)
QUEUE_CONNECTION, CACHE_STORE, SESSION_DRIVER (database locally, redis in Docker/production)
MAIL_MAILER (log locally — emails print to the log instead of sending)
```

### Demo / fake mode (no external credentials needed)
```
SOCIAL_FAKE=true    # every network resolves to an in-memory FakeProvider
AI_FAKE=true        # AI caption/agent endpoints return deterministic stub output
```

### Branding & white-label
```
BRAND_PRIMARY, BRAND_SECONDARY, BRAND_LOGO, BRAND_FAVICON, BRAND_POWERED_BY
WHITE_LABEL_ENABLED, WHITE_LABEL_CUSTOM_DOMAIN, WHITE_LABEL_HIDE_POWERED_BY
```

### Billing (Stripe via Cashier)
```
STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, CASHIER_CURRENCY
STRIPE_PRICE_PRO, STRIPE_PRICE_AGENCY, STRIPE_PRICE_SCALE   # price IDs for the paid plans
```
Not needed for local dev unless you're testing checkout — plan limits themselves
(`config/plans.php`) work with no Stripe keys at all.

### Edge proxy / custom-domain TLS (Caddy, Docker only)
```
APP_DOMAIN, ACME_EMAIL
```

### AI caption assistant
```
AI_FAKE, AI_ENDPOINT, AI_API_KEY, AI_MODEL   # defaults to an OpenRouter-compatible endpoint
```

### Brain Gateway (optional — grounds AI in a team's own brand knowledge)
```
BRAIN_GATEWAY_ENABLED=false   # off by default; app works fully ungrounded
BRAIN_GATEWAY_URL, BRAIN_GATEWAY_SECRET, BRAIN_GATEWAY_FAKE
```

### Social network OAuth credentials
Only needed once you turn `SOCIAL_FAKE=false` and want to connect a real account. Each is
optional independently — a network without credentials simply won't be connectable yet.
```
LINKEDIN_CLIENT_ID/SECRET
FACEBOOK_CLIENT_ID/SECRET        # also powers Instagram + Threads unless overridden
INSTAGRAM_CLIENT_ID/SECRET
THREADS_CLIENT_ID/SECRET
GOOGLE_CLIENT_ID/SECRET          # powers Google Business Profile + YouTube
YOUTUBE_CLIENT_ID/SECRET
PINTEREST_CLIENT_ID/SECRET
TIKTOK_CLIENT_ID/SECRET
```
Mastodon, Bluesky, Medium, and WordPress are token/app-password based — users connect them
directly in the app UI with no developer app or `.env` entry required.

---

## 6. Verify the install

```bash
php artisan test
```

The full suite (169 tests) should pass, with 7 intentionally skipped — those are Jetstream's own
conditional tests for features this app disables (API tokens, self-service account deletion), and
skipping them is expected, not a failure.

---

## 7. Troubleshooting

| Symptom | Fix |
| --- | --- |
| `Vite manifest not found` when visiting the app or running tests | Run `npm run build` (or `npm run dev` and leave it running) — the Blade layout needs a built manifest. |
| Composer complains about `ext-bcmath` | Enable the `bcmath` PHP extension (`phpenv`/`apt install php-bcmath`/etc.) or install with `--ignore-platform-req=ext-bcmath` — a few Cashier-related tests need it. |
| `SQLSTATE[HY000] [14] unable to open database file` | `database/database.sqlite` doesn't exist yet — `mkdir -p database && touch database/database.sqlite`. |
| Posts never "publish" locally | Make sure a queue worker is running (`php artisan queue:work`, or `composer dev`, or the Docker `worker` service) — publishing always happens through the queue, never inline. |
| Can't see the **Admin** nav item | You haven't set `is_platform_admin` on your user yet — see the `tinker` command in §2. |
| 419 / session errors after changing `.env` | Run `php artisan config:clear` (or `config:cache` in production) after editing environment variables. |

---

## Next steps

- **Using the app:** [`USER_GUIDE.md`](USER_GUIDE.md) — onboarding, composing, scheduling,
  approvals, billing, AI, and admin, from the end user's point of view.
- **Going to production:** [`scheduler/app/DEPLOYMENT_HOSTINGER.md`](scheduler/app/DEPLOYMENT_HOSTINGER.md).
- **How it's built:** [`scheduler/docs/01-architecture.md`](scheduler/docs/01-architecture.md).
