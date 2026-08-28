#!/usr/bin/env sh

set -eu

sh scripts/verify-rls-poc-environment.sh

APP_ENV=testing \
DB_CONNECTION=pgsql \
DB_URL= \
DB_HOST="$RLS_DB_HOST" \
DB_PORT="$RLS_DB_PORT" \
DB_DATABASE="$RLS_POSTGRES_DATABASE" \
DB_USERNAME="$RLS_POSTGRES_ADMIN_USERNAME" \
DB_PASSWORD="$RLS_POSTGRES_ADMIN_PASSWORD" \
DB_SCHEMA="${RLS_DB_SCHEMA:-public}" \
DB_SSLMODE=disable \
php artisan migrate --force --no-interaction

exec vendor/bin/phpunit --configuration=phpunit.rls-poc.xml "$@"
