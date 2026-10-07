<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Support\Payments\Gateway;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What an order puts on paper, and what the panel shows about what was bought.
 *
 * «یک خروجی آدرس … یک طرف ادرس خودمون، یک طرفم ادرس مشتری» and «یک خروجی
 * فاکتور … ک کالا چی بوده، مبلغ چقد بوده، قسطی یا نقدی بوده، اگ قسطیه تاریخ
 * سر رسید قسطاش چ زمانیه و پیش پرداخت چقد داده». Plus the photograph on each
 * order line — «تا بدونیم کدوم محصولو سفارش داده» — and the rule that a
 * discount code is for paying in cash only.
 */
class OrderPaperworkTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
        $this->branch = Branch::central();
    }

    private function admin(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'paper@vikyplus.test'],
            ['name' => 'مدیر', 'password' => 'secret'],
        );

        if ($user->roles()->count() === 0) {
            $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());
        }

        return $user;
    }

    private function order(int $discount = 0, ?string $paidThrough = null): Order
    {
        $customer = Customer::firstOrCreate(['phone' => '09121110000'], ['is_active' => true]);

        return app(TenantContext::class)->forBranch($this->branch, function () use ($customer, $discount, $paidThrough): Order {
            $variant = Variant::whereHas('product.media')->firstOrFail();

            $order = Order::create([
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => $paidThrough ? Order::PAID : Order::PLACED,
                'payment_status' => $paidThrough ? 'paid' : 'unpaid',
                'subtotal' => 1_200_000,
                'discount_total' => $discount,
                'shipping_total' => 100_000,
                'grand_total' => 1_200_000 - $discount + 100_000,
                'contact_name' => 'سارا رضایی',
                'contact_phone' => '09121110000',
                'province' => 'اصفهان',
                'city' => 'کاشان',
                'address' => 'خیابان امیرکبیر، کوچه ۱۲',
                'postal_code' => '8715913456',
                'placed_at' => now(),
                'paid_at' => $paidThrough ? now() : null,
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

            if ($paidThrough) {
                Payment::create([
                    'order_id' => $order->id,
                    'gateway' => $paidThrough,
                    'authority' => 'V000000077',
                    'amount' => $order->grand_total,
                    'status' => Payment::PAID,
                    'ref_id' => 'REF-555',
                    'paid_at' => now(),
                ]);
            }

            return $order;
        });
    }

    // --- the order screen ----------------------------------------------

    public function test_each_order_line_shows_the_photograph_of_what_was_bought(): void
    {
        $order = $this->order();
        $item = $order->items()->first();
        $path = $item->photoPath();

        $this->assertNotNull($path);

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee(asset($path), false)
            ->assertSee(route('admin.order.invoice', $order), false)
            ->assertSee(route('admin.order.label', $order), false);
    }

    /**
     * **The photograph opens the shoe on the shop, not in the panel.**
     *
     * «روش میزنیم بره رو همون محصول تو فروشگاه» — what the person packing an
     * order wants is the listing the customer saw: every photograph, the
     * sizes, the published price. The panel's own edit screen is still reached
     * from the title beside it, so the change adds a destination rather than
     * moving one, and both are asserted here because a view that quietly loses
     * one of them looks identical.
     */
    public function test_the_line_opens_the_shoe_on_the_shop_and_the_title_opens_it_in_the_panel(): void
    {
        $order = $this->order();
        $shoe = $order->items()->first()->variant->product;

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee(storefront_route('product', $shoe), false)
            ->assertSee(route('admin.product.edit', $shoe->id), false);
    }

    public function test_a_line_whose_size_was_deleted_still_renders(): void
    {
        $order = $this->order();
        $order->items()->update(['variant_id' => null]);

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('بدون عکس')
            // And it links nowhere rather than at a product that is gone:
            // `variant_id` is nullOnDelete(), so the line keeps its own title
            // and price and has nothing left to point at.
            ->assertDontSee('در فروشگاه', false);
    }

    // --- the label -----------------------------------------------------

    public function test_the_label_carries_both_addresses(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}/label")
            ->assertOk()
            ->assertSeeInOrder(['فرستنده', 'ویکی پلاس', 'گیرنده', 'سارا رضایی', 'کاشان', 'خیابان امیرکبیر، کوچه ۱۲', '8715913456', '09121110000']);
    }

    public function test_the_label_uses_the_shops_address_when_the_branch_has_none(): void
    {
        $this->branch->update(['address' => null]);
        $order = $this->order();

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}/label")
            ->assertOk()
            ->assertSee(config('storefront.contact.address'));
    }

    // --- the invoice ---------------------------------------------------

    public function test_a_cash_invoice_says_so_and_carries_the_money(): void
    {
        $order = $this->order(paidThrough: 'zarinpal');

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}/invoice")
            ->assertOk()
            ->assertSee('کتونی آزمایشی')
            ->assertSee('نقدی')
            ->assertDontSee('برنامهٔ اقساط این خرید')
            ->assertSee(toman(1_300_000))
            ->assertSee('زرین‌پال (کارت بانکی)')
            ->assertSee('REF-555');
    }

    public function test_an_instalment_invoice_without_a_plan_says_the_plan_is_missing(): void
    {
        $order = $this->order(paidThrough: 'snapppay');

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}/invoice")
            ->assertOk()
            ->assertSee('اقساطی')
            ->assertSee('برنامهٔ اقساط این خرید هنوز در پنل ثبت نشده');
    }

    public function test_the_plan_is_written_on_the_order_and_printed_on_the_invoice(): void
    {
        $order = $this->order(paidThrough: 'snapppay');

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/plan", [
                'down_payment' => '۳۲۵,۰۰۰',
                'instalments' => [
                    ['due' => '2026-11-25', 'amount' => '325000'],
                    ['due' => '2026-10-25', 'amount' => '۳۲۵٬۰۰۰'],
                    ['due' => '', 'amount' => ''],
                ],
            ])
            ->assertRedirect("/admin/orders/{$order->number}")
            ->assertSessionHas('status');

        $plan = $order->fresh()->instalmentPlan();

        // Rial, and in date order whatever order they were typed in.
        $this->assertSame(3_250_000, $plan['down_payment']);
        $this->assertSame(['2026-10-25', '2026-11-25'], array_column($plan['instalments'], 'due'));
        $this->assertSame([3_250_000, 3_250_000], array_column($plan['instalments'], 'amount'));

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}/invoice")
            ->assertOk()
            ->assertSee('پیش‌پرداخت')
            ->assertSee(toman(3_250_000))
            ->assertSee(fa_date(new \DateTimeImmutable('2026-10-25')))
            ->assertSee(fa_date(new \DateTimeImmutable('2026-11-25')));
    }

    public function test_half_an_instalment_is_refused_and_writes_nothing(): void
    {
        $order = $this->order(paidThrough: 'snapppay');

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/plan", [
                'instalments' => [['due' => '2026-10-25', 'amount' => '']],
            ])
            ->assertSessionHasErrors('instalments');

        $this->assertNull($order->fresh()->instalment_plan);
    }

    public function test_emptying_every_box_takes_the_plan_back(): void
    {
        $order = $this->order();
        $order->forceFill(['instalment_plan' => ['down_payment' => 10_000, 'instalments' => []]])->save();

        $this->assertTrue($order->fresh()->isInstalment());

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/plan", ['down_payment' => '', 'instalments' => []])
            ->assertRedirect();

        $this->assertNull($order->fresh()->instalment_plan);
        $this->assertFalse($order->fresh()->isInstalment());
    }

    // --- a discount code is for paying in cash -------------------------

    private function withBothGateways(): void
    {
        config()->set('services.payment.driver', 'zarinpal');
        config()->set('services.payment.zarinpal.merchant_id', str_repeat('a', 36));
        config()->set('services.payment.instalments', 'snapppay');
        config()->set('services.payment.snapppay', [
            'base_url' => 'https://api.snapppay.test',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'username' => 'merchant',
            'password' => 'merchant-password',
            'commission_type' => 100,
        ]);

        $this->app->forgetInstance(Gateway::class);
    }

    /**
     * **A discount code does not take instalments off an order.** It used to
     * («کد تخفیف فقط برای خرید نقدی باشه»), and SnappPay's review refused that
     * on 2026-10-07 as treating cash and instalment buyers differently.
     */
    public function test_an_order_with_a_discount_is_offered_instalments_too(): void
    {
        $this->withBothGateways();
        $order = $this->order(discount: 100_000);

        $this->withSession(["order.{$order->number}" => true])
            ->get("/orders/{$order->number}")
            ->assertOk()
            ->assertSee("/orders/{$order->number}/pay/zarinpal", false)
            ->assertSee("/orders/{$order->number}/pay/snapppay", false)
            ->assertDontSee('کد تخفیف فقط برای پرداخت نقدی است');
    }

    public function test_an_order_without_a_discount_still_is(): void
    {
        $this->withBothGateways();
        $order = $this->order();

        $this->withSession(["order.{$order->number}" => true])
            ->get("/orders/{$order->number}")
            ->assertOk()
            ->assertSee("/orders/{$order->number}/pay/snapppay", false);
    }

    public function test_the_eligibility_check_asks_the_lender_for_a_discounted_order(): void
    {
        $this->withBothGateways();
        Http::fake([
            '*/api/online/v1/oauth/token' => Http::response(['access_token' => 'bearer-1', 'expires_in' => 3600]),
            '*/api/online/offer/v1/eligible*' => Http::response([
                'successful' => true,
                'response' => ['eligible' => true, 'title_message' => 'پرداخت اقساطی', 'description' => '۴ قسط'],
            ]),
        ]);
        $order = $this->order(discount: 100_000);

        $this->withSession(["order.{$order->number}" => true])
            ->getJson("/orders/{$order->number}/instalments")
            ->assertOk()
            ->assertJson(['eligible' => true]);

        // Asked with what the shopper actually owes, the discount already off.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'offer/v1/eligible')
            && str_contains($request->url(), 'amount='.$order->grand_total));
    }

    // --- which gateway the shopper went to ------------------------------

    /**
     * «مشخص باشه که مشتری من از طریق چه درگاهی … می‌خواسته سفارشش رو پرداخت
     * کنه … اگر … تا مرحله پرداخت رفت ولی پرداختش نکرد».
     */
    private function attempt(Order $order, string $gateway, string $status, ?string $failure = null, int $minutesAgo = 0): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => $gateway,
            'authority' => 'V'.mt_rand(100000000, 999999999),
            'amount' => $order->grand_total,
            'status' => $status,
            'failure' => $failure,
        ]);

        if ($minutesAgo > 0) {
            $payment->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();
        }

        return $payment;
    }

    public function test_the_order_screen_names_each_gateway_and_what_came_of_it(): void
    {
        $order = $this->order();
        $this->attempt($order, 'snapppay', Payment::FAILED, 'snapppay 1003 Access Denied');
        $this->attempt($order, 'zarinpal', Payment::CANCELLED);
        $this->attempt($order, 'zarinpal', Payment::PENDING, minutesAgo: 60);

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('اسنپ‌پی (اقساطی)')
            ->assertSee('درگاه نپذیرفت یا تأیید نشد')
            ->assertSee('snapppay 1003 Access Denied')
            ->assertSee('زرین‌پال (کارت بانکی)')
            ->assertSee('مشتری در درگاه انصراف داد')
            ->assertSee('به درگاه رفت و برنگشت');
    }

    public function test_the_list_says_where_an_unpaid_order_went_and_filters_by_it(): void
    {
        $toLender = $this->order();
        $this->attempt($toLender, 'snapppay', Payment::FAILED, 'snapppay 1003 Access Denied');

        $toCard = $this->order();
        $this->attempt($toCard, 'zarinpal', Payment::CANCELLED);

        $this->actingAs($this->admin())
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('اسنپ‌پی (اقساطی) — درگاه نپذیرفت یا تأیید نشد')
            ->assertSee('زرین‌پال (کارت بانکی) — مشتری در درگاه انصراف داد');

        $this->actingAs($this->admin())
            ->get('/admin/orders?gateway=snapppay')
            ->assertOk()
            ->assertSee($toLender->number)
            ->assertDontSee($toCard->number);
    }

    public function test_the_export_carries_the_gateway_and_its_answer(): void
    {
        $order = $this->order();
        $this->attempt($order, 'snapppay', Payment::FAILED, 'snapppay 1003 Access Denied');

        $csv = $this->actingAs($this->admin())->get('/admin/orders/export')->streamedContent();

        $this->assertStringContainsString('درگاه', $csv);
        $this->assertStringContainsString('snapppay 1003 Access Denied', $csv);
    }
}
