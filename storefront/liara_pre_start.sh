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

# The main store has to exist before any page can be served: every request
# resolves to a branch, and without this one there is nothing for the site's
# own address to resolve to — the symptom is a 404 on every page at once, for
# a reason nobody would guess from the symptom. Structural and idempotent, so
# it runs on every deploy.
php artisan db:seed --class=BranchSeeder --force

# Seeds only when the catalogue is empty, so a redeploy never puts a seeded
# price back over an edited one. To reseed on purpose, run
# `php artisan catalogue:seed --force` from the app's console.
php artisan catalogue:seed

# Roles and permissions are structure rather than content, so unlike the
# catalogue they are re-synced on every deploy — that is how a permission
# added in code reaches production at all. Grants made by hand to non-system
# roles are left alone.
php artisan db:seed --class=RolesAndPermissionsSeeder --force

# The two directories a mounted disk arrives without.
#
# The photographs live under `storage/app`, and that path is where a Liara disk
# gets mounted so they survive a deploy. A fresh disk is **empty**, and mounting
# it hides whatever the image had there — including `storage/app/public`, which
# is the `public` disk's root, and `storage/app/private`, which is the `local`
# disk's. Without them the first upload throws and `storage:link` points at
# nothing, and the symptom is a broken image rather than an error anybody sees.
mkdir -p storage/app/public storage/app/private

# The link that makes uploaded and imported photographs reachable.
#
# `storage/app/public` is served at `/storage` only through this symlink, and a
# fresh container has no symlink — so without this, every picture the panel
# uploads and every picture `basalam:fetch` downloads is a broken image, with
# the file sitting right there on disk. `--force` because the link may already
# exist on a warm container, and `|| true` because `set -eu` is in force above
# and a missing link is not a reason to refuse to start the shop.
#
# **The files behind it are only permanent if a disk is mounted at
# `storage/app`.** Without one they are a container's lifetime, which for a
# catalogue imported from Basalam means until the next deploy.
php artisan storage:link --force || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "storefront: ready"
