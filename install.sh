#!/usr/bin/env bash
#
# Automated local setup for Personal Monitor (MySQL + DomPDF).
#
# Run this FROM INSIDE this package's folder (personal-monitor/), pointing
# it at where you want the real Laravel app created:
#
#   ./install.sh ../my-personal-monitor-app
#
# What it does:
#   1. composer create-project laravel/laravel <target>
#   2. Installs Laravel Breeze (Blade stack) for auth scaffolding
#   3. Installs barryvdh/laravel-dompdf (for the AI plan / personal report
#      PDF downloads)
#   4. Copies every file from this package into the new app
#   5. Configures .env for MySQL (prompts for host/port/db/user/password)
#      and tries to CREATE DATABASE if the `mysql` CLI is available
#   6. Runs php artisan migrate
#
# What it does NOT do (two small manual edits — see the printed instructions
# at the end, and README.md):
#   - Merging the 4 OTP routes + 1 import into routes/auth.php
#   - Registering the 'subscribed' middleware alias in bootstrap/app.php
#
# Requires: PHP 8.2+, Composer, and a reachable MySQL server. You can
# override the prompts non-interactively with env vars, e.g.:
#   DB_NAME=pm DB_USER=root DB_PASS=secret ./install.sh ../my-app

set -euo pipefail

if [ -z "${1:-}" ]; then
    echo "Usage: ./install.sh <path-to-new-laravel-app>"
    echo "Example: ./install.sh ../my-personal-monitor-app"
    exit 1
fi

TARGET="$1"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

command -v php >/dev/null 2>&1 || { echo "PHP is required but not found on PATH."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "Composer is required but not found on PATH."; exit 1; }

if [ -d "$TARGET" ]; then
    echo "Error: '$TARGET' already exists. Choose a new directory (Laravel's installer needs to create it)."
    exit 1
fi

echo "==> Creating fresh Laravel app at $TARGET"
composer create-project laravel/laravel "$TARGET"

cd "$TARGET"

echo "==> Installing Laravel Breeze (Blade stack) for auth scaffolding"
composer require laravel/breeze --dev
php artisan breeze:install blade --no-interaction

echo "==> Installing DomPDF (for PDF report / AI plan downloads)"
composer require barryvdh/laravel-dompdf

echo "==> Copying Personal Monitor files into the new app"
mkdir -p app/Models app/Http/Controllers/Auth app/Http/Controllers/Admin app/Http/Controllers/Api app/Http/Middleware app/Console/Commands app/Notifications app/Services app/Providers config
mkdir -p resources/views/layouts resources/views/crud resources/views/crud/extras resources/views/auth resources/views/reports resources/views/components resources/views/partials
mkdir -p resources/views/api-credentials resources/views/ai-plans resources/views/subscription resources/views/privacy resources/views/profile
mkdir -p resources/views/admin/users resources/views/admin/settings resources/views/admin/payment-gateways resources/views/admin/payments resources/views/admin/feedback resources/views/admin/subscription-plans resources/views/activity resources/views/meetings resources/views/tips resources/views/signature

