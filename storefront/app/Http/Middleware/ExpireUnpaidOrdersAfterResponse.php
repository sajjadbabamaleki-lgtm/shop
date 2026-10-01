<?php

namespace App\Http\Middleware;

use App\Support\Checkout\ExpireUnpaidOrders;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sweep abandoned reservations back onto the shelf, after the page is sent.
 *
 * The scheduler is the obvious home for this and it is wired there too, but
 * the Liara app runs no cron — and without one every abandoned checkout holds
 * its shoes for ever, which is the fault this exists to end. So the storefront does it itself: at most once a minute,
 * in `terminate()`, which PHP-FPM runs after the response has gone, so no
 * shopper waits on it.
 */
class ExpireUnpaidOrdersAfterResponse
{
    private const KEY = 'orders:expire:swept';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Off in the test suite (phpunit.xml), where it would cancel the
        // fixtures of tests that are about something else entirely.
        if (! config('storefront.sweep_unpaid_orders', true)) {
            return;
        }

        try {
            if (! Cache::add(self::KEY, true, 60)) {
                return;
            }

            app(ExpireUnpaidOrders::class)->run();
        } catch (Throwable $e) {
            Log::error("Sweeping unpaid orders failed: {$e->getMessage()}");
        }
    }
}
