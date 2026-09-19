<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOffer;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Branches\BranchOpener;
use App\Support\Catalogue\OfferPrice;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * «قیمت گلدن گوس هارو بالا ببرم همشونو ۲۰ درصد».
 *
 * A shoe reaches this shop one colourway per product, priced one row per size,
 * so putting one make up twenty percent was thirty forms filled in by hand.
 *
 * Most of this file is not the arithmetic. It is the two ways a bulk price
 * change is expensive to get wrong: **changing a different set from the one on
 * the screen**, and **moving a price without the struck-through one beside
 * it** — the second walks the pair into a CHECK constraint and, before it gets
 * there, shows a shopper a discount that shrank while the shop thought it had
 * raised a price.
 */
class BulkPriceChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());

        $this->staff = User::factory()->create();
        $this->staff->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());
        $this->staff = $this->staff->fresh();
    }

    /** @return Collection<int, BranchOffer> */
    private function offersOf(string $slug)
    {
        $ids = Product::where('slug', $slug)->firstOrFail()
            ->variants()->withoutGlobalScopes()->pluck('id');

        return BranchOffer::withoutGlobalScopes()
            ->where('branch_id', Branch::central()->id)
            ->whereIn('variant_id', $ids)
            ->get();
    }

    private function bulk(array $query, array $body)
    {
        return $this->actingAs($this->staff)
            ->post(route('admin.pricing.bulk', $query), $body + ['direction' => 'up', 'percent' => 20]);
    }

    // ---- the arithmetic, on its own ------------------------------------

    public function test_a_price_goes_up_by_the_percentage_and_lands_on_a_round_number(): void
    {
        // 5,586,000 Toman + 20% is 6,703,200, which no shop prints.
        $this->assertSame(67_030_000, OfferPrice::scaled(55_860_000, 20, true));
    }

    public function test_a_price_comes_down_the_same_way(): void
    {
        $this->assertSame(44_690_000, OfferPrice::scaled(55_860_000, 20, false));
    }

    /**
     * **Every result is a whole number of Toman**, which `toman()` throws
     * without — so a percentage that lands on a fraction would take down every
     * page the price appears on rather than printing a rounded one.
     */
    public function test_every_result_is_a_price_this_application_can_hold(): void
    {
        foreach ([1, 3, 7, 13, 17, 33, 99, 150] as $percent) {
            foreach ([10_000, 999_990, 55_860_000, 1_234_567_890] as $rial) {
                foreach ([true, false] as $up) {
                    $out = OfferPrice::scaled($rial, $percent, $up);

                    $this->assertSame(0, $out % 10, "{$rial} at {$percent}% is not a whole Toman");
                    $this->assertGreaterThan(0, $out);
                    // Which is the same as saying `toman()` will not throw.
                    toman($out);
                }
            }
        }
    }

    /** A percentage of a small enough price rounds to nought, and must not. */
    public function test_it_never_rounds_a_price_away_to_nothing(): void
    {
        $this->assertSame(10_000, OfferPrice::scaled(10_000, 99, false));
    }

    // ---- what it changes ------------------------------------------------

    public function test_it_moves_every_price_the_brand_filter_is_showing(): void
    {
        $before = $this->offersOf('golden-goose')->pluck('price', 'id');

        $this->assertNotEmpty($before);

        $this->bulk(['brand' => 'golden-goose'], [])->assertRedirect();

        foreach ($this->offersOf('golden-goose') as $offer) {
            $this->assertSame(
                OfferPrice::scaled($before[$offer->id], 20, true),
                $offer->price,
                'A Golden Goose price did not move.',
            );
        }
    }

    /**
     * **And nothing else.** The set it writes is the set the screen is
     * showing; a bulk change that reached past its filter would look right and
     * be wrong in somebody else's prices.
     */
    public function test_it_leaves_every_other_brand_where_it_was(): void
    {
        $others = $this->offersOf('new-balance-530')->pluck('price', 'id');

        $this->bulk(['brand' => 'golden-goose'], [])->assertRedirect();

        foreach ($this->offersOf('new-balance-530') as $offer) {
            $this->assertSame($others[$offer->id], $offer->price, 'Another brand was repriced.');
        }
    }

    /** The search box narrows it the same way the list is narrowed. */
    public function test_the_search_box_narrows_what_it_changes(): void
    {
        $target = $this->offersOf('new-balance-530')->pluck('price', 'id');
        $others = $this->offersOf('golden-goose')->pluck('price', 'id');

        $this->bulk(['q' => 'نیوبالانس'], [])->assertRedirect();

        foreach ($this->offersOf('new-balance-530') as $offer) {
            $this->assertNotSame($target[$offer->id], $offer->price);
        }

        foreach ($this->offersOf('golden-goose') as $offer) {
            $this->assertSame($others[$offer->id], $offer->price);
        }
    }

    /**
     * **The struck-through price moves with the price.**
     *
     * Without this the pair walks into `branch_offers_compare_at_above_price`,
     * and before it gets there the shopper watches a discount shrink while the
     * shop believes it raised a price.
     */
    public function test_a_sale_keeps_its_shape(): void
    {
        $offer = $this->offersOf('golden-goose')->first();

        app(TenantContext::class)->forBranch(Branch::central(), fn () => BranchOffer::whereKey($offer->id)
            ->update(['price' => 40_000_000, 'compare_at_price' => 50_000_000]));

        $this->bulk(['brand' => 'golden-goose'], [])->assertRedirect();

        $after = BranchOffer::withoutGlobalScopes()->findOrFail($offer->id);

        $this->assertSame(48_000_000, $after->price);
        $this->assertSame(60_000_000, $after->compare_at_price);
        $this->assertGreaterThanOrEqual($after->price, $after->compare_at_price);
    }

    /** An offer with no sale keeps none: null stays null. */
    public function test_an_offer_with_no_sale_does_not_grow_one(): void
    {
        $offer = $this->offersOf('golden-goose')->first();

        app(TenantContext::class)->forBranch(Branch::central(), fn () => BranchOffer::whereKey($offer->id)
            ->update(['compare_at_price' => null]));

        $this->bulk(['brand' => 'golden-goose'], [])->assertRedirect();

        $this->assertNull(BranchOffer::withoutGlobalScopes()->findOrFail($offer->id)->compare_at_price);
    }

    // ---- the refusals ---------------------------------------------------

    /** A hundred percent off is a shelf of free shoes. */
    public function test_it_refuses_to_take_a_hundred_percent_off(): void
    {
        $before = $this->offersOf('golden-goose')->pluck('price', 'id');

        $this->bulk(['brand' => 'golden-goose'], ['direction' => 'down', 'percent' => 100])
            ->assertSessionHasErrors('percent');

        foreach ($this->offersOf('golden-goose') as $offer) {
            $this->assertSame($before[$offer->id], $offer->price);
        }
    }

    /**
     * **Typed in Persian digits, which is how this panel is typed.**
     *
     * `integer` refuses «۲۰», and so does a `type="number"` box — with no
     * message and nothing to press. The fold is the same one the price boxes
     * on this screen already do.
     */
    public function test_the_percentage_can_be_typed_in_persian(): void
    {
        $before = $this->offersOf('golden-goose')->pluck('price', 'id');

        $this->actingAs($this->staff)
            ->post(route('admin.pricing.bulk', ['brand' => 'golden-goose']), [
                'direction' => 'up',
                'percent' => '۲۰',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        foreach ($this->offersOf('golden-goose') as $offer) {
            $this->assertSame(OfferPrice::scaled($before[$offer->id], 20, true), $offer->price);
        }
    }

    /** A filter that matches nothing is a mistake, not a no-op to be silent about. */
    public function test_a_filter_that_matches_nothing_says_so(): void
    {
        $this->bulk(['q' => 'یک چیزی که در این فروشگاه نیست'], [])
            ->assertSessionHasErrors('percent');
    }

    /**
     * **A franchise's prices are its own.** `BranchOffer` is branch-scoped, so
     * this can only ever reach the shop the person is signed in to — and that
     * is the one thing here nothing on the screen would reveal.
     */
    public function test_it_cannot_reach_another_branch(): void
    {
        $shiraz = app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );

        $ids = Product::where('slug', 'golden-goose')->firstOrFail()
            ->variants()->withoutGlobalScopes()->pluck('id');

        $before = BranchOffer::withoutGlobalScopes()
            ->where('branch_id', $shiraz->id)->whereIn('variant_id', $ids)
            ->pluck('price', 'id');

        $this->assertNotEmpty($before, 'Shiraz has to be selling them for this to assert anything.');

        $this->bulk(['brand' => 'golden-goose'], [])->assertRedirect();

        foreach ($before as $id => $was) {
            $this->assertSame(
                $was,
                BranchOffer::withoutGlobalScopes()->findOrFail($id)->price,
                'The central shop repriced a franchise.',
            );
        }
    }

    // ---- the screen ------------------------------------------------------

    /** The count on the button is the count that will change. */
    public function test_the_screen_counts_what_it_would_change(): void
    {
        $expected = $this->offersOf('golden-goose')->count();

        $this->actingAs($this->staff)
            ->get(route('admin.pricing', ['brand' => 'golden-goose']))
            ->assertOk()
            ->assertSee('اعمال روی '.fa_number($expected).' قیمت', false);
    }

    /**
     * **The group is chosen in the panel that changes it.**
     *
     * «فیلد انتخاب اون گروهی که قراره قیمتش بره بالا کو؟» — it was up in the
     * search bar, looking like part of the search box, while the panel two
     * cards below said «روی ۸ قیمتِ فیلترشده». Asserted as «the select is
     * inside `.vp-adm-bulk`», because a control that exists somewhere on the
     * page is not the same as one somebody can find.
     */
    public function test_the_group_selector_is_inside_the_bulk_panel(): void
    {
        $html = $this->actingAs($this->staff)->get(route('admin.pricing'))->assertOk()->getContent();

        $panel = mb_substr($html, (int) mb_strpos($html, 'vp-adm-bulk'));
        $panel = mb_substr($panel, 0, (int) mb_strpos($panel, 'vp-adm-bulk-form'));

        $this->assertStringContainsString('id="vp-pri-brand"', $panel, 'The group selector is not in the panel.');
        $this->assertStringContainsString('form="vp-pricing-filter"', $panel,
            'The selector is a second control rather than the filter\'s own, so the page now has two ideas of the group.');
    }

    /** Choosing a group narrows the list, the count and what would be written. */
    public function test_choosing_a_group_narrows_the_count(): void
    {
        $all = $this->actingAs($this->staff)->get(route('admin.pricing'))->getContent();
        $one = $this->actingAs($this->staff)->get(route('admin.pricing', ['brand' => 'golden-goose']))->getContent();

        $geese = $this->offersOf('golden-goose')->count();

        $this->assertStringContainsString('اعمال روی '.fa_number($geese).' قیمت', $one);
        $this->assertStringNotContainsString('اعمال روی '.fa_number($geese).' قیمت', $all);
    }

    /**
     * **The panel says which group, not only how many.**
     *
     * The count answers «how many»; a screen that writes prices has to answer
     * «which» beside it.
     */
    public function test_the_panel_names_the_group(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.pricing', ['brand' => 'golden-goose']))
            ->assertOk()
            ->assertSee('گلدن گوس', false);
    }

    /**
     * **And it says «the whole shop» out loud when nothing is chosen**, which
     * is the one group nobody should reach by accident.
     */
    public function test_an_unfiltered_panel_says_it_would_move_the_whole_shop(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.pricing'))
            ->assertOk()
            ->assertSee('همه قیمت‌های این فروشگاه', false);
    }

    /**
     * A group with nothing in it still shows the selector.
     *
     * The panel used to be hidden when the filter matched nothing — which hid
     * the only control that could choose a different group, leaving somebody
     * on a dead end with no way back but the address bar.
     */
    public function test_a_group_with_nothing_in_it_keeps_its_selector(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.pricing', ['q' => 'یک چیزی که در این فروشگاه نیست']))
            ->assertOk()
            ->assertSee('id="vp-pri-brand"', false)
            ->assertDontSee('اعمال روی', false);
    }

    /** And the brand list is there to be chosen from. */
    public function test_the_brand_filter_is_on_the_screen(): void
    {
        $brand = Brand::where('slug', 'golden-goose')->firstOrFail();

        $this->actingAs($this->staff)
            ->get(route('admin.pricing'))
            ->assertOk()
            ->assertSee('value="'.$brand->slug.'"', false)
            ->assertSee($brand->name, false);
    }
}
