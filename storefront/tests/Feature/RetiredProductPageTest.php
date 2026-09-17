<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTorobToken;
use App\Models\Branch;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The address of a shoe the shop has stopped selling.
 *
 * ترب, 2026-09-15: «آدرس نمونهٔ https://vikyplus.ir/products/golden-goose در
 * حال حاضر صفحهٔ معتبر محصول را باز نمی‌کند … آدرس‌های قدیمی مانند نمونهٔ بالا
 * خطای کالا وجود ندارد ندهند». That slug is one of the five setup shoes, and
 * `take_the_five_setup_shoes_off_the_shop` archived it on 2026-09-07 — a
 * correct migration that left a live address answering 404.
 *
 * Nothing in this repository could see it. The suite renders product pages for
 * products it has just seeded, which are never retired; the feed's own test
 * reads the feed, which correctly stops listing a retired shoe; and
 * `check-parity.js` and `check-overflow.js` never open an address that no
 * longer has a product behind it. The failure is entirely in what a URL
 * somebody else is holding does afterwards, so this is the file that holds it.
 */
class RetiredProductPageTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
    }

    /** Retire one shoe the way the panel and the migrations do: archived, unpublished. */
    private function retire(string $slug): Product
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $product->forceFill(['status' => 'archived', 'published_at' => null])->save();

        return $product;
    }

    /**
     * The whole catalogue, as ترب is sent it.
     *
     * The token is skipped on purpose — `TorobFeedTest` is where a signature
     * is made and checked, and a second copy of that setup here would be a
     * second thing to keep in step for no extra guard.
     */
    private function feed(): TestResponse
    {
        config()->set('services.torob.enabled', true);

        return $this->withoutMiddleware(VerifyTorobToken::class)
            ->postJson('/torob_api/v3/products', ['page' => 1, 'sort' => 'date_added_desc'])
            ->assertOk();
    }

    /**
     * The one ترب asked about: the address answers, and says what happened.
     */
    public function test_a_retired_shoe_keeps_its_address(): void
    {
        $product = $this->retire('golden-goose');

        $this->get('/products/golden-goose')
            ->assertOk()
            ->assertSee($product->title, false)
            ->assertSee('دیگر در فروشگاه عرضه نمی‌شود', false);
    }

    /**
     * 200, not 404 and not 410.
     *
     * The whole point: an aggregator reads any of the error codes as «کالا
     * وجود ندارد» and takes the shop's listing down. Asserted as a number
     * rather than through `assertOk()` so that changing it is a deliberate act
     * by whoever reads this line.
     */
    public function test_the_answer_is_two_hundred_and_asks_not_to_be_indexed(): void
    {
        $this->retire('golden-goose');

        $response = $this->get('/products/golden-goose');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    /**
     * Somewhere to go next, and it is the shop's real stock.
     *
     * A page that only says «gone» is a dead end with a nicer sentence.
     */
    public function test_it_offers_what_the_shop_does_have(): void
    {
        $this->retire('golden-goose');

        // Bound by hand: `listable()` reads an offer, offers belong to a
        // branch, and a query with nothing bound correctly returns nothing —
        // which here would look exactly like a shop with nothing left.
        $living = $this->tenant->forBranch(
            Branch::central(),
            fn () => Product::query()->listable()->pluck('title'),
        );

        $this->assertNotEmpty($living, 'The seeded catalogue has to have something left to offer.');

        $response = $this->get('/products/golden-goose')->assertOk();

        $response->assertSee('به جای آن، این‌ها هست', false);
        $response->assertSee($living->first(), false);
    }

    /**
     * The retired shoe is never its own consolation.
     *
     * Asserted on the link and not on the name: the name is on this page
     * twice already, in the sentence and in the browser's tab. A card is the
     * only thing that would carry the address.
     */
    public function test_the_retired_shoe_is_not_among_the_alternatives(): void
    {
        $this->retire('golden-goose');

        $this->get('/products/golden-goose')
            ->assertOk()
            ->assertDontSee('href="'.url('/products/golden-goose').'"', false);
    }

    /**
     * Retiring it is still retiring it.
     *
     * The page coming back must not put the shoe back in the shop: it stays
     * out of the listing, out of the sitemap and out of ترب's feed. That is
     * the line between keeping an address alive and undoing the client's own
     * decision — «این موارد اوایل راه اندازی سایت قرار داده شدن».
     */
    public function test_a_retired_shoe_is_still_out_of_the_shop(): void
    {
        $product = $this->retire('golden-goose');

        $this->get('/products')->assertOk()->assertDontSee($product->title, false);

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/products/golden-goose', false);

        $this->feed()->assertDontSee('/products/golden-goose', false);
    }

    /**
     * Every address the feed hands out opens.
     *
     * The other half of ترب's request — «آدرس هر محصول در اطلاعات ارسالی،
     * دقیقاً همان آدرس نهایی و عمومی صفحهٔ محصول باشد». The feed builds its
     * `page_url` from the product's own route, so this can only fail if the
     * two ever learn different rules about what a product page is; that is
     * exactly what happened in the other direction, and it is cheap to hold.
     */
    public function test_every_address_the_feed_sends_opens_a_product_page(): void
    {
        $this->retire('golden-goose');

        $feed = $this->feed()->json('products');

        $this->assertNotEmpty($feed, 'A feed with nothing in it would pass this without asking anything.');

        foreach ($feed as $row) {
            $this->get(parse_url($row['page_url'], PHP_URL_PATH))
                ->assertOk()
                ->assertDontSee('دیگر در فروشگاه عرضه نمی‌شود', false);
        }
    }

    /**
     * A slug that was never a product is still a 404.
     *
     * The change is about addresses the shop handed out, not about making
     * every string under /products answer 200 — which would tell a crawler the
     * shop has infinitely many pages.
     */
    public function test_a_slug_that_never_existed_is_still_not_found(): void
    {
        $this->get('/products/there-has-never-been-such-a-shoe')->assertNotFound();
    }
}
