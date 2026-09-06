<?php

use App\Http\Middleware\ReportServerTiming;
use App\Http\Middleware\ResolveAdminTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        /*
         * ترب's product feed, in a group of its own with **no middleware**.
         *
         * Not in `routes/web.php`, and that is the whole point: the `web`
         * group carries `ValidateCsrfToken`, and a POST from Torob's servers
         * has no CSRF token — every request came back 419 before reaching any
         * code of ours. See routes/torob.php for the rest of it.
         */
        then: function (): void {
            Route::group([], base_path('routes/torob.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every host this can be deployed to terminates TLS in front of the
        // app and forwards plain HTTP. Without this the request looks like
        // http:// from inside, asset() writes http:// URLs into an https://
        // page, and the browser blocks all of them as mixed content — the
        // page arrives with no stylesheets at all. The proxy is the platform's
        // own and is the only thing that can reach the container, so trusting
        // its headers is trusting the platform.
        $middleware->trustProxies(at: '*');

        // Every response says how long the server took, in the standard
        // `Server-Timing` header. Prepended so it wraps everything else and
        // the figure is the whole request rather than part of it. See the
        // middleware: the live site spends about 915ms of its own on the home
        // page, and this is what says where that goes.
        $middleware->prepend(ReportServerTiming::class);

        /*
         * The tenant has to be resolved before route model binding, not after.
         *
         * Every branch-owned model carries a global scope that returns nothing
         * when no branch is bound — deliberately, so a forgotten middleware is
         * an empty page rather than a leak. SubstituteBindings runs inside the
         * web group, so with ResolveTenant merely appended to that group, the
         * binding for /orders/VP-XXXX ran while nothing was bound: the scope
         * did its job, the order was invisible, and every page that binds a
         * branch-owned model answered 404 no matter who asked.
         *
         * The middleware itself is applied per route group in routes/web.php,
         * not globally, because administration routes must resolve the branch
         * from the signed-in user and never from the URL (§18). This only says
         * that wherever it *is* applied, it runs first.
         */
        /*
         * There is no route called `login`, and there are two sign-ins: staff
         * at /admin/login and shoppers at /account/enter. Laravel's `auth`
         * middleware would otherwise redirect to a route name that does not
         * exist and produce a routing exception instead of a login page.
         *
         * Which one a guest is sent to is decided by where they were going. A
         * customer bounced off /account onto the staff sign-in would be shown a
         * form their account cannot satisfy, and told it is «برای مدیران و
         * کارکنان شعبه است» — a dead end that looks like a mistake they made.
         *
         * `storefront_route` rather than `route`, so a franchise's customer
         * lands on that franchise's sign-in and not the main store's.
         */
        /*
         * `product.comment` and `article.comment` are named beside the pattern
         * rather than renamed to match it. Both are shoppers' routes, but their
         * addresses belong to a product and to an article —
         * `/products/{slug}/comments`, `/articles/{slug}/comments` — and calling
         * either `account.*` to win this redirect would misname a URL in the one
         * file a reader looks it up in. A storefront route behind
         * `auth:customer` belongs here; there is no third kind.
         */
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('*account*', 'product.comment', 'article.comment')
            ? storefront_route('account.enter')
            : route('admin.login'));

        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenant::class,
        );

        // The same rule for the panel, which resolves its branch from the
        // signed-in user rather than from the address. It has to land after
        // authentication (it needs a user) and before binding (Order is
        // branch-scoped), and this puts it exactly there: Laravel's default
        // priority already has authentication ahead of SubstituteBindings.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveAdminTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `torob_api/*` beside `api/*` so a machine that asks for the wrong
        // path under the feed's prefix — a version we do not serve, a trailing
        // segment — is told so in JSON rather than handed the error page's
        // HTML. Torob reads status codes and bodies, not pages. (`api/*`
        // already covers `api/torob_api/*`, which is the path their bot builds
        // for itself; see routes/torob.php.)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'torob_api/*'),
        );
    })->create();
