<?php

use App\Domains\Tenancy\Middleware\GuardMiniStoreIsolation;
use App\Domains\Tenancy\Middleware\ResolvePanelTenant;
use App\Domains\Tenancy\Middleware\ResolveTenant;
use App\Domains\Tenancy\TenantRouting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // The route table is chosen per request rather than fixed, because on
        // a mini store's own hostname the parent store's routes must not
        // exist. See App\Domains\Tenancy\TenantRouting for why the isolation
        // is done here and what it costs.
        using: function () {
            Route::get('/up', fn () => response('ok'))->name('health');

            TenantRouting::register();
        },
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.panel' => ResolvePanelTenant::class,
            'ministore.isolated' => GuardMiniStoreIsolation::class,
        ]);

        // The tenant has to be bound before route-model binding runs, not
        // after. Otherwise `/panel/vendor/mine/offers/{offer}` resolves the
        // offer with no tenant scope on the query, and a seller who types
        // another seller's offer id gets that offer handed to their
        // controller. Binding first means the lookup itself is scoped, so the
        // id simply does not exist and Laravel 404s before any code runs.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolvePanelTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
