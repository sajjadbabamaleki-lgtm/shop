<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a program is told a product page is about.
 *
 * **ترب, 2026-09-15 and again 2026-09-19:** «لینک‌های ارسالی شما همچنان فاقد
 * محصول می‌باشند و محتوای کالا در آن‌ها یافت نمی‌شود. این موارد مربوط به زیرساخت
 * سایت شماست.» Measured from a GitHub runner against a live, in-stock,
 * entirely ordinary product page the same morning:
 *
 *     title:       <title>VikyPlus</title>
 *     og:title:    (none)
 *     json-ld:     0 blocks
 *     itemprop:    0 attributes
 *     canonical:   (none)
 *     description: the same sentence as every other page on the site
 *
 * The shoe's name, its price and its stock were all on the page — as words for
 * a person, and in not one of the fields a program reads. Every check this
 * repository had rendered pages and counted pixels, which is a question about
 * what a *person* sees, so none of them could fail on this. That is what this
 * file is for, and it asserts the fields rather than the appearance.
 */
class ProductPageIsLegibleToAMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    private function shoe(): Product
    {
        return Product::where('slug', 'new-balance-530')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function structuredData(string $path): array
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~<script type="application/ld\+json">~',
            $html,
            'The page carries no JSON-LD at all, which is the field an aggregator reads first.',
        );

        preg_match('~<script type="application/ld\+json">\s*(.*?)\s*</script>~s', $html, $m);

        $data = json_decode($m[1], true);

        $this->assertIsArray($data, 'The JSON-LD block is not valid JSON: '.json_last_error_msg());

        return $data;
    }

    /**
     * The title names the shoe. This is the whole of what ترب's first check
     * looks at, and it said «VikyPlus» on all 128 products.
     */
    public function test_the_title_names_the_shoe_and_not_the_shop(): void
    {
        $product = $this->shoe();

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        preg_match('~<title>(.*?)</title>~s', $html, $m);

        $this->assertStringContainsString($product->title, $m[1] ?? '');
    }

    /**
     * And the description describes *this* shoe.
     *
     * Asserted as «not the site-wide sentence» as well as «contains the
     * name», because the failure being fixed was not a missing tag — it was a
     * tag that was there, was valid, and said the same thing on every page.
     */
    public function test_the_description_describes_this_shoe(): void
    {
        $product = $this->shoe();

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        preg_match('~<meta name="description" content="([^"]*)">~u', $html, $m);

        $this->assertNotEmpty($m[1] ?? '', 'The product page has no description at all.');
        $this->assertStringContainsString($product->title, $m[1]);
        $this->assertStringNotContainsString(
            'فروشگاه اینترنتی کیف و کفش زنانه: کتانی',
            $m[1],
            'The product page is still serving the site-wide description, so every product looks alike to a crawler.',
        );
    }

    /** One page, one address it wants to be known by. */
    public function test_the_page_says_which_address_it_is(): void
    {
        $product = $this->shoe();

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/products/'.$product->slug).'">', false);
    }

    /**
     * The block an aggregator actually reads, and every field in it.
     *
     * Not «a JSON-LD block exists»: a block with the shop's name in it would
     * pass that and would be the same failure in a new syntax.
     */
    public function test_the_structured_data_is_the_shoe_its_price_and_its_shelf(): void
    {
        $product = $this->shoe();
        $data = $this->structuredData('/products/'.$product->slug);

        $this->assertSame('Product', $data['@type']);
        $this->assertSame($product->title, $data['name']);
        $this->assertSame(url('/products/'.$product->slug), $data['url']);
        $this->assertNotEmpty($data['image'], 'A product with no image in its structured data is half a listing.');
        $this->assertSame($product->brand?->name, $data['brand']['name'] ?? null);

        $offers = $data['offers'] ?? [];

        $this->assertSame('AggregateOffer', $offers['@type'] ?? null);
        $this->assertSame('IRR', $offers['priceCurrency'] ?? null);
        $this->assertIsInt($offers['lowPrice'] ?? null);
        $this->assertGreaterThan(0, $offers['lowPrice']);
        $this->assertSame('https://schema.org/InStock', $offers['availability'] ?? null);
    }

    /**
     * **The price is Rial and says `IRR`, and the feed's is Toman.**
     *
     * The two are the same money answering two schemas, and the way to be
     * wrong here is silent and costs a factor of ten in public — so it is
     * pinned against what the offer actually holds.
     */
    public function test_the_price_is_the_rial_the_shop_stores(): void
    {
        $product = $this->shoe();

        $price = app(TenantContext::class)->forBranch(
            Branch::central(),
            fn () => Product::where('slug', $product->slug)->firstOrFail()->offerHere()->price,
        );

        $data = $this->structuredData('/products/'.$product->slug);

        $this->assertSame($price, $data['offers']['lowPrice']);
    }

    /** A shoe with nothing on the shelf says so rather than saying nothing. */
    public function test_an_empty_shelf_is_reported_as_out_of_stock(): void
    {
        $product = $this->shoe();

        app(TenantContext::class)->forBranch(Branch::central(), function () use ($product) {
            foreach ($product->variants as $variant) {
                $variant->stock?->forceFill(['stock_on_hand' => 0, 'stock_reserved' => 0])->save();
            }
        });

        $data = $this->structuredData('/products/'.$product->slug);

        $this->assertSame('https://schema.org/OutOfStock', $data['offers']['availability']);
    }

    /**
     * A retired shoe says «Discontinued» in the field, not only in Persian.
     *
     * ترب read «این محصول دیگر عرضه نمی‌شود» and answered «فاقد محصول». This is
     * the same sentence in the vocabulary their side of the conversation uses.
     */
    public function test_a_retired_shoe_is_marked_discontinued(): void
    {
        $product = Product::where('slug', 'on-cloudtilt')->firstOrFail();
        $product->forceFill(['status' => 'archived', 'published_at' => null])->save();

        $data = $this->structuredData('/products/on-cloudtilt');

        $this->assertSame('Product', $data['@type']);
        $this->assertSame($product->title, $data['name']);
        $this->assertSame('https://schema.org/Discontinued', $data['offers']['availability']);
    }

    /**
     * Every other page keeps the shop's own sentence.
     *
     * The swap happens in the layout, on a tag that is already in a generated
     * file, and a page that defines no description of its own has to come out
     * byte for byte as the generator wrote it — that is what keeps
     * `check-parity.js` at zero.
     */
    public function test_a_page_that_is_not_about_one_thing_is_left_alone(): void
    {
        foreach (['/', '/products'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<title>VikyPlus</title>', false)
                ->assertSee('فروشگاه اینترنتی کیف و کفش زنانه: کتانی', false);
        }
    }
}
