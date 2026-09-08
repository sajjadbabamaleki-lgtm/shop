<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * robots.txt, which is how the sitemap gets found.
 *
 * **This used to be a static file in public/ and it had no `Sitemap:` line at
 * all** — it said `User-agent: * / Disallow:` and nothing else. That is most
 * of why an aggregator reported «سایت مپ پیدا نشد»: a crawler that is not told
 * where the sitemap is either guesses /sitemap.xml or gives up, and this shop
 * answered the 404 page to the guess. The file is served from here instead of
 * from disk because two of the things in it cannot be written down in advance:
 *
 *  - **The `Sitemap:` directive has to be a fully-qualified URL**, and this
 *    application answers on more than one host — vikyplus.ir, www.vikyplus.ir
 *    and the Liara address all resolve to the central branch through
 *    branch_domains. A hard-coded host would be wrong on two of the three, and
 *    wrong in the direction where a crawler is pointed at a domain the client
 *    may not have finished moving to. `URL::to()` builds it from the host the
 *    request actually arrived on.
 *  - **Every franchise has a sitemap of its own** at /{branch}/sitemap.xml,
 *    because a sitemap may only name URLs at or below its own path. Franchises
 *    are opened by a person running `branch:open`, so the list is a query and
 *    not something a file in public/ could ever know.
 *
 * Deleting the static file is what makes this route reachable at all — the web
 * server hands over anything it cannot find on disk, so as long as
 * public/robots.txt existed it would answer and this would never run.
 * `RobotsAndSitemapTest` asserts it is gone, because putting it back is a
 * silent revert of the whole feature.
 */
class RobotsController extends Controller
{
    /**
     * The paths no crawler has any business in, relative to a storefront root.
     *
     * Private, or infinite. `/search` is the second kind: a query string is an
     * unbounded space of pages and none of them is a page of this shop so much
     * as a question about it. Everything here is deliberately absent from the
     * sitemap too — see SitemapController, which says the same list in the
     * other direction.
     *
     * @var list<string>
     */
    private const PRIVATE_PATHS = ['cart', 'checkout', 'account', 'orders', 'search'];

    public function __invoke(): Response
    {
        $franchises = Branch::query()->franchises()->where('is_active', true)->orderBy('slug')->get();

        $lines = ['User-agent: *'];

        /*
         * The panels. `/admin` bare, because nothing public begins with those
         * six characters — but `/vendor` gets a **trailing slash and must keep
         * it**: a Disallow is a prefix match, so `/vendor` would also match
         * `/vendors/apply`, which is the public «فروشنده شوید» page this shop
         * wants found. Tidying that slash away deindexes it, and nothing goes
         * red.
         */
        $lines[] = 'Disallow: /admin';
        $lines[] = 'Disallow: /vendor/';

        // The storefront's own private paths, once for the main store and
        // again for every franchise — a franchise's basket is at /shiraz/cart,
        // which no rule about /cart matches. Written out rather than reached
        // with a `*` wildcard, which is an extension some crawlers ignore.
        foreach (['', ...$franchises->pluck('slug')->all()] as $prefix) {
            $root = $prefix === '' ? '' : '/'.$prefix;

            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = 'Disallow: '.$root.'/'.$path;
            }
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.URL::to('/sitemap.xml');

        foreach ($franchises as $franchise) {
            $lines[] = 'Sitemap: '.URL::to('/'.$franchise->slug.'/sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
