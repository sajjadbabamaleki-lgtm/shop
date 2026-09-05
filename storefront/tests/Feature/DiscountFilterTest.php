<?php

namespace Tests\Feature;

use App\Http\Controllers\ShopController;
use App\Models\Branch;
use App\Models\BranchOffer;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «تبدیل بشه به پنج دکمه فیلتر درصد قیمت» — the five under the stepped sale.
 *
 * They replaced one link to the whole listing, so each has to actually narrow
 * it: `?cut=N` is «discounted by N or more». The comparison is in SQL, because
 * the deepest cuts among two hundred products are not the deepest among
 * whichever page you happen to be on.
 *
 * The case that matters most is the boundary. A card's badge prints
 * `discountPercent()`, which **rounds**, so a shoe cut 29.6% wears a ٪۳۰ badge
 * — and if the filter compared without rounding, that shoe would be missing
 * from the ٪۳۰ filter it advertises. The two have to agree, and the only way to
 * be sure is to put a shoe on each side of the line and ask.
 */
class DiscountFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    /** Put one product's offers at exactly this cut, and return its title. */
    private function cutOneAt(float $percent): string
    {
        $product = Product::query()->purchasable()->orderBy('id')->firstOrFail();

        BranchOffer::withoutGlobalScopes()
            ->whereIn('variant_id', $product->variants()->pluck('id'))
            ->get()
            ->each(function (BranchOffer $offer) use ($percent): void {
                $was = $offer->compare_at_price ?: $offer->price;
                // A whole number of Toman either way, so `toman()` can print it.
                $offer->forceFill([
                    'compare_at_price' => $was,
                    'price' => (int) round($was * (100 - $percent) / 100 / 10) * 10,
                    'promotion_starts_at' => null,
                    'promotion_ends_at' => null,
                ])->saveQuietly();
            });

        return $product->title;
    }

    /**
     * The names on the cards the listing drew, and nothing else on the page.
     *
     * **Not the raw HTML.** The first version of these cases searched the whole
     * response for the product's name and three of them failed against a filter
     * that was working correctly: a listing page names products in places that
     * are not its results — the drawer, the search panel, the mini basket — so
     * «is this shoe in the results» cannot be asked of the document.
     *
     * @return list<string>
     */
    private function cardsAt(int $cut): array
    {
        preg_match_all(
            '/class="vp-card-name"[^>]*>([^<]*)/',
            $this->get("/products?cut={$cut}")->assertOk()->getContent(),
            $found
        );

        return array_map('trim', $found[1]);
    }

    /** The five the buttons offer are the five the listing accepts. */
    public function test_the_five_cuts_are_the_ones_the_front_page_links_to(): void
    {
        $this->assertSame([15, 30, 45, 60, 70], ShopController::CUTS);

        $home = $this->get('/')->assertOk()->getContent();

        foreach (ShopController::CUTS as $cut) {
            $this->assertStringContainsString("cut={$cut}", $home, "the front page has no ٪{$cut} button");
        }
    }

    /** A deeper cut than the filter asks for is in it. */
    public function test_a_deeper_cut_is_included(): void
    {
        $title = $this->cutOneAt(60);

        $this->assertContains($title, $this->cardsAt(45));
        $this->assertContains($title, $this->cardsAt(60));
    }

    /** A shallower one is not. */
    public function test_a_shallower_cut_is_excluded(): void
    {
        $title = $this->cutOneAt(20);

        $this->assertContains($title, $this->cardsAt(15));
        $this->assertNotContains($title, $this->cardsAt(30));
    }

    /**
     * The boundary agrees with the badge, from just above it.
     *
     * 29.6% rounds to ٪۳۰ on the card, so the ٪۳۰ filter has to find it — a
     * shoe wearing a badge the filter it advertises cannot see is the shop
     * contradicting itself.
     *
     * **Two methods, not one with the application rebuilt in the middle.** The
     * first version did the second half after `refreshApplication()` and a
     * second `setUp()`, which re-seeds inside the transaction `RefreshDatabase`
     * has already opened; the run hung, and took two more with it because three
     * suites were then holding the same test database. One case per method.
     */
    public function test_a_cut_that_rounds_up_to_the_threshold_is_included(): void
    {
        $title = $this->cutOneAt(29.6);

        $this->assertContains(
            $title,
            $this->cardsAt(30),
            'a shoe badged ٪۳۰ is missing from the ٪۳۰ filter'
        );
    }

    /** And from just below it: 29.4% rounds to ٪۲۹ and must not be there. */
    public function test_a_cut_that_rounds_down_is_excluded(): void
    {
        $title = $this->cutOneAt(29.4);

        $this->assertNotContains(
            $title,
            $this->cardsAt(30),
            'a shoe badged ٪۲۹ is in the ٪۳۰ filter'
        );
    }

    /** A cut nobody offers is no filter at all, not an invented heading. */
    public function test_a_cut_the_shop_does_not_offer_is_ignored(): void
    {
        $page = $this->get('/products?cut=37')->assertOk()->getContent();

        $this->assertStringNotContainsString('٪۳۷', $page);
    }

    /** And a shoe whose campaign has ended is not discounted at all. */
    public function test_a_closed_campaign_is_not_a_cut(): void
    {
        $title = $this->cutOneAt(60);

        BranchOffer::withoutGlobalScopes()->update(['promotion_ends_at' => now()->subDay()]);

        $this->assertNotContains($title, $this->cardsAt(45));
    }
}
