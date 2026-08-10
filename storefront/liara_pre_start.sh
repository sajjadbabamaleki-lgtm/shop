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

php artisan migrate --force

# Seeds only when the catalogue is empty, so a redeploy never puts a seeded
# price back over an edited one. To reseed on purpose, run
# `php artisan catalogue:seed --force` from the app's console.
php artisan catalogue:seed

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "storefront: ready"
