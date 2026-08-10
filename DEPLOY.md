# Putting the storefront on a URL

`HANDOFF.md` says what is built. This says how to run it somewhere other than a
laptop, and — as honestly as it can — which parts of that have been tested and
which have not.

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

## On a platform

Anything that builds a Dockerfile will take this: Liara, Arvan, Fly.io,
Railway, Render, a plain VM. Set these, and nothing else is required:

| | |
|---|---|
| `APP_KEY` | `php artisan key:generate --show`. Never commit it. |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | the real https:// address |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | the managed database's |
| `LOG_CHANNEL` | `stderr`, so the platform collects the logs |

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
