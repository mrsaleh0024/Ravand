#Requires -Version 5.1
<#
.SYNOPSIS
    Ravand — اجرای کامل محیط توسعه با یک دستور.

.DESCRIPTION
    1) PHP را بررسی می‌کند (حداقل 8.3)
    2) در صورت نبود .env آن را می‌سازد و APP_KEY تولید می‌کند
    3) MySQL (XAMPP) را در صورت خاموش بودن روشن می‌کند
    4) دیتابیس را در صورت نبود می‌سازد و migrationها را اجرا می‌کند
    5) وابستگی‌های Composer و npm را در صورت نبود نصب می‌کند
    6) سه پروسه را با هم اجرا می‌کند: وب‌سرور، صف و Vite
       و با Ctrl+C هر سه با هم بسته می‌شوند.

.EXAMPLE
    .\dev.ps1
#>

$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
Set-Location $root
$startedAt = Get-Date

$AppPort = 8000
$VitePort = 5173
$MysqlHome = 'C:\xampp\mysql\bin'
$MysqlIni = Join-Path $MysqlHome 'my.ini'

function Write-Step($message) { Write-Host "> $message" -ForegroundColor Cyan }
function Write-Ok($message) { Write-Host "+ $message" -ForegroundColor Green }
function Write-Warn($message) { Write-Host "! $message" -ForegroundColor Yellow }
function Write-Err($message) { Write-Host "x $message" -ForegroundColor Red }

function Test-Port($HostName, $Port, $TimeoutMs = 800) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $task = $client.ConnectAsync($HostName, $Port)
        if ($task.Wait($TimeoutMs) -and $client.Connected) { return $true }
        return $false
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Get-EnvValue($Key, $Default = '') {
    $envFile = Join-Path $root '.env'
    if (-not (Test-Path $envFile)) { return $Default }
    $line = Get-Content $envFile | Where-Object { $_ -match "^\s*$Key\s*=" } | Select-Object -Last 1
    if (-not $line) { return $Default }
    $value = ($line -split '=', 2)[1].Trim()
    if ($value -match '^"(.*)"$') { $value = $Matches[1] }
    if ($value -match "^'(.*)'$") { $value = $Matches[1] }
    return $value
}

function Stop-LeftoverProcesses {
    Get-CimInstance Win32_Process -Filter "Name='php.exe' OR Name='node.exe' OR Name='npm.exe'" -ErrorAction SilentlyContinue |
        Where-Object {
            $_.CreationDate -and $_.CreationDate -ge $startedAt -and $_.CommandLine -and
            ($_.CommandLine -match 'artisan' -or $_.CommandLine -match 'vite' -or $_.CommandLine -match 'npm-cli')
        } |
        ForEach-Object {
            Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        }
}

Write-Host ''
Write-Host '  Ravand — dev' -ForegroundColor White
Write-Host '  ----------------------------------------' -ForegroundColor DarkGray

# --- 1) PHP -----------------------------------------------------------------
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCommand) {
    Write-Err 'PHP در PATH پیدا نشد. PHP 8.3+ را نصب یا PATH را اصلاح کنید.'
    Write-Host '  اگر XAMPP دارید، PHP آن 8.2 است و کافی نیست؛ از PHP سیستم استفاده کنید.'
    exit 1
}

$phpVersion = (& php -r "echo PHP_VERSION;").Trim()
if ([version]$phpVersion -lt [version]'8.3.0') {
    Write-Err "نسخه PHP شما $phpVersion است؛ Laravel 13 حداقل 8.3 می‌خواهد."
    Write-Host '  PATH را طوری تنظیم کنید که php 8.3+ پیدا شود.'
    exit 1
}
Write-Ok "PHP $phpVersion"

# --- 2) .env ----------------------------------------------------------------
$envPath = Join-Path $root '.env'
if (-not (Test-Path $envPath)) {
    Copy-Item (Join-Path $root '.env.example') $envPath
    & php artisan key:generate | Out-Null
    Write-Ok '.env ساخته شد و APP_KEY تولید شد'
} else {
    Write-Ok '.env موجود است'
}

# --- 3) MySQL ---------------------------------------------------------------
$dbHost = Get-EnvValue 'DB_HOST' '127.0.0.1'
$dbPort = [int](Get-EnvValue 'DB_PORT' '3306')
$dbName = Get-EnvValue 'DB_DATABASE' 'ravand'
$dbUser = Get-EnvValue 'DB_USERNAME' 'root'
$dbPass = Get-EnvValue 'DB_PASSWORD' ''

