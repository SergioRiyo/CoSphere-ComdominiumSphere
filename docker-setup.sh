#!/bin/sh

set -eu

cd /var/www/html

if [ -f .env ]; then
    if grep -Eq '^DB_CONNECTION=pgsql$' .env || grep -Eq '^DB_USERNAME=seu_usuario_supabase$' .env || grep -Eq '^DB_PASSWORD=sua_senha_supabase$' .env; then
        cp .env.example .env
    fi
else
    cp .env.example .env
fi

if [ ! -w .env ]; then
    temp_env_file="$(mktemp)"
    cat .env > "$temp_env_file"
    rm -f .env
    mv "$temp_env_file" .env
fi

composer install --no-interaction --prefer-dist
npm install --no-fund --no-audit

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
