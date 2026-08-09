<?php

use Illuminate\Support\Facades\Route;

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

        return route($route);
    }
}
