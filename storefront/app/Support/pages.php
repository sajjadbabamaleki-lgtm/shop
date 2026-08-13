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
