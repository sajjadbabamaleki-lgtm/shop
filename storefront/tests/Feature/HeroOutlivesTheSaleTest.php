<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The front page when no campaign is running.
 *
 * **This is the state nothing else here can reach.** Every other test seeds
 * its catalogue moments before it renders a page, and `CatalogueSeeder` opens
 * the stepped sale's window a week back and three weeks forward — so in a test
 * the sale is always live, and a band that only exists while a discount is
 * running looks permanent.
 *
 * On the live site it is a clock. Four weeks after the shop went up the window
 * closed, and the hero, the best-sellers band and the sale's own cards all
 * emptied in the same request, because all three were handed one collection:
 * «everything discounted». The client's phone showed the header, the category
 * tiles, and then the trust badges where the hero had been — «چرا هیروهای سایت
 * حذف شدن؟؟؟!!!». Nothing had been deleted, nothing was red, and every test
 * passed.
 *
 * So the rule this file holds is: **a band that does not print a discount must
 * not depend on one.** The hero prints no price — it is three products the shop
 * chose, with a line of copy over each — and it now reads the catalogue.
 */
class HeroOutlivesTheSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    /** Every campaign over: the window closed yesterday and nothing replaced it. */
    private function closeEveryCampaign(): void
    {
        BranchOffer::query()->update(['promotion_ends_at' => now()->subDay()]);
    }

    public function test_the_hero_is_still_there_when_no_campaign_is_running(): void
    {
        $this->closeEveryCampaign();

        $page = $this->get('/')->assertOk();

        $slides = $page->viewData('heroSlides');

        $this->assertCount(
            count(config('storefront.hero.products')) * config('storefront.hero.repeat'),
            $slides,
            'The hero emptied because the sale ended. It prints no price and must not depend on one.'
        );

        // And it is really on the page, not merely in the view data: an empty
        // `@foreach` leaves the deck's markup behind with nothing in it, which
        // is exactly what the client photographed.
        $page->assertSee(config('storefront.hero.products')[array_key_first(config('storefront.hero.products'))]);
    }

    /**
     * The same rule, one shelf along: **the deck outlives a sell-out too.**
     *
     * «قبلا کارتهای هیرو ۳ تا بودن الان دوتا هستن، اون جردن صورتی حذف شده».
     * The front page's catalogue is `purchasable()`, which wants a variant that
     * can go in a basket today — so selling the last pair of a hero shoe took
     * its slide off the front page, silently, in the same minute. The deck
     * still rendered; it just had two in it.
     *
     * A hero slide prints an eyebrow, a name, a photograph and a button. No
     * price, no discount, no stock. So it may not be built from stock.
     */
    public function test_the_hero_keeps_its_three_when_one_of_them_sells_out(): void
    {
        $slugs = array_keys(config('storefront.hero.products'));
        $soldOut = $slugs[1];

        // Empty every shelf this shoe has, at every branch, the way the last
        // sale of the last pair does.
        BranchInventory::query()
            ->whereIn('variant_id', Product::where('slug', $soldOut)->firstOrFail()->variants()->pluck('id'))
            ->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

        // The premise: it really is out of the buyable catalogue now.
        $this->assertFalse(
            Product::query()->purchasable()->where('slug', $soldOut)->exists(),
            'The shoe is still purchasable, so this case is not testing anything.'
        );

        $page = $this->get('/')->assertOk();

        $this->assertCount(
            count($slugs) * config('storefront.hero.repeat'),
            $page->viewData('heroSlides'),
            'A hero slide went missing because the shoe sold out. It prints no stock and must not depend on any.'
        );

        $this->assertSame(
            $slugs,
            collect($page->viewData('heroSlides'))
                ->take(count($slugs))
                ->pluck('product.slug')
                ->all(),
            'The deck is there but the sold-out shoe is not the one in it.'
        );
    }

    /** A shoe that is genuinely gone is still gone — this is not a licence. */
    public function test_an_archived_shoe_does_not_come_back_to_the_hero(): void
    {
        $slugs = array_keys(config('storefront.hero.products'));

        Product::where('slug', $slugs[1])->update(['status' => 'archived']);

        $slides = $this->get('/')->assertOk()->viewData('heroSlides');

        $this->assertCount((count($slugs) - 1) * config('storefront.hero.repeat'), $slides);
    }

    /**
     * The other half of the rule. These two write `compare_at_price` into the
     * page in large type, and it is null when nothing is discounted — so they
     * are right to go quiet, and handing them the catalogue would print a zero
     * rather than fill the page.
     */
    public function test_the_bands_that_print_a_struck_through_price_go_quiet_instead(): void
    {
        $this->closeEveryCampaign();

        $page = $this->get('/')->assertOk();

        $this->assertEmpty($page->viewData('ladderDeals'));
        $this->assertEmpty($page->viewData('bestSellers'));
        $this->assertNull($page->viewData('dailyDeal'));

        // Whatever else is missing, the page is a page: it renders, and the
        // bands that owe nothing to a campaign are all still on it.
        foreach (['vp-category-row', 'vp-trust-row', 'vp-brands-section', 'footer-wrapper'] as $band) {
            $page->assertSee($band, false);
        }
    }

    /** With the campaign running, nothing about any of it changed. */
    public function test_a_running_campaign_still_fills_every_band(): void
    {
        $page = $this->get('/')->assertOk();

        $this->assertNotEmpty($page->viewData('heroSlides'));
        $this->assertNotEmpty($page->viewData('ladderDeals'));
        $this->assertNotEmpty($page->viewData('bestSellers'));
        $this->assertNotNull($page->viewData('dailyDeal'));
    }

    /**
     * The stepped sale is six cards on the phone, whatever the shelf says.
     *
     * «حراج پله ای چرا ناقص شده باید ۶ محصول توش باشه» — five, because one of
     * the five promoted shoes had sold its last pair. This band is the one that
     * may *not* be given the shoe back the way the hero and the story rings
     * were: its cards print a struck-through price beside a real one, so it has
     * to be built from what is genuinely discounted and genuinely sellable.
     *
     * The client's own answer from an earlier round is what covers the gap —
     * «یک محصول تکراری در حراج پله ای بزار که ۶ تایی بشه» — and it only had to
     * learn to count: it padded by one where it should pad up to six.
     */
    public function test_the_sale_is_six_cards_on_a_phone_even_when_one_sells_out(): void
    {
        $this->assertSame(6, $this->saleCards(), 'The full pool should already fill the phone.');

        $slug = config('storefront.front_page.ladder_products')[1];

        BranchInventory::query()
            ->whereIn('variant_id', Product::where('slug', $slug)->firstOrFail()->variants()->pluck('id'))
            ->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

        // The premise: it really has left the band.
        $this->assertNotContains($slug, $this->get('/')->viewData('ladderDeals')->pluck('slug')->all());

        $this->assertSame(6, $this->saleCards(), 'A sold-out shoe left a gap in the sale.');
    }

    /**
     * And every card past the fifth is hidden above 992.
     *
     * `row-cols-xl-5` puts five on one line; a sixth without `d-lg-none` wraps
     * the desktop row onto two, which is the thing the pad was built to avoid.
     */
    public function test_the_padding_never_reaches_the_desktop_row(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            6 - count(config('storefront.front_page.ladder_products')),
            substr_count($page, 'class="col d-lg-none"'),
            'The pads are not all hidden above 992.'
        );
    }

    private function saleCards(): int
    {
        return substr_count($this->get('/')->assertOk()->getContent(), '<div class="vp-deal">');
    }
}
