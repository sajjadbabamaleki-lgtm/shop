#!/bin/sh
#
# What has to be true before the first request is served.
#
# Every step here is deliberately safe to run again: a deploy restarts the
# container, and a restart must not be a different thing from a first boot.
set -eu

fail() {
    echo "storefront: $1" >&2
    exit 1
}

# --- the two things that cannot be defaulted --------------------------------
#
# Laravel will happily boot without an APP_KEY and then fail on the first
# encrypted cookie, which reads as a mysterious 500 rather than as a missing
# variable. Say it here instead.
[ -n "${APP_KEY:-}" ] || fail "APP_KEY is not set. Generate one with: php artisan key:generate --show"
[ -n "${DB_HOST:-}" ] || fail "DB_HOST is not set. The catalogue is the page; there is nothing to render without it."

# --- some hosts choose the port ---------------------------------------------
if [ -n "${PORT:-}" ] && [ "${PORT}" != "80" ]; then
    sed -ri "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf
fi

# --- wait for the database ---------------------------------------------------
#
# On a managed database this returns first time. In a compose stack Postgres is
# still starting, and the alternative to waiting is a container that crash-loops
# until it happens to win the race.
attempt=1
until php artisan db:show --quiet 2>/dev/null; do
    [ "$attempt" -le 30 ] || fail "the database at ${DB_HOST} did not answer after 30 attempts"
    echo "storefront: waiting for the database (${attempt}/30)"
    attempt=$((attempt + 1))
    sleep 2
done

php artisan migrate --force

# Seeds only when the catalogue is empty, so a restart never puts a seeded
# price back over an edited one. Pass --force by hand to reseed deliberately.
php artisan catalogue:seed

# Structure rather than content, so this one runs every time.
php artisan db:seed --class=RolesAndPermissionsSeeder --force

# After the environment exists, never at build time: a config cache baked into
# an image would carry the builder's environment into production.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
