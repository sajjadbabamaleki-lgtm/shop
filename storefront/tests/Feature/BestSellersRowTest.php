<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Product;
use App\Support\FrontPage;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * «پرفروش‌ترین‌ها» — six tiles, and as many different shoes as the shop has.
 *
 * The client sent six photographs for this row, one per tile, and photographed
 * what the live shop drew: two shoes, three times each. «من ۶ عکس مختلف برای
 * این بخش بهت دادم ایناااااا چیین؟؟»
 *
 * Nothing had gone wrong with the six he chose. The band was being handed
 * `$onSale` — the subset of the catalogue carrying a live promotion — and on
 * that shop two of his six were in a campaign that week. The other four were
 * dropped, and the row's «fill six tiles from whatever you are given» cycled
 * the survivors.
 *
 * **Nothing here could see it**, which is why this file exists. Every other
 * test seeds `CatalogueSeeder`, which puts a promotion on the whole catalogue,
 * so the discounted subset and the catalogue are the same collection in a test
 * and the filter is invisible. The cases below take promotions *away*, which
 * is the state the live shop is in and no fixture reaches by default.
 *
 * Two rules are held, and they are separate:
 *
 *  1. The row is built from the catalogue, not from what is discounted. The
 *     tile prints `offerHere()->price` — the price the shop charges — and no
 *     struck-through one, so a campaign may not decide who is on it.
 *  2. The row never draws one shoe twice while it has another to draw. That is
 *     what cycling did, and a chosen shoe selling out would have put the repeat
 *     straight back.
 */
class BestSellersRowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    /** @return Collection<int, string> the slug on each tile, in order */
    private function tiles(): Collection
    {
        return collect($this->get('/')->assertOk()->viewData('bestSellers'))
            ->pluck('product.slug');
    }

    /**
     * How many different shoes the row could draw: the ones somebody chose,
     * plus the band's own default to fill it out, and only the ones this branch
     * can actually sell.
     *
     * Not the whole catalogue — the filler is a chosen list on purpose, so that
     * a supplier import cannot walk its newest rows onto the front page. See
     * `BasalamImportTest`, which caught exactly that.
     */
    private function couldDraw(): int
    {
        $sellable = Product::query()->purchasable()->pluck('slug');

        return collect(app(FrontPage::class)->slugs('best_sellers'))
            ->concat(config('storefront.placeholders.best_sellers.priced_from', []))
            ->unique()
            ->filter(fn (string $slug) => $sellable->contains($slug))
            ->count();
    }

    /**
     * The bug, exactly: one shoe left in the campaign, and the row must not
     * become six of it.
     */
    public function test_the_row_does_not_collapse_onto_what_is_discounted(): void
    {
        $only = Product::query()->purchasable()->orderBy('id')->firstOrFail();

        // Every offer out of its campaign but that one shoe's — the live shop's
        // ordinary state, where a campaign covers some of the catalogue.
        BranchOffer::query()
            ->whereNotIn('variant_id', $only->variants()->pluck('id'))
            ->update(['promotion_ends_at' => now()->subDay()]);

        $tiles = $this->tiles();

        $this->assertGreaterThan(
            1,
            $tiles->unique()->count(),
            'The best sellers collapsed onto the shoes that happen to be discounted. The row prints no struck-through price and must not be built from one.'
        );
    }

    /**
     * The general shape of it: the row shows as many different shoes as it has
     * room for, or as many as the shop has, whichever is fewer.
     *
     * A repeat is only ever a band whose chosen list and default together come
     * to fewer shoes than the row has tiles — this repository's five seeded
     * sneakers, and not a shop.
     */
    public function test_no_tile_repeats_while_another_shoe_is_unshown(): void
    {
        $room = config('storefront.placeholders.best_sellers.tiles');

        $tiles = $this->tiles();

        $this->assertCount($room, $tiles, 'The row lost a tile.');

        $this->assertSame(
            min($room, $this->couldDraw()),
            $tiles->unique()->count(),
            'A shoe is on the row twice while another one the shop sells is on it not at all.'
        );
    }

    /**
     * A chosen shoe selling out is filled from the band's own default list,
     * not papered over by drawing one of the others a second time.
     *
     * It is also *gone*: unlike the hero and the story rings, a tile here
     * carries a price and a control labelled «افزودن به سبد خرید», so it is an
     * offer, and the shop does not offer what it has run out of.
     */
    public function test_a_sold_out_shoe_is_replaced_rather_than_repeated(): void
    {
        $gone = Product::query()->purchasable()->orderBy('id')->firstOrFail();

        BranchInventory::query()
            ->whereIn('variant_id', $gone->variants()->pluck('id'))
            ->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

        $tiles = $this->tiles();

        $this->assertNotContains($gone->slug, $tiles->all(), 'A sold-out shoe is still being offered on the row.');

        $this->assertSame(
            min(config('storefront.placeholders.best_sellers.tiles'), $this->couldDraw()),
            $tiles->unique()->count(),
            'The row repeated a shoe instead of filling the gap the sell-out left.'
        );
    }

    /**
     * Every tile's picture belongs to the shoe named under it.
     *
     * The override is keyed by slug for this reason — built by position once,
     * four of the six tiles came out labelled with a different shoe than the
     * one shown — and every file it names has to be on disk, or the tile draws
     * a broken image where the client's cut-out should be.
     */
    public function test_every_photograph_the_row_can_choose_is_on_disk(): void
    {
        foreach (config('storefront.placeholders.best_sellers.photos') as $slug => $path) {
            $this->assertFileExists(
                public_path($path),
                "«پرفروش‌ترین‌ها» would draw a broken image for {$slug}."
            );
        }
    }

    /**
     * Every tile links to the shoe's own page in the shop, and not to a page
     * built for the row.
     *
     * «کفشایی که تو این قسمت میزاری باید لینک بشن به صفحه موجود همون مدل در
     * فروشگاه نه اینکه یه صفحه جدا بسازی» — there has never been a second page,
     * and this is what says so out loud.
     */
    public function test_each_tile_goes_to_that_shoe_in_the_shop(): void
    {
        $page = $this->get('/')->assertOk();

        foreach ($page->viewData('bestSellers') as $tile) {
            $url = storefront_route('product', $tile['product']);

            $page->assertSee($url, false);
            $this->get($url)->assertOk()->assertSee($tile['product']->title, false);
        }
    }
}
