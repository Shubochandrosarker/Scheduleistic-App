# Scheduleistic 2.0 — Deployment

Verified against the 2.0 branch. Commands run from `scheduler/app`.

---

## 1. Server requirements

| Requirement | Notes |
| --- | --- |
| PHP | **8.3+** (tested on 8.4) |
| PHP extensions | `bcmath`, `gd`, `fileinfo`, `exif`, `intl`, `mbstring`, `pdo_*`, `zip`, `redis` |
| Database | MySQL 8+ / MariaDB 10.6+ / PostgreSQL 14+ (SQLite for local only) |
| Redis | Queues + cache |
| Node | 20+ to build assets |
| Web server | Caddy (on-demand TLS for tenant custom domains) or nginx |

**`ext-bcmath` is required** — Laravel Cashier will not install without it.
This was discovered during the 2.0 audit: the container it was audited in could
not install bcmath, and Composer only completed with
`--ignore-platform-req=ext-bcmath`. Do **not** carry that flag into
production; install the extension.

**`ext-gd` is required for media derivatives.** Without it uploads still
succeed and are marked ready — they simply render from the original with no
thumbnails.

---

## 2. Environment variables

### New in 2.0

```dotenv
# Media library
MEDIA_DISK=s3                 # or "local"; defaults to FILESYSTEM_DISK
MEDIA_MAX_UPLOAD_MB=512       # hard per-upload ceiling
MEDIA_AV_COMMAND=             # optional, e.g. "clamdscan --no-summary --stdout"

# Stripe price ids for the new plan tiers
STRIPE_PRICE_SOLO=
STRIPE_PRICE_ENTERPRISE=

# Optional media import sources — a source is only offered in the UI when its
# credentials are present, so the interface never advertises a dead integration
CANVA_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_ID=
DROPBOX_APP_KEY=
UNSPLASH_ACCESS_KEY=
GIPHY_API_KEY=
```

### Carried over from 1.x (unchanged)

`APP_KEY`, `APP_URL`, `DB_*`, `REDIS_*`, `QUEUE_CONNECTION=redis`,
`CACHE_STORE=redis`, `SESSION_DRIVER`, `MAIL_*`, `AWS_*`,
`STRIPE_KEY`/`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET`,
`STRIPE_PRICE_PRO`/`_AGENCY`/`_SCALE`, `BRAND_*`, `WHITE_LABEL_*`,
`SOCIAL_FAKE`, `AI_FAKE`, and the per-provider OAuth credentials.

### S3-compatible storage

```dotenv
FILESYSTEM_DISK=s3
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=…
AWS_SECRET_ACCESS_KEY=…
AWS_DEFAULT_REGION=…
AWS_BUCKET=…
AWS_URL=https://cdn.example.com      # CDN-compatible public URLs
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Media URLs are generated per request and signed where the driver supports it,
so object keys are never exposed and cannot be guessed across tenants.

---

## 3. Deploying

```bash
cd scheduler/app

# 1. Back up first — the rollback path drops 2.0 tables.
mysqldump -u… -p… scheduleistic > backup-pre-2.0.sql

# 2. Maintenance window (migrations add indexes to `posts`, which locks on
#    large tables in MySQL).
php artisan down --render=errors::503

# 3. Code + dependencies
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# 4. Schema
php artisan migrate --force

# 5. Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Restart workers so they pick up the new job classes
php artisan queue:restart

php artisan up
```

### Index creation on large tables

`2026_07_30_000004` adds four composite indexes to `posts`. On MySQL with more
than ~1M rows, create them out-of-band first with `ALGORITHM=INPLACE,
LOCK=NONE` and then run the migration — it will be a no-op for the indexes that
already exist.

---

## 4. Queue workers

2.0 adds two job classes. If you run dedicated workers per queue, both use the
default queue unless you route them.

```ini
# supervisor
[program:scheduleistic-worker]
command=php /var/www/scheduleistic/scheduler/app/artisan queue:work redis --sleep=1 --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
```

Media processing is CPU-bound (GD image encoding). On a busy install, give it
its own queue and workers:

```php
ProcessMediaAssetJob::dispatch($asset->id)->onQueue('media');
```

---

## 5. Scheduler

One cron entry drives everything:

```cron
* * * * * cd /var/www/scheduleistic/scheduler/app && php artisan schedule:run >> /dev/null 2>&1
```

| Command | Frequency | Purpose |
| --- | --- | --- |
| `posts:dispatch-due` | every minute | Publishing heartbeat |
| `analytics:fetch` | hourly | Engagement metrics |
| `feeds:poll` | 15 minutes | RSS/WordPress ingestion |
| `domains:verify` | 5 minutes | Custom-domain DNS verification |
| **`channels:check-health`** | **hourly** | **New in 2.0** — flags expiring tokens before they break a publish |

---

## 6. Post-deploy verification

```bash
php artisan about                       # framework, cache and queue drivers
php artisan migrate:status | tail -6    # the five 2.0 migrations are "Ran"
php artisan channels:check-health       # should report a profile count
php artisan queue:monitor redis:default --max=100
```

In the browser:

1. `/planner` renders, and switching month → week issues a new request.
2. `/media` accepts an upload; the card shows "Processing…" and then a
   thumbnail once a worker picks it up.
3. `/social-profiles/health` lists profiles with a state.
4. `/webhooks` (Agency+) creates an endpoint and shows the secret once.
5. A locked navigation item names the plan that unlocks it.

---

## 7. Dependency advisories at release

| Package | Severity | Fix |
| --- | --- | --- |
| `guzzlehttp/guzzle` ×4 | medium | `composer update guzzlehttp/guzzle` — transitive, resolves on a lock refresh |
| `axios` | high | `npm audit fix` — build-time dependency |
| `postcss` | high | `npm audit fix` — build-time dependency |

None are critical and none are introduced by 2.0, but all should be cleared
before a public launch. They were not bundled into this change so the diff
stays reviewable.

---

## 8. Rollback

**The 2.0 migrations are reversible, and rolling back destroys 2.0 data**
(campaigns, ideas, media assets, health history, webhook configuration and
delivery logs). Restore from the backup taken in step 1 rather than relying on
`migrate:rollback` if that data matters.

```bash
php artisan down
git checkout <previous-release-tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate:rollback --step=5      # drops the five 2.0 migrations
php artisan config:cache && php artisan route:cache
php artisan queue:restart
php artisan up
```

Partial rollback — keeping the schema, reverting the code — also works, because
every 2.0 column is nullable or defaulted and 1.x ignores them.

### Rolling back only the plan ladder

If the new tiers are wrong for your billing setup, revert `config/plans.php`
alone. `PlanService` reads limits and capabilities from config at request time,
so no data migration is involved. Existing `teams.entitlements` overrides
continue to apply.

---

## 9. Caddy (unchanged from 1.x)

On-demand TLS still asks `/tls/check` before issuing a certificate, which
approves only the platform domain and verified tenant custom domains. See
`scheduler/app/caddy/`.
