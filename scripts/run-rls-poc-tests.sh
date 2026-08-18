#!/usr/bin/env sh

set -eu

sh scripts/verify-rls-poc-environment.sh

exec php artisan test --configuration=phpunit.rls-poc.xml "$@"
