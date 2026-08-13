# Putting the storefront on a URL

**The deploy is Liara, app `vikyplus`, driven by
`.github/workflows/deploy-liara.yml` — pushing to a branch that workflow names,
and nothing else. Netlify is the static preview, not the shop.** The full
version of that rule is at the top of `CLAUDE.md`; it is written there because
a session once sent a build to Netlify and the client saw an old page.

`HANDOFF.md` says what is built. This says how to run it somewhere other than a
laptop, and — as honestly as it can — which parts of that have been tested and
which have not.

**If you are not a programmer, read `DEPLOY-FA.md` instead.** It is the same
deployment written as a click-by-click guide in Persian, with no command line
in it at all. This file assumes you can read a Dockerfile.

## What Netlify can and cannot do

The link the client has been reviewing is Netlify, and `netlify.toml` publishes
`download-version/` — the static design preview. **Netlify cannot host this
app.** It serves files; the storefront needs PHP and PostgreSQL.

It is worth being clear that moving the link buys nothing to *look* at. The
storefront and the preview render the same pixels — `node theme/check-parity.js`
says zero difference at 992, 1200, 1440 and 1920. What a real deployment buys
is a page that answers to the catalogue: change a price, a category name or a
stock level in the database and the page changes. That is the whole reason to
do it.

Leave the Netlify link alone until the new one is green. `download-version/` is
also the surface the design still gets argued on, and three scripts in `theme/`
read from it, so deleting it is not free — see «The preview and the Blade are
two copies of the same page» in `HANDOFF.md`.

## What is verified and what is not

**Verified**, by running it:

- The app in production configuration — `APP_ENV=production`, `APP_DEBUG=false`,
  `config:cache`, `route:cache`, `view:cache`, an optimized authoritative
  classmap — against PostgreSQL 16. It renders the page pixel-identically to
  the preview at all four widths, and a missing route returns a 404 that leaks
  nothing.
- `php artisan catalogue:seed` in all three states: catalogue present (does
  nothing), `--force` (reseeds), catalogue empty (seeds).
- The entrypoint's database probe (`db:show` exits 0 connected, 1 not) and its
  shell syntax.
- `docker compose config`, including that it refuses to start without an
  `APP_KEY`.

**Not verified**: the image itself. It could not be built in the session that
wrote it — the egress policy blocked `production.cloudfront.docker.com`, Docker
Hub's blob CDN, so no base image could be pulled and even `docker build --check`
could not resolve metadata. Expect to fix something the first time you build it.
The Dockerfile is written to make that as unlikely as possible: Apache with
mod_php, no fpm socket, no supervisor, no Caddyfile — the recipe with the fewest
parts that can be wrong, rather than the fastest one.

## The shape of it

One container, one database. No Node stage: the page loads the template's own
stylesheets out of `public/assets` and no view carries a `@vite` directive.

```
storefront/Dockerfile          the image
storefront/.dockerignore
storefront/docker/entrypoint.sh   what must be true before the first request
storefront/docker/opcache.ini
docker-compose.yml             app + postgres, for a VPS or for local use
```

The entrypoint, on every boot: refuse to start without `APP_KEY` or `DB_HOST`,
take `$PORT` if the host sets one, wait for the database, `migrate --force`,
`catalogue:seed`, then cache config, routes and views. Every step is safe to run
again, because a restart must not be a different thing from a first boot.

`catalogue:seed` seeds **only when the catalogue is empty**. `CatalogueSeeder`
uses `updateOrCreate` and so is harmless to the schema, but it is not harmless
to content: once somebody edits a price in the running shop, a seeder firing on
the next restart would put the seeded one back.

## Locally, or on a VPS

```bash
export APP_KEY="$(cd storefront && php artisan key:generate --show)"
docker compose up --build
```

Then `http://localhost:8080`.

## Liara

This is the one it is going on, and it does **not** use the Dockerfile. Liara
has a native Laravel platform, and everything this app needs is on it — PHP
8.4, `intl` and `pdo_pgsql` — so the runtime is theirs to maintain and the
untested image stays out of the path entirely.

Three files carry it, all in `storefront/`:

| | |
|---|---|
| `liara.json` | the platform, the PHP version, the timezone |
| `liara_pre_start.sh` | migrations, seeding and the caches |
| `.liaraignore` | what not to upload |

