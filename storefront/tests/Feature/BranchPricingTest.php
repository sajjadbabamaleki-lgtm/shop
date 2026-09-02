<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\BranchScope;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2: a price and a pile of stock belong to a branch, not to a shoe.
 *
 * The isolation tests next door prove the scope holds on branch-owned rows in
 * general. These prove the thing that scope was moved for: that two shops can
 * charge two prices for the same SKU, that neither can see the other's, and
 * that the storefront asks the branch it is actually standing in.
 */
class BranchPricingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $central;

    private Branch $shiraz;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->central = Branch::central();
        $this->tenant = app(TenantContext::class);

        // Five per cent dearer than the main store, two of everything on the
        // shelf — enough for the shop to actually be open.
        $this->shiraz = app(BranchOpener::class)->open(
            slug: 'shiraz',
            name: 'ویکی پلاس شیراز',
            attributes: ['city' => 'شیراز'],
            markupPercent: 5,
            openingStock: 2,
        );
    }

    private function centralOffer(string $slug): BranchOffer
    {
        return $this->tenant->forBranch(
            $this->central,
            fn () => Product::where('slug', $slug)->firstOrFail()->offerHere(),
        );
    }

    public function test_a_franchise_is_reached_at_its_path(): void
    {
        $this->get('/shiraz')->assertOk()->assertViewIs('home');
    }

    /**
     * The one this whole phase exists for. Same shoe, two shops, two prices,
     * and each page quotes the shop it was asked at.
     */
    public function test_each_branch_quotes_its_own_price(): void
    {
        $central = $this->centralOffer('new-balance-530');
        $shirazPrice = intdiv($central->price * 105, 100);

        $this->assertNotSame($central->price, $shirazPrice);

        $this->get('/')->assertSee(toman($central->price), false);
        $this->get('/shiraz')
            ->assertSee(toman($shirazPrice), false)
            ->assertDontSee(toman($central->price), false);
    }

    /**
     * Stock is the branch's too: selling out in Shiraz must not empty the
     * shelf in the main store.
     */
    public function test_stock_is_counted_per_branch(): void
    {
        $variants = Product::where('slug', 'jordan-one-air')->firstOrFail()->variants()->pluck('id');

        $this->tenant->forBranch($this->shiraz, fn () => BranchInventory::whereIn('variant_id', $variants)
            ->update(['stock_on_hand' => 0]));

        // Two occurrences is the story ring and nothing else — its
        // `aria-label` and its `data-vp-story-name`. The ring is one of five
        // the client named and stays whatever the shelf says, offering no
        // variant; see `StoriesTest`. Shiraz offering it would be a third.
        $this->assertSame(
            2,
            substr_count($this->get('/shiraz')->getContent(), 'کتونی جردن وان ایر'),
            'Shiraz is offering a shoe it has sold out of.'
        );

        $this->assertGreaterThan(
            2,
            substr_count($this->get('/')->getContent(), 'کتونی جردن وان ایر'),
            'The main store lost the shoe because Shiraz ran out of it.'
        );
    }

    /**
     * A branch that does not list a variant does not sell it. Delisting is a
     * missing row, not a flag, so a franchise's catalogue is what it chose
     * rather than everything minus what it remembered to hide.
     */
    public function test_a_variant_a_branch_does_not_list_is_not_for_sale_there(): void
    {
        $variants = Product::where('slug', 'golden-goose')->firstOrFail()->variants()->pluck('id');

        $this->tenant->forBranch($this->shiraz, fn () => BranchOffer::whereIn('variant_id', $variants)->delete());

        $this->get('/shiraz')->assertDontSee('کتونی گلدن گوس', false);
        $this->get('/')->assertSee('کتونی گلدن گوس', false);
    }

    public function test_one_branch_cannot_read_anothers_offer_by_id(): void
    {
        $central = $this->centralOffer('new-balance-530');

        $this->tenant->set($this->shiraz);

        $this->assertNull(BranchOffer::find($central->id));
        $this->assertSame(0, BranchOffer::whereKey($central->id)->update(['price' => 1]));

        $this->assertDatabaseHas('branch_offers', ['id' => $central->id, 'price' => $central->price]);
    }

    /**
     * Failing closed, at the layer money is read from. With no branch bound a
     * price is not "the central one by default" — it is nothing.
     */
    public function test_with_no_branch_bound_nothing_has_a_price(): void
    {
        $this->tenant->forget();

        $this->assertSame(0, BranchOffer::count());
        $this->assertSame(0, BranchInventory::count());
        $this->assertNull(Product::where('slug', 'new-balance-530')->first()?->offerHere());
    }

    /**
     * Opening a shop gives it the catalogue and nothing else: central's cost
     * price is not its cost price, and its shelves are its own.
     */
    public function test_opening_a_branch_copies_the_catalogue_but_not_the_shelf(): void
    {
        $everyVariant = BranchOffer::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $this->central->id)
            ->count();

        $this->assertSame($everyVariant, $this->shiraz->offers()->count());
        $this->assertSame($everyVariant, $this->shiraz->inventory()->count());
        $this->assertSame(0, $this->shiraz->offers()->whereNotNull('cost_price')->count());

        // Every unit it has is explained by a delivery, like every other unit
        // of stock in the system.
        $this->assertSame(
            $everyVariant * 2,
            (int) $this->tenant->forBranch(
                $this->shiraz,
                fn () => InventoryMovement::where('type', 'receipt')->sum('quantity'),
            ),
        );
    }

    /**
     * A price copied at opening is a starting point, not a link back to
     * central — which is the entire reason price left `variants`.
     */
    public function test_a_franchise_price_change_leaves_the_main_store_alone(): void
    {
        $before = $this->centralOffer('new-balance-530');

        $this->tenant->forBranch($this->shiraz, function () {
            $offer = Product::where('slug', 'new-balance-530')->firstOrFail()->offerHere();
            $offer->update(['price' => 1_000_000]);
        });

        $this->assertSame($before->price, $this->centralOffer('new-balance-530')->price);
    }

    public function test_a_branch_cannot_be_opened_at_an_address_the_shop_already_uses(): void
    {
        $this->expectExceptionMessage('reserved address');

        app(BranchOpener::class)->open('cart', 'سبد');
    }

    public function test_a_branch_cannot_be_opened_at_an_address_that_is_not_one(): void
    {
        $this->expectExceptionMessage('cannot be an address');

        app(BranchOpener::class)->open('شیراز', 'ویکی پلاس شیراز');
    }
}
