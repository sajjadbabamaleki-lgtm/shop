<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «پیشنهاد ویژه» — the band, the two prices and the burst on the photograph.
 *
 * **The migration this exercises is a no-op in every other test, and that is
 * why this file exists.** Migrations run against an empty database and the
 * catalogue is seeded afterwards, so a data migration that finds its product
 * by slug finds nothing and skips both of its writes. It is the right shape —
 * production is not a fresh install and the seeder cannot reach it — but it
 * means the only thing that ever runs these statements is the live deploy,
 * where a mistake is a wrong price in front of customers.
 *
 * So this seeds first and then calls `up()` itself, the way `DemoProductTest`
 * does with the migration that retires the test product.
 *
 * What it holds is the arithmetic the client asked for — «قیمت اصلیش ۴ میلیون
 * ۹۰۰ باشه که ۲۰ درصد تخفیف خورده» — in the unit the column is actually in,
 * which is the half that is easy to get wrong by a factor of ten.
 */
class SpecialOfferPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    private function migration(): Migration
    {
        return require database_path(
            'migrations/2026_09_05_140000_the_on_running_is_the_special_offer_at_twenty_off.php'
        );
    }

    public function test_the_band_becomes_the_on_running_at_the_asked_for_prices(): void
    {
        $this->migration()->up();

        $deal = $this->get('/')->assertOk()->viewData('dailyDeal');

        $this->assertSame('on-cloudtilt', $deal['product']->slug);

        $offer = $deal['product']->offerHere();

        // Rial in the column, Toman on the page. Asserted in both, because a
        // factor of ten is the mistake this is here to catch and only one of
        // the two units would show it.
        $this->assertSame(4_900_000 * 10, $offer->compare_at_price);
        $this->assertSame(3_920_000 * 10, $offer->price);
        $this->assertSame(fa_number(4_900_000), toman($offer->compare_at_price));
        $this->assertSame(fa_number(3_920_000), toman($offer->price));

        // And ٪۲۰ exactly, so the burst on the photograph says the number the
        // two prices beside it imply rather than a rounded neighbour of it.
        $this->assertSame(20, $offer->discountPercent());
    }

    /**
     * The band prints both numbers and draws the burst.
     *
     * Asserted on the rendered page rather than on the view data: the whole
     * point of the ask was what a customer sees, and a `@if` that never opens
     * would leave every assertion above passing.
     */
    public function test_the_page_shows_the_before_price_and_the_burst(): void
    {
        $this->migration()->up();

        $page = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<del>'.fa_number(4_900_000).'</del>',
            $page,
            'The band is not printing the price before the cut.'
        );

        $this->assertStringContainsString(
            'vp-daily-deal-shot"><svg class="vp-deal-burst"',
            $page,
            'The burst is not on the photograph.'
        );
    }

    /**
     * **With no discount there is no struck-through number and no burst.**
     *
     * This is the case that keeps the band honest, and it is the failure this
     * repository has already had once in another shape: a campaign that ends
     * leaves `compare_at_price` behind, and a band guarded on that column
     * alone would go on drawing a struck price and a ٪ badge over a price that
     * is no longer cut. Nothing would go red — see «the stepped sale has no
     * end date» in CLAUDE.md for the afternoon that cost.
     */
    public function test_it_prints_one_price_when_nothing_is_discounted(): void
    {
        $this->migration()->up();

        Product::query()->where('slug', 'on-cloudtilt')->firstOrFail()
            ->variants()->pluck('id')
            ->each(fn (int $id) => \DB::table('branch_offers')
                ->where('variant_id', $id)
                ->update(['compare_at_price' => null]));

        $page = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('vp-daily-deal-shot"><svg', $page);
        $this->assertStringContainsString('vp-daily-deal-price">'.fa_number(3_920_000), $page);
    }
}
