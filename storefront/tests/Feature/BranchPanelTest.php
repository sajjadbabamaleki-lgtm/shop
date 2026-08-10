<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\BranchUser;
use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Branches\BranchOpener;
use App\Support\Checkout\PlaceOrder;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 5: §19's branch panel.
 *
 * The screens are ordinary. What is not ordinary, and what these tests are
 * mostly about, is that the branch being administered comes from the signed-in
 * user and never from the address — §18's rule for the administration side.
 * A Shiraz manager who edits an id, a slug or a form field must still be in
 * Shiraz afterwards.
 */
class BranchPanelTest extends TestCase
{
    use RefreshDatabase;

    private Branch $central;

    private Branch $shiraz;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
        $this->central = Branch::central();
        $this->shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 3);
    }

    private function staff(Branch $branch, string $role = Role::FRANCHISE_MANAGER): User
    {
        $user = User::factory()->create();

        $this->tenant->forBranch($branch, fn () => BranchUser::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
        ]));

        return $user->fresh();
    }

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());

        return $user->fresh();
    }

    /** Places a real order at one branch, so the panel has something to show. */
    private function orderAt(Branch $branch, string $slug = 'nike-v2k-run'): Order
    {
        return $this->tenant->forBranch($branch, function () use ($branch, $slug) {
            $variant = Product::where('slug', $slug)->firstOrFail()->defaultVariant;

            $cart = Cart::create(['branch_id' => $branch->id, 'token' => Str::random(40)]);
            $cart->items()->create(['variant_id' => $variant->id, 'quantity' => 1]);

            return app(PlaceOrder::class)($cart->load('items.variant'), [
                'name' => 'مشتری', 'phone' => '09121112233', 'address' => 'یک نشانی',
            ]);
        });
    }

    // --- getting in -------------------------------------------------------

    public function test_the_panel_is_shut_to_anybody_not_signed_in(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/orders')->assertRedirect(route('admin.login'));
    }

    public function test_staff_sign_in_and_land_on_their_own_branch(): void
    {
        $user = $this->staff($this->shiraz);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('ویکی پلاس شیراز', false);
    }

    public function test_a_wrong_password_says_nothing_about_the_address(): void
    {
        $user = $this->staff($this->shiraz);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'not-it'])
            ->assertSessionHasErrors('email');

        $this->post('/admin/login', ['email' => 'nobody@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_somebody_with_no_branch_at_all_is_not_let_in(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    // --- §18, the administration half -------------------------------------

    /**
     * The one that matters. A Shiraz manager sees Shiraz's orders and cannot
     * reach Tehran's by quoting its number — the branch comes from them, not
     * from the address.
     */
    public function test_a_manager_sees_only_their_own_branchs_orders(): void
    {
        $mine = $this->orderAt($this->shiraz);
        $theirs = $this->orderAt($this->central);

        $this->actingAs($this->staff($this->shiraz))
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee($mine->number, false)
            ->assertDontSee($theirs->number, false);

        $this->actingAs($this->staff($this->shiraz))
            ->get("/admin/orders/{$theirs->number}")
            ->assertNotFound();
    }

    /**
     * And they cannot ask to be somewhere else. The switcher only accepts a
     * branch the user genuinely has authority at; a posted id is refused
     * rather than obeyed (§30).
     */
    public function test_a_manager_cannot_switch_to_a_branch_they_do_not_work_at(): void
    {
        $user = $this->staff($this->shiraz);

        $this->actingAs($user)
            ->post('/admin/branch', ['branch' => $this->central->id])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('branch');

        $this->actingAs($user)->get('/admin')->assertSee('ویکی پلاس شیراز', false);
    }

    public function test_a_platform_administrator_may_look_at_any_branch(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get('/admin')->assertOk();

        $this->actingAs($admin)->post('/admin/branch', ['branch' => $this->shiraz->id]);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('ویکی پلاس شیراز', false);
    }

    public function test_branch_staff_may_not_touch_prices(): void
    {
        $staff = $this->staff($this->shiraz, Role::FRANCHISE_STAFF);

        $this->actingAs($staff)->get('/admin/pricing')->assertForbidden();
        $this->actingAs($staff)->get('/admin/inventory')->assertOk();
    }

    // --- what the screens actually do -------------------------------------

    public function test_a_price_change_applies_to_this_branch_and_nowhere_else(): void
    {
        $offer = $this->tenant->forBranch(
            $this->shiraz,
            fn () => Product::where('slug', 'nike-v2k-run')->firstOrFail()->offerHere(),
        );

        $central = $this->tenant->forBranch(
            $this->central,
            fn () => Product::where('slug', 'nike-v2k-run')->firstOrFail()->offerHere(),
        );

        $this->actingAs($this->staff($this->shiraz))->post('/admin/pricing', [
            'offer' => $offer->id,
            // Typed the way somebody types it: Toman, Persian digits, separators.
            'price' => '۴٬۰۰۰٬۰۰۰',
            'compare_at_price' => '5,000,000',
            'status' => 'active',
        ])->assertRedirect(route('admin.pricing'));

        $this->assertSame(40_000_000, $offer->fresh()->price);
        $this->assertSame(50_000_000, $offer->fresh()->compare_at_price);
        $this->assertSame($central->price, $central->fresh()->price);

        // §29: who changed a branch price, from what, and when.
        $this->assertDatabaseHas('audits', ['auditable_type' => BranchOffer::class, 'action' => 'branch_offer.updated']);
    }

    public function test_a_price_below_its_own_struck_through_price_is_refused(): void
    {
        $offer = $this->tenant->forBranch(
            $this->shiraz,
            fn () => Product::where('slug', 'nike-v2k-run')->firstOrFail()->offerHere(),
        );

        $this->actingAs($this->staff($this->shiraz))->post('/admin/pricing', [
            'offer' => $offer->id,
            'price' => '5000000',
            'compare_at_price' => '4000000',
            'status' => 'active',
        ])->assertSessionHasErrors('compare_at_price');

        $this->assertSame($offer->price, $offer->fresh()->price);
    }

    public function test_another_branchs_offer_cannot_be_repriced_by_posting_its_id(): void
    {
        $central = $this->tenant->forBranch(
            $this->central,
            fn () => Product::where('slug', 'nike-v2k-run')->firstOrFail()->offerHere(),
        );

        $this->actingAs($this->staff($this->shiraz))->post('/admin/pricing', [
            'offer' => $central->id,
            'price' => '1000',
            'status' => 'active',
        ])->assertNotFound();

        $this->assertSame($central->price, $central->fresh()->price);
    }

    /**
     * A stock correction is a movement with a reason, not an edit. Otherwise
     * the ledger stops being able to explain the shelf.
     */
    public function test_counting_the_shelf_writes_the_difference_to_the_ledger(): void
    {
        $row = $this->tenant->forBranch(
            $this->shiraz,
            fn () => BranchInventory::firstOrFail(),
        );

        $this->actingAs($this->staff($this->shiraz))->post('/admin/inventory', [
            'inventory' => $row->id,
            'counted' => 7,
            'low_stock_threshold' => 2,
            'note' => 'شمارش انبار',
        ])->assertRedirect(route('admin.inventory'));

        $this->assertSame(7, $row->fresh()->stock_on_hand);

        $this->tenant->forBranch($this->shiraz, function () use ($row) {
            $movement = InventoryMovement::where('variant_id', $row->variant_id)
                ->where('type', 'adjustment')
                ->firstOrFail();

            $this->assertSame(4, $movement->quantity, 'the ledger records the difference, not the new total');
            $this->assertSame('شمارش انبار', $movement->note);
        });
    }

    /**
     * Counting fewer than are already reserved would break a promise to a
     * customer, and the database would refuse it. The panel says why first.
     */
    public function test_the_count_cannot_go_below_what_is_reserved(): void
    {
        $order = $this->orderAt($this->shiraz);

        $row = $this->tenant->forBranch(
            $this->shiraz,
            fn () => BranchInventory::where('variant_id', $order->items->first()->variant_id)->firstOrFail(),
        );

        $this->assertSame(1, $row->stock_reserved);

        $this->actingAs($this->staff($this->shiraz))->post('/admin/inventory', [
            'inventory' => $row->id,
            'counted' => 0,
        ])->assertSessionHasErrors('counted');

        $this->assertSame(3, $row->fresh()->stock_on_hand);
    }

    public function test_marking_an_order_paid_sells_the_stock(): void
    {
        $order = $this->orderAt($this->shiraz);
        $variantId = $order->items->first()->variant_id;

        $this->actingAs($this->staff($this->shiraz))
            ->post("/admin/orders/{$order->number}", ['status' => Order::PAID])
            ->assertRedirect(route('admin.order', $order->number));

        $this->assertSame(Order::PAID, $order->fresh()->status);

        $this->tenant->forBranch($this->shiraz, function () use ($variantId) {
            $stock = BranchInventory::where('variant_id', $variantId)->firstOrFail();

            $this->assertSame(0, $stock->stock_reserved);
            $this->assertSame(2, $stock->stock_on_hand);
        });
    }

    /**
     * Written-out transitions, so an order already paid for cannot be marked
     * paid again and sell the same pair twice.
     */
    public function test_an_order_cannot_be_paid_for_twice(): void
    {
        $order = $this->orderAt($this->shiraz);
        $user = $this->staff($this->shiraz);

        $this->actingAs($user)->post("/admin/orders/{$order->number}", ['status' => Order::PAID]);

        $this->actingAs($user)
            ->post("/admin/orders/{$order->number}", ['status' => Order::PAID])
            ->assertSessionHasErrors('status');

        $this->tenant->forBranch($this->shiraz, function () use ($order) {
            $stock = BranchInventory::where('variant_id', $order->items->first()->variant_id)->firstOrFail();

            $this->assertSame(2, $stock->stock_on_hand, 'the pair left the shelf once, not twice');
        });
    }

    public function test_a_branch_can_edit_its_own_record_but_not_its_address_on_the_site(): void
    {
        $this->actingAs($this->staff($this->shiraz, Role::FRANCHISE_OWNER))->post('/admin/settings', [
            'name' => 'ویکی پلاس شیراز — معالی‌آباد',
            'city' => 'شیراز',
            'presence' => 'both',
            'slug' => 'somewhere-else',
        ])->assertRedirect(route('admin.settings'));

        $this->assertSame('ویکی پلاس شیراز — معالی‌آباد', $this->shiraz->fresh()->name);
        $this->assertSame('shiraz', $this->shiraz->fresh()->slug, 'the slug is the shop address and is not editable here');
    }
}
