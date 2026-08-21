<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUser;
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
 * Choosing the front page's cast from the panel.
 *
 * The catalogue grew a supplier and the front page stopped being able to answer
 * «which products?» from the catalogue itself — «پرفروش ترین ها و موارد داخل
 * تخفیف پله ایرو ما خودمون دستی اد میکنیم». What these hold is the pair of
 * states that makes that safe: a band nobody has touched still shows the
 * design's own list, and a band somebody has chosen shows exactly what they
 * chose and nothing else.
 */
class FrontPageBandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    /**
     * Just the stepped sale's row of cards.
     *
     * The band is what these tests are about, and the page carries the same
     * products in three other places.
     */
    private function saleRow(): string
    {
        $html = $this->get('/')->assertOk()->getContent();

        $start = strpos($html, 'vp-ladder-deals');
        $this->assertNotFalse($start, 'The sale row is not on the page at all.');

        return substr($html, $start, 6000);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());

        return $user->fresh();
    }

    /** Someone who may run a branch may not rearrange the chain's front page. */
    public function test_the_screen_needs_the_catalogue_permission(): void
    {
        $this->actingAs($this->admin())->get('/admin/front-page')->assertOk();

        $manager = User::factory()->create();
        BranchUser::create([
            'branch_id' => Branch::central()->id,
            'user_id' => $manager->id,
            'role_id' => Role::where('slug', Role::FRANCHISE_MANAGER)->firstOrFail()->id,
        ]);

        $this->actingAs($manager->fresh())->get('/admin/front-page')->assertForbidden();
    }

    /**
     * The state that keeps a fresh install looking like the design: no rows,
     * so the file decides.
     */
    public function test_an_untouched_band_shows_the_configured_default(): void
    {
        $this->assertSame(
            config('storefront.front_page.ladder_products'),
            app(FrontPage::class)->slugs('ladder')
        );

        $this->actingAs($this->admin())->get('/admin/front-page')
            ->assertOk()
            ->assertSee('پیش‌فرض', false);
    }

    /** What the panel chooses is what the page shows, and only that. */
    public function test_choosing_products_changes_the_sale_row(): void
    {
        $keep = Product::where('slug', 'new-balance-530')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/admin/front-page/ladder/add', ['product' => $keep->id])
            ->assertRedirect();

        $row = $this->saleRow();

        $this->assertStringContainsString('کتونی نیوبالانس', $row);
        // Every other seeded shoe is out of the *sale row* now. The whole page
        // is the wrong thing to assert on: the hero and the best-seller tiles
        // keep their own defaults, and golden-goose is still in both.
        $this->assertStringNotContainsString('کتونی گلدن گوس', $row);
    }

    /** And putting it back is one button. */
    public function test_reset_returns_a_band_to_its_default(): void
    {
        $keep = Product::where('slug', 'new-balance-530')->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/front-page/ladder/add', ['product' => $keep->id]);
        $this->assertSame(1, FrontPagePlacement::where('band', 'ladder')->count());

        $this->actingAs($admin)->post('/admin/front-page/ladder/reset')->assertRedirect();

        $this->assertSame(0, FrontPagePlacement::where('band', 'ladder')->count());
        $this->assertStringContainsString('کتونی گلدن گوس', $this->saleRow());
    }

    /** The layout has five places; a sixth is refused rather than wrapped. */
    public function test_a_band_refuses_more_than_it_has_room_for(): void
    {
        $admin = $this->admin();
        $products = Product::query()->orderBy('id')->take(5)->get();

        foreach ($products as $product) {
            $this->actingAs($admin)->post('/admin/front-page/ladder/add', ['product' => $product->id]);
        }

        $sixth = Product::create([
            'slug' => 'a-sixth-shoe',
            'title' => 'کفش ششم',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/front-page/ladder/add', ['product' => $sixth->id])
            ->assertSessionHasErrors('product');

        $this->assertSame(5, FrontPagePlacement::where('band', 'ladder')->count());
    }

    /** A band with one slot replaces instead of refusing. */
    public function test_the_daily_deal_replaces_its_one_product(): void
    {
        $admin = $this->admin();
        $first = Product::where('slug', 'on-cloudtilt')->firstOrFail();
        $second = Product::where('slug', 'golden-goose')->firstOrFail();

        $this->actingAs($admin)->post('/admin/front-page/daily_deal/add', ['product' => $first->id]);
        $this->actingAs($admin)->post('/admin/front-page/daily_deal/add', ['product' => $second->id]);

        $rows = FrontPagePlacement::where('band', 'daily_deal')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($second->id, $rows->first()->product_id);
    }

    /** Order is the shopkeeper's, and moving one swaps it with its neighbour. */
    public function test_a_placement_can_be_moved(): void
    {
        $admin = $this->admin();
        $a = Product::where('slug', 'golden-goose')->firstOrFail();
        $b = Product::where('slug', 'on-cloudtilt')->firstOrFail();

        $this->actingAs($admin)->post('/admin/front-page/stories/add', ['product' => $a->id]);
        $this->actingAs($admin)->post('/admin/front-page/stories/add', ['product' => $b->id]);

        $second = FrontPagePlacement::where('band', 'stories')->orderBy('position')->get()->last();

        $this->actingAs($admin)
            ->post("/admin/front-page/placements/{$second->id}/move", ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(
            [$b->id, $a->id],
            FrontPagePlacement::where('band', 'stories')->orderBy('position')->pluck('product_id')->all()
        );
    }

    /**
     * A product that leaves the catalogue leaves the front page with it, rather
     * than leaving a row pointing at nothing.
     */
    public function test_deleting_a_product_clears_its_placement(): void
    {
        $product = Product::where('slug', 'golden-goose')->firstOrFail();

        $this->actingAs($this->admin())->post('/admin/front-page/stories/add', ['product' => $product->id]);
        $this->assertSame(1, FrontPagePlacement::where('band', 'stories')->count());

        $product->delete();

        $this->assertSame(0, FrontPagePlacement::where('band', 'stories')->count());
    }

    /** A band that is not a band is a 404, not a new band. */
    public function test_an_unknown_band_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/front-page/whatever/add', ['product' => Product::first()->id])
            ->assertNotFound();
    }
}
