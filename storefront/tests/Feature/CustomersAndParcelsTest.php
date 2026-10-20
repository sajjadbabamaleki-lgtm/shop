<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Variant;
use App\Rules\PersianName;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Five requests of 2026-10-20, held together because they are one customer's
 * journey: the name they type, the parcel's label, the list the shop keeps
 * of who ordered, and what the order page says about the parcel afterwards.
 *
 * - «امکان نوشتن اسم به انگلیسی … فقط فارسی بتونن بنویسن»
 * - «سایز خروجی آدرس به A5 تبدیل بشه»
 * - «در خروجی آدرس اسم سفارش و نوع ارسالش … هم بخوره»
 * - «در پنل ادمین یک بخشی … شماره‌هاشون همراه اسمشون و تاریخ تولدشون»
 * - «اگر حساب کاربری تشکیل بده میتونه ارسالش رو … ببینه»
 */
class CustomersAndParcelsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
        $this->branch = Branch::central();
    }

    private function admin(string $role = Role::ADMIN): User
    {
        $user = User::create(['name' => 'مدیر', 'email' => $role.'@vikyplus.test', 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', $role)->sole());

        return $user;
    }

    private function order(Customer $customer, string $status = Order::PLACED, ?string $tracking = null): Order
    {
        return app(TenantContext::class)->forBranch($this->branch, function () use ($customer, $status, $tracking): Order {
            $variant = Variant::whereHas('product.media')->firstOrFail();
            $method = ShippingMethod::create(['branch_id' => $this->branch->id, 'name' => 'تیپاکس', 'price' => 0, 'is_active' => true]);

            $order = Order::create([
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'shipping_method_id' => $method->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => $status,
                'payment_status' => $status === Order::PLACED ? 'unpaid' : 'paid',
                'subtotal' => 1_200_000,
                'discount_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 1_200_000,
                'contact_name' => 'سارا رضایی',
                'contact_phone' => $customer->phone,
                'address' => 'خیابان امیرکبیر، کوچه ۱۲',
                'tracking_number' => $tracking,
                'placed_at' => now(),
            ]);

            $order->items()->create([
                'variant_id' => $variant->id,
                'product_title' => 'کتونی آزمایشی',
                'sku' => 'T-1',
                'size_value' => '38',
                'unit_price' => 600_000,
                'quantity' => 2,
                'line_total' => 1_200_000,
            ]);

            return $order;
        });
    }

    // --- the name ---------------------------------------------------------

    public function test_the_rule_takes_persian_and_refuses_latin_and_digits(): void
    {
        foreach (['سجاد بابامالکی' => true, 'علی‌رضا' => true, 'علي كاظمي' => true, 'Sajjad' => false, 'سجاد 2' => false, 'سجاد ۲' => false] as $name => $ok) {
            $failed = false;
            (new PersianName)->validate('name', $name, function () use (&$failed) {
                $failed = true;
            });
            $this->assertSame(! $ok, $failed, $name);
        }
    }

    // --- the label ----------------------------------------------------------

    public function test_the_label_is_a5_and_says_what_is_in_it_and_how_it_goes(): void
    {
        $order = $this->order(Customer::create(['name' => 'سارا', 'phone' => '09121110000', 'is_active' => true]));

        $this->actingAs($this->admin())
            ->get(route('admin.order.label', $order))
            ->assertOk()
            ->assertSee('class="is-a5"', false)
            ->assertSee('نوع ارسال', false)
            ->assertSee('<b>تیپاکس</b>', false)
            ->assertSee('کتونی آزمایشی — سایز 38', false)
            ->assertSee('۲ عدد', false);

        $this->assertStringContainsString('size: A5', file_get_contents(public_path('assets/css/print-sheet.css')));
    }

    // --- the list of customers -------------------------------------------------

    public function test_the_list_holds_who_ordered_with_name_phone_and_birth_date(): void
    {
        $bought = Customer::create(['name' => 'مریم رضایی', 'phone' => '09121112222', 'birth_date' => '1991-03-21', 'is_active' => true]);
        $this->order($bought);
        Customer::create(['name' => 'فقط ثبت‌نام', 'phone' => '09121113333', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->get(route('admin.customers'))
            ->assertOk()
            ->assertSee('مریم رضایی', false)
            ->assertSee('09121112222', false)
            ->assertSee(fa_date($bought->fresh()->birth_date), false)
            ->assertDontSee('09121113333', false);

        $csv = $this->actingAs($this->admin(Role::OWNER))->get(route('admin.customers.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('"مریم رضایی",09121112222', $csv);
        $this->assertStringNotContainsString('09121113333', $csv);
    }

    public function test_the_list_is_not_for_a_role_that_does_not_hold_it(): void
    {
        $this->actingAs($this->admin(Role::MARKETPLACE_MANAGER))
            ->get(route('admin.customers'))
            ->assertForbidden();
    }

    // --- the order page -----------------------------------------------------

    public function test_the_order_page_invites_an_account_and_shows_the_parcel(): void
    {
        $customer = Customer::create(['name' => 'سارا', 'phone' => '09121110000', 'is_active' => true]);
        $order = $this->order($customer, Order::SHIPPED, '123456789012');

        $this->withSession(["order.{$order->number}" => true])
            ->get("/orders/{$order->number}")
            ->assertOk()
            ->assertSee('ساخت حساب کاربری', false)
            ->assertSee('در حال آماده‌سازی', false)
            ->assertSee('<li class="is-now">ارسال شد</li>', false)
            ->assertSee('<b>تیپاکس</b>', false)
            ->assertSee('123456789012', false);
    }

    /** The account's own link to an order opens it — it used to answer 403. */
    public function test_an_account_holder_is_not_invited_to_make_one(): void
    {
        $customer = Customer::create(['name' => 'سارا', 'phone' => '09121110000', 'password' => 'گل', 'is_active' => true]);
        $order = $this->order($customer, Order::PAID);

        $this->actingAs($customer, 'customer')
            ->get("/orders/{$order->number}")
            ->assertOk()
            ->assertDontSee('ساخت حساب کاربری', false)
            ->assertSee('<li class="is-now">در حال آماده‌سازی</li>', false);
    }

    public function test_somebody_elses_order_still_needs_the_number(): void
    {
        $owner = Customer::create(['name' => 'سارا', 'phone' => '09121110000', 'is_active' => true]);
        $other = Customer::create(['name' => 'مریم', 'phone' => '09121119999', 'password' => 'گل', 'is_active' => true]);
        $order = $this->order($owner, Order::PAID);

        $this->actingAs($other, 'customer')
            ->get("/orders/{$order->number}")
            ->assertForbidden();
    }
}
