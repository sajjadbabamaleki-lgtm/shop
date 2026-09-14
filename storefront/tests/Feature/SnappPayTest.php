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
use Illuminate\Support\Facades\Http;
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
            'min_amount' => null,
            'max_amount' => null,
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
    private function fakeSnappPay(array $open = [], array $verify = [], array $settle = []): void
    {
        Http::fake([
            '*/api/online/v1/oauth/token' => Http::response(['access_token' => 'bearer-1', 'expires_in' => 3600]),
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

    /** Instalments are the only method this shop asks for. */
    public function test_it_asks_for_instalments(): void
    {
        $this->fakeSnappPay();

        $this->payFor($this->order());

        $this->assertSame('INSTALLMENT', $this->opening()['paymentMethodTypeDto']);
    }

    // --- coming back -------------------------------------------------------

    /**
     * **This side chooses the key, and it is in the path of the return
     * address.**
     *
     * ZarinPal mints an authority and puts it in the callback; SnappPay hands
     * back a token the browser never carries, so a customer returning would be
     * unidentifiable unless the address they return to says who they are. The
     * key is written before the provider is asked, it is what `authority`
     * holds, and it is unguessable for the same reason ZarinPal's is: it
     * stands in for a session that may have been lost on the way.
     */
    public function test_the_return_address_carries_the_key_the_attempt_is_found_by(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->assertNotEmpty($payment->authority);
        $this->assertSame('PT-000111', $payment->gateway_token, "SnappPay's own handle is kept apart");
        $this->assertSame($payment->authority, $this->opening()['transactionId']);
        $this->assertStringEndsWith(
            "/checkout/callback/snapppay/{$payment->authority}",
            $this->opening()['returnURL']
        );
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
            'http://localhost/shiraz/checkout/callback/snapppay/vpkey',
            route('branch.payment.callback', ['branch' => 'shiraz', 'gateway' => 'snapppay', 'key' => 'vpkey'])
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

        $this->get("/checkout/callback/snapppay/{$payment->authority}")
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

        $this->get("/checkout/callback/snapppay/{$payment->authority}")
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

    /** A refused verify pays for nothing, whatever the customer came back on. */
    public function test_a_refused_verification_pays_for_nothing(): void
    {
        $this->fakeSnappPay(verify: [
            'successful' => false,
            'errorData' => ['errorCode' => '2018', 'message' => 'اعتبار کافی نیست.'],
        ]);

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->get("/checkout/callback/snapppay/{$payment->authority}")
            ->assertRedirect()
            // The provider's own sentence, which is the one that says what to
            // do about it. Nothing written here could know it.
            ->assertSessionHasErrors(['payment' => 'اعتبار کافی نیست.']);

        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Nothing was settled on the strength of a return that carried no
        // claim at all: with this provider the answer is always asked for.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'payment/v1/settle'));
    }

    /** Gateways retry and people press back; the second one does nothing. */
    public function test_a_second_callback_does_not_settle_the_order_twice(): void
    {
        $this->fakeSnappPay();

        $order = $this->order();
        $payment = $this->payFor($order);

        $this->get("/checkout/callback/snapppay/{$payment->authority}")->assertRedirect();

        $paidAt = Payment::sole()->paid_at;
        $this->travel(1)->minutes();

        $this->get("/checkout/callback/snapppay/{$payment->authority}")
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

        $this->get('/checkout/callback/snapppay/vpnothinglikethisone')
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Payment::count());
    }

    /** A gateway this shop does not have is a made-up URL, not a message. */
    public function test_a_gateway_this_shop_does_not_have_is_a_404(): void
    {
        $order = $this->order();

        $this->holding($order)->post("/orders/{$order->number}/pay/digipay")->assertNotFound();
        $this->get('/checkout/callback/digipay/whatever')->assertNotFound();
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
     * The token is kept for its hour.
     *
     * A fresh token per payment adds a round trip at the slowest moment in the
     * shop — the one where a customer is waiting to be sent away — on a machine
     * already measured at thirteen times slower than this one.
     */
    public function test_the_token_is_not_fetched_again_for_every_payment(): void
    {
        $this->fakeSnappPay();

        $this->payFor($this->order());
        $this->payFor($this->order());

        $minted = 0;

        Http::assertSent(function ($request) use (&$minted): bool {
            if (str_contains($request->url(), 'oauth/token')) {
                $minted++;
            }

            return true;
        });

        $this->assertSame(1, $minted, 'one token, reused');
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

    // --- the range ---------------------------------------------------------

    /**
     * **A button certain to be refused is worse than no button.**
     *
     * SnappPay lends between a floor and a ceiling agreed with the shop. The
     * range is read from the environment rather than asked over the network,
     * because the question is asked while an order page renders and the live
     * machine is slow enough that a round trip there would be felt on every
     * load.
     */
    public function test_an_order_outside_the_lending_range_is_not_offered_instalments(): void
    {
        config()->set('services.payment.snapppay.max_amount', 1_000_000);

        $order = $this->order();

        $this->holding($order)->get("/orders/{$order->number}")
            ->assertOk()
            ->assertSee('پرداخت')
            ->assertDontSee('خرید اقساطی با اسنپ‌پی');

        // And the post is refused as well as the button hidden: the page is a
        // render, the post is what actually charges.
        $this->holding($order)->post("/orders/{$order->number}/pay/snapppay")
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Payment::count());
    }

    /** Inside it, the order page offers both ways to pay. */
    public function test_the_order_page_offers_the_card_first_and_the_instalments_after(): void
    {
        $order = $this->order();

        $page = $this->holding($order)->get("/orders/{$order->number}")->assertOk();

        $page->assertSee('خرید اقساطی با اسنپ‌پی');
        $page->assertSee("/orders/{$order->number}/pay/zarinpal", escape: false);
        $page->assertSee("/orders/{$order->number}/pay/snapppay", escape: false);

        $content = (string) $page->getContent();

        $this->assertLessThan(
            strpos($content, "/orders/{$order->number}/pay/snapppay"),
            strpos($content, "/orders/{$order->number}/pay/zarinpal"),
            'the card is the ordinary way to pay and comes first'
        );
    }
}
