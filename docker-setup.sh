#!/bin/sh

set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -w .env ]; then
    temp_env_file="$(mktemp)"
    cat .env > "$temp_env_file"
    rm -f .env
    mv "$temp_env_file" .env
fi

if grep -Eq '^DB_USERNAME=seu_usuario_supabase$' .env || grep -Eq '^DB_PASSWORD=sua_senha_supabase$' .env; then
    echo "Configure as credenciais reais do Supabase no arquivo .env antes de subir o projeto."
    exit 1
fi

composer install --no-interaction --prefer-dist
npm install --no-fund --no-audit

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force
fi

if [ ! -L public/storage ]; then
    php artisan storage:link
fi

php artisan migrate --force
