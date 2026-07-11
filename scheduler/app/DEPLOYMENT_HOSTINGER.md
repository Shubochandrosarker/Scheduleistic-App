# Deploying Scheduleistic on a Hostinger VPS

This guide takes you from a fresh Ubuntu VPS to a live, two-domain production
deployment:

| Domain                     | Serves                          | Container        |
| -------------------------- | ------------------------------- | ---------------- |
| `scheduleistic.com`        | Static marketing website        | `caddy` (static) |
| `app.scheduleistic.com`    | Laravel SaaS app (Inertia/Vue)  | `caddy → app`    |
| tenant custom domains      | White-label dashboards          | `caddy → app`    |

The whole stack runs with Docker Compose: `caddy`, `app`, `worker`,
`scheduler`, `mysql`, `redis`. TLS is automatic (Let's Encrypt) for both
first-party domains and verified tenant custom domains (on-demand TLS gated by
the app's `/tls/check` endpoint).

Everything below is run from the repository's `scheduler/app` directory unless
stated otherwise.

---

## 1. DNS setup

In your DNS provider (Hostinger DNS, or Cloudflare if you proxy), create:

| Type  | Name  | Value                    | Notes                          |
| ----- | ----- | ------------------------ | ------------------------------ |
| A     | `@`   | `<VPS_IPv4>`             | `scheduleistic.com` → marketing |
| A     | `app` | `<VPS_IPv4>`             | `app.scheduleistic.com` → app   |
| AAAA  | `@`   | `<VPS_IPv6>` (optional)  | if your VPS has IPv6            |
| AAAA  | `app` | `<VPS_IPv6>` (optional)  |                                |

**SSL:** Caddy obtains and renews certificates automatically. Do **not** put
Cloudflare in front in "Full (strict)" without an origin cert until the first
issuance succeeds. If you use Cloudflare, set the records to **DNS only (grey
cloud)** for the first deploy so Let's Encrypt's HTTP-01 challenge reaches
Caddy; you can enable the orange-cloud proxy afterward.

Verify propagation before deploying:

```bash
dig +short scheduleistic.com
dig +short app.scheduleistic.com
```

---

## 2. Server setup (run as root on first login)

```bash
# Create a non-root deploy user with sudo + docker access
adduser deploy
usermod -aG sudo deploy

# SSH key login (paste your public key)
mkdir -p /home/deploy/.ssh
nano /home/deploy/.ssh/authorized_keys      # paste your id_ed25519.pub
chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh

# Harden SSH: disable root login + password auth
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart ssh

# Firewall: only SSH + HTTP + HTTPS
apt update && apt install -y ufw
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp
ufw --force enable
```

Install Docker + the Compose plugin (as `deploy`, or with sudo):

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy
# log out and back in so the docker group applies
docker --version && docker compose version
```

---

## 3. Clone & configure

```bash
sudo mkdir -p /opt && sudo chown deploy:deploy /opt
cd /opt
git clone https://github.com/Shubochandrosarker/Scheduleistic-App.git scheduleistic
cd scheduleistic/scheduler/app

cp .env.production.example .env
nano .env
```

Fill in, at minimum:

- `APP_KEY` — generate with `docker compose run --rm app php artisan key:generate --show`
  (paste the `base64:...` value), or run `php artisan key:generate` after first boot.
- `APP_DOMAIN=app.scheduleistic.com`, `MARKETING_DOMAIN=scheduleistic.com`
- `ACME_EMAIL=` your real email (Let's Encrypt notices)
- `DB_PASSWORD=` a long random string — **must match** `MYSQL_PASSWORD` in
  `docker-compose.yml` (edit the compose file or move these to env).
- `MAIL_*` — a real SMTP provider so verification/approval emails send.
- `STRIPE_*` — see [`../docs/04-build-deploy-maintain-guide.md`](../docs/04-build-deploy-maintain-guide.md#5-connecting-the-networks-stripe-and-ai)
  §5.2 for the Stripe setup steps (optional for first boot).

> **Never commit `.env`.** It is git-ignored. Keep an encrypted off-server backup.

---

## 4. Build & launch

```bash
docker compose up -d --build

# First-boot app initialisation (run inside the app container):
docker compose exec app php artisan key:generate --force   # if not already set
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan queue:restart
docker compose exec app php artisan schedule:list           # sanity check
```

Create your platform super-admin (operator) account:

```bash
# Register normally at https://app.scheduleistic.com/register, then grant
# platform-admin to that user by email:
docker compose exec app php artisan tinker --execute="\App\Models\User::where('email','you@example.com')->update(['is_platform_admin'=>true]);"
```

### Verify it's live

```bash
docker compose ps                     # all services Up; mysql healthy
curl -I https://scheduleistic.com     # 200, marketing site
curl -I https://app.scheduleistic.com/login   # 200, app login
docker compose logs -f worker         # queue worker processing jobs
```

The marketing site is served straight from the repo's `marketing/` directory
(mounted read-only into Caddy). To update it, `git pull` and Caddy serves the
new files immediately — no rebuild needed.

---

## 5. Backups (nightly cron recommended)

```bash
# MySQL dump
docker compose exec -T mysql mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" scheduleistic \
  | gzip > /opt/backups/db-$(date +%F).sql.gz

# Uploaded media + app storage
tar czf /opt/backups/storage-$(date +%F).tar.gz \
  -C /opt/scheduleistic/scheduler/app storage/app
```

Add to `crontab -e`:

```
30 3 * * * cd /opt/scheduleistic/scheduler/app && docker compose exec -T mysql mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" scheduleistic | gzip > /opt/backups/db-$(date +\%F).sql.gz
```

**Restore:** `gunzip < db-YYYY-MM-DD.sql.gz | docker compose exec -T mysql mysql -u root -p"$MYSQL_ROOT_PASSWORD" scheduleistic`

> Back up `.env` separately and securely — losing `APP_KEY` makes encrypted
> OAuth tokens unrecoverable.

---

## 6. Monitoring & common fixes

```bash
docker compose ps                       # service health
docker compose logs --tail=100 app      # app log
docker compose logs --tail=100 worker   # publishing engine
docker compose exec app php artisan queue:failed   # failed jobs
docker compose exec app tail -f storage/logs/laravel.log
```

| Symptom                                   | Fix                                                                 |
| ----------------------------------------- | ------------------------------------------------------------------- |
| 502 / app unreachable                     | `docker compose logs app`; ensure migrations ran, `APP_KEY` set     |
| TLS cert not issued                       | DNS not pointing to VPS, or Cloudflare proxy on during first issue  |
| Posts not publishing                      | check `worker` + `scheduler` are Up; `queue:failed`                 |
| "419 page expired" on login              | `SESSION_DOMAIN` / `APP_URL` mismatch; clear config cache           |
| Emails not arriving                       | `MAIL_*` not set or SMTP creds wrong; check `laravel.log`           |

Uptime monitoring: point an external monitor (UptimeRobot, etc.) at
`https://app.scheduleistic.com/login` and `https://scheduleistic.com`.

---

## 7. Production security checklist

- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] Strong, unique `APP_KEY` (never reuse across environments)
- [ ] Strong `DB_PASSWORD` / `MYSQL_ROOT_PASSWORD` (not the compose defaults)
- [ ] UFW allows only `22/80/443`
- [ ] SSH: key-only login, root login disabled
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- [ ] Backups scheduled **and** a restore tested
- [ ] Stripe webhook endpoint added (`/stripe/webhook`) + `STRIPE_WEBHOOK_SECRET` set
- [ ] OAuth redirect URLs registered as `https://app.scheduleistic.com/channels/callback/{provider}`
- [ ] File uploads validated (enforced by the app's form requests)
- [ ] Tenant-isolation tests passing (`php artisan test --filter=Workspace`)

---

## 8. Updating a running deployment

```bash
cd /opt/scheduleistic && git pull
cd scheduler/app
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache route:cache view:cache
docker compose exec app php artisan queue:restart
```
