<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOffer;
use App\Models\FrontPagePlacement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\FrontPage;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two things `/admin/front-page` could not do, and now can.
 *
 * The screen was built to answer «پرفروش ترین ها و موارد داخل تخفیف پله ایرو ما
 * خودمون دستی اد میکنیم» and it left out the two decisions that turned out to
 * matter most:
 *
 * - **the hero**, excluded on the grounds that a slide is a product *and* the
 *   line above its name, which was two decisions where the screen collected
 *   one. The cost was that the largest thing on the front page could only be
 *   changed by a deploy.
 * - **whether the sale is on at all**, which nothing anywhere could change. The
 *   seeded campaign closed itself on a four-week timer and the front page
 *   emptied; see `SteppedSaleIsManualTest`.
 *
 * Both are on the screen now — «مدیریت هوم» — and these hold what they do.
 */
class FrontPageHeroAndSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());

        return $user->fresh();
    }

    // --- the hero ----------------------------------------------------------

    /**
     * The trap this band brings with it: its default is a map of slug => the
     * line above it, where every other band's is a plain list. Read the values
     * instead of the keys and `slugs('hero')` returns three eyebrows, none of
     * which is a product, and the deck goes silently empty.
     */
    public function test_the_heros_default_slugs_are_slugs_and_not_the_lines_above_them(): void
    {
        $this->assertSame(
            array_keys(config('storefront.hero.products')),
            app(FrontPage::class)->slugs('hero')
        );

        foreach (app(FrontPage::class)->slugs('hero') as $slug) {
            $this->assertNotNull(Product::where('slug', $slug)->first(), "«{$slug}» is not a product.");
        }
    }

    public function test_an_untouched_hero_is_still_the_files_deck(): void
    {
        $slides = app(FrontPage::class)->heroSlides();

        $this->assertSame(
            config('storefront.hero.products'),
            collect($slides)->pluck('eyebrow', 'slug')->all()
        );
    }

    public function test_the_panel_can_choose_the_hero_and_the_line_above_each_slide(): void
    {
        $product = Product::where('slug', 'nike-v2k-run')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/admin/front-page/hero/add', [
                'product' => $product->id,
                'caption' => 'انتخاب سردبیر',
            ])
            ->assertRedirect();

        $this->assertSame(
            [['slug' => 'nike-v2k-run', 'eyebrow' => 'انتخاب سردبیر']],
            app(FrontPage::class)->heroSlides()
        );

        // And it is really on the page, above the product's own name.
        $page = $this->get('/')->assertOk();

        $page->assertSee('انتخاب سردبیر');
        $this->assertCount(config('storefront.hero.repeat'), $page->viewData('heroSlides'));
    }

    /**
     * The line is optional, and what happens when it is left empty is a
     * decision rather than an accident: a slide with a blank space over the
     * title reads as a rendering fault. It falls back to the file's line for
     * that same product, and then to the product's name.
     */
    public function test_a_slide_with_no_line_falls_back_rather_than_printing_nothing(): void
    {
        $known = Product::where('slug', array_key_first(config('storefront.hero.products')))->firstOrFail();
        $unknown = Product::where('slug', 'nike-v2k-run')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/admin/front-page/hero/add', ['product' => $known->id])
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->post('/admin/front-page/hero/add', ['product' => $unknown->id])
            ->assertRedirect();

        $slides = collect(app(FrontPage::class)->heroSlides())->pluck('eyebrow', 'slug');

        $this->assertSame(config('storefront.hero.products')[$known->slug], $slides[$known->slug]);
        $this->assertSame($unknown->title, $slides[$unknown->slug]);
        $this->assertNotEmpty($slides[$unknown->slug]);
    }

    public function test_the_hero_holds_three_slides_and_refuses_a_fourth(): void
    {
        $admin = $this->admin();

        foreach (Product::query()->take(3)->get() as $product) {
            $this->actingAs($admin)
                ->post('/admin/front-page/hero/add', ['product' => $product->id])
                ->assertRedirect();
        }

        $fourth = Product::query()->skip(3)->take(1)->first();

        $this->actingAs($admin)
            ->post('/admin/front-page/hero/add', ['product' => $fourth->id])
            ->assertSessionHasErrors('product');

        $this->assertSame(3, FrontPagePlacement::where('band', 'hero')->count());
    }

    /** Only a captioned band stores one; the others must not grow a stray line. */
    public function test_a_band_that_prints_no_line_does_not_store_one(): void
    {
        $product = Product::where('slug', 'nike-v2k-run')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/admin/front-page/ladder/add', [
                'product' => $product->id,
                'caption' => 'چیزی که این بخش نمی‌نویسد',
            ])
            ->assertRedirect();

        $this->assertNull(
            FrontPagePlacement::where('band', 'ladder')->firstOrFail()->caption
        );
    }

    // --- the sale ----------------------------------------------------------

    public function test_the_switch_turns_the_sale_off_and_the_page_holds(): void
    {
        $this->assertTrue(BranchOffer::query()->acrossAllBranches()->promoted()->exists());

        $this->actingAs($this->admin())
            ->post('/admin/front-page/sale', ['state' => 'off'])
            ->assertRedirect();

        $this->assertFalse(BranchOffer::query()->acrossAllBranches()->promoted()->exists());

        // The hero is not a sale band and must survive this — the whole point
        // of the split in HomeController.
        $page = $this->get('/')->assertOk();
        $this->assertNotEmpty($page->viewData('heroSlides'));
        $this->assertEmpty($page->viewData('ladderDeals'));
    }

    public function test_the_switch_turns_it_back_on_with_no_end_date(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/front-page/sale', ['state' => 'off']);
        $this->actingAs($admin)->post('/admin/front-page/sale', ['state' => 'on']);

        $this->assertTrue(BranchOffer::query()->acrossAllBranches()->promoted()->exists());

        foreach (BranchOffer::query()->acrossAllBranches()->whereNotNull('compare_at_price')->get() as $offer) {
            $this->assertNull($offer->promotion_ends_at, 'Turning the sale on must not set a new countdown.');
        }

        $page = $this->get('/')->assertOk();
        $this->assertNotEmpty($page->viewData('ladderDeals'));
    }

    /**
     * The screen reads the offers rather than a setting of its own, so its
     * badge cannot disagree with the shop. Both states are asserted, because a
     * badge that is always right about one of them is a constant.
     */
    public function test_the_screen_says_which_state_the_sale_is_in(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/front-page')->assertOk()->assertViewHas('saleIsOn', true);

        $this->actingAs($admin)->post('/admin/front-page/sale', ['state' => 'off']);

        $this->actingAs($admin)->get('/admin/front-page')->assertOk()->assertViewHas('saleIsOn', false);
    }

    /** A franchise manager may not switch the chain's campaign off. */
    public function test_the_switch_needs_the_catalogue_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/front-page/sale', ['state' => 'off'])
            ->assertForbidden();

        $this->assertTrue(BranchOffer::query()->acrossAllBranches()->promoted()->exists());
    }
}
