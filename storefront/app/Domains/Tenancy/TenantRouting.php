<?php

namespace App\Domains\Tenancy;

use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Decides which route table this request gets (§4).
 *
 * This is the strongest part of the mini-store isolation the client asked for,
 * and it is worth being blunt about why it is done here rather than in a
 * layout. Hiding the parent's links is a presentation choice that one careless
 * shared partial undoes. Not registering the parent's routes is structural: on
 * `shiraz.vikyplus.ir` there is no route that renders the parent home page, no
 * route to the parent's catalogue, and no route to the panel. A link back to
 * the parent store cannot be followed because the URL does not exist on that
 * hostname, not because nothing happens to link to it.
 *
 * The trade-off, stated plainly: `php artisan route:cache` compiles one route
 * table, and this application has two. Route caching must stay off, or be run
 * per host into per-host cache files. For a store of this size the cost is a
 * fraction of a millisecond per request and the isolation is worth it.
 */
class TenantRouting
{
    /**
     * Whether the current request arrived at a mini store's own hostname.
     *
     * Called during route registration, which happens before the database is
     * guaranteed to be reachable — during `artisan migrate` on a fresh
     * machine, for instance — so a failed lookup answers "no" and leaves the
     * parent's routes registered rather than taking the application down.
     *
     * Artisan is short-circuited because a console command has no hostname to
     * resolve and `route:list` should show the parent's table. The test suite
     * is deliberately *not* short-circuited even though it also runs in the
     * console: the isolation this method provides is the thing most worth
     * testing, and a guard that switched it off under PHPUnit would leave the
     * only real enforcement untested.
     */
    public static function requestHasMiniStoreHost(): bool
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        try {
            return app(TenantResolver::class)->byHost(request()->getHost()) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Register the routes this request is allowed to see.
     *
     * The mini-store route file is registered exactly once either way, so
     * route names have one meaning and `route('store.product', $p)` produces
     * `/product/...` on a bound hostname and `/s/kafsh-ali/product/...`
     * otherwise, without a single caller knowing which.
     */
    public static function register(): void
    {
        if (static::requestHasMiniStoreHost()) {
            Route::middleware('web')
                ->group(base_path('routes/store.php'));

            return;
        }

        // Path form. One registration covers both kinds: the prefix segment
        // is a parameter constrained to the two configured letters, so vendor
        // and franchise mini stores share a name space without sharing a slug
        // space.
        Route::middleware('web')
            ->prefix('{tenantPrefix}/{tenantSlug}')
            ->where([
                'tenantPrefix' => implode('|', [
                    preg_quote((string) config('tenancy.vendor_path_prefix'), '/'),
                    preg_quote((string) config('tenancy.franchise_path_prefix'), '/'),
                ]),
                'tenantSlug' => '[A-Za-z0-9\-]+',
            ])
            ->group(base_path('routes/store.php'));

        Route::middleware('web')->group(base_path('routes/panel.php'));
        Route::middleware('web')->group(base_path('routes/web.php'));
    }
}
