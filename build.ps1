#Requires -Version 5.1
<#
.SYNOPSIS
    Ravand — production build & optimization in one command.

.DESCRIPTION
    Prepares the app for fast serving without touching dev.ps1:

    1) Checks PHP (>= 8.3) and Node
    2) Makes sure .env exists (dev.ps1 normally creates it)
    3) Installs Composer dependencies without dev packages and
       with an optimized autoloader (only when vendor/ is missing or
       -Refresh is passed)
    4) Installs npm dependencies and builds the frontend production
       bundle (npm run build) — hashed, code-split assets in public/build
    5) Runs the migrations (only pending ones)
    6) Caches config, routes, views and events — the single biggest
       PHP-side speed win on every request
    7) Prints a summary of what to serve

    Run this after pulling changes or whenever frontend/backend code changed.
    Safe to run repeatedly: every step is idempotent.

.EXAMPLE
    .\build.ps1            # normal production build
    .\build.ps1 -Refresh   # force composer/npm reinstall + full caches

.NOTES
    With XAMPP, serve through Apache (recommended) pointing the vhost
    DocumentRoot at the project's /public folder, or run:
        php artisan serve --host=0.0.0.0 --port=8000
#>

param(
    [switch]$Refresh
)

$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
Set-Location $root

function Write-Step($message) { Write-Host "> $message" -ForegroundColor Cyan }
function Write-Ok($message)   { Write-Host "+ $message" -ForegroundColor Green }
function Write-Warn($message) { Write-Host "! $message" -ForegroundColor Yellow }
function Write-Err($message)  { Write-Host "x $message" -ForegroundColor Red }

Write-Host ''
Write-Host '  Ravand — build (production)' -ForegroundColor White
Write-Host '  ----------------------------------------' -ForegroundColor DarkGray

# --- 1) PHP / Node -----------------------------------------------------------
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCommand) {
    Write-Err 'PHP not found in PATH. Install PHP 8.3+ and fix PATH.'
    exit 1
}

$phpVersion = (& php -r "echo PHP_VERSION;").Trim()
if ([version]$phpVersion -lt [version]'8.3.0') {
    Write-Err "PHP $phpVersion is too old; Laravel 13 needs 8.3+."
    exit 1
}
Write-Ok "PHP $phpVersion"

$nodeCommand = Get-Command node -ErrorAction SilentlyContinue
if (-not $nodeCommand) {
    Write-Err 'Node not found in PATH. Install Node.js 20+ to build the frontend.'
    exit 1
}
Write-Ok "Node $((& node -v).Trim())"

# --- 2) .env -----------------------------------------------------------------
$envPath = Join-Path $root '.env'
if (-not (Test-Path $envPath)) {
    Write-Warn '.env missing — copying from .env.example (dev.ps1 usually does this).'
    Copy-Item (Join-Path $root '.env.example') $envPath
    & php artisan key:generate | Out-Null
    Write-Ok 'APP_KEY generated'
} else {
    Write-Ok '.env present'
}

# --- 3) Composer dependencies -------------------------------------------------
$composerFlags = @('install', '--no-interaction', '--prefer-dist',
    '--no-dev', '--optimize-autoloader')
$needsComposer = $Refresh -or -not (Test-Path (Join-Path $root 'vendor\autoload.php'))
if ($needsComposer) {
    Write-Step 'Composer install (no-dev, optimized autoloader)...'
    & composer @composerFlags
    if ($LASTEXITCODE -ne 0) { Write-Err 'composer install failed.'; exit 1 }
} else {
    Write-Ok 'vendor/ present (use -Refresh to reinstall without dev packages)'
}

# --- 4) Frontend production build ---------------------------------------------
$needsNpm = $Refresh -or -not (Test-Path (Join-Path $root 'node_modules'))
if ($needsNpm) {
    Write-Step 'npm install...'
    & npm ci --no-audit --no-fund 2>$null
    if ($LASTEXITCODE -ne 0) {
        & npm install --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) { Write-Err 'npm install failed.'; exit 1 }
    }
}

Write-Step 'Building frontend (vite production bundle)...'
& npm run build
if ($LASTEXITCODE -ne 0) { Write-Err 'npm run build failed.'; exit 1 }
Write-Ok 'public/build refreshed (hashed + code-split assets)'

# --- 5) Migrations --------------------------------------------------------------
Write-Step 'Running pending migrations...'
& php artisan migrate --force
if ($LASTEXITCODE -ne 0) { Write-Err 'migrate failed.'; exit 1 }
Write-Ok 'Database schema up to date'

# --- 6) Framework caches (the big PHP speed win) ---------------------------------
Write-Step 'Caching config / routes / views / events...'
& php artisan config:cache  | Out-Null; if ($LASTEXITCODE -ne 0) { Write-Err 'config:cache failed.'; exit 1 }
& php artisan route:cache   | Out-Null; if ($LASTEXITCODE -ne 0) { Write-Err 'route:cache failed.'; exit 1 }
& php artisan view:cache    | Out-Null; if ($LASTEXITCODE -ne 0) { Write-Err 'view:cache failed.'; exit 1 }
& php artisan event:cache   | Out-Null; if ($LASTEXITCODE -ne 0) { Write-Err 'event:cache failed.'; exit 1 }
Write-Ok 'config + routes + views + events cached'

# --- 7) Summary -------------------------------------------------------------------
Write-Host ''
Write-Host '  Build complete' -ForegroundColor White
Write-Host '  ----------------------------------------' -ForegroundColor DarkGray
Write-Host '  Serve with Apache (XAMPP) vhost -> /public' -ForegroundColor Gray
Write-Host '  or:  php artisan serve --port=8000' -ForegroundColor Gray
Write-Host ''
Write-Host '  Notes:' -ForegroundColor Gray
Write-Host '   - Re-run .\build.ps1 after every pull / code change.' -ForegroundColor DarkGray
Write-Host '   - During local PHP edits use .\dev.ps1 instead (hot reload).' -ForegroundColor DarkGray
Write-Host '   - Clear caches with: php artisan optimize:clear' -ForegroundColor DarkGray
Write-Host ''
