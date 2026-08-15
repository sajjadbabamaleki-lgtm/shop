<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The story strip above the listing's search line.
 *
 * **The strip is invisible to every visual check we have**, the same way the
 * phone drawer is: the viewer is a `hidden` overlay, so `check-parity.js` and
 * `check-overflow.js` both see an empty div and report nothing. This file is
 * what watches it.
 *
 * What it holds down is the thing that changed: the circles were the
 * catalogue's five *categories* and are five *products* now — «اصلا استوری ها
 * نباید دسته بندی باشن» — and that is the whole reason the two buttons under
 * the picture can exist at all. A category has no variant to put in a basket.
 * If these ever drift back to categories, the basket button starts posting a
 * category id at a route that wants a variant.
 */
class StoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
    }

    /**
     * A second shop, opened the way every other test opens one — the seeders
     * leave a single branch, so a franchise has to be created rather than
     * looked up.
     */
    private function franchise(): Branch
    {
        return app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );
    }

    public function test_the_strip_is_products_and_not_categories(): void
    {
        $page = $this->get('/products')->assertOk();

        $products = app(TenantContext::class)->forBranch(
            Branch::central(),
            fn () => Product::query()->purchasable()->pricedHere()->latest('id')->take(5)->get(),
        );

        $this->assertNotEmpty($products, 'the seeded catalogue should have something purchasable to tell a story about');

        foreach ($products as $product) {
            $page->assertSee('data-vp-story-product="'.$product->id.'"', false);
            // The circle still points at the product page: that is the
            // fallback when the script is blocked, not a leftover.
            $page->assertSee('href="'.storefront_route('product', $product).'"', false);
        }
    }

    public function test_every_story_carries_a_variant_the_basket_would_accept(): void
    {
        $page = $this->get('/products')->assertOk()->getContent();

        preg_match_all('/data-vp-story-variant="(\d*)"/', $page, $found);

        $this->assertNotEmpty($found[1], 'the strip should render its circles');

        foreach ($found[1] as $variantId) {
            // Empty would mean the shoe has no sellable size, which the
            // composer's `purchasable()` is supposed to have kept out. The
            // viewer disables the button in that case rather than posting a
            // 404, but a strip full of disabled buttons is a broken strip.
            $this->assertNotSame('', $variantId, 'a story with no addable variant cannot be bought from');
        }
    }

    public function test_both_buttons_post_to_the_routes_that_do_the_work(): void
    {
        $this->get('/products')->assertOk()
            ->assertSee('action="'.storefront_route('cart.add').'"', false)
            ->assertSee('action="'.storefront_route('account.wishlist.add').'"', false)
            // Short labels beside their marks — the two buttons sit on one row
            // now, and the full sentences they used to read could only stack.
            ->assertSee('سبد خرید')
            ->assertSee('علاقمندی');
    }

    /**
     * A franchise tells its own stories, out of its own sellable catalogue.
     *
     * The composer goes through `pricedHere`, so this follows from the same
     * rule every other product query obeys — but a strip is the one place a
     * stale central-catalogue list would look harmless while offering a
     * franchise's customer a shoe it cannot sell.
     */
    public function test_a_franchise_tells_stories_it_can_actually_sell(): void
    {
        $shiraz = $this->franchise();

        $page = $this->get('/'.$shiraz->slug.'/products')->assertOk()->getContent();

        preg_match_all('/data-vp-story-product="(\d+)"/', $page, $found);

        $sellable = app(TenantContext::class)->forBranch(
            $shiraz,
            fn () => Product::query()->purchasable()->pricedHere()->pluck('id')->all(),
        );

        foreach (array_unique($found[1]) as $id) {
            $this->assertContains((int) $id, $sellable, 'a franchise offered a story for a shoe it does not sell');
        }
    }

    /**
     * The circle is the sale now — «بجای تصویر کفش یک انیمیشن لوپ داشته باشم از
     * نوشته ۳۰٪» — and the number on it is the stepped sale's live step.
     *
     * Read out of the same config the board, the track and every card's cut
     * read, so the strip cannot announce one number while the page under it
     * shows another.
     */
    public function test_the_circle_carries_the_live_step_of_the_stepped_sale(): void
    {
        $ladder = config('storefront.ladder');
        $cut = $ladder['steps'][$ladder['live'] - 1]['cut'];

        $page = $this->get('/products')->assertOk();

        // ٪ first, then a digit per element — the ladder's own spelling of a
        // rate, and the three elements are what the wave's three delays need.
        $mark = '<b>٪</b>';

        foreach (mb_str_split(fa_number($cut)) as $digit) {
            $mark .= '<i>'.$digit.'</i>';
        }

        $page->assertSee($mark, false);
    }

    /**
     * **This is the test that earns its place.** A literal ۳۰ typed into the
     * strip would pass everything above and go on saying ۳۰ the week the sale
     * steps to ۴۵ — on the phone, in the client's hand, with nothing anywhere
     * gone red. So move the sale on and watch the strip move with it.
     */
    public function test_the_strip_steps_when_the_sale_does(): void
    {
        // The third step is ٪۴۵. Nothing is seeded or migrated for this: the
        // strip reads config on render, which is the whole claim being made.
        config(['storefront.ladder.live' => 3]);

        $this->get('/products')->assertOk()
            ->assertSee('<i>۴</i><i>۵</i>', false)
            ->assertDontSee('<i>۳</i><i>۰</i>', false);
    }

    /**
     * Only the thumbnail changed. The photograph is what the story still opens
     * with, and it rides on the link, so taking the shoe off the circle must
     * not have taken it out of the viewer.
     */
    public function test_the_shoe_is_still_what_the_story_opens(): void
    {
        $page = $this->get('/products')->assertOk()->getContent();

        preg_match_all('/data-vp-story-src="([^"]*)"/', $page, $found);

        $this->assertNotEmpty($found[1], 'the strip should render its circles');

        foreach ($found[1] as $src) {
            $this->assertNotSame('', $src, 'a story with no picture opens onto an empty island');
        }

        // And the ring itself no longer loads one: five photographs fetched to
        // be covered by three glyphs is five requests a phone does not make.
        // Matched rather than compared, because what sits between the ring and
        // its content is whatever whitespace Blade leaves behind a directive.
        $this->assertSame(
            0,
            preg_match('/<span class="vp-story-ring">\s*<img/', $page),
            'the circle is still loading the photograph it no longer shows',
        );
    }

    /**
     * The categories have not simply been dropped on the floor — the row of
     * eight icons under the search line is still the catalogue's sections, and
     * that is a different control from the stories. This is here because the
     * two were one thing until this round, and the next person reading it
     * should not have to guess which one lost the categories.
     */
    public function test_the_category_row_is_still_categories(): void
    {
        $page = $this->get('/products')->assertOk();

        foreach (Category::where('is_active', true)->orderBy('position')->take(3)->get() as $category) {
            $page->assertSee(storefront_route('category', $category), false);
        }
    }
}