$mysqlRunning = Get-Process mysqld, mariadbd -ErrorAction SilentlyContinue
if (-not $mysqlRunning) {
    $mysqld = Join-Path $MysqlHome 'mysqld.exe'
    if (Test-Path $mysqld) {
        Write-Step 'MySQL خاموش است؛ روشن می‌کنم...'
        if (Test-Path $MysqlIni) {
            $mysqlCommand = "`"$mysqld`" --defaults-file=`"$MysqlIni`""
        } else {
            $mysqlCommand = "`"$mysqld`""
        }

        # اجرای جدا از کنسول فعلی تا Ctrl+C اسکریپت، دیتابیس را نبندد.
        $created = $null
        try {
            $created = Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{ CommandLine = $mysqlCommand } -ErrorAction Stop
        } catch {
            $created = $null
        }

        if (-not $created -or $created.ReturnValue -ne 0) {
            Start-Process $mysqld -ArgumentList $(if (Test-Path $MysqlIni) { "--defaults-file=$MysqlIni" } else { @() }) -WindowStyle Hidden | Out-Null
        }

        $deadline = (Get-Date).AddSeconds(30)
        while ((Get-Date) -lt $deadline -and -not (Test-Port $dbHost $dbPort)) {
            Start-Sleep -Milliseconds 600
        }
    }
}

if (Test-Port $dbHost $dbPort) {
    Write-Ok "MySQL روی $dbHost`:$dbPort پاسخ می‌دهد"

    # ساخت دیتابیس در صورت نبود (بی‌صدا)
    $mysqlCli = Join-Path $MysqlHome 'mysql.exe'
    if (Test-Path $mysqlCli) {
        $createSql = 'CREATE DATABASE IF NOT EXISTS `{0}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' -f $dbName
        $mysqlArgs = @("-h$dbHost", "-P$dbPort", "-u$dbUser")
        if ($dbPass) { $mysqlArgs += "-p$dbPass" }
        $mysqlArgs += @('-e', $createSql)
        & $mysqlCli @mysqlArgs 2>&1 | Out-Null
    }
} else {
    Write-Warn "MySQL روی $dbHost`:$dbPort در دسترس نیست."
    Write-Host '  XAMPP Control Panel را باز کنید و MySQL را Start کنید، سپس دوباره اجرا کنید.'
    exit 1
}

# --- 4) وابستگی‌ها ----------------------------------------------------------
if (-not (Test-Path (Join-Path $root 'vendor\autoload.php'))) {
    Write-Step 'نصب وابستگی‌های Composer...'
    & composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { Write-Err 'composer install شکست خورد.'; exit 1 }
} else {
    Write-Ok 'وابستگی‌های Composer موجودند'
}

if (-not (Test-Path (Join-Path $root 'node_modules'))) {
    Write-Step 'نصب وابستگی‌های npm...'
    & npm install --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) { Write-Err 'npm install شکست خورد.'; exit 1 }
} else {
    Write-Ok 'وابستگی‌های npm موجودند'
}

# --- 5) migration -----------------------------------------------------------
& php artisan migrate --force 2>&1 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Ok 'migrationها به‌روزند'
} else {
    Write-Warn 'migration اجرا نشد؛ با php artisan migrate جزئیات را ببینید.'
}

# --- 6) بررسی پورت‌ها -------------------------------------------------------
if (Test-Port '127.0.0.1' $AppPort) {
    Write-Warn "پورت $AppPort اشغال است. اگر اجرای قبلی همین پروژه است، آن را ببندید."
}

Write-Host ''
Write-Host "  آدرس برنامه:  http://localhost:$AppPort" -ForegroundColor White
Write-Host "  Vite (HMR):   http://localhost:$VitePort" -ForegroundColor DarkGray
Write-Host '  توقف همه:     Ctrl + C' -ForegroundColor DarkGray
Write-Host '  ----------------------------------------' -ForegroundColor DarkGray
Write-Host ''

# --- 7) اجرای هم‌زمان سه پروسه ---------------------------------------------
$exitCode = 0
try {
    & php artisan dev
    $exitCode = $LASTEXITCODE
} finally {
    Stop-LeftoverProcesses
    Write-Host ''
    Write-Ok 'همه پروسه‌ها بسته شدند (server / queue / vite)'
}

exit $exitCode
