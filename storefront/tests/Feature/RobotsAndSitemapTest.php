<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Models\Article;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\Product;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /sitemap.xml and /robots.txt.
 *
 * Both exist because an aggregator refused the shop — «سایت مپ پیدا نشد». The
 * shop had neither: robots.txt was a static two-line file naming no sitemap,
 * and /sitemap.xml was the 404 page.
 *
 * **The case that matters most here is `test_every_url_in_the_sitemap_answers`**,
 * and it is the same guard, for the same reason, as ContentPagesTest counting
 * the footer's dead links. A sitemap is a list of promises made to a machine
 * that will not tell anybody when one is broken: a URL that 404s is not a
 * visible fault, it is a line in somebody else's crawl report weeks later, and
 * a slug renamed in the panel or a route renamed here breaks one silently.
 * Rendering every page the file names is the only thing that notices.
 *
 * The other half is what must **not** be in it. A sitemap that names /cart or
 * /account is not broken in any way a test of the happy path can see; it is
 * simply the shop asking to be indexed on its own checkout.
 */
class RobotsAndSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    /**
     * Every `<loc>` in the file, as a path on this site.
     *
     * Read through SimpleXML rather than a regular expression, and through the
     * sitemaps namespace explicitly: **a urlset in the wrong namespace, or one
     * that does not parse, is refused whole** — every URL in it, not the line
     * that is wrong — so both are part of what is being asserted.
     *
     * @return list<string>
     */
    private function locations(string $url = '/sitemap.xml'): array
    {
        $response = $this->get($url)->assertOk();

        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());

        $this->assertNotFalse($xml, 'The sitemap did not parse as XML.');
        $this->assertSame('urlset', $xml->getName());

        $urls = $xml->children('http://www.sitemaps.org/schemas/sitemap/0.9');

        $this->assertGreaterThan(0, count($urls), 'The sitemap named nothing at all.');

        $locations = [];

        foreach ($urls as $entry) {
            $locations[] = (string) $entry->loc;
        }

        return $locations;
    }

    /**
     * The same locations as paths on this site.
     *
     * The home page's `<loc>` is the site root with no trailing slash — that is
     * what `route('home')` builds and what every link on the page already
     * carries — and `parse_url` gives back nothing at all for it rather than
     * '/'. Normalised here so a path is always a path.
     *
     * @return list<string>
     */
    private function paths(string $url = '/sitemap.xml'): array
    {
        return array_map(function (string $loc): string {
            $path = rawurldecode((string) parse_url($loc, PHP_URL_PATH));

            return $path === '' ? '/' : $path;
        }, $this->locations($url));
    }

    /**
     * The thing the aggregator asked for: a sitemap at the address everybody
     * looks for it at, in the format everybody reads.
     */
    public function test_the_sitemap_is_served_as_xml(): void
    {
        $this->assertNotEmpty($this->locations());
    }

    /**
     * Absolute URLs, on the host the request arrived on.
     *
     * The format requires it, and a relative `<loc>` is the kind of thing that
     * validates by eye and is rejected by every consumer.
     */
    public function test_every_location_is_absolute_and_on_this_host(): void
    {
        foreach ($this->locations() as $loc) {
            $this->assertStringStartsWith(config('app.url'), $loc, "«{$loc}» is not an absolute URL on this site.");
        }
    }

    /**
     * **The guard.** Every page the sitemap names is a page this shop serves.
     *
     * See the note at the top of the class: a broken promise here is invisible
     * from inside the application and turns up in somebody else's crawl report.
     */
    public function test_every_url_in_the_sitemap_answers(): void
    {
        foreach ($this->paths() as $path) {
            $this->get($path)->assertOk();
        }
    }

    /**
     * The fixed pages, named one by one rather than counted.
     *
     * `PageController::PAGES` and `Enquiry::kinds()` generate the routes, and
     * the sitemap reads the same two lists, so a page added to either is here
     * without anybody remembering. That is worth pinning: the reason the
     * content pages were built at all is that 21 footer links pointed at pages
     * nobody had made, and a sitemap is the same failure written for machines.
     */
    public function test_the_fixed_pages_are_all_there(): void
    {
        $paths = $this->paths();

        foreach (['/', '/products', '/articles', '/vendors/apply'] as $expected) {
            $this->assertContains($expected, $paths);
        }

        foreach (array_keys(PageController::PAGES) as $page) {
            $this->assertContains('/'.$page, $paths, "The content page «{$page}» is missing from the sitemap.");
        }

        foreach (array_keys(Enquiry::kinds()) as $kind) {
            $this->assertContains('/'.$kind, $paths, "The «{$kind}» page is missing from the sitemap.");
        }
    }

    /**
     * Nothing private, and nothing infinite.
     *
     * `/search` is the second kind — a query string is an unbounded space of
     * pages — and it is the one most easily added by somebody reasoning that
     * the listing is in there so the search should be too.
     */
    public function test_the_sitemap_names_nothing_private(): void
    {
        foreach ($this->paths() as $path) {
            foreach (['/cart', '/checkout', '/account', '/orders', '/admin', '/vendor/', '/search'] as $forbidden) {
                $this->assertStringStartsNotWith($forbidden, $path, "«{$path}» has no business in a sitemap.");
            }
        }
    }

    /**
     * Every shoe this branch lists, including the ones it has run out of.
     *
     * `listable()` and not `purchasable()`, the same choice the listing and the
     * Torob feed make: a shoe that is out of stock keeps its page and its
     * address, so dropping it from the sitemap asks a crawler to forget a URL
     * that still answers 200.
     */
    public function test_every_listable_product_is_in_the_sitemap(): void
    {
        $paths = $this->paths();

        $products = Product::query()->listable()->get();

        $this->assertGreaterThan(0, $products->count());

        foreach ($products as $product) {
            $this->assertContains('/products/'.$product->slug, $paths);
        }
    }

    /**
     * A draft is not a page, and the sitemap must not offer one — route-model
     * binding resolves on the slug alone, so the only thing keeping a draft
     * private is that nobody has its address. A sitemap publishes addresses.
     */
    public function test_an_unpublished_article_is_not_offered(): void
    {
        $draft = Article::create([
            'title' => 'یادداشتی که هنوز تمام نشده',
            'slug' => 'unfinished-note',
            'body' => 'متن',
            'status' => Article::DRAFT,
        ]);

        $paths = $this->paths();

        $this->assertNotContains('/articles/'.$draft->slug, $paths);

        foreach (Article::query()->published()->get() as $article) {
            $this->assertContains('/articles/'.$article->slug, $paths);
        }
    }

    /**
     * «به‌زودی» sections stay out.
     *
     * The rule for a closed section is that nothing about it may *look*
     * different — no badge, no grey mark — and the one place it is kept out of
     * is the listing's filter rail, «where a section that cannot narrow
     * anything is a control that does nothing». A sitemap entry for a section
     * holding no products is the same shape of thing pointed at a crawler.
     */
    public function test_a_coming_soon_section_is_not_in_the_sitemap(): void
    {
        /*
         * The section is taken from the sitemap rather than looked up here on
         * purpose. `Product::listable()` reads a price, prices belong to a
         * branch, and a query run from a test with no branch bound correctly
         * matches nothing — so the obvious `whereHas('products', listable)`
         * finds no section at all and the case passes for the wrong reason.
         * The request has a branch; this borrows its answer.
         */
        $sections = array_values(array_filter(
            $this->paths(),
            fn (string $path) => str_starts_with($path, '/categories/'),
        ));

        $this->assertNotEmpty($sections, 'The sitemap named no sections, so there is nothing to close.');

        $slug = basename($sections[0]);

        Category::query()->where('slug', $slug)->firstOrFail()->update(['coming_soon' => true]);

        $this->assertNotContains('/categories/'.$slug, $this->paths());
    }

    /**
     * A franchise gets a sitemap of its own, and it may only name its own
     * pages.
     *
     * That is the format's rule — a sitemap covers its own path and below —
     * and it is why the route is registered inside the storefront closure
     * rather than once at the site root.
     */
    public function test_a_franchise_has_its_own_sitemap_and_names_only_its_own_pages(): void
    {
        app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );

        app(TenantContext::class)->forget();

        $paths = $this->paths('/shiraz/sitemap.xml');

        foreach ($paths as $path) {
            $this->assertStringStartsWith('/shiraz', $path, "«{$path}» is not Shiraz's to list.");
        }

        $this->assertContains('/shiraz/products', $paths);
    }

    /**
     * robots.txt comes from the application now, and the static file that used
     * to sit in public/ is gone.
     *
     * Both halves matter. The web server answers anything it finds on disk
     * before Laravel sees the request, so restoring that file would take this
     * route out of service and put back the version naming no sitemap at all —
     * the exact state the aggregator refused, with nothing going red.
     */
    public function test_robots_is_served_by_the_application_and_names_the_sitemap(): void
    {
        $this->assertFileDoesNotExist(
            public_path('robots.txt'),
            'A static public/robots.txt shadows the route and silently un-does the sitemap.',
        );

        $body = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('Sitemap: '.config('app.url').'/sitemap.xml', $body);
    }

    /**
     * robots.txt refuses exactly what the sitemap leaves out — and lets
     * through the one path that looks like a panel and is not.
     *
     * `/vendors/apply` is the public «فروشنده شوید» page. A Disallow is a
     * prefix match, so a `/vendor` rule without its trailing slash would
     * deindex it, which is why the slash is asserted here rather than trusted.
     */
    public function test_robots_refuses_the_private_paths_and_not_the_public_one(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['/cart', '/checkout', '/account', '/orders', '/search', '/admin', '/vendor/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}\n", $body);
        }

        $this->assertStringNotContainsString("Disallow: /vendor\n", $body);
    }

    /**
     * A franchise's private paths, and its sitemap.
     *
     * No rule about /cart matches /shiraz/cart, so each branch's paths are
     * written out. They are written out rather than reached with a `*`
     * wildcard, which is an extension not every crawler honours.
     */
    public function test_robots_covers_every_franchise(): void
    {
        app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );

        app(TenantContext::class)->forget();

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Sitemap: '.config('app.url').'/shiraz/sitemap.xml', $body);
        $this->assertStringContainsString("Disallow: /shiraz/cart\n", $body);
    }

    /**
     * No branch can ever be opened at an address that would swallow the
     * sitemap — the fixed route is registered first and would win, leaving a
     * franchise unreachable for a reason nobody would guess.
     */
    public function test_sitemap_cannot_be_taken_by_a_branch(): void
    {
        $this->assertContains('sitemap', Branch::RESERVED_SLUGS);
    }
}
