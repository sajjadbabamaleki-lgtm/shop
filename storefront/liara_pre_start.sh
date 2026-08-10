#!/bin/sh
#
# Liara runs this after the build and before the app starts. It is the one
# hook of the three that has the environment variables, which is why every
# step that needs to know the database or the app key is here and not in
# liara.json.
#
# That is also why configCache and routeCache are switched off in liara.json
# and done here instead: a config cache written before the environment exists
# is a config cache with no database credentials in it, and the failure it
# produces looks nothing like its cause.
set -eu

echo "storefront: preparing"

# Warn, do not stop. Without APP_KEY every page that touches a session returns
# 500, which reads as a mystery unless something says this out loud — but
# stopping here would keep the app from starting at all, and the app has to be
# running before its console can be opened to generate the key.
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=.+' .env 2>/dev/null; then
    echo "storefront: ==============================================="
    echo "storefront: APP_KEY is not set, so every page will fail."
    echo "storefront: Open this app's console in the Liara panel, run"
    echo "storefront:     php artisan key:generate --show"
    echo "storefront: and add the result as an APP_KEY variable."
    echo "storefront: ==============================================="
fi

php artisan migrate --force

# Seeds only when the catalogue is empty, so a redeploy never puts a seeded
# price back over an edited one. To reseed on purpose, run
# `php artisan catalogue:seed --force` from the app's console.
php artisan catalogue:seed

# Roles and permissions are structure rather than content, so unlike the
# catalogue they are re-synced on every deploy — that is how a permission
# added in code reaches production at all. Grants made by hand to non-system
# roles are left alone.
php artisan db:seed --class=RolesAndPermissionsSeeder --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "storefront: ready"
