<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Payments\Gateway;
use App\Support\Payments\Gateways;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Paying in instalments — اسنپ‌پی beside زرین‌پال.
 *
 * The gateway is faked at the HTTP boundary rather than mocked as an
 * interface, for the same reason as `PaymentTest`: what is being asserted is
 * the request SnappPay would really receive. A mock would let a Toman figure,
 * a basket that does not add up, or a missing settle call pass for ever.
 *
 * Four things here fail silently and fail with somebody's money:
 *
 *   - **the basket's arithmetic**, because this provider lends against goods
 *     and checks that the parts sum to the amount;
 *   - **the unit**, because there is no currency field to get wrong — a Toman
 *     figure would simply be a bill one tenth the size, accepted by everybody;
 *   - **settle**, because a payment verified and never settled is reverted and
 *     the shop is paid nothing, while the order would read «paid»;
 *   - **the key the customer comes back on**, which this side chooses.
 */
class SnappPayTest extends TestCase
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
        config()->set('services.payment.instalments', 'snapppay');
        config()->set('services.payment.snapppay', [
            'base_url' => 'https://api.snapppay.test',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'username' => 'merchant',
            'password' => 'merchant-password',
            'commission_type' => 3,
        ]);

        $this->app->forgetInstance(Gateway::class);
    }

    /**
     * An order with a line on it, because SnappPay refuses a basket-less
     * purchase — it is lending against the goods.
     *
     * Two of one shoe on purpose: unit price and line total are the same
     * number for a single item, so an order of one could never catch the
     * `amount`/`count` pair being read the wrong way round.
     */
    private function order(int $unit = 600_000, int $quantity = 2, int $shipping = 100_000, int $discount = 50_000): Order
    {
        // firstOrCreate rather than create: one test pays twice, and the
        // phone number is unique because it is the shopper's credential.
        $customer = Customer::firstOrCreate(['phone' => '09121110000'], ['is_active' => true]);
        $subtotal = $unit * $quantity;

        return app(TenantContext::class)->forBranch($this->branch, function () use ($customer, $unit, $quantity, $subtotal, $shipping, $discount): Order {
            $order = Order::create([
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => Order::PLACED,
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_total' => $shipping,
                'grand_total' => $subtotal - $discount + $shipping,
                'contact_name' => 'خریدار',
                'contact_phone' => '09121110000',
                'address' => 'نشانی',
                'placed_at' => now(),
            ]);

            $order->items()->create([
                'product_title' => 'کتانی نایک',
                'sku' => 'NK-1',
                'unit_price' => $unit,
                'quantity' => $quantity,
                'line_total' => $unit * $quantity,
            ]);

            return $order;
        });
    }

    private function holding(Order $order): self
    {
        $this->withSession(["order.{$order->number}" => true]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $open
     * @param  array<string, mixed>  $verify
     * @param  array<string, mixed>  $settle
     */
    private function fakeSnappPay(array $open = [], array $verify = [], array $settle = [], array $status = []): void
    {
        Http::fake([
            '*/api/online/v1/oauth/token' => Http::response(['access_token' => 'bearer-1', 'expires_in' => 3600]),
            '*/api/online/payment/v1/status*' => Http::response($status ?: [
                'successful' => true,
                'response' => ['transactionId' => 'SNP-777', 'status' => 'VERIFY', 'amount' => 1_250_000],
            ]),
            '*/api/online/payment/v1/token' => Http::response($open ?: [
                'successful' => true,
                'response' => [
                    'paymentToken' => 'PT-000111',
                    'paymentPageUrl' => 'https://snapppay.test/pay/PT-000111',
                ],
            ]),
            '*/api/online/payment/v1/verify' => Http::response($verify ?: [
                'successful' => true,
                'response' => ['transactionId' => 'SNP-777'],
            ]),
            '*/api/online/payment/v1/settle' => Http::response($settle ?: [
                'successful' => true,
                'response' => ['transactionId' => 'SNP-777'],
            ]),
        ]);
    }

    /** The whole happy path, for the tests that only care where it ends. */
    private function payFor(Order $order): Payment
    {
        $this->holding($order)->post("/orders/{$order->number}/pay/snapppay")->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    /**
     * The return, as SnappPay makes it: a **POST** of a form carrying
     * `transactionId`, `state` and `amount`.
     *
     * Not a GET with a query string — that is ZarinPal's shape, and writing
     * this helper the other way round is how the difference would go unnoticed
     * until a real customer came back.
     */
    private function comeBackFrom(Payment $payment, string $state = 'OK'): TestResponse
    {
        return $this->post('/checkout/callback/snapppay', [
            'transactionId' => $payment->authority,
            'state' => $state,
            'amount' => $payment->amount,
        ]);
    }

    /** @return array<string, mixed> */
    private function opening(): array
    {
        $body = [];

        Http::assertSent(function ($request) use (&$body): bool {
            if (! str_contains($request->url(), 'payment/v1/token')) {
                return false;
            }

            $body = $request->data();

            return true;
        });

        return $body;
    }

    // --- the basket --------------------------------------------------------

    /**
     * **The parts of the basket have to sum to the amount being financed.**
     *
     * This application stores `grand_total = subtotal - discount + shipping`,
     * and SnappPay wants the basket *before* the discount with the discount
     * named beside it. Getting that wrong is a validation refusal with nothing
     * in it about which number was wrong.
     */
    public function test_the_basket_adds_up_to_the_amount_being_financed(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $this->payFor($order);

        $body = $this->opening();
        $cart = $body['cartList'][0];

        // 1,200,000 of shoes + 100,000 delivery − 50,000 off = 1,250,000.
        $this->assertSame(1_250_000, $body['amount']);
        $this->assertSame(50_000, $body['discountAmount']);
        $this->assertSame(0, $body['externalSourceAmount']);

        $this->assertSame(1_300_000, $cart['totalAmount']);
        $this->assertSame(100_000, $cart['shippingAmount']);
        $this->assertSame(0, $cart['taxAmount']);

        // **The flags say what is inside the item lines, not what is inside
        // the total.** SnappPay's own arithmetic is «count × item amount +
        // shipment (if not included) + tax (if not included) = totalAmount»,
        // so false is what asks for delivery to be added — and this shop's
        // unit prices carry none. Sending true here while also adding
        // `shippingAmount` claims both, and the two sides of their equation
        // then disagree by exactly the delivery charge.
        $this->assertFalse($cart['isShipmentIncluded'], 'delivery is not inside the item prices, so it is added');
        $this->assertFalse($cart['isTaxIncluded']);

        // Their second equation, verbatim: amount = Σ totalAmount − (discount
        // + external source).
        $this->assertSame(
            $cart['totalAmount'] - $body['discountAmount'] - $body['externalSourceAmount'],
            $body['amount'],
            'the basket, the discount and the financed amount must agree'
        );

        // And their first, for this one cart: count × unit + shipping + tax.
        $item = $cart['cartItems'][0];

        $this->assertSame(
            $item['count'] * $item['amount'] + $cart['shippingAmount'] + $cart['taxAmount'],
            $cart['totalAmount'],
            'the lines, the delivery and the cart total must agree'
        );
    }

    /**
     * **`amount` is the unit price and `count` is how many.**
     *
     * The two readings of that pair agree for every order of single items and
     * differ only where somebody bought two of one shoe — so a test with a
     * quantity of one could not see this, and the mistake would look correct
     * until the first customer bought a pair.
     */
    public function test_a_line_is_priced_per_unit_with_its_count_beside_it(): void
    {
        $this->fakeSnappPay();

        $this->payFor($this->order());

        $item = $this->opening()['cartList'][0]['cartItems'][0];

        $this->assertSame(600_000, $item['amount'], 'the unit price, not the line total');
        $this->assertSame(2, $item['count']);
        $this->assertSame('کتانی نایک', $item['name']);
        $this->assertSame(3, $item['commissionType'], 'the commission group the shop agreed with SnappPay');
    }

    /**
     * Rial, untouched.
     *
     * There is no currency field on this API, which means **nothing anywhere
     * would notice** a Toman figure: SnappPay would finance a tenth of the
     * price, the shop would be paid a tenth, and every log on both sides would
     * look normal.
     */
    public function test_the_amount_is_rial_and_is_never_converted(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $this->payFor($order);

        $this->assertSame((int) $order->grand_total, $this->opening()['amount']);
    }

    /** The number the credit belongs to, in the form SnappPay matches. */
    public function test_the_shoppers_number_goes_in_international_form(): void
    {
        $this->fakeSnappPay();

        $this->payFor($this->order());

        $this->assertSame('+989121110000', $this->opening()['mobile']);
    }

    /**
     * **No payment-method field is sent at all.**
     *
     * `paymentMethodTypeDto` was carried here from a public implementation and
     * appears nowhere in SnappPay's own document; what does appear is
     * `forcedPaymentMethodTypes`, which is optional, needs enabling per
     * merchant, and exists to *narrow* what the shopper may use. Sending
     * neither is what offers them every method their own account can pay with.
     */
    public function test_it_forces_no_payment_method_on_the_shopper(): void
    {
        $this->fakeSnappPay();

        $this->payFor($this->order());

        $body = $this->opening();

        $this->assertArrayNotHasKey('paymentMethodTypeDto', $body);
        $this->assertArrayNotHasKey('forcedPaymentMethodTypes', $body);
    }

    // --- coming back -------------------------------------------------------

    /**
     * **The transaction id is this shop's, and its shape is SnappPay's rule.**
     *
     * «تراکنش آیدی باید بین ۵ تا ۱۰ رقم باشد. (برای موارد ۱۰ رقم به بالا حتماً
     * از یک حرف در آن استفاده شود)» — so ten characters with a letter in them,
     * and unique per purchase. It is the payment row's own id, which makes the
     * uniqueness a fact rather than a hope, and it is what `authority` holds:
     * the column a returning customer is found by, whichever gateway they came
     * through.
     */
    public function test_the_transaction_id_has_the_shape_snapppay_requires(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->assertSame('V'.str_pad((string) $payment->id, 9, '0', STR_PAD_LEFT), $payment->authority);
        $this->assertSame(10, strlen((string) $payment->authority));
        $this->assertMatchesRegularExpression('/[A-Za-z]/', (string) $payment->authority, 'ten characters need a letter');
        $this->assertSame($payment->authority, $this->opening()['transactionId']);

        $this->assertSame('PT-000111', $payment->gateway_token, "SnappPay's own handle is kept apart");
    }

    /**
     * **The return address is one fixed path, because they POST to it.**
     *
     * An earlier shape put an unguessable key in the path, which this no
     * longer needs: the form SnappPay posts carries `transactionId` itself.
     * The address also has to sit on the domain registered with them —
     * «درخواست‌های شامل returnURL که دامنه آن با دامنه تعریف شده در سرویس
     * منطبق نباشند رد خواهند شد» — which is why it is built from the host the
     * shopper is on rather than from a constant.
     */
    public function test_the_return_address_is_the_registered_one(): void
    {
        $this->fakeSnappPay();

        $this->payFor($this->order());

        $this->assertSame('http://localhost/checkout/callback/snapppay', $this->opening()['returnURL']);
    }

    /** The address زرین‌پال was given has not moved. */
    public function test_the_card_gateways_callback_address_is_unchanged(): void
    {
        $this->assertSame('http://localhost/checkout/callback', route('payment.callback'));
    }

    /**
     * A franchise's shopper comes back to the franchise's own address.
     *
     * The route is declared inside the storefront closure, which is registered
     * twice — at the root and under `/{branch}` — so this has a branch version
     * at all. Declared outside it, a franchise's customer would return to the
     * main shop and land on the wrong shop's order page, which is the failure
     * the callback's own comment in `routes/web.php` has warned about since
     * زرین‌پال was wired.
     */
    public function test_a_franchise_comes_back_to_its_own_address(): void
    {
        $this->assertSame(
            'http://localhost/shiraz/checkout/callback/snapppay',
            route('branch.payment.callback', ['branch' => 'shiraz', 'gateway' => 'snapppay'])
        );

        // And the POST they actually make has a franchise version too — a
        // route registered outside the storefront closure would not.
        $this->assertSame(
            'http://localhost/shiraz/checkout/callback/snapppay',
            route('branch.payment.callback.post', ['branch' => 'shiraz', 'gateway' => 'snapppay'])
        );
    }

    /**
     * **Verified is not paid. Settled is.**
     *
     * SnappPay reverts a payment that is verified and never settled: the
     * shopper's credit unwinds and the shop is paid nothing. An order marked
     * paid on the strength of the first call alone would be shoes posted for
     * money that came back.
     */
    public function test_a_payment_that_will_not_settle_is_not_a_payment(): void
    {
        $this->fakeSnappPay(settle: [
            'successful' => false,
            'errorData' => ['errorCode' => 'SETTLE_FAILED', 'message' => 'تسویه انجام نشد.'],
        ]);

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->comeBackFrom($payment)
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(Payment::FAILED, Payment::sole()->status);
        $this->assertNull(Payment::sole()->paid_at);
    }

    /** And a settled one settles the order and keeps the reference. */
    public function test_a_settled_payment_settles_the_order(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->comeBackFrom($payment)
            ->assertRedirect()
            ->assertSessionHas('status');

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(Order::PAID, $order->status);
        $this->assertSame('SNP-777', Payment::sole()->ref_id);
        $this->assertNull(Payment::sole()->card_pan, 'there is no card in an instalment purchase');

        // Both calls, in that order, every time.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'payment/v1/verify'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'payment/v1/settle'));
    }

    /**
     * A refused verify pays for nothing — once `status` agrees.
     *
     * The refusal alone is not the end of it: the document's procedure is to
     * ask `status`, because a refusal and a lost answer are indistinguishable
     * from here and mean opposite things. Here the provider says the purchase
     * was cancelled, and only then is the order left unpaid.
     */
    public function test_a_refused_verification_pays_for_nothing(): void
    {
        $this->fakeSnappPay(
            verify: [
                'successful' => false,
                'errorData' => ['errorCode' => '2018', 'message' => 'اعتبار کافی نیست.'],
            ],
            status: ['successful' => true, 'response' => ['status' => 'CANCEL']],
        );

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->comeBackFrom($payment)
            ->assertRedirect()
            // The provider's own sentence, which is the one that says what to
            // do about it. Nothing written here could know it.
            ->assertSessionHasErrors(['payment' => 'اعتبار کافی نیست.']);

        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Nothing was settled on the strength of a return that carried no
        // claim at all: with this provider the answer is always asked for.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'payment/v1/settle'));
    }

    /**
     * **A lost answer is not a refusal, and `status` is what tells them
     * apart.**
     *
     * The document sets a thirty-second timeout on `verify` and says what to
     * do when it expires: ask `status`, and if it says VERIFY the call worked
     * and only the answer was lost. Reading that as a failure would leave a
     * paid shopper with an unpaid order and a credit they owe.
     */
    public function test_a_verify_whose_answer_is_lost_is_recovered_from_the_status(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $payment = $this->payFor($order);

        // The provider stops answering verify — a timeout, not a refusal.
        Http::fake([
            '*/api/online/v1/oauth/token' => Http::response(['access_token' => 'bearer-1', 'expires_in' => 3600]),
            '*/api/online/payment/v1/verify' => fn () => throw new ConnectionException('timed out'),
            '*/api/online/payment/v1/status*' => Http::response([
                'successful' => true,
                'response' => ['status' => 'VERIFY', 'transactionId' => 'SNP-777'],
            ]),
            '*/api/online/payment/v1/settle' => Http::response([
                'successful' => true,
                'response' => ['transactionId' => 'SNP-777'],
            ]),
        ]);

        $this->comeBackFrom($payment)->assertRedirect()->assertSessionHas('status');

        $this->assertSame('paid', $order->fresh()->payment_status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'payment/v1/status'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'payment/v1/settle'));
    }

    /**
     * **And a settle whose answer is lost is recovered the same way.**
     *
     * This is the expensive direction: a payment verified and not settled is
     * reverted, so a settle that answers false while the money has in fact
     * moved would leave the shop believing it was never paid. `status` saying
     * SETTLE is the answer, and it is a success.
     */
    public function test_a_settle_that_already_happened_is_read_off_the_status(): void
    {
        $this->fakeSnappPay(
            settle: ['successful' => false, 'errorData' => ['errorCode' => '500', 'message' => 'خطا']],
            status: ['successful' => true, 'response' => ['status' => 'SETTLE', 'transactionId' => 'SNP-777']],
        );

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->comeBackFrom($payment)->assertRedirect()->assertSessionHas('status');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(Payment::PAID, Payment::sole()->status);
    }

    /**
     * `state=FAILED` is the purchase that did not happen, and nothing is asked.
     *
     * Asking `verify` about it produces a refusal that reads like a fault on
     * this side. The state is still never *evidence* of payment in the other
     * direction: an OK goes straight to `verify()`, which decides.
     */
    public function test_a_failed_state_asks_the_provider_nothing(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->comeBackFrom($payment, state: 'FAILED')
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame(Payment::CANCELLED, Payment::sole()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'payment/v1/verify'));
    }

    /** Gateways retry and people press back; the second one does nothing. */
    public function test_a_second_callback_does_not_settle_the_order_twice(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->comeBackFrom($payment)->assertRedirect();

        $paidAt = Payment::sole()->paid_at;
        $this->travel(1)->minutes();

        $this->comeBackFrom($payment)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::count());
        $this->assertEquals($paidAt, Payment::sole()->paid_at, 'the receipt was rewritten by the second callback');
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    /** A key nobody issued finds nothing. */
    public function test_an_unknown_key_pays_for_nothing(): void
    {
        $this->fakeSnappPay();

        $this->post('/checkout/callback/snapppay', ['transactionId' => 'V000999999', 'state' => 'OK'])
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Payment::count());
    }

    /** A gateway this shop does not have is a made-up URL, not a message. */
    public function test_a_gateway_this_shop_does_not_have_is_a_404(): void
    {
        $order = $this->order();

        $this->holding($order)->post("/orders/{$order->number}/pay/digipay")->assertNotFound();
        $this->post('/checkout/callback/digipay', ['transactionId' => 'V000000001'])->assertNotFound();
    }

    // --- the credentials ---------------------------------------------------

    /**
     * **Two pairs with two jobs**, and the commonest way this is wrong is that
     * they have been swapped. The client id and secret identify the
     * integration and are HTTP Basic on the token call; the username and
     * password are the shop's merchant account and go in that call's form.
     */
    public function test_the_two_pairs_of_credentials_go_to_their_own_places(): void
    {
        $this->fakeSnappPay();

        $this->payFor($this->order());

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'oauth/token')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret'))
                && $request['grant_type'] === 'password'
                && $request['scope'] === 'online-merchant'
                && $request['username'] === 'merchant'
                && $request['password'] === 'merchant-password';
        });

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'payment/v1/token')
                && $request->hasHeader('Authorization', 'Bearer bearer-1');
        });
    }

    /**
     * **A token per call, and it is deliberately not cached.**
     *
     * The first version of this driver cached it for its hour, which is the
     * obvious thing to do and costs one fewer round trip at the slowest moment
     * in the shop. SnappPay asks for the opposite in as many words — «لازم است
     * که access token در حافظه کش نشود و منقضی شود، تا برای ادامه فرآیند خطای
     * دسترسی دریافت نکنید» — and a held token turning stale is a failure this
     * shop cannot see from the outside.
     */
    public function test_a_token_is_minted_for_every_call_and_never_cached(): void
    {
        $this->fakeSnappPay();

        $this->payFor($this->order());
        $this->payFor($this->order());

        $minted = 0;
        $opened = 0;

        Http::assertSent(function ($request) use (&$minted, &$opened): bool {
            if (str_contains($request->url(), 'oauth/token')) {
                $minted++;
            }

            if (str_contains($request->url(), 'payment/v1/token')) {
                $opened++;
            }

            return true;
        });

        $this->assertSame(2, $opened);
        $this->assertSame($opened, $minted, 'one token minted for each call, none reused');
    }

    /**
     * Named and not configured is refused, loudly, by name.
     *
     * An instalment button on every order page that refuses every shopper who
     * presses it is worse than no button, and three credentials out of four
     * look exactly like four until somebody tries to pay.
     */
    public function test_snapppay_without_its_credentials_refuses_rather_than_half_working(): void
    {
        config()->set('services.payment.snapppay.client_secret', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SNAPPPAY_CLIENT_SECRET');

        app(Gateways::class)->named('snapppay');
    }

    /** And a provider nothing implements is refused by name too. */
    public function test_an_unknown_instalment_provider_is_refused_by_name(): void
    {
        config()->set('services.payment.instalments', 'digipay');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PAYMENT_INSTALMENTS is «digipay»');

        app(Gateways::class);
    }

    // --- whether it is offered at all --------------------------------------

    /**
     * **The range is SnappPay's and it is asked for, never guessed.**
     *
     * This shop used to answer from two environment variables, to keep a
     * network call out of a page render on a machine thirteen times slower
     * than this one. Their document forbids it — the range is dynamic, differs
     * between staging and production, and «از هر گونه پیاده‌سازی دستی در سمت
     * خود خودداری فرمایید». So the page asks, and the two lines it prints are
     * their words rather than any written here.
     */
    public function test_the_page_asks_snapppay_whether_this_order_can_be_financed(): void
    {
        Http::fake([
            '*/api/online/v1/oauth/token' => Http::response(['access_token' => 'bearer-1', 'expires_in' => 3600]),
            '*/api/online/offer/v1/eligible*' => Http::response([
                'successful' => true,
                'response' => [
                    'eligible' => true,
                    'title_message' => 'پرداخت اقساطی اسنپ‌پی',
                    'description' => '۴ قسط ماهیانه ۳۱۲٬۵۰۰ تومان (بدون کارمزد)',
                ],
            ]),
        ]);

        $order = $this->order();

        $this->holding($order)->getJson("/orders/{$order->number}/instalments")
            ->assertOk()
            ->assertExactJson([
                'eligible' => true,
                'title' => 'پرداخت اقساطی اسنپ‌پی',
                'description' => '۴ قسط ماهیانه ۳۱۲٬۵۰۰ تومان (بدون کارمزد)',
            ]);

        // Asked about this order's own total, in Rial.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'offer/v1/eligible')
            && str_contains($request->url(), 'amount=1250000'));
    }

    /** A refusal is a button that never appears. */
    public function test_a_refused_amount_is_not_offered_instalments(): void
    {
        Http::fake([
            '*/api/online/v1/oauth/token' => Http::response(['access_token' => 'bearer-1', 'expires_in' => 3600]),
            '*/api/online/offer/v1/eligible*' => Http::response([
                'successful' => true,
                'response' => ['eligible' => false],
            ]),
        ]);

        $order = $this->order();

        $this->holding($order)->getJson("/orders/{$order->number}/instalments")
            ->assertOk()
            ->assertJson(['eligible' => false]);
    }

    /**
     * And so is a provider that cannot be reached.
     *
     * The safe direction: a button that goes nowhere is worse than no button,
     * and the card beside it is untouched either way.
     */
    public function test_a_silent_provider_offers_nothing(): void
    {
        Http::fake([
            '*/api/online/v1/oauth/token' => fn () => throw new ConnectionException('timed out'),
        ]);

        $order = $this->order();

        $this->holding($order)->getJson("/orders/{$order->number}/instalments")
            ->assertOk()
            ->assertJson(['eligible' => false]);
    }

    /** The amount is what is being asked about, so it is not asked by strangers. */
    public function test_a_stranger_cannot_ask_what_an_order_is_worth(): void
    {
        $order = $this->order();

        $this->getJson("/orders/{$order->number}/instalments")->assertForbidden();
    }

    /**
     * The page ships the card button drawn and the instalment one hidden.
     *
     * Hidden rather than absent, because the script that reveals it has to
     * have something to reveal — and absent rather than visible, because
     * whether SnappPay will finance this basket is not known when the page is
     * built. A shopper whose browser never asks sees the card button, which is
     * the failure this can afford.
     */
    public function test_the_card_button_is_drawn_and_the_instalment_one_waits(): void
    {
        $order = $this->order();

        $page = $this->holding($order)->get("/orders/{$order->number}")->assertOk();

        $page->assertSee("/orders/{$order->number}/pay/zarinpal", escape: false);
        $page->assertSee("/orders/{$order->number}/pay/snapppay", escape: false);
        $page->assertSee("/orders/{$order->number}/instalments", escape: false);

        $content = (string) $page->getContent();

        $this->assertLessThan(
            strpos($content, "/orders/{$order->number}/pay/snapppay"),
            strpos($content, "/orders/{$order->number}/pay/zarinpal"),
            'the card is the ordinary way to pay and comes first'
        );

        // The instalment form is in the page and not on it.
        $this->assertMatchesRegularExpression('/<form[^>]*vp-snapp-form[^>]*hidden/', $content);

        // And nothing on it says what the instalments cost: those words are
        // SnappPay's, they arrive with the eligible answer, and a sentence
        // written here would be the thing their document forbids.
        $page->assertDontSee('قسط ماهیانه');
    }

    /** Their logo, at both of the sizes they supply. */
    public function test_the_component_carries_their_own_mark(): void
    {
        $order = $this->order();

        $page = $this->holding($order)->get("/orders/{$order->number}")->assertOk();

        $page->assertSee('assets/img/snapppay/logo-40.svg', escape: false);
        $page->assertSee('assets/img/snapppay/logo-32.svg', escape: false);

        foreach (['logo-40.svg', 'logo-32.svg'] as $mark) {
            $this->assertFileExists(public_path('assets/img/snapppay/'.$mark));
        }
    }
}
