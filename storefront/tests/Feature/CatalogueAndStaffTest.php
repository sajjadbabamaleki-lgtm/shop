<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Making a shoe, and hiring somebody, without a terminal.
 *
 * The split these tests are mostly about: the **catalogue** is the platform's
 * — one product, the same everywhere — and the **staff list** is the branch's.
 * So a franchise manager may hire at their own shop and may not rename the
 * brand's products for everybody, and neither of those is a matter of which
 * screen they happen to find.
 */
class CatalogueAndStaffTest extends TestCase
{
    use RefreshDatabase;

    private Branch $central;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
        $this->central = Branch::central();
        $this->tenant->set($this->central);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());

        return $user->fresh();
    }

    private function manager(Branch $branch, string $role = Role::FRANCHISE_MANAGER): User
    {
        $user = User::factory()->create();

        $this->tenant->forBranch($branch, fn () => BranchUser::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
        ]));

        return $user->fresh();
    }

    // --- the catalogue ----------------------------------------------------

    public function test_a_product_can_be_made_from_the_panel_and_reaches_the_shop(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/catalogue', [
            'title' => 'کتونی آدیداس سامبا',
            'status' => 'active',
            'published_at' => now()->toDateString(),
        ])->assertRedirect();

        $product = Product::where('title', 'کتونی آدیداس سامبا')->firstOrFail();

        // No sizes yet, so it is not in the shop — which is correct, and is
        // what the screen says out loud.
        $this->get('/products')->assertDontSee('کتونی آدیداس سامبا', false);

        $this->actingAs($admin)->post("/admin/catalogue/{$product->slug}/variants", [
            'size_value' => '42',
            'display_color' => 'مشکی',
            'color_family' => 'black',
            'price' => '3500000',
            'stock_on_hand' => 4,
        ])->assertRedirect();

        $this->get('/products')->assertSee('کتونی آدیداس سامبا', false);
        $this->get('/products/'.$product->fresh()->slug)->assertOk()->assertSee(toman(35_000_000), false);
    }

    /**
     * A size opens for sale at the branch the person is standing in. A SKU
     * with no offer anywhere would exist and be invisible.
     */
    public function test_adding_a_size_prices_it_at_this_branch_and_not_at_another(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);
        $product = Product::where('slug', 'nike-v2k-run')->firstOrFail();

        $this->actingAs($this->admin())->post("/admin/catalogue/{$product->slug}/variants", [
            'size_value' => '44',
            'display_color' => 'نامشخص',
            'color_family' => 'unspecified',
            'price' => '4000000',
            'stock_on_hand' => 2,
        ]);

        $variant = Variant::where('size_value', '44')->firstOrFail();

        $this->assertSame(40_000_000, $this->tenant->forBranch($this->central, fn () => $variant->offer?->price));
        $this->assertNull($this->tenant->forBranch($shiraz, fn () => $variant->fresh()->offer));
    }

    public function test_the_same_size_and_colour_cannot_be_added_twice(): void
    {
        $product = Product::where('slug', 'nike-v2k-run')->firstOrFail();
        $existing = $product->variants->first();

        $this->actingAs($this->admin())->post("/admin/catalogue/{$product->slug}/variants", [
            'size_value' => $existing->size_value,
            'display_color' => $existing->display_color,
            'color_family' => $existing->color_family,
            'price' => '1000000',
            'stock_on_hand' => 1,
        ])->assertSessionHasErrors('size_value');
    }

    /**
     * A size that has been sold is on an order line, and an order is a record
     * of a day in the past. So it is retired, not deleted.
     */
    public function test_a_size_is_retired_rather_than_deleted(): void
    {
        $product = Product::where('slug', 'nike-v2k-run')->firstOrFail();
        $variant = $product->defaultVariant;

        $this->actingAs($this->admin())
            ->post("/admin/catalogue/{$product->slug}/variants/{$variant->id}")
            ->assertRedirect();

        $this->assertSame('inactive', $variant->fresh()->status);
        $this->assertDatabaseHas('variants', ['id' => $variant->id]);
    }

    public function test_a_photograph_can_be_uploaded_and_the_primary_one_chosen(): void
    {
        Storage::fake('public');

        $product = Product::where('slug', 'nike-v2k-run')->firstOrFail();
        $product->media()->delete();

        $admin = $this->admin();

        foreach (['one.jpg', 'two.jpg'] as $name) {
            $this->actingAs($admin)->post("/admin/catalogue/{$product->slug}/media", [
                'photo' => UploadedFile::fake()->image($name, 800, 800),
            ])->assertRedirect();
        }

        $shots = $product->media()->orderBy('position')->get();

        $this->assertCount(2, $shots);
        // The first upload becomes the primary, because a card with no
        // photograph is worse than an arbitrary one.
        $this->assertTrue($shots->first()->is_primary);
        $this->assertFalse($shots->last()->is_primary);

        $this->actingAs($admin)->post("/admin/catalogue/{$product->slug}/media/{$shots->last()->id}/primary");

        $this->assertTrue($shots->last()->fresh()->is_primary);
        $this->assertFalse($shots->first()->fresh()->is_primary);
    }

    public function test_deleting_the_primary_photograph_promotes_another(): void
    {
        Storage::fake('public');

        $product = Product::where('slug', 'nike-v2k-run')->firstOrFail();
        $product->media()->delete();

        $admin = $this->admin();

        foreach (['one.jpg', 'two.jpg'] as $name) {
            $this->actingAs($admin)->post("/admin/catalogue/{$product->slug}/media", [
                'photo' => UploadedFile::fake()->image($name, 800, 800),
            ]);
        }

        $primary = $product->media()->where('is_primary', true)->firstOrFail();

        $this->actingAs($admin)->post("/admin/catalogue/{$product->slug}/media/{$primary->id}/delete");

        $this->assertSame(1, $product->media()->count());
        $this->assertTrue($product->media()->first()->is_primary);
    }

    /**
     * The catalogue belongs to the platform. A franchise manager sets their
     * own prices and counts their own shelf; they do not rename the brand's
     * products for everybody.
     */
    public function test_a_franchise_manager_cannot_touch_the_catalogue(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);
        $manager = $this->manager($shiraz);

        $this->actingAs($manager)->get('/admin/catalogue')->assertForbidden();
        $this->actingAs($manager)->post('/admin/catalogue', ['title' => 'مال من', 'status' => 'active'])->assertForbidden();
    }

    // --- staff ------------------------------------------------------------

    public function test_a_manager_hires_at_their_own_branch(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);
        $manager = $this->manager($shiraz, Role::FRANCHISE_OWNER);

        $this->actingAs($manager)->post('/admin/staff', [
            'name' => 'بهاره',
            'email' => 'sara@vikyplus.ir',
            'role' => Role::FRANCHISE_STAFF,
        ])->assertRedirect(route('admin.staff'))->assertSessionHas('password');

        $hired = User::where('email', 'sara@vikyplus.ir')->firstOrFail();

        $this->assertTrue($hired->fresh()->worksAt($shiraz));
        $this->assertFalse($hired->fresh()->worksAt($this->central));
    }

    /**
     * A branch screen may only grant branch roles. Otherwise the way to have
     * authority over every franchise is to be hired by one.
     */
    public function test_a_platform_role_cannot_be_granted_from_a_branch_screen(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);

        $this->actingAs($this->manager($shiraz, Role::FRANCHISE_OWNER))->post('/admin/staff', [
            'name' => 'کسی',
            'email' => 'someone@vikyplus.ir',
            'role' => Role::ADMIN,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'someone@vikyplus.ir']);
    }

    public function test_nobody_changes_their_own_role_or_removes_themselves(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);
        $owner = $this->manager($shiraz, Role::FRANCHISE_OWNER);

        $mine = $this->tenant->forBranch($shiraz, fn () => BranchUser::where('user_id', $owner->id)->firstOrFail());

        $this->actingAs($owner)->post("/admin/staff/{$mine->id}", ['role' => Role::FRANCHISE_STAFF])
            ->assertSessionHasErrors('role');

        $this->actingAs($owner)->post("/admin/staff/{$mine->id}/remove")->assertSessionHasErrors('staff');

        $this->assertSame($mine->role_id, $mine->fresh()->role_id);
    }

    /**
     * Removing somebody from a shop is not deleting a person: their name is
     * still on last month's orders.
     */
    public function test_removing_somebody_leaves_their_account_alone(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);
        $owner = $this->manager($shiraz, Role::FRANCHISE_OWNER);
        $staff = $this->manager($shiraz, Role::FRANCHISE_STAFF);

        $theirs = $this->tenant->forBranch($shiraz, fn () => BranchUser::where('user_id', $staff->id)->firstOrFail());

        $this->actingAs($owner)->post("/admin/staff/{$theirs->id}/remove")->assertRedirect(route('admin.staff'));

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertFalse($staff->fresh()->worksAt($shiraz));
    }

    /**
     * §18 on the staff list: a manager sees their own shop's people and
     * nobody else's, and an id from another branch is simply not there.
     */
    public function test_a_manager_cannot_touch_another_branchs_staff(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);

        $theirs = $this->manager($this->central, Role::FRANCHISE_STAFF);
        $central = $this->tenant->forBranch($this->central, fn () => BranchUser::where('user_id', $theirs->id)->firstOrFail());

        $mine = $this->manager($shiraz, Role::FRANCHISE_OWNER);

        $this->actingAs($mine)->get('/admin/staff')->assertOk()->assertDontSee($theirs->email, false);
        $this->actingAs($mine)->post("/admin/staff/{$central->id}", ['role' => Role::FRANCHISE_STAFF])->assertNotFound();
    }

    public function test_branch_staff_cannot_reach_the_staff_screen_at_all(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);

        $this->actingAs($this->manager($shiraz, Role::FRANCHISE_STAFF))
            ->get('/admin/staff')
            ->assertForbidden();
    }
}
