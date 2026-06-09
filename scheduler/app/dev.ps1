# SOCIALISTIC - one-command local dev setup (Windows PowerShell).
#
# Gets the app running locally with NO real API keys: installs deps, creates a
# local sqlite .env (SOCIAL_FAKE + AI_FAKE on), generates the app key,
# migrates, builds the front-end, then runs the web server and queue worker.
#
# Usage:   .\dev.ps1            (full setup + run)
#          .\dev.ps1 setup      (setup only, don't start servers)
#
# Requirements: PHP 8.3+, Composer, Node 20+ on your PATH.
# (Tip: WSL2 + ./dev.sh tends to be smoother and matches the VPS environment.)

param([string]$Mode = "run")

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

function Info($m) { Write-Host "==> $m" -ForegroundColor Green }
function Warn($m) { Write-Host "!!  $m" -ForegroundColor Yellow }

# 0. Prerequisites
foreach ($bin in @("php", "composer", "node", "npm")) {
    if (-not (Get-Command $bin -ErrorAction SilentlyContinue)) {
        Write-Host "Missing required tool: $bin" -ForegroundColor Red
        exit 1
    }
}

# Required PHP extensions (bcmath powers Stripe/Cashier; pdo_sqlite the local DB).
$loaded = (php -m)
foreach ($ext in @("bcmath", "pdo_sqlite", "mbstring", "openssl", "curl", "gd", "zip")) {
    if ($loaded -notcontains $ext) {
        Write-Host "Missing PHP extension: $ext - enable 'extension=$ext' in php.ini and re-run." -ForegroundColor Red
        exit 1
    }
}

# 1-2. Dependencies
Info "Installing PHP dependencies (composer)..."
composer install --no-interaction
Info "Installing JS dependencies (npm)..."
npm install --no-audit --no-fund

# 3. Environment
if (-not (Test-Path ".env")) {
    Info "Creating .env (local sqlite, fake providers ON)..."
    Copy-Item ".env.example" ".env"
    (Get-Content ".env") `
        -replace '^SOCIAL_FAKE=.*', 'SOCIAL_FAKE=true' `
        -replace '^AI_FAKE=.*', 'AI_FAKE=true' `
        -replace '^APP_URL=.*', 'APP_URL=http://localhost:8000' |
        Set-Content ".env"
} else {
    Warn ".env already exists - leaving it as-is."
}

# 4. App key
if (-not (Select-String -Path ".env" -Pattern '^APP_KEY=base64:' -Quiet)) {
    Info "Generating application key..."
    php artisan key:generate
}

# 5. Database (sqlite)
Info "Preparing sqlite database..."
New-Item -ItemType Directory -Force -Path "database" | Out-Null
if (-not (Test-Path "database/database.sqlite")) { New-Item -ItemType File -Path "database/database.sqlite" | Out-Null }
php artisan migrate --force

# 6. Front-end build
Info "Building front-end assets..."
npm run build

Write-Host ""
Info "Setup complete."
Write-Host "   - Log in / register at http://localhost:8000"
Write-Host "   - Make yourself admin:  php artisan tinker"
Write-Host ""

if ($Mode -eq "setup") { Info "Setup-only mode - not starting servers."; exit 0 }

# 7. Run web + queue worker (worker in a separate window)
Info "Starting queue worker in a new window + web server here (Ctrl+C to stop)..."
Start-Process php -ArgumentList "artisan","queue:work","--tries=3"
php artisan serve --host=127.0.0.1 --port=8000
