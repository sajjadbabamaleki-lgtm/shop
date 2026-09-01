<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOffer;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shelf under a shoe's description.
 *
 * «پایین توضیحات کفش باید کفش های مشابه با اون بودجه بیان». It used to be four
 * from the same categories in publication order, so a shoe at eight million sat
 * under one at three — the same kind of thing, not the same decision. Somebody
 * reading a price is choosing within a budget.
 *
 * So the band is the rule and the category is the tiebreak, and what these hold
 * is that the band is real: something dearer than the budget stays off the
 * shelf however well it matches otherwise.
 */
class RelatedByBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    /** What the page hands the view, by slug. */
    private function shelf(string $slug): array
    {
        return $this->get('/products/'.$slug)
            ->assertOk()
            ->viewData('related')
            ->pluck('slug')
            ->all();
    }

    private function priceOf(string $slug): int
    {
        return Product::where('slug', $slug)->firstOrFail()->offerHere()->price;
    }

    public function test_everything_on_the_shelf_is_within_the_budget(): void
    {
        $price = $this->priceOf('nike-v2k-run');
        $shelf = $this->shelf('nike-v2k-run');

        $this->assertNotEmpty($shelf, 'The shelf is empty, so this proves nothing.');

        foreach ($shelf as $slug) {
            $other = $this->priceOf($slug);

            $this->assertLessThanOrEqual(
                0.34,
                abs($other - $price) / $price,
                "«{$slug}» is outside the budget of the shoe being looked at."
            );
        }
    }

    /**
     * The cheapest shoe in the shop does not get the dearest one offered to it.
     * This is the whole point, and it is what the old query got wrong.
     */
    public function test_a_shoe_far_outside_the_budget_stays_off_the_shelf(): void
    {
        $this->assertNotContains('jordan-one-air', $this->shelf('on-cloudtilt'));

        // And the two really are far apart, so this is not passing on a
        // catalogue where everything costs the same.
        $this->assertGreaterThan(
            1.5,
            $this->priceOf('jordan-one-air') / $this->priceOf('on-cloudtilt')
        );
    }

    /** Repricing a shoe moves it onto the shelf. Nothing is cached. */
    public function test_bringing_a_price_into_the_band_brings_the_shoe_with_it(): void
    {
        $this->assertNotContains('jordan-one-air', $this->shelf('on-cloudtilt'));

        $jordan = Product::where('slug', 'jordan-one-air')->firstOrFail();

        BranchOffer::query()
            ->acrossAllBranches()
            ->whereIn('variant_id', $jordan->variants->pluck('id'))
            ->update(['price' => $this->priceOf('on-cloudtilt')]);

        $this->assertContains('jordan-one-air', $this->shelf('on-cloudtilt'));
    }

    /** Never the shoe you are already looking at. */
    public function test_the_shelf_never_offers_the_page_its_own_shoe(): void
    {
        foreach (['nike-v2k-run', 'on-cloudtilt', 'jordan-one-air'] as $slug) {
            $this->assertNotContains($slug, $this->shelf($slug));
        }
    }

    public function test_the_shelf_says_what_it_is(): void
    {
        $this->get('/products/nike-v2k-run')
            ->assertOk()
            ->assertSee('کفش‌های مشابه در همین بودجه', false);
    }
}
