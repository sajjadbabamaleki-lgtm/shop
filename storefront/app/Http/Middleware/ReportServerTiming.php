<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * How long this request took the server, said out loud on the response.
 *
 * **Why this exists.** «سایت وحشتناک کند هستش» was answered three times by
 * cutting bytes, because bytes were the only thing this repository could
 * measure: the development container's proxy cannot reach the live site, so
 * every figure ever quoted here came from `php artisan serve` on the same
 * machine as its database. The first probe that asked the live site (the
 * `probe` job in the deploy workflow) found the home page taking **1,080ms**
 * to answer where a static file on the same connection took 165ms — about
 * **915ms of server**, on every load, whether or not a single byte of CSS
 * needed downloading. The same page answers in 57ms here. No amount of
 * cutting stylesheets touches that second.
 *
 * Knowing it is a second is not knowing where the second goes, and the two
 * candidates want opposite fixes: PHP that is slow (a cold opcache, a small
 * container) is a platform setting, and a database that is far away is caching
 * and query count. Guessing between them costs a deploy either way.
 *
 * So the response carries the answer. `Server-Timing` is the standard header
 * for this — every browser's network panel draws it, `curl -I` prints it, and
 * the probe job reads it on every push:
 *
 *     Server-Timing: app;dur=812.4, db;dur=281.9, dbq;dur=0;desc="35 queries"
 *
 * `app` is measured from `LARAVEL_START`, which the front controller sets
 * before the framework boots, so it includes autoloading and bootstrapping —
 * the part a missing opcache would show up in. `db` is the time the driver
 * itself reports, so `app` minus `db` is PHP's own work.
 *
 * **It is three integers and no secret.** The header says how long the server
 * took and how many queries it ran; it names no query, no table and no value,
 * and it is the same information any visitor can already infer with a
 * stopwatch. That is why it is on for everybody rather than behind a flag: a
 * diagnostic that has to be switched on is one nobody has on when the thing
 * they need to diagnose happens.
 */
class ReportServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $queries = 0;
        $sql = 0.0;

        // Registered here rather than in a provider, because it has to close
        // over this request's own two counters. That is safe on php-fpm, where
        // the process ends with the response — **if this app is ever put on
        // Octane, this becomes a leak**: every request would add another
        // listener to a worker that never dies, and by the thousandth request
        // each query would be counted a thousand times. On Octane the fix is a
        // listener registered once against a counter reset per request.
        DB::listen(function ($query) use (&$queries, &$sql): void {
            $queries++;
            $sql += $query->time;
        });

        $response = $next($request);

        // LARAVEL_START is defined by public/index.php before the autoloader
        // runs. If something else dispatched this request — a test, a console
        // command — fall back to this middleware's own start, which is still
        // a truthful number, just a smaller one.
        $start = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT');
        $app = $start ? (microtime(true) - $start) * 1000 : 0.0;

        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.1f, db;dur=%.1f, dbq;dur=0;desc="%d queries"',
            $app,
            $sql,
            $queries,
        ));

        return $response;
    }
}