**Deploy from `storefront/`, not from the repository root** — that directory is
the application.

Normally nobody does that by hand. `.github/workflows/deploy-liara.yml` runs
the suite on every push and pull request and deploys on a push to a deploy
branch, only if the tests passed — so a red suite cannot reach the site. It
needs two things set once on the GitHub repository:

| | |
|---|---|
| secret `LIARA_API_TOKEN` | from <https://console.liara.ir/API> |
| variable `LIARA_APP` | only if the app is not named `vikyplus` |

By hand, if it ever comes to that:

```bash
cd storefront
liara deploy
```

`APP_KEY` is the one variable that cannot be set before the first deploy,
because it is generated from the running app's own console
(`php artisan key:generate --show`). `liara_pre_start.sh` warns loudly in the
log when it is missing rather than failing — a container that refuses to start
is a container whose console cannot be opened to generate the key.

### What `liara.json` says, and why

- `"buildAssets": false` — the page loads the template's own stylesheets out of
  `public/assets` and no view carries a `@vite` directive. There is also no
  `package-lock.json`. Letting Liara run `npm run build` would be a build step
  for nothing, and one more thing that can fail.
- `"installDevDependencies": false` — nothing in `require-dev` is needed to
  serve a request.
- `"build": {"location": "germany"}` with `"composerMirror": false` — Packagist
  is reached directly. If that build is slow or blocked, the other combination
  is the Iranian location with the mirror on.
- `"configCache": false`, `"routeCache": false` — **deliberately off here and
  done in the hook instead.** Of Liara's three hooks, only `liara_pre_start.sh`
  is documented to have the environment variables. A config cache written
  before the environment exists is a config cache with no database credentials
  in it, and the failure that produces looks nothing like its cause. Same end
  state, no ambiguity about ordering.

### The database

Create a PostgreSQL database in the Liara panel; it hands you a host, a port, a
name and a password. Set them on the app together with the rest of the table
below — panel, or `liara env:set`. Nothing else has to be running: the
migrations already create the tables that `SESSION_DRIVER=database` and
`CACHE_STORE=database` need.

On the first deploy the hook migrates and seeds, and the page comes up with the
catalogue on it. On every deploy after that `catalogue:seed` finds products and
leaves them alone.

### HTTPS

Liara puts a reverse proxy in front of every app, and `bootstrap/app.php`
already trusts its forwarded headers. Liara's own documentation still describes
the older `config/trustedproxy.php` file — that is the Laravel 10 way; this app
is on Laravel 13, where the same thing is `$middleware->trustProxies(at: '*')`
in `bootstrap/app.php`. It is done. Do not add the config file as well.

### What was tested

`liara.json` parses, and `liara_pre_start.sh` was run end to end against a real
PostgreSQL — migrate, seed-if-empty, and all three caches — and exited clean.
What could not be tested from here is Liara itself: the upload, the build and
the platform's own runtime.

## On any other platform

Anything that builds a Dockerfile will take this: Arvan, Fly.io, Railway,
Render, a plain VM. Set these, and nothing else is required:

| | |
|---|---|
| `APP_KEY` | `php artisan key:generate --show`. Never commit it. |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | the real https:// address — see below; this one breaks the whole site when it is wrong |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | the managed database's |
| `LOG_CHANNEL` | `stderr`, so the platform collects the logs |

**`APP_URL` decides whether the site answers at all.** Every request resolves
to a branch before anything renders, so a host the application does not
recognise gets a 404 — on every page, not one. `BranchSeeder` registers
`APP_URL`'s own host for the main store precisely so a platform-assigned
address like `something.liara.run` works without anyone thinking about it. Set
`CENTRAL_HOSTS` (comma-separated) when more than one host should reach the
main store.

`SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION` can all be `database`:
the migrations already create every table those need, so nothing else has to be
running.

**HTTPS.** Every one of these hosts terminates TLS in front of the container and
forwards plain HTTP. `bootstrap/app.php` trusts the forwarded headers for that
reason — without it the request looks like `http://` from inside, `asset()`
writes `http://` URLs into an `https://` page, and the browser blocks every
stylesheet on it as mixed content. The symptom is a page with no styling at all,
and it is the single most likely thing to go wrong on a first deploy.

## Then

Point `vikyplus.ir` — or a subdomain — at it, and keep the Netlify link as the
design preview until the two are no longer worth keeping apart.
