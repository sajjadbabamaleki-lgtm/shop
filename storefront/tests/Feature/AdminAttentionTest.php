<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\Admin\Attention;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §17's bell.
 *
 * **It counts state, not events.** This application has no notifications table
 * and nothing that writes one, so the bell asks the database what is waiting
 * every time the page is drawn. That makes it always right and unable to
 * remember: it can say «three orders are waiting» and never «three orders
 * arrived while you were out». The tests below pin the half that exists rather
 * than pretending at the half that does not.
 *
 * The one that matters most is the last: a franchise manager's bell must not
 * report head office's franchise applications. A count is a small leak and
 * exactly the kind nobody looks for.
 */
class AdminAttentionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();
    }

    private ?User $admin = null;

    private function admin(): User
    {
        if ($this->admin === null) {
            $this->admin = User::create(['name' => 'مدیر', 'email' => 'admin@vikyplus.test', 'password' => 'secret']);
            $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->sole());
        }

        return $this->admin;
    }

    private function order(string $status, ?string $shipBy = null): Order
    {
        $customer = Customer::create(['phone' => '0912'.mt_rand(1000000, 9999999), 'is_active' => true]);

        return app(TenantContext::class)->forBranch($this->branch, fn () => Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'number' => 'VP-'.mt_rand(100000, 999999),
            'status' => $status,
            'payment_status' => $status === Order::PAID ? 'paid' : 'unpaid',
            'subtotal' => 1_000_000, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => 1_000_000,
            'contact_name' => 'خریدار', 'contact_phone' => '09120000000',
            'address' => 'ن', 'placed_at' => now(), 'paid_at' => $status === Order::PAID ? now() : null,
            'estimated_ship_by' => $shipBy,
        ]));
    }

    /** @return array<string, int> the items, keyed */
    private function waiting(User $user, ?Branch $branch = null): array
    {
        $items = app(Attention::class)->for($user, $branch ?? $this->branch);

        return array_column($items, 'count', 'key');
    }

    /** Nothing waiting is no items at all — not a list of zeroes. */
    public function test_a_quiet_shop_produces_no_items(): void
    {
        $this->assertSame([], app(Attention::class)->for($this->admin(), $this->branch));
        $this->assertSame(0, app(Attention::class)->count($this->admin(), $this->branch));
    }

    public function test_orders_waiting_to_be_paid_and_to_be_shipped_are_counted_apart(): void
    {
        $this->order(Order::PLACED);
        $this->order(Order::PLACED);
        $this->order(Order::PAID);

        $waiting = $this->waiting($this->admin());

        $this->assertSame(2, $waiting['placed']);
        $this->assertSame(1, $waiting['unconfirmed']);
    }

    /**
     * A delivered order is finished and must not sit in the bell for ever.
     * The obvious mistake here is counting «not delivered» rather than the two
     * states that actually need somebody.
     */
    public function test_a_finished_order_is_not_waiting(): void
    {
        $this->order(Order::DELIVERED);
        $this->order(Order::CANCELLED);

        $this->assertSame([], app(Attention::class)->for($this->admin(), $this->branch));
    }

    /**
     * Overdue is derived from the promise, not stored, so an order whose ship
     * date has passed counts the morning after without anything having run.
     */
    public function test_an_order_past_its_promised_ship_date_is_counted_as_overdue(): void
    {
        $this->order(Order::PAID, now()->subDays(2)->toDateString());

        $this->assertSame(1, $this->waiting($this->admin())['overdue']);
    }

    /** An order still inside its promise is not overdue. */
    public function test_an_order_inside_its_promise_is_not_overdue(): void
    {
        $this->order(Order::PAID, now()->addDays(3)->toDateString());

        $this->assertArrayNotHasKey('overdue', $this->waiting($this->admin()));
    }

    /**
     * A line with no warning level set is not being warned about. A branch that
     * has not said what «low» means for a shoe has not asked to be told.
     */
    public function test_a_line_with_no_threshold_is_not_reported_as_low(): void
    {
        app(TenantContext::class)->forBranch($this->branch, function (): void {
            $row = BranchInventory::query()->firstOrFail();
            $row->update(['sellable_stock' => 0, 'low_stock_threshold' => 0]);
        });

        $this->assertArrayNotHasKey('low', $this->waiting($this->admin()));
    }

    public function test_a_line_at_its_warning_level_is_reported(): void
    {
        app(TenantContext::class)->forBranch($this->branch, function (): void {
            $row = BranchInventory::query()->firstOrFail();
            $row->update(['sellable_stock' => 1, 'low_stock_threshold' => 3]);
        });

        $this->assertSame(1, $this->waiting($this->admin())['low']);
    }

    /**
     * **A count is a leak too.**
     *
     * The enquiries are the platform's — a franchise application is not
     * Shiraz's to answer — so somebody without `platform.enquiry.manage` must
     * not be told how many there are. This is the assertion that would catch
     * the tempting shortcut of counting everything and hiding the row in Blade.
     */
    public function test_a_branch_manager_is_not_told_how_many_enquiries_head_office_has(): void
    {
        Enquiry::create([
            'kind' => Enquiry::WHOLESALE,
            'name' => 'فروشگاه نمونه',
            'phone' => '09120000000',
            'message' => 'قیمت عمده',
            'status' => Enquiry::NEW,
        ]);

        // Head office sees it.
        $this->assertSame(1, $this->waiting($this->admin())['enquiries']);

        // A branch manager does not.
        $manager = User::create(['name' => 'مدیر شعبه', 'email' => 'shop@vikyplus.test', 'password' => 'secret']);
        $manager->branchRoles()->create([
            'branch_id' => $this->branch->id,
            'role_id' => Role::where('slug', Role::FRANCHISE_MANAGER)->sole()->id,
        ]);

        $this->assertArrayNotHasKey('enquiries', $this->waiting($manager));
    }

    /**
     * The marketplace has no branch, and the bell has to hold that — without
     * throwing, and without quietly counting some other shop's work.
     */
    public function test_the_bell_on_a_screen_with_no_branch_shows_only_the_platforms_own(): void
    {
        // One of each: an order, which is a branch's, and an enquiry, which is
        // the platform's.
        $this->order(Order::PLACED);

        Enquiry::create([
            'kind' => Enquiry::FRANCHISE,
            'name' => 'متقاضی نمایندگی',
            'phone' => '09120000000',
            'message' => 'شرایط نمایندگی',
            'status' => Enquiry::NEW,
        ]);

        $keys = array_column(app(Attention::class)->for($this->admin(), null), 'key');

        $this->assertSame(['enquiries'], $keys);
    }

    /** And it is drawn in the shell, so it is on every screen. */
    public function test_the_bell_is_in_the_topbar_with_its_total(): void
    {
        $this->order(Order::PLACED);
        $this->order(Order::PLACED);

        $page = $this->actingAs($this->admin(), 'web')->get('/admin')->assertOk();

        $page->assertSee('vp-adm-bell', false)
            ->assertSee('سفارش در انتظار پرداخت')
            ->assertSee(fa_number(2));
    }

    /** With nothing waiting there is no number on it, and it says so inside. */
    public function test_an_empty_bell_carries_no_count(): void
    {
        $page = $this->actingAs($this->admin(), 'web')->get('/admin')->assertOk();

        $page->assertDontSee('vp-adm-bell-count', false)
            ->assertSee('چیزی در انتظار نیست');
    }
}
