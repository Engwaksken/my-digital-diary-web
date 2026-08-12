<#
.SYNOPSIS
    Automated local setup for Personal Monitor (MySQL + DomPDF) - Windows / PowerShell version.

.DESCRIPTION
    Does the same thing as install.sh, for people running PowerShell instead
    of bash. install.sh will not run directly in PowerShell - there is no
    bash interpreter tied to .sh files on Windows by default, which is why
    "./install.sh ..." silently did nothing.

    Run this FROM INSIDE this package's folder (personal-monitor\), pointing
    it at where you want the real Laravel app created:

        .\install.ps1 ..\my-personal-monitor-app

    What it does:
      1. composer create-project laravel/laravel <target>
      2. Installs Laravel Breeze (Blade stack) for auth scaffolding
      3. Installs barryvdh/laravel-dompdf (for the AI plan / personal
         report PDF downloads)
      4. Copies every file from this package into the new app
      5. Configures .env for MySQL (prompts for host/port/db/user/password,
         or use -DbName/-DbUser/-DbPass/-DbHostName/-DbPort to skip prompts)
         and tries to CREATE DATABASE if the mysql CLI is on PATH
      6. Runs php artisan migrate

    What it does NOT do (two small manual edits - see the printed
    instructions at the end, and README.md):
      - Merging the 4 OTP routes + 1 import into routes\auth.php
      - Registering the 'subscribed' middleware alias in bootstrap\app.php

.PARAMETER Target
    Path to the new Laravel app to create. Must not already exist.

.EXAMPLE
    .\install.ps1 ..\my-personal-monitor-app

.EXAMPLE
    .\install.ps1 ..\my-app -DbName pm -DbUser root -DbPass secret
#>

param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$Target,

    [string]$DbName,
    [string]$DbUser,
    [string]$DbPass,
    [string]$DbHostName = "127.0.0.1",
    [int]$DbPort = 3306
)

$ErrorActionPreference = "Stop"

function Assert-CommandExists {
    param([string]$Name)
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        Write-Error "$Name is required but was not found on PATH."
        exit 1
    }
}

Assert-CommandExists "php"
Assert-CommandExists "composer"

$SourceDir = Split-Path -Parent $MyInvocation.MyCommand.Path

if (Test-Path $Target) {
    Write-Error "Error: '$Target' already exists. Choose a new directory (Laravel's installer needs to create it)."
    exit 1
}

Write-Host "==> Creating fresh Laravel app at $Target"
composer create-project laravel/laravel $Target
if ($LASTEXITCODE -ne 0) { throw "composer create-project failed." }

Push-Location $Target