cp -v "$SOURCE_DIR"/database/migrations/*.php database/migrations/
cp -v "$SOURCE_DIR"/app/Models/*.php app/Models/
cp -v "$SOURCE_DIR"/app/Http/Controllers/*.php app/Http/Controllers/
cp -v "$SOURCE_DIR"/app/Http/Controllers/Auth/*.php app/Http/Controllers/Auth/
cp -v "$SOURCE_DIR"/app/Http/Controllers/Admin/*.php app/Http/Controllers/Admin/
cp -v "$SOURCE_DIR"/app/Http/Controllers/Api/*.php app/Http/Controllers/Api/
cp -v "$SOURCE_DIR"/app/Http/Middleware/*.php app/Http/Middleware/
cp -v "$SOURCE_DIR"/app/Console/Commands/*.php app/Console/Commands/
cp -v "$SOURCE_DIR"/app/Notifications/*.php app/Notifications/
cp -v "$SOURCE_DIR"/app/Services/*.php app/Services/
cp -v "$SOURCE_DIR"/app/Providers/AppServiceProvider.php app/Providers/AppServiceProvider.php
cp -v "$SOURCE_DIR"/config/sanctum.php config/sanctum.php

cp -v "$SOURCE_DIR"/resources/views/layouts/app.blade.php resources/views/layouts/
cp -v "$SOURCE_DIR"/resources/views/components/*.blade.php resources/views/components/
cp -v "$SOURCE_DIR"/resources/views/crud/*.blade.php resources/views/crud/
cp -v "$SOURCE_DIR"/resources/views/crud/extras/*.blade.php resources/views/crud/extras/ 2>/dev/null || true
cp -v "$SOURCE_DIR"/resources/views/partials/*.blade.php resources/views/partials/
cp -v "$SOURCE_DIR"/resources/views/dashboard.blade.php resources/views/
cp -v "$SOURCE_DIR"/resources/views/auth/login.blade.php resources/views/auth/
cp -v "$SOURCE_DIR"/resources/views/auth/register.blade.php resources/views/auth/
cp -v "$SOURCE_DIR"/resources/views/auth/verify-otp.blade.php resources/views/auth/
cp -v "$SOURCE_DIR"/resources/views/api-credentials/*.blade.php resources/views/api-credentials/
cp -v "$SOURCE_DIR"/resources/views/ai-plans/*.blade.php resources/views/ai-plans/
cp -v "$SOURCE_DIR"/resources/views/subscription/*.blade.php resources/views/subscription/
cp -v "$SOURCE_DIR"/resources/views/privacy/*.blade.php resources/views/privacy/
cp -v "$SOURCE_DIR"/resources/views/privacy-policy.blade.php resources/views/
cp -v "$SOURCE_DIR"/resources/views/reports/*.blade.php resources/views/reports/
cp -v "$SOURCE_DIR"/resources/views/profile/*.blade.php resources/views/profile/
cp -v "$SOURCE_DIR"/resources/views/admin/*.blade.php resources/views/admin/
cp -v "$SOURCE_DIR"/resources/views/admin/users/*.blade.php resources/views/admin/users/
cp -v "$SOURCE_DIR"/resources/views/admin/settings/*.blade.php resources/views/admin/settings/
cp -v "$SOURCE_DIR"/resources/views/admin/payment-gateways/*.blade.php resources/views/admin/payment-gateways/
cp -v "$SOURCE_DIR"/resources/views/admin/payments/*.blade.php resources/views/admin/payments/
cp -v "$SOURCE_DIR"/resources/views/admin/feedback/*.blade.php resources/views/admin/feedback/
cp -v "$SOURCE_DIR"/resources/views/admin/subscription-plans/*.blade.php resources/views/admin/subscription-plans/
cp -v "$SOURCE_DIR"/resources/views/activity/*.blade.php resources/views/activity/
cp -v "$SOURCE_DIR"/resources/views/meetings/*.blade.php resources/views/meetings/
cp -v "$SOURCE_DIR"/resources/views/tips/*.blade.php resources/views/tips/
cp -v "$SOURCE_DIR"/resources/views/signature/*.blade.php resources/views/signature/

cp -v "$SOURCE_DIR"/routes/web.php routes/web.php
cp -v "$SOURCE_DIR"/routes/console.php routes/console.php
cp -v "$SOURCE_DIR"/routes/admin.php routes/admin.php
cp -v "$SOURCE_DIR"/routes/api.php routes/api.php
# NOTE: routes/api.php is copied in, but Laravel 11+ does NOT wire it up
# automatically — you still need to add
#   api: __DIR__.'/../routes/api.php',
# to bootstrap/app.php's withRouting() call yourself. See the comment at
# the top of routes/api.php for the exact line and where it goes.
# Also run: composer require laravel/sanctum   (this script does NOT do
# that automatically, unlike breeze/dompdf above, since it's only needed
# if you're building the Flutter mobile app — see personal_monitor_mobile/SETUP.md)

if [ -d "$SOURCE_DIR/.vscode" ]; then
    echo "==> Adding VS Code project config (recommended extensions, debug config, artisan tasks)"
    mkdir -p .vscode
    cp -v "$SOURCE_DIR"/.vscode/*.json .vscode/
fi

echo ""
echo "==> Configuring MySQL connection in .env"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-}"
DB_PORT="${DB_PORT:-}"

if [ -t 0 ]; then
    [ -z "$DB_NAME" ] && read -rp "MySQL database name [personal_monitor]: " DB_NAME
    [ -z "$DB_USER" ] && read -rp "MySQL username [root]: " DB_USER
    if [ -z "$DB_PASS" ]; then
        read -rsp "MySQL password (leave blank if none): " DB_PASS
        echo ""
    fi
    [ -z "$DB_HOST" ] && read -rp "MySQL host [127.0.0.1]: " DB_HOST
    [ -z "$DB_PORT" ] && read -rp "MySQL port [3306]: " DB_PORT
else
    echo "(non-interactive shell — using defaults / env vars for DB settings)"
fi

DB_NAME="${DB_NAME:-personal_monitor}"
DB_USER="${DB_USER:-root}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

# Laravel 11+ ships a fresh .env defaulting to SQLite. Strip any existing
# DB_* lines (commented or not) and append a clean MySQL block.
sed -i.bak -E '/^#? ?DB_CONNECTION=/d; /^#? ?DB_HOST=/d; /^#? ?DB_PORT=/d; /^#? ?DB_DATABASE=/d; /^#? ?DB_USERNAME=/d; /^#? ?DB_PASSWORD=/d' .env
rm -f .env.bak

{
    echo ""
    echo "DB_CONNECTION=mysql"
    echo "DB_HOST=$DB_HOST"
    echo "DB_PORT=$DB_PORT"
    echo "DB_DATABASE=$DB_NAME"
    echo "DB_USERNAME=$DB_USER"
    echo "DB_PASSWORD=$DB_PASS"
} >> .env

if command -v mysql >/dev/null 2>&1; then
    echo "==> Attempting to create MySQL database '$DB_NAME' (safe if it already exists)"
    if MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
        -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null; then
        echo "Database ready."
    else
        echo "Could not auto-create the database — create it manually, e.g.:"
        echo "    mysql -u $DB_USER -p -e \"CREATE DATABASE $DB_NAME;\""
    fi
else
    echo "mysql client not found on PATH — create the database manually, e.g.:"
    echo "    CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
fi

echo "==> Linking storage (needed for avatar/logo/favicon uploads to be publicly reachable)"
php artisan storage:link

echo "==> Running migrations"
php artisan migrate --force

echo ""
echo "======================================================================"
echo " Files copied and migrated. Two manual edits remain (Composer/artisan"
echo " can't make these for you since they're small merges into existing"
echo " framework files):"
echo ""
echo " 1) routes/auth.php"
echo "    Open personal-monitor/routes/auth-otp-additions.php for the exact"
echo "    4 routes + 1 import to paste into the existing 'guest' middleware"
echo "    group in $TARGET/routes/auth.php."
echo ""
echo " 2) bootstrap/app.php"
echo "    Open personal-monitor/config-notes/bootstrap-app-middleware-additions.php"
echo "    for the 'subscribed' AND 'admin' middleware aliases to add to the"
echo "    (currently empty) withMiddleware() closure in $TARGET/bootstrap/app.php."
echo ""
echo " 3) OPTIONAL — only if you're building the Flutter mobile app:"
echo "    composer require laravel/sanctum"
echo "    Then add  api: __DIR__.'/../routes/api.php',  to bootstrap/app.php's"
echo "    withRouting() call (see the comment at the top of routes/api.php),"
echo "    and merge personal-monitor/config-notes/services-php-firebase-addition.php's"
echo "    one array entry into your existing config/services.php. See"
echo "    personal_monitor_mobile/SETUP.md for the full mobile-app setup."
echo ""
echo " Then, to make your own account an admin (there's no signup path to"
echo " become one — that's intentional):"
echo "    php artisan tinker"
echo "    >>> \App\Models\User::where('email', 'you@example.com')->update(['role' => 'admin']);"
echo "======================================================================"
echo ""
echo "==> Once both edits are done:"
echo "    cd $TARGET"
echo "    php artisan serve"
