<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Support\Payments\Gateway;
use App\Support\Payments\Gateways;
use App\Support\Payments\Lender;
use App\Support\Payments\SnappPay;
use App\Support\Payments\TorobPay;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * ترب‌پی — the second lender, beside اسنپ‌پی and زرین‌پال.
 *
 * Faked at the HTTP boundary, like `SnappPayTest`, because what is asserted is
 * the request ترب‌پی would really receive. Each test here is one line of their
 * document («مستندات درگاه خرید اعتباری (CPG) ترب‌پی», v1.3.31) that differs
 * from SnappPay's, since those differences are the places a copy of the
 * SnappPay driver would have been silently wrong:
 *
 *   - the token call is JSON `{username, password}` behind HTTP Basic;
 *   - opening names `paymentMethodTypeDto: ONLINE_CREDIT` and the checkout's
 *     address, postal code, city, province, name and registration phone;
 *   - ids are strings and `commissionType` is 0;
 *   - a refusal is `error.{code, message, user_message}`;
 *   - a discounted order may not be updated (1016);
 *   - a cancelled order's status reads REVERT, not CANCEL.
 *
 * And the one that matters more than all of those: **with two lenders, the
 * panel must talk to the one that was paid.**
 */
class TorobPayTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();

        config()->set('services.payment.driver', 'zarinpal');
        config()->set('services.payment.zarinpal.merchant_id', str_repeat('a', 36));
        config()->set('services.payment.instalments', 'snapppay,torobpay');
        config()->set('services.payment.snapppay', [
            'base_url' => 'https://api.snapppay.test',
            'client_id' => 'snapp-id',
            'client_secret' => 'snapp-secret',
            'username' => 'snapp-merchant',
            'password' => 'snapp-password',
            'commission_type' => 100,
        ]);
        config()->set('services.payment.torobpay', [
            'base_url' => 'https://cpg.torobpay.test',
            'client_id' => 'torob-id',
            'client_secret' => 'torob-secret',
            'username' => 'torob-merchant',
            'password' => 'torob-password',
            'commission_type' => 0,
        ]);

        $this->app->forgetInstance(Gateway::class);
    }

    private function order(int $discount = 0, string $status = Order::PLACED): Order
    {
        $customer = Customer::firstOrCreate(['phone' => '09121110000'], ['is_active' => true]);

        return app(TenantContext::class)->forBranch($this->branch, function () use ($customer, $discount, $status): Order {
            $variants = Variant::whereHas('product', fn ($q) => $q->whereNotNull('published_at'))->take(2)->get();

            // A placed order holds its units the way `PlaceOrder` leaves
            // them — reserved — so settling it sells out of the reservation
            // rather than below nothing. A paid one has already sold them.
            foreach ($variants as $i => $variant) {
                BranchInventory::updateOrCreate(
                    ['branch_id' => $this->branch->id, 'variant_id' => $variant->id],
                    ['stock_on_hand' => 5, 'stock_reserved' => $status === Order::PLACED ? [2, 1][$i] : 0]
                );
            }

            $order = Order::create([
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => $status,
                'payment_status' => $status === Order::PAID ? 'paid' : 'unpaid',
                'payment_method' => 'online',
                'subtotal' => 2_000_000,
                'discount_total' => $discount,
                'shipping_total' => 100_000,
                'grand_total' => 2_000_000 - $discount + 100_000,
                'contact_name' => 'سارا محمدی',
                'contact_phone' => '09121110000',
                'province' => 'تهران',
                'city' => 'تهران',
                'address' => 'خیابان ولیعصر، پلاک ۱۲',
                'postal_code' => '۱۲۳۴۵۶۷۸۹۰',
                'placed_at' => now(),
                'paid_at' => $status === Order::PAID ? now() : null,
            ]);

            $order->items()->create([
                'variant_id' => $variants[0]->id,
                'product_title' => 'کتانی الف', 'sku' => 'A-1', 'size_value' => '38',
                'unit_price' => 600_000, 'quantity' => 2, 'line_total' => 1_200_000,
            ]);

            $order->items()->create([
                'variant_id' => $variants[1]->id,
                'product_title' => 'کتانی ب', 'sku' => 'B-1', 'size_value' => '39',
                'unit_price' => 800_000, 'quantity' => 1, 'line_total' => 800_000,
            ]);

            return $order->fresh('items');
        });
    }

    private function paidThroughTorob(Order $order): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'gateway' => 'torobpay',
            'authority' => 'T000000042',
            'gateway_token' => 'TP-abc123xyz',
            'amount' => $order->grand_total,
            'status' => Payment::PAID,
            'ref_id' => 'txn-001',
            'paid_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'torob@vikyplus.test'],
            ['name' => 'مدیر', 'password' => 'secret'],
        );

        if ($user->roles()->count() === 0) {
            $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());
        }

        return $user;
    }

    /** Every ترب‌پی path answering as their document's examples do. */
    private function fakeTorob(array $open = [], array $verify = [], array $settle = [], string $state = 'VERIFY', array $eligible = []): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'cpg.torobpay.test/api/online/v1/oauth/token' => Http::response(['access_token' => 'jwt-1']),
            'cpg.torobpay.test/api/online/offer/v1/eligible*' => Http::response($eligible ?: [
                'successful' => true,
                'response' => [
                    'eligible' => true,
                    'title_message' => 'پرداخت اقساطی با ترب پی',
                    'description' => 'دریافت اعتبار و خرید در ۴ قسط',
                ],
            ]),
            'cpg.torobpay.test/api/online/payment/v1/status*' => Http::response([
                'successful' => true,
                'response' => ['status' => $state, 'amount' => 2_100_000, 'transactionId' => 'txn-001'],
            ]),
            'cpg.torobpay.test/api/online/payment/v1/token' => Http::response($open ?: [
                'successful' => true,
                'response' => [
                    'paymentToken' => 'abc123xyz',
                    'paymentPageUrl' => 'https://cpg.torobpay.test/payment/brief-details?payment_token=abc123xyz',
                ],
            ]),
            'cpg.torobpay.test/api/online/payment/v1/verify' => Http::response($verify ?: [
                'successful' => true, 'response' => ['transactionId' => 'txn-001'],
            ]),
            'cpg.torobpay.test/api/online/payment/v1/settle' => Http::response($settle ?: [
                'successful' => true, 'response' => ['transactionId' => 'txn-001'],
            ]),
            'cpg.torobpay.test/api/online/payment/v1/update' => Http::response([
                'successful' => true, 'response' => ['transactionId' => 'txn-001'],
            ]),
            'cpg.torobpay.test/api/online/payment/v1/cancel' => Http::response([
                'successful' => true, 'response' => ['transactionId' => 'txn-001'],
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function sentTo(string $path): array
    {
        $body = [];

        Http::assertSent(function ($request) use (&$body, $path): bool {
            if (! str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', $path)) {
                return false;
            }

            $body = $request->data();

            return true;
        });

        return $body;
    }

    private function pay(Order $order): TestResponse
    {
        return $this->withSession(["order.{$order->number}" => true])
            ->post("/orders/{$order->number}/pay/torobpay");
    }

    // --- configuration ------------------------------------------------------

    public function test_both_lenders_are_offered_after_the_card_in_the_order_named(): void
    {
        $names = array_map(fn (Gateway $g): string => $g->name(), app(Gateways::class)->all());

        $this->assertSame(['zarinpal', 'snapppay', 'torobpay'], $names);
        $this->assertInstanceOf(SnappPay::class, app(Gateways::class)->named('snapppay'));
        $this->assertInstanceOf(TorobPay::class, app(Gateways::class)->named('torobpay'));
    }

    public function test_torobpay_alone_is_a_valid_shop(): void
    {
        config()->set('services.payment.instalments', 'torobpay');

        $names = array_map(fn (Gateway $g): string => $g->name(), app(Gateways::class)->all());

        $this->assertSame(['zarinpal', 'torobpay'], $names);
    }

    public function test_a_missing_credential_refuses_to_boot_and_names_it(): void
    {
        config()->set('services.payment.torobpay.password', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TOROBPAY_PASSWORD');

        app(Gateways::class);
    }

    // --- the token ------------------------------------------------------------

    /** «Authorization: Basic base64(client_id:client_secret)» and a JSON body. */
    public function test_the_token_is_asked_for_with_basic_auth_and_a_json_body(): void
    {
        $this->fakeTorob();

        $this->pay($this->order())->assertRedirect();

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/api/online/v1/oauth/token')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Basic '.base64_encode('torob-id:torob-secret'))
                && $request->isJson()
                && $request->data() === ['username' => 'torob-merchant', 'password' => 'torob-password'];
        });
    }

    // --- opening a payment ---------------------------------------------------

    public function test_opening_sends_what_the_document_requires(): void
    {
        $this->fakeTorob();

        $order = $this->order(discount: 200_000);

        $this->pay($order)->assertRedirect('https://cpg.torobpay.test/payment/brief-details?payment_token=abc123xyz');

        $payment = Payment::where('gateway', 'torobpay')->latest('id')->sole();
        $body = $this->sentTo('/api/online/payment/v1/token');
        $cart = $body['cartList'][0];

        // The handle: the shop chooses it, ترب‌پی's own token is kept apart.
        $this->assertSame('T'.str_pad((string) $payment->id, 9, '0', STR_PAD_LEFT), $body['transactionId']);
        $this->assertSame($body['transactionId'], $payment->authority);
        $this->assertSame('abc123xyz', $payment->gateway_token);
        $this->assertStringEndsWith('/checkout/callback/torobpay', $body['returnURL']);

        $this->assertSame('ONLINE_CREDIT', $body['paymentMethodTypeDto']);

        // Rial, and the arithmetic SnappPay also checks.
        $this->assertSame(1_900_000, $body['amount']);
        $this->assertSame(200_000, $body['discountAmount']);
        $this->assertSame(0, $body['externalSourceAmount']);
        $this->assertSame(2_100_000, $cart['totalAmount']);
        $this->assertSame($cart['totalAmount'] - $body['discountAmount'], $body['amount']);

        // Strings where the document says string, 0 where it says «مقدار مجاز: 0».
        $this->assertSame((string) $order->id, $cart['cartId']);
        $this->assertCount(2, $cart['cartItems']);
        $this->assertIsString($cart['cartItems'][0]['id']);
        $this->assertSame(600_000, $cart['cartItems'][0]['amount'], 'unit price');
        $this->assertSame(2, $cart['cartItems'][0]['count']);
        $this->assertSame(0, $cart['cartItems'][0]['commissionType']);

        // The checkout, as the shopper typed it.
        $this->assertSame('خیابان ولیعصر، پلاک ۱۲', $body['address']);
        $this->assertSame('1234567890', $body['postalCode']);
        $this->assertSame('تهران', $body['city']);
        $this->assertSame('تهران', $body['province']);
        $this->assertSame('سارا محمدی', $body['customer_full_name']);
        $this->assertSame('09121110000', $body['registration_phone_number']);
        $this->assertSame('09121110000', $body['mobile']);
    }

    /** `user_message` is theirs for the shopper; the code goes on the row. */
    public function test_a_refusal_shows_their_user_message_and_records_the_code(): void
    {
        $this->fakeTorob(open: [
            'successful' => false,
            'error' => ['code' => 1003, 'message' => 'invalid input', 'data' => null, 'user_message' => 'اطلاعات ارسالی معتبر نیست'],
        ]);

        $order = $this->order();

        $this->pay($order)->assertSessionHasErrors();

        $payment = Payment::where('gateway', 'torobpay')->latest('id')->sole();

        $this->assertSame(Payment::FAILED, $payment->status);
        $this->assertSame('torobpay 1003 invalid input', $payment->failure);
        $this->assertStringContainsString(
            'اطلاعات ارسالی معتبر نیست',
            collect(session('errors')?->all() ?? [])->implode(' ')
        );
    }

    // --- coming back ---------------------------------------------------------

    public function test_an_ok_return_is_verified_and_settled_before_the_order_is_paid(): void
    {
        $this->fakeTorob();

        $order = $this->order();
        $this->pay($order);
        $payment = Payment::where('gateway', 'torobpay')->latest('id')->sole();

        $this->post('/checkout/callback/torobpay', [
            'transactionId' => $payment->authority,
            'state' => 'OK',
            'amount' => $payment->amount,
        ])->assertRedirect();

        $this->assertSame(Payment::PAID, $payment->fresh()->status);
        $this->assertSame('txn-001', $payment->fresh()->ref_id);
        $this->assertSame(['paymentToken' => 'abc123xyz'], $this->sentTo('/api/online/payment/v1/verify'));
        $this->assertSame(['paymentToken' => 'abc123xyz'], $this->sentTo('/api/online/payment/v1/settle'));
        $this->assertTrue($order->fresh()->isInstalment());
    }

    public function test_a_failed_return_is_never_verified(): void
    {
        $this->fakeTorob();

        $order = $this->order();
        $this->pay($order);
        $payment = Payment::where('gateway', 'torobpay')->latest('id')->sole();

        $this->post('/checkout/callback/torobpay', [
            'transactionId' => $payment->authority,
            'state' => 'FAILED',
            'amount' => $payment->amount,
        ])->assertRedirect();

        $this->assertNotSame(Payment::PAID, $payment->fresh()->status);
        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/payment/v1/verify'));
    }

    /** A verify refused for state, with status already VERIFY, still settles. */
    public function test_a_lost_verify_is_recovered_through_status(): void
    {
        $this->fakeTorob(
            verify: ['successful' => false, 'error' => ['code' => 1053, 'message' => 'invalid order state', 'data' => null, 'user_message' => '']],
            state: 'VERIFY',
        );

        $order = $this->order();
        $this->pay($order);
        $payment = Payment::where('gateway', 'torobpay')->latest('id')->sole();

        $this->post('/checkout/callback/torobpay', [
            'transactionId' => $payment->authority, 'state' => 'OK', 'amount' => $payment->amount,
        ]);

        $this->assertSame(Payment::PAID, $payment->fresh()->status);
    }

    // --- the button ----------------------------------------------------------

    public function test_the_eligibility_endpoint_asks_torobpay_and_prints_its_words(): void
    {
        $this->fakeTorob();

        $order = $this->order();

        $this->withSession(["order.{$order->number}" => true])
            ->getJson("/orders/{$order->number}/instalments/torobpay")
            ->assertOk()
            ->assertExactJson([
                'eligible' => true,
                'title' => 'پرداخت اقساطی با ترب پی',
                'description' => 'دریافت اعتبار و خرید در ۴ قسط',
            ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'cpg.torobpay.test/api/online/offer/v1/eligible')
            && $request['amount'] === 2_100_000);
    }

    public function test_the_order_page_draws_a_hidden_torobpay_button_that_asks_its_own_lender(): void
    {
        $this->fakeTorob();

        $order = $this->order();

        $html = $this->withSession(["order.{$order->number}" => true])
            ->get("/orders/{$order->number}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("/orders/{$order->number}/instalments/torobpay", $html);
        $this->assertStringContainsString("/orders/{$order->number}/pay/torobpay", $html);
        // One copy of the asking script however many lenders are on the page.
        $this->assertSame(1, substr_count($html, "querySelectorAll('form[data-vp-instalments]')"));
    }

    // --- the panel talks to the lender that was paid ------------------------

    public function test_a_partial_return_on_a_torobpay_order_updates_torobpay_not_snapppay(): void
    {
        $this->fakeTorob(state: 'SETTLE');

        $order = $this->order(status: Order::PAID);
        $this->paidThroughTorob($order);
        $line = $order->items->first();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$line->id => 1],
                'reason' => 'سایز بزرگ بود',
                'confirmed' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $body = $this->sentTo('/api/online/payment/v1/update');

        $this->assertSame('TP-abc123xyz', $body['paymentToken']);
        $this->assertSame('ONLINE_CREDIT', $body['paymentMethodTypeDto']);
        $this->assertSame(1_500_000, $body['amount'], '2,000,000 − 600,000 + 100,000 delivery');
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'snapppay'));
    }

    /** «سفارش‌های دارای کد تخفیف اجازه ویرایش ندارند» — refused before the shelf moves. */
    public function test_a_discounted_torobpay_order_refuses_a_partial_return_before_anything_moves(): void
    {
        $this->fakeTorob(state: 'SETTLE');

        $order = $this->order(discount: 200_000, status: Order::PAID);
        $this->paidThroughTorob($order);
        $line = $order->items->first();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$line->id => 1],
                'reason' => 'سایز بزرگ بود',
                'confirmed' => 1,
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, (int) $line->fresh()->returned_quantity);
        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/payment/v1/update'));
    }

    public function test_a_cancel_reaches_torobpay_and_reads_revert_as_already_done(): void
    {
        $this->fakeTorob(state: 'SETTLE');

        $order = $this->order(status: Order::PAID);
        $payment = $this->paidThroughTorob($order);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/instalments/cancel", ['reason' => 'مشتری منصرف شد', 'confirmed' => 1])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(['paymentToken' => 'TP-abc123xyz'], $this->sentTo('/api/online/payment/v1/cancel'));
        $this->assertNotNull($payment->fresh()->gateway_cancelled_at);
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }

    public function test_revert_is_what_an_already_cancelled_torobpay_order_reads(): void
    {
        $this->fakeTorob(state: 'REVERT');

        $lender = app(Gateways::class)->named('torobpay');
        $this->assertInstanceOf(Lender::class, $lender);

        $payment = $this->paidThroughTorob($this->order(status: Order::PAID));

        $this->assertTrue($lender->alreadyCancelled($payment));
    }
}
