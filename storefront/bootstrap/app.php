<?php

use App\Http\Middleware\ReportServerTiming;
use App\Http\Middleware\ResolveAdminTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
         * `product.comment` is named beside the pattern rather than renamed to
         * match it. It is a shopper's route — «فقط کسی که خریده» — but its
         * address is a product's, `/products/{slug}/comments`, and calling it
         * `account.*` to win this redirect would misname a URL in the one file
         * a reader looks it up in. A storefront route behind `auth:customer`
         * belongs here; there is no third kind.
         */
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('*account*', 'product.comment')
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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
