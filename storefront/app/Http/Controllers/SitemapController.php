<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The sitemap, at /sitemap.xml.
 *
 * It exists because a shopping aggregator refused the shop over it — «سایت مپ
 * پیدا نشد». Torob reads a feed we POST to it (see TorobFeedController), but
 * that is one aggregator's private protocol; everybody else, search engines
 * included, asks for this file, and until now the answer was the 404 page.
 *
 * **Registered inside the storefront's route closure**, like every other page,
 * so it is mounted at the site root and again under /{branch}. That is not
 * only tidiness — it is what makes the file correct. A sitemap may only name
 * URLs at or below its own path, so a franchise's sitemap has to live at
 * /shiraz/sitemap.xml to be allowed to name /shiraz/products/… , and it must,
 * because a franchise sells the central catalogue at its own prices and every
 * one of those pages is its own address. `storefront_route()` carries the
 * prefix, so the same code writes both.
 *
 * It also means the tenant is already resolved when this runs, which the
 * queries below depend on: price and stock belong to a branch, so
 * `Product::listable()` with nothing bound correctly returns nothing. The
 * Torob feed binds the central branch by hand because a POST from their
 * servers is not a visitor at an address; this is a visitor at an address, so
 * `ResolveTenant` has already done it.
 *
 * **What is in it: every page a stranger can open that means something on its
 * own.** What is deliberately not:
 *
 *  - `/cart`, `/checkout`, `/account…`, `/orders…` — private, or empty until
 *    somebody has done something. A crawler signed in as nobody sees a shell.
 *  - `/search` — a query string is an infinite space of pages, and none of
 *    them is a page of this shop so much as a question about it.
 *  - `/admin` and `/vendor/…` — panels.
 *
 * `robots.txt` names this file (and every branch's) so the aggregators and
 * crawlers that look there rather than guessing find it; see RobotsController,
 * which also says the same "not this" list in the other direction.
 *
 * **One flat `urlset`, which the format caps at 50,000 URLs and 50MB.** This
 * shop is two orders of magnitude under both and a sitemap index would be
 * machinery for nothing today — but the ceiling is silent when it arrives (the
 * file is simply refused), so whoever imports a catalogue that size splits
 * this into an index of paged children before they do it, not after.
 *
 * **No `priority` and no `changefreq`.** Google has said for years that it
 * ignores both, and every number anyone writes into them is invented — this
 * repository has a rule about invented numbers. `lastmod` is here, but only on
 * the URLs that have one row behind them with a timestamp of its own; the
 * fixed pages carry none rather than a date derived from something that is not
 * quite what changed.
 */
class SitemapController extends Controller
{
    /**
     * How long a built sitemap is served again before it is rebuilt.
     *
     * Five minutes, and the number is a trade rather than a preference. The
     * cost of caching is staleness — a product published in the panel is not in
     * the file until this expires — and five minutes is short enough that
     * nobody waits on it and long enough that no burst of requests is ever
     * answered by more than a handful of rebuilds.
     *
     * **Deliberately not an hour**, which is the reflex. The thing being
     * defended against is a crawler asking repeatedly in a short window; an
     * hour buys almost nothing more against that and costs twelve times the
     * staleness against the shop's own catalogue.
     *
     * Worth being straight about what this does not do: **the crawl load is the
     * product pages, not this file.** A crawler reads the sitemap once and then
     * walks the hundred-odd URLs in it, so this is the cheap half. The other
     * half is the `Crawl-delay` in RobotsController and, past that, the plan.
     */
    private const FRESH_FOR = 300;

    public function __invoke(Request $request): Response
    {
        /*
         * Built once every few minutes per branch and per host, rather than
         * once per request.
         *
         * This file is a whole-catalogue scan — the products, the sections and
         * the articles, each `get()` in full — and it is asked for by machines,
         * repeatedly, at whatever rate they please. It is also the one address
         * this shop published *in order to be crawled*: robots.txt names it, so
         * the same push that made it findable made it worth caching.
         *
         * **The host is in the key, and taking it out would be a bug that only
         * shows up on one domain.** Every `loc` in here is absolute and built
         * from the host the request arrived on — that is the whole reason this
         * is a route and not a file on disk — and this application answers on
         * vikyplus.ir, www.vikyplus.ir and the Liara address alike. One cache
         * entry for all three would hand a crawler on one domain a sitemap
         * full of URLs on another, which is precisely the cross-domain
         * duplication a sitemap exists to prevent.
         *
         * The branch is in the key for the ordinary reason: /shiraz/sitemap.xml
         * lists Shiraz's shelves, and prices and stock belong to a branch.
         *
         * Measured here on the seeded catalogue: **8 queries uncached, 6 on a
         * hit** — and that understates it, because the five seeded shoes are
         * the smallest catalogue this shop will ever have. Most of the six that
         * remain are the framework's own; what the cache actually removes are
         * the whole-table `get()`s, and those are the ones that grow with the
         * catalogue while a cache hit stays flat. The store is `database`, so a
         * hit is a row read rather than free.
         */
        $key = sprintf(
            'sitemap:%s:%s',
            $request->getHost(),
            app(TenantContext::class)->branchOrNull()?->slug ?? 'central',
        );

        $xml = Cache::remember($key, self::FRESH_FOR, fn (): string => $this->xml([
            ...$this->fixedPages(),
            ...$this->categories(),
            ...$this->products(),
            ...$this->articles(),
        ]));

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * The pages that are copy rather than catalogue.
     *
     * No `lastmod` on any of them: a content page changes when somebody edits
     * a Blade file, which is a deploy and not a row, and this application has
     * no honest way to date it. An absent `lastmod` is a crawler's own
     * business; a wrong one is a lie it believes.
     *
     * @return list<array{loc: string, lastmod?: string}>
     */
    private function fixedPages(): array
    {
        $names = [
            'home',
            'shop',
            'articles',
            'vendors.apply',
            // The content pages and the two enquiry pages are generated from
            // the same lists the routes are, so a page added there is in the
            // sitemap without anybody remembering this file.
            ...array_values(PageController::PAGES),
            ...array_keys(Enquiry::kinds()),
        ];

        return array_map(fn (string $name) => ['loc' => storefront_route($name)], $names);
    }

    /**
     * The sections, and only the ones with something behind them.
     *
     * `open()` takes out «به‌زودی»: a section that is announced and holds
     * nothing is the same page for every visitor and has no products to offer
     * an aggregator. The `whereHas` takes out the ones that are open and empty
     * anyway — this branch may not stock what another does, and a URL in a
     * sitemap is a promise that there is something at the end of it.
     *
     * @return list<array{loc: string, lastmod?: string}>
     */
    private function categories(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->open()
            ->whereHas('products', fn (Builder $p) => $p->listable())
            ->orderBy('position')
            ->get()
            ->map(fn (Category $c) => $this->row(storefront_route('category', $c), $c->updated_at))
            ->all();
    }

    /**
     * `listable()`, not `purchasable()` — the same choice the listing and the
     * Torob feed make, for the same reason. A shoe this branch sells and has
     * run out of keeps its page and its address, so taking it out of the
     * sitemap would ask crawlers to forget a URL that still answers 200.
     *
     * @return list<array{loc: string, lastmod?: string}>
     */
    private function products(): array
    {
        return Product::query()
            ->listable()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Product $p) => $this->row(storefront_route('product', $p), $p->updated_at))
            ->all();
    }

    /**
     * @return list<array{loc: string, lastmod?: string}>
     */
    private function articles(): array
    {
        return Article::query()
            ->published()
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Article $a) => $this->row(storefront_route('article', $a), $a->updated_at))
            ->all();
    }

    /** @return array{loc: string, lastmod?: string} */
    private function row(string $loc, ?Carbon $lastmod): array
    {
        return $lastmod === null
            ? ['loc' => $loc]
            : ['loc' => $loc, 'lastmod' => $lastmod->toAtomString()];
    }

    /**
     * @param  list<array{loc: string, lastmod?: string}>  $urls
     */
    private function xml(array $urls): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            // A slug may be Persian — the catalogue is imported, and Basalam's
            // names arrive in Persian. `route()` percent-encodes the path, and
            // this escapes what is left, because a bare `&` in a <loc> is a
            // parse error and the whole file is refused rather than one line.
            $lines[] = '    <loc>'.$this->escape($url['loc']).'</loc>';

            if (isset($url['lastmod'])) {
                $lines[] = '    <lastmod>'.$this->escape($url['lastmod']).'</lastmod>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
