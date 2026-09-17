#!/bin/sh
set -e

ROLE="${CONTAINER_ROLE:-app}"

until php -r 'try { new PDO("pgsql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 5432).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo "ok"; } catch (Throwable $e) { exit(1); }' > /dev/null 2>&1; do
    echo "Waiting for postgres..."
    sleep 2
done

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    APP_KEY="$(php artisan key:generate --show --no-interaction)"
    export APP_KEY
fi

# Passport RSA keys: shared storage volume keeps signing consistent.
if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --no-interaction --force
fi

php artisan migrate --force

if [ "$ROLE" = "app" ] && [ "${SEED_DEMO:-false}" = "true" ] && ! php artisan tinker --execute 'exit(App\Models\User::where("email", "owner@acme.test")->exists() ? 0 : 1);' > /dev/null 2>&1; then
    php artisan db:seed --force
fi

case "$ROLE" in
    horizon)   exec php artisan horizon --no-interaction ;;
    scheduler) exec php artisan schedule:work --no-interaction ;;
    app)       exec "$@" ;;
    *)         exec "$@" ;;
esac
