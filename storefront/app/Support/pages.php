<?php

use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Support\Facades\Route;

if (! function_exists('storefront_route')) {
    /**
     * A storefront URL that stays inside the branch the visitor is browsing.
     *
     * The storefront's routes are registered twice — once at the site root for
     * the main store and once under /{branch} for every franchise — so a
     * visitor in Shiraz following an ordinary link must land on
     * vikyplus.ir/shiraz/… rather than being tipped out into the central
     * store's prices without noticing.
     *
     * The branch segment itself is filled in by URL::defaults(), set when the
     * request was resolved, so no call site has to carry it.
     *
     * Takes whatever `route()` takes — an array, a single value, or a model,
     * which is what a link to a product or a category is written as.
     *
     * @param  array<string, mixed>|string|int|UrlRoutable  $parameters
     */
    function storefront_route(string $name, mixed $parameters = []): string
    {
        $tenant = app(TenantContext::class);

        if ($tenant->has() && ! $tenant->isCentral() && Route::has("branch.{$name}")) {
            return route("branch.{$name}", $parameters);
        }

        return route($name, $parameters);
    }
}

if (! function_exists('page_url')) {
    /**
     * Resolve one of the template's page names to a URL on this site.
     *
     * The ported markup links to the ThemeForest demo's filenames — shop.html,
     * cart.html, forty-odd of them — because that is what the design was drawn
     * against. The storefront has one page so far, so a name with no route
     * behind it resolves to '#': the link stays where the design puts it and
     * goes nowhere, rather than pointing at a 404.
     *
     * config/storefront.php is the one place to point a name at a real route
     * as each page gets built.
     */
    function page_url(string $page): string
    {
        $route = config('storefront.pages')[$page] ?? null;

        if ($route === null || ! Route::has($route)) {
            return '#';
        }

        return storefront_route($route);
    }
}

if (! function_exists('photo_srcset')) {
    /**
     * The same photograph offered at the size the reader's screen can show.
     *
     * Returns the `srcset` and `sizes` attributes for an `<img>`, or an empty
     * string for a picture with no small copy — an uploaded one, the
     * placeholder — so every call site can write it unconditionally.
     *
     * **Why there is a second copy at all.** The product photographs are 1400
     * wide because a 2x desktop draws one at 583 CSS pixels and wants 1166.
     * A phone draws the same photograph into 267 and can show 534. Measured on
     * the home page at 390 through a throttled line, those photographs were
     * 655KB of a 2.5MB page — and unlike the stylesheets they are already
     * compressed, so nothing downstream can help them. The 700-wide copies are
     * 217KB. `theme/make-photo-sizes.js` cuts them and writes the manifest.
     *
     * **`sizes` is one rule for every photograph on this site, and it is the
     * same string `theme/make-rtl-page.js` writes into the preview page.** The
     * two copies of the home page have to choose the same file at every width
     * or `check-parity.js` reports a difference that is really two encodings
     * of one picture. It is deliberately not measured per image: at 390 it
     * asks for 273 CSS pixels — 546 on a 2x screen, so the 700 — and at 1920
     * for 768, which takes the original, as does any 2x screen. The largest
     * box any photograph is drawn into is 583, so nothing that was sharp
     * becomes soft.
     */
    function photo_srcset(string $path): string
    {
        static $photos = null;

        if ($photos === null) {
            // Under public/, with the photographs it describes. Only
            // `storefront/` is deployed — `download-version/` is not on the
            // server — so a manifest read from the design bundle would be
            // there in every test and missing in production, which is the
            // shape of failure this repository has been bitten by before.
            // `sync-storefront-assets.js` copies it in by name.
            $manifest = public_path('assets/img/photo-sizes.json');
            $photos = is_file($manifest)
                ? (json_decode(file_get_contents($manifest), true)['photos'] ?? [])
                : [];
        }

        $photo = $photos[$path] ?? null;

        if ($photo === null || ! is_file(public_path($photo['small']))) {
            return '';
        }

        return sprintf(
            ' srcset="%s %dw, %s %dw" sizes="%s"',
            e(asset($photo['small'])),
            $photo['smallWidth'],
            e(asset($path)),
            $photo['width'],
            '(min-width: 992px) 40vw, 70vw',
        );
    }
}
