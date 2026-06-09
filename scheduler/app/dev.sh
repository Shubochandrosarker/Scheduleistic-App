#!/usr/bin/env bash
#
# SOCIALISTIC — one-command local dev setup.
#
# Gets the app running on your machine end-to-end with NO real API keys:
# installs deps, creates a local sqlite .env (with SOCIAL_FAKE + AI_FAKE on),
# generates the app key, migrates, builds the front-end, then runs the web
# server AND the queue worker together.
#
# Usage:   ./dev.sh            (full setup + run)
#          ./dev.sh setup      (setup only, don't start servers)
#
# Requirements: PHP 8.3+, Composer, Node 20+ (npm). On Windows, run this from
# WSL2 (Ubuntu) — or use dev.ps1 in PowerShell.

set -euo pipefail
cd "$(dirname "$0")"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info() { echo -e "${GREEN}==>${NC} $1"; }
warn() { echo -e "${YELLOW}!! ${NC} $1"; }

# --- 0. Prerequisite checks -------------------------------------------------
missing=0
for bin in php composer node npm; do
  if ! command -v "$bin" >/dev/null 2>&1; then
    echo -e "${RED}Missing required tool: $bin${NC}"; missing=1
  fi
done
[ "$missing" = 1 ] && { echo "Install the missing tool(s) above, then re-run."; exit 1; }

PHP_OK=$(php -r 'echo version_compare(PHP_VERSION, "8.3.0", ">=") ? "1" : "0";')
[ "$PHP_OK" != "1" ] && { echo -e "${RED}PHP 8.3+ required (found $(php -r 'echo PHP_VERSION;')).${NC}"; exit 1; }

# Required PHP extensions (bcmath powers Stripe/Cashier; pdo_sqlite the local DB).
for ext in bcmath pdo_sqlite mbstring openssl curl gd zip; do
  if ! php -m | grep -qix "$ext"; then
    echo -e "${RED}Missing PHP extension: $ext${NC}"
    echo "  Enable it in your php.ini (e.g. uncomment 'extension=$ext') and re-run."
    missing=1
  fi
done
[ "$missing" = 1 ] && { echo "Enable the missing extension(s) above, then re-run."; exit 1; }

# --- 1. PHP dependencies ----------------------------------------------------
info "Installing PHP dependencies (composer)…"
composer install --no-interaction

# --- 2. JS dependencies -----------------------------------------------------
info "Installing JS dependencies (npm)…"
npm install --no-audit --no-fund

# --- 3. Environment ---------------------------------------------------------
if [ ! -f .env ]; then
  info "Creating .env (local sqlite, fake providers ON)…"
  cp .env.example .env
  # Default the local env to a no-credentials, fully testable setup.
  sed -i.bak -E 's/^SOCIAL_FAKE=.*/SOCIAL_FAKE=true/' .env && rm -f .env.bak
  sed -i.bak -E 's/^AI_FAKE=.*/AI_FAKE=true/' .env && rm -f .env.bak
  sed -i.bak -E 's#^APP_URL=.*#APP_URL=http://localhost:8000#' .env && rm -f .env.bak
else
  warn ".env already exists — leaving it as-is."
fi

# --- 4. App key -------------------------------------------------------------
if ! grep -qE '^APP_KEY=base64:' .env; then
  info "Generating application key…"
  php artisan key:generate
fi

# --- 5. Database (sqlite) ---------------------------------------------------
info "Preparing sqlite database…"
mkdir -p database
touch database/database.sqlite
php artisan migrate --force

# --- 6. Front-end build -----------------------------------------------------
info "Building front-end assets…"
npm run build

echo ""
info "Setup complete. 🎉"
echo "   • Log in / register at http://localhost:8000"
echo "   • Make yourself admin:  php artisan tinker  →  App\\Models\\User::first()->forceFill(['is_platform_admin'=>true])->save();"
echo ""

[ "${1:-run}" = "setup" ] && { info "Setup-only mode — not starting servers."; exit 0; }

# --- 7. Run web + queue worker together -------------------------------------
info "Starting web server + queue worker (Ctrl+C to stop both)…"
php artisan queue:work --tries=3 &
WORKER_PID=$!
trap 'echo; info "Stopping…"; kill $WORKER_PID 2>/dev/null || true' EXIT INT TERM

php artisan serve --host=127.0.0.1 --port=8000
