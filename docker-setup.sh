#!/bin/sh

set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install --no-interaction --prefer-dist
npm ci --no-fund --no-audit

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force
fi

if [ ! -L public/storage ]; then
    php artisan storage:link
fi

if grep -Eq '^DB_CONNECTION=sqlite' .env; then
    echo "SQLite is intended for automated tests only. Configure PostgreSQL/Supabase for local development."
fi

php artisan migrate --seed --force
