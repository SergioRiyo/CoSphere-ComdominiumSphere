#!/usr/bin/env sh

set -eu

required_variables='RLS_POSTGRES_DATABASE RLS_POSTGRES_ADMIN_USERNAME RLS_POSTGRES_ADMIN_PASSWORD RLS_DB_HOST RLS_DB_PORT RLS_DB_DATABASE RLS_DB_USERNAME RLS_DB_PASSWORD'

for variable in $required_variables; do
    eval "value=\${$variable:-}"

    if [ -z "$value" ]; then
        echo "RLS POC blocked: $variable must be configured." >&2
        exit 1
    fi
done

case "$RLS_DB_HOST" in
    127.0.0.1|localhost|postgres_rls_test) ;;
    *)
        echo "RLS POC blocked: RLS_DB_HOST must target localhost or postgres_rls_test." >&2
        exit 1
        ;;
esac

case "$RLS_DB_DATABASE" in
    cosphere_rls_poc) ;;
    *)
        echo "RLS POC blocked: RLS_DB_DATABASE must be cosphere_rls_poc." >&2
        exit 1
        ;;
esac

case "$RLS_POSTGRES_DATABASE" in
    cosphere_rls_poc) ;;
    *)
        echo "RLS POC blocked: RLS_POSTGRES_DATABASE must be cosphere_rls_poc." >&2
        exit 1
        ;;
esac

case "$RLS_POSTGRES_ADMIN_USERNAME" in
    cosphere_rls_admin) ;;
    *)
        echo "RLS POC blocked: RLS_POSTGRES_ADMIN_USERNAME must be cosphere_rls_admin." >&2
        exit 1
        ;;
esac

case "$RLS_DB_USERNAME" in
    cosphere_app_test) ;;
    *)
        echo "RLS POC blocked: RLS_DB_USERNAME must be cosphere_app_test." >&2
        exit 1
        ;;
esac

if [ -f bootstrap/cache/config.php ]; then
    echo 'RLS POC blocked: clear the Laravel configuration cache before running migrations.' >&2
    exit 1
fi

if [ "$RLS_DB_HOST" = "postgres_rls_test" ] && [ "$RLS_DB_PORT" != "5432" ]; then
    echo "RLS POC blocked: postgres_rls_test must use port 5432." >&2
    exit 1
fi

if [ "$RLS_DB_HOST" != "postgres_rls_test" ] && [ "$RLS_DB_PORT" != "55432" ]; then
    echo "RLS POC blocked: localhost must use port 55432." >&2
    exit 1
fi

if [ "${RLS_DB_SSLMODE:-disable}" != "disable" ]; then
    echo "RLS POC blocked: RLS_DB_SSLMODE must be disable for the local database." >&2
    exit 1
fi

echo 'RLS POC environment guard passed.'
