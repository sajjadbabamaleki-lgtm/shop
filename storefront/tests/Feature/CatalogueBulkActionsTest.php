<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Category;
use App\Models\FrontPagePlacement;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\FrontPage;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «محصولاتی ک در سایت وجود دارند را یک گزینه داشته باشیم ک ب صورت دسته بندی
 * بتونیم انتقالشون بدیم به دسته بندی های دیگ مثل حراج پله ای، و ب صورت دسته‌ای
 * هم بتونم ناموجود کنیمشون».
 *
 * The catalogue list's bulk bar: move to a section, add to a section, onto the
 * stepped sale, out of stock here.
 */
class CatalogueBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
    }

    private function admin(string $role = Role::ADMIN): User
    {
        // firstOrCreate, because one test signs in twice and the email is
        // unique.
        $user = User::firstOrCreate(
            ['email' => $role.'@vikyplus.test'],
            ['name' => 'مدیر', 'password' => 'secret'],
        );

        if ($user->roles()->count() === 0) {
            $user->roles()->attach(Role::where('slug', $role)->sole());
        }

        return $user;
    }

    /** @return list<int> */
    private function two(): array
    {
        return Product::orderBy('id')->take(2)->pluck('id')->all();
    }

    public function test_the_list_offers_the_bar(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/catalogue')
            ->assertOk()
            ->assertSee(route('admin.catalogue.bulk'), false)
            ->assertSee('انتقال به دسته')
            ->assertSee('افزودن به حراج پله‌ای')
            ->assertSee('ناموجود کردن در این شعبه');
    }

    public function test_the_list_narrows_to_one_section(): void
    {
        $section = Category::orderBy('position')->firstOrFail();
        $inside = Product::orderBy('id')->firstOrFail();
        $outside = Product::orderByDesc('id')->firstOrFail();

        $inside->categories()->sync([$section->id]);
        $outside->categories()->sync([]);

        $this->actingAs($this->admin())
            ->get('/admin/catalogue?category='.$section->id)
            ->assertOk()
            ->assertSee($inside->title)
            ->assertDontSee('vp-p-'.$outside->id, false);
    }

    public function test_move_replaces_every_section_with_the_chosen_one(): void
    {
        [$a, $b] = $this->two();
        $from = Category::orderBy('position')->firstOrFail();
        $to = Category::orderByDesc('position')->firstOrFail();

        Product::find($a)->categories()->sync([$from->id]);

        $this->actingAs($this->admin())
            ->post('/admin/catalogue/bulk', ['action' => 'move', 'category' => $to->id, 'products' => [$a, $b]])
            ->assertRedirect()
            ->assertSessionHas('status');

        foreach ([$a, $b] as $id) {
            $this->assertSame([$to->id], Product::find($id)->categories()->pluck('categories.id')->all());
        }
    }

    public function test_add_keeps_the_sections_it_had(): void
    {
        [$a] = $this->two();
        $from = Category::orderBy('position')->firstOrFail();
        $to = Category::orderByDesc('position')->firstOrFail();

        Product::find($a)->categories()->sync([$from->id]);

        $this->actingAs($this->admin())
            ->post('/admin/catalogue/bulk', ['action' => 'add', 'category' => $to->id, 'products' => [$a]])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$from->id, $to->id],
            Product::find($a)->categories()->pluck('categories.id')->all(),
        );
    }

    public function test_a_move_with_no_section_is_refused(): void
    {
        [$a] = $this->two();

        $this->actingAs($this->admin())
            ->post('/admin/catalogue/bulk', ['action' => 'move', 'products' => [$a]])
            ->assertSessionHasErrors('category');
    }

    public function test_nothing_ticked_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/catalogue/bulk', ['action' => 'out'])
            ->assertSessionHasErrors('products');
    }

    public function test_the_stepped_sale_takes_what_fits_and_counts_the_rest(): void
    {
        FrontPagePlacement::where('band', 'ladder')->delete();

        $max = FrontPage::BANDS['ladder']['max'];
        $ids = Product::orderBy('id')->pluck('id')->all();

        // More than the band has room for, if the catalogue has that many.
        $this->actingAs($this->admin())
            ->post('/admin/catalogue/bulk', ['action' => 'ladder', 'products' => $ids])
            ->assertRedirect()
            ->assertSessionHas('status');

        $placed = FrontPagePlacement::where('band', 'ladder')->orderBy('position')->pluck('product_id')->all();

        $this->assertSame(array_slice($ids, 0, $max), $placed);

        // Again: nothing doubles.
        $this->actingAs($this->admin())
            ->post('/admin/catalogue/bulk', ['action' => 'ladder', 'products' => [$ids[0]]])
            ->assertRedirect();

        $this->assertSame(1, FrontPagePlacement::where('band', 'ladder')->where('product_id', $ids[0])->count());
    }

    public function test_out_of_stock_empties_this_branchs_shelves_down_to_what_orders_hold(): void
    {
        $central = Branch::central();
        $product = Product::whereHas('variants')->orderBy('id')->firstOrFail();
        $variants = $product->variants()->pluck('id');

        // One shelf with pairs held by an order, to prove those stay held.
        $held = BranchInventory::withoutGlobalScopes()
            ->where('branch_id', $central->id)
            ->whereIn('variant_id', $variants)
            ->firstOrFail();
        $held->forceFill(['stock_on_hand' => 6, 'stock_reserved' => 2])->save();

        $this->actingAs($this->admin())
            ->post('/admin/catalogue/bulk', ['action' => 'out', 'products' => [$product->id]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $shelves = BranchInventory::withoutGlobalScopes()
            ->where('branch_id', $central->id)
            ->whereIn('variant_id', $variants)
            ->get();

        foreach ($shelves as $shelf) {
            $this->assertSame($shelf->stock_reserved, $shelf->stock_on_hand, 'a shelf still has something to sell');
        }

        $this->assertSame(2, $held->fresh()->stock_on_hand);

        // Every change is on the ledger, as a count on the inventory screen is.
        $this->assertTrue(
            InventoryMovement::withoutGlobalScopes()
                ->where('variant_id', $held->variant_id)
                ->where('type', 'adjustment')
                ->where('quantity', -4)
                ->exists()
        );

        // And the shoe is still a product on the shop: listing and stock are
        // two separate questions.
        $this->assertSame('active', $product->fresh()->status);
        $this->assertNotNull($product->fresh()->published_at);
    }
}
