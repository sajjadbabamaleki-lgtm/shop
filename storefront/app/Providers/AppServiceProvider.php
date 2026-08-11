<?php

namespace App\Providers;

use App\Support\Checkout\CartManager;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request. Every query scope, policy and view has to answer
        // "which branch is this?" the same way, and they can only do that if
        // they are all asking the same object — spec §17.
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * The header's basket badge.
         *
         * It was the template's «5» — a number that never moved however full
         * the basket was, which is worse than no number at all. A composer
         * rather than a variable passed from every controller: the header is
         * on every storefront page and there is no controller that owns it.
         *
         * `count()` never creates a basket. `current()` would, and the header
         * runs for every visitor who has not put anything in one.
         */
        View::composer('partials.header', function ($view): void {
            $view->with('basketCount', app(CartManager::class)->count());
        });

        //
    }
}
