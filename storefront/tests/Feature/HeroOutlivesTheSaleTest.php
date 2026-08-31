<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOffer;
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
}