try {
    Write-Host "==> Installing Laravel Breeze (Blade stack) for auth scaffolding"
    composer require laravel/breeze --dev
    if ($LASTEXITCODE -ne 0) { throw "composer require laravel/breeze failed." }
    php artisan breeze:install blade --no-interaction
    if ($LASTEXITCODE -ne 0) { throw "breeze:install failed." }

    Write-Host "==> Installing DomPDF (for PDF report / AI plan downloads)"
    composer require barryvdh/laravel-dompdf
    if ($LASTEXITCODE -ne 0) { throw "composer require barryvdh/laravel-dompdf failed." }

    Write-Host "==> Copying Personal Monitor files into the new app"
    $dirs = @(
        "app\Models", "app\Http\Controllers\Auth", "app\Http\Controllers\Admin", "app\Http\Controllers\Api", "app\Http\Middleware",
        "app\Console\Commands", "app\Notifications", "app\Services", "app\Providers", "config",
        "resources\views\layouts", "resources\views\crud", "resources\views\crud\extras", "resources\views\auth", "resources\views\components", "resources\views\partials",
        "resources\views\reports", "resources\views\api-credentials",
        "resources\views\ai-plans", "resources\views\subscription",
        "resources\views\privacy", "resources\views\profile",
        "resources\views\admin\users", "resources\views\admin\settings",
        "resources\views\admin\payment-gateways", "resources\views\admin\payments", "resources\views\admin\feedback", "resources\views\admin\subscription-plans", "resources\views\activity", "resources\views\meetings", "resources\views\tips", "resources\views\signature", ".vscode"
    )
    foreach ($d in $dirs) {
        New-Item -ItemType Directory -Force -Path $d | Out-Null
    }

    Copy-Item "$SourceDir\database\migrations\*.php" "database\migrations\" -Force
    Copy-Item "$SourceDir\app\Models\*.php" "app\Models\" -Force
    Copy-Item "$SourceDir\app\Http\Controllers\*.php" "app\Http\Controllers\" -Force
    Copy-Item "$SourceDir\app\Http\Controllers\Auth\*.php" "app\Http\Controllers\Auth\" -Force
    Copy-Item "$SourceDir\app\Http\Controllers\Admin\*.php" "app\Http\Controllers\Admin\" -Force
    Copy-Item "$SourceDir\app\Http\Controllers\Api\*.php" "app\Http\Controllers\Api\" -Force
    Copy-Item "$SourceDir\app\Http\Middleware\*.php" "app\Http\Middleware\" -Force
    Copy-Item "$SourceDir\app\Console\Commands\*.php" "app\Console\Commands\" -Force
    Copy-Item "$SourceDir\app\Notifications\*.php" "app\Notifications\" -Force
    Copy-Item "$SourceDir\app\Services\*.php" "app\Services\" -Force
    Copy-Item "$SourceDir\app\Providers\AppServiceProvider.php" "app\Providers\AppServiceProvider.php" -Force
    Copy-Item "$SourceDir\config\sanctum.php" "config\sanctum.php" -Force

    Copy-Item "$SourceDir\resources\views\layouts\app.blade.php" "resources\views\layouts\" -Force
    Copy-Item "$SourceDir\resources\views\components\*.blade.php" "resources\views\components\" -Force
    Copy-Item "$SourceDir\resources\views\crud\*.blade.php" "resources\views\crud\" -Force
    if (Test-Path "$SourceDir\resources\views\crud\extras") {
        Copy-Item "$SourceDir\resources\views\crud\extras\*.blade.php" "resources\views\crud\extras\" -Force
    }
    Copy-Item "$SourceDir\resources\views\partials\*.blade.php" "resources\views\partials\" -Force
    Copy-Item "$SourceDir\resources\views\dashboard.blade.php" "resources\views\" -Force
    Copy-Item "$SourceDir\resources\views\auth\login.blade.php" "resources\views\auth\" -Force
    Copy-Item "$SourceDir\resources\views\auth\register.blade.php" "resources\views\auth\" -Force
    Copy-Item "$SourceDir\resources\views\auth\verify-otp.blade.php" "resources\views\auth\" -Force
    Copy-Item "$SourceDir\resources\views\api-credentials\*.blade.php" "resources\views\api-credentials\" -Force
    Copy-Item "$SourceDir\resources\views\ai-plans\*.blade.php" "resources\views\ai-plans\" -Force
    Copy-Item "$SourceDir\resources\views\subscription\*.blade.php" "resources\views\subscription\" -Force
    Copy-Item "$SourceDir\resources\views\privacy\*.blade.php" "resources\views\privacy\" -Force
    Copy-Item "$SourceDir\resources\views\privacy-policy.blade.php" "resources\views\" -Force
    Copy-Item "$SourceDir\resources\views\reports\*.blade.php" "resources\views\reports\" -Force
    Copy-Item "$SourceDir\resources\views\profile\*.blade.php" "resources\views\profile\" -Force
    Copy-Item "$SourceDir\resources\views\admin\*.blade.php" "resources\views\admin\" -Force
    Copy-Item "$SourceDir\resources\views\admin\users\*.blade.php" "resources\views\admin\users\" -Force
    Copy-Item "$SourceDir\resources\views\admin\settings\*.blade.php" "resources\views\admin\settings\" -Force
    Copy-Item "$SourceDir\resources\views\admin\payment-gateways\*.blade.php" "resources\views\admin\payment-gateways\" -Force
    Copy-Item "$SourceDir\resources\views\admin\payments\*.blade.php" "resources\views\admin\payments\" -Force
    Copy-Item "$SourceDir\resources\views\admin\feedback\*.blade.php" "resources\views\admin\feedback\" -Force
    Copy-Item "$SourceDir\resources\views\admin\subscription-plans\*.blade.php" "resources\views\admin\subscription-plans\" -Force
    Copy-Item "$SourceDir\resources\views\activity\*.blade.php" "resources\views\activity\" -Force
    Copy-Item "$SourceDir\resources\views\meetings\*.blade.php" "resources\views\meetings\" -Force
    Copy-Item "$SourceDir\resources\views\tips\*.blade.php" "resources\views\tips\" -Force
    Copy-Item "$SourceDir\resources\views\signature\*.blade.php" "resources\views\signature\" -Force

    Copy-Item "$SourceDir\routes\web.php" "routes\web.php" -Force
    Copy-Item "$SourceDir\routes\console.php" "routes\console.php" -Force
    Copy-Item "$SourceDir\routes\admin.php" "routes\admin.php" -Force
    Copy-Item "$SourceDir\routes\api.php" "routes\api.php" -Force
    # NOTE: routes\api.php is copied in, but Laravel 11+ does NOT wire it up
    # automatically — you still need to add
    #   api: __DIR__.'/../routes/api.php',
    # to bootstrap/app.php's withRouting() call yourself. See the comment
    # at the top of routes\api.php for the exact line and where it goes.
    # Also run: composer require laravel/sanctum   (this script does NOT
    # do that automatically, unlike breeze/dompdf above, since it's only
    # needed if you're building the Flutter mobile app — see
    # personal_monitor_mobile\SETUP.md)

    if (Test-Path "$SourceDir\.vscode") {
        Write-Host "==> Adding VS Code project config (recommended extensions, debug config, artisan tasks)"
        Copy-Item "$SourceDir\.vscode\*.json" ".vscode\" -Force
    }

    Write-Host ""
    Write-Host "==> Configuring MySQL connection in .env"

    if (-not $DbName) {
        $typed = Read-Host "MySQL database name [personal_monitor]"
        if ($typed) { $DbName = $typed } else { $DbName = "personal_monitor" }
    }
    if (-not $DbUser) {
        $typed = Read-Host "MySQL username [root]"
        if ($typed) { $DbUser = $typed } else { $DbUser = "root" }
    }
    if (-not $DbPass) {
        $securePass = Read-Host "MySQL password (leave blank if none)" -AsSecureString
        $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePass)
        $DbPass = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
        [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }

    # Laravel 11+ ships a fresh .env defaulting to SQLite. Strip any existing
    # DB_* lines (commented or not) and append a clean MySQL block.
    $envPath = ".env"
    $envLines = Get-Content $envPath | Where-Object {
        ($_ -notmatch '^#?\s*DB_CONNECTION=') -and
        ($_ -notmatch '^#?\s*DB_HOST=') -and
        ($_ -notmatch '^#?\s*DB_PORT=') -and
        ($_ -notmatch '^#?\s*DB_DATABASE=') -and
        ($_ -notmatch '^#?\s*DB_USERNAME=') -and
        ($_ -notmatch '^#?\s*DB_PASSWORD=')
    }
    $envLines += ""
    $envLines += "DB_CONNECTION=mysql"
    $envLines += "DB_HOST=$DbHostName"
    $envLines += "DB_PORT=$DbPort"
    $envLines += "DB_DATABASE=$DbName"
    $envLines += "DB_USERNAME=$DbUser"
    $envLines += "DB_PASSWORD=$DbPass"
    Set-Content -Path $envPath -Value $envLines

    if (Get-Command mysql -ErrorAction SilentlyContinue) {
        Write-Host "==> Attempting to create MySQL database '$DbName' (safe if it already exists)"
        $createSql = "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        $env:MYSQL_PWD = $DbPass
        try {
            mysql -h $DbHostName -P $DbPort -u $DbUser -e $createSql 2>$null
            if ($LASTEXITCODE -eq 0) {
                Write-Host "Database ready."
            } else {
                Write-Host "Could not auto-create the database - create it manually, e.g.:"
                Write-Host "    mysql -u $DbUser -p -e `"CREATE DATABASE $DbName;`""
            }
        } finally {
            Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
        }
    } else {
        Write-Host "mysql client not found on PATH - create the database manually, e.g.:"
        Write-Host "    CREATE DATABASE $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    }

    Write-Host "==> Linking storage (needed for avatar/logo/favicon uploads to be publicly reachable)"
    php artisan storage:link
    if ($LASTEXITCODE -ne 0) { throw "php artisan storage:link failed." }

    Write-Host "==> Running migrations"
    php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw "php artisan migrate failed." }

    Write-Host ""
    Write-Host "======================================================================"
    Write-Host " Files copied and migrated. Two manual edits remain (Composer/artisan"
    Write-Host " can't make these for you since they're small merges into existing"
    Write-Host " framework files):"
    Write-Host ""
    Write-Host " 1) routes\auth.php"
    Write-Host "    Open personal-monitor\routes\auth-otp-additions.php for the exact"
    Write-Host "    4 routes + 1 import to paste into the existing 'guest' middleware"
    Write-Host "    group in $Target\routes\auth.php."
    Write-Host ""
    Write-Host " 2) bootstrap\app.php"
    Write-Host "    Open personal-monitor\config-notes\bootstrap-app-middleware-additions.php"
    Write-Host "    for the 'subscribed' AND 'admin' middleware aliases to add to the"
    Write-Host "    (currently empty) withMiddleware() closure in $Target\bootstrap\app.php."
    Write-Host ""
    Write-Host " 3) OPTIONAL - only if you're building the Flutter mobile app:"
    Write-Host "    composer require laravel/sanctum"
    Write-Host "    Then add  api: __DIR__.'/../routes/api.php',  to bootstrap/app.php's"
    Write-Host "    withRouting() call (see the comment at the top of routes\api.php),"
    Write-Host "    and merge personal-monitor\config-notes\services-php-firebase-addition.php's"
    Write-Host "    one array entry into your existing config\services.php. See"
    Write-Host "    personal_monitor_mobile\SETUP.md for the full mobile-app setup."
    Write-Host ""
    Write-Host " Then, to make your own account an admin (there's no signup path to"
    Write-Host " become one - that's intentional):"
    Write-Host "    php artisan tinker"
    Write-Host "    >>> \App\Models\User::where('email', 'you@example.com')->update(['role' => 'admin']);"
    Write-Host "======================================================================"
    Write-Host ""
    Write-Host "==> Once both edits are done:"
    Write-Host "    cd $Target"
    Write-Host "    php artisan serve"
}
finally {
    Pop-Location
}
