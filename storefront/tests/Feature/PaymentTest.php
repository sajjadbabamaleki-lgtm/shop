<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Checkout\SettleOrder;
use App\Support\Payments\AtTheDoor;
use App\Support\Payments\Gateway;
use App\Support\Payments\PaymentFailed;
use App\Support\Payments\ZarinPal;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Paying with a card — §6.
 *
 * These are the tests that matter most in the repository, because everything
 * they cover fails silently and fails with somebody's money:
 *
 *   - the amount sent to the gateway, and the unit it is sent in;
 *   - what counts as proof that a payment happened;
 *   - what happens when the callback arrives twice.
 *
 * The gateway is faked at the HTTP boundary rather than mocked as an
 * interface, so the request body ZarinPal would actually receive is the thing
 * being asserted. A mock would let a wrong `currency` pass for ever.
 */
class PaymentTest extends TestCase
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
        config()->set('services.payment.zarinpal.sandbox', false);
    }

    /** An order worth paying for. 1,000,000 Rial = 100,000 Toman. */
    private function order(int $total = 1_000_000): Order
    {
        $customer = Customer::create(['phone' => '09121110000', 'is_active' => true]);

        return app(TenantContext::class)->forBranch($this->branch, fn () => Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'number' => 'VP-'.mt_rand(100000, 999999),
            'status' => Order::PLACED,
            'payment_status' => 'unpaid',
            'subtotal' => $total, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => $total,
            'contact_name' => 'خریدار', 'contact_phone' => '09121110000',
            'address' => 'نشانی', 'placed_at' => now(),
        ]));
    }

    /** The session proof the order page and the pay button both ask for. */
    private function holding(Order $order): self
    {
        $this->withSession(["order.{$order->number}" => true]);

        return $this;
    }

    private function fakeZarinPal(array $request = [], array $verify = []): void
    {
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::response($request ?: [
                'data' => ['code' => 100, 'authority' => 'A00000000000000000000000000000123456'],
                'errors' => [],
            ]),
            '*/pg/v4/payment/verify.json' => Http::response($verify ?: [
                'data' => ['code' => 100, 'ref_id' => 987654321, 'card_pan' => '502229******5480'],
                'errors' => [],
            ]),
        ]);
    }

    // --- the amount --------------------------------------------------------

    /**
     * **The currency is named on every call.**
     *
     * ZarinPal takes Rial or Toman and decides by this field. Leaving it out
     * means trusting whatever the account is set to, and being wrong is a
     * charge ten times too big or too small that looks entirely normal in
     * every log on both sides. This application stores Rial, so it says IRR.
     */
    public function test_the_amount_goes_to_the_gateway_in_rial_with_the_currency_named(): void
    {
        $this->fakeZarinPal();

        $order = $this->order(1_000_000);

        $this->holding($order)->post("/orders/{$order->number}/pay")->assertRedirect();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'payment/request.json')) {
                return false;
            }

            return $request['amount'] === 1_000_000 && $request['currency'] === 'IRR';
        });
    }

    /**
     * **The amount is the order's, not the form's.**
     *
     * Nothing about money is read from the request — the same rule the
     * checkout already holds. A posted amount must not reach the gateway.
     */
    public function test_an_amount_in_the_request_is_ignored(): void
    {
        $this->fakeZarinPal();

        $order = $this->order(1_000_000);

        $this->holding($order)->post("/orders/{$order->number}/pay", ['amount' => 1000]);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'request.json')
            || $request['amount'] === 1_000_000);
    }

    /** And the verify call sends the same number back, from the row. */
    public function test_the_verification_sends_the_orders_own_amount(): void
    {
        $this->fakeZarinPal();

        $order = $this->order(2_500_000);
        $this->holding($order)->post("/orders/{$order->number}/pay");

        $authority = Payment::sole()->authority;

        $this->get("/checkout/callback?Authority={$authority}&Status=OK")->assertRedirect();

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'verify.json')
            || ($request['amount'] === 2_500_000 && $request['currency'] === 'IRR'));
    }

    // --- what counts as proof ----------------------------------------------

    /**
     * **`Status=OK` is not proof.** It arrives in a URL the customer's browser
     * carried. If the gateway says the payment is not verified, the order does
     * not move — however cheerful the query string.
     */
    public function test_a_cheerful_query_string_does_not_pay_for_an_order(): void
    {
        $this->fakeZarinPal(verify: [
            'data' => [],
            'errors' => ['code' => -51, 'message' => 'Session is not valid'],
        ]);

        $order = $this->order();
        $this->holding($order)->post("/orders/{$order->number}/pay");

        $authority = Payment::sole()->authority;

        $this->get("/checkout/callback?Authority={$authority}&Status=OK")
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(Order::PLACED, $order->fresh()->status);
        $this->assertSame(Payment::FAILED, Payment::sole()->status);
    }

    /** A customer who pressed cancel is not asked about, and is not charged. */
    public function test_coming_back_without_paying_cancels_the_attempt(): void
    {
        $this->fakeZarinPal();

        $order = $this->order();
        $this->holding($order)->post("/orders/{$order->number}/pay");

        $authority = Payment::sole()->authority;

        $this->get("/checkout/callback?Authority={$authority}&Status=NOK")
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame(Payment::CANCELLED, Payment::sole()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // And it did not even ask: there is nothing to verify, and asking
        // produces an error message about a session that was never opened.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'verify.json'));
    }

    /** A verified payment settles the order and records the receipt. */
    public function test_a_verified_payment_settles_the_order(): void
    {
        $this->fakeZarinPal();

        $order = $this->order();
        $this->holding($order)->post("/orders/{$order->number}/pay");

        $authority = Payment::sole()->authority;

        $this->get("/checkout/callback?Authority={$authority}&Status=OK")
            ->assertRedirect()
            ->assertSessionHas('status');

        $order->refresh();
        $payment = Payment::sole();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(Order::PAID, $order->status);
        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertSame('987654321', $payment->ref_id);
        $this->assertSame('502229******5480', $payment->card_pan);
        $this->assertNotNull($payment->paid_at);
    }

    // --- arriving twice ----------------------------------------------------

    /**
     * **The callback is idempotent, and it has to be.**
     *
     * Gateways retry and people press back. `SettleOrder::paid()` moves stock,
     * so running it twice sells the same shoes twice — and the second run
     * would also overwrite the receipt. The second callback finds a paid row
     * and does nothing.
     */
    public function test_a_second_callback_does_not_settle_the_order_twice(): void
    {
        $this->fakeZarinPal();

        $order = $this->order();
        $this->holding($order)->post("/orders/{$order->number}/pay");

        $authority = Payment::sole()->authority;

        $this->get("/checkout/callback?Authority={$authority}&Status=OK")->assertRedirect();

        $paidAt = Payment::sole()->paid_at;

        $this->travel(1)->minutes();

        $this->get("/checkout/callback?Authority={$authority}&Status=OK")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::count());
        $this->assertEquals($paidAt, Payment::sole()->paid_at, 'the receipt was rewritten by the second callback');
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    /**
     * ZarinPal's «101» means «already verified», which is what its own retry
     * looks like. Reading it as a failure would mark a paid order failed.
     */
    public function test_already_verified_is_a_success_not_a_failure(): void
    {
        $this->fakeZarinPal(verify: [
            'data' => ['code' => 101, 'ref_id' => 555111, 'card_pan' => null],
            'errors' => [],
        ]);

        $order = $this->order();
        $this->holding($order)->post("/orders/{$order->number}/pay");

        $authority = Payment::sole()->authority;

        $this->get("/checkout/callback?Authority={$authority}&Status=OK")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    // --- what must not work ------------------------------------------------

    /** An authority nobody issued finds nothing and settles nothing. */
    public function test_an_unknown_authority_pays_for_nothing(): void
    {
        $this->fakeZarinPal();

        $order = $this->order();

        $this->get('/checkout/callback?Authority=A00000000000000000000000000000999999&Status=OK')
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(0, Payment::count());
    }

    /** A paid order cannot be sent to the gateway again. */
    public function test_an_order_already_paid_is_not_opened_a_second_time(): void
    {
        $this->fakeZarinPal();

        $order = $this->order();

        app(TenantContext::class)->forBranch($this->branch, fn () => app(SettleOrder::class)->paid($order));

        $this->holding($order)->post("/orders/{$order->number}/pay")->assertRedirect();

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    /** And a cancelled one cannot be paid for at all. */
    public function test_a_cancelled_order_cannot_be_paid_for(): void
    {
        $this->fakeZarinPal();

        $order = $this->order();

        app(TenantContext::class)->forBranch($this->branch, fn () => app(SettleOrder::class)->cancelled($order));

        $this->holding($order)->post("/orders/{$order->number}/pay")
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Payment::count());
    }

    /** Somebody who cannot open the order cannot start a payment for it. */
    public function test_a_stranger_cannot_start_a_payment(): void
    {
        $this->fakeZarinPal();

        $order = $this->order();

        // No session proof at all.
        $this->post("/orders/{$order->number}/pay")->assertForbidden();

        $this->assertSame(0, Payment::count());
    }

    // --- how it is configured ----------------------------------------------

    /**
     * **A named gateway with no merchant id is refused, loudly.**
     *
     * A checkout that offers a card payment and cannot take one is worse than
     * one that does not offer it — and the failure would otherwise be a
     * ZarinPal error page after the customer has committed.
     */
    public function test_zarinpal_without_a_merchant_id_refuses_rather_than_half_working(): void
    {
        config()->set('services.payment.zarinpal.merchant_id', '');

        $this->app->forgetInstance(Gateway::class);

        $this->expectException(\RuntimeException::class);

        app(Gateway::class);
    }

    /**
     * The provider must **not** refuse to boot on the default driver, the way
     * the SMS provider refuses to boot on `log`. A shop with no gateway
     * configured yet can still be browsed and ordered from while somebody sets
     * two variables; refusing to boot takes the whole site down over a setting
     * that is merely incomplete.
     */
    public function test_the_default_driver_does_not_refuse_to_boot_in_production(): void
    {
        config()->set('services.payment.driver', 'at-the-door');
        $this->app->forgetInstance(Gateway::class);

        $this->assertInstanceOf(AtTheDoor::class, app(Gateway::class));

        // In production too: the site staying up is worth more than the
        // configuration being complete.
        $this->app['env'] = 'production';
        $this->app->forgetInstance(Gateway::class);

        $this->assertInstanceOf(AtTheDoor::class, app(Gateway::class));
    }

    /** Asking it to take a card fails rather than quietly doing nothing. */
    public function test_the_door_gateway_refuses_to_start_a_card_payment(): void
    {
        $this->expectException(PaymentFailed::class);

        (new AtTheDoor)->start(new Payment, 'https://example.test/callback');
    }

    /** A driver nothing implements is named in the error. */
    public function test_an_unknown_driver_is_refused_by_name(): void
    {
        config()->set('services.payment.driver', 'saman');
        $this->app->forgetInstance(Gateway::class);

        $this->expectExceptionMessageMatches('/saman/');

        app(Gateway::class);
    }

    /** The sandbox is a different host, and only when asked for. */
    public function test_the_sandbox_is_a_different_host(): void
    {
        Http::fake(['*' => Http::response(['data' => ['code' => 100, 'authority' => 'A1'], 'errors' => []])]);

        $order = $this->order();
        $payment = Payment::create([
            'order_id' => $order->id, 'gateway' => 'zarinpal',
            'amount' => $order->grand_total, 'status' => Payment::PENDING,
        ]);

        $url = (new ZarinPal(str_repeat('a', 36), sandbox: true))
            ->start($payment, 'https://vikyplus.ir/checkout/callback');

        $this->assertStringStartsWith('https://sandbox.zarinpal.com/pg/StartPay/', $url);
    }

    // --- the page ----------------------------------------------------------

    /**
     * With a gateway the order page offers to pay; without one it says so.
     *
     * The second sentence used to be «پرداخت هنگام تحویل انجام می‌شود», which
     * is no longer an arrangement this shop has — the client took paying at
     * the door off the site. A card-only shop with no gateway configured is
     * broken, not offering something else, and the page has to read that way
     * or a customer waits at home for a courier nobody sent.
     */
    public function test_the_order_page_offers_payment_only_when_a_gateway_can_take_it(): void
    {
        $order = $this->order();

        $this->holding($order)->get("/orders/{$order->number}")
            ->assertOk()
            ->assertSee('پرداخت')
            ->assertDontSee('پرداخت اینترنتی همین حالا در دسترس نیست');

        config()->set('services.payment.driver', 'at-the-door');
        $this->app->forgetInstance(Gateway::class);

        $this->holding($order)->get("/orders/{$order->number}")
            ->assertOk()
            ->assertSee('پرداخت اینترنتی همین حالا در دسترس نیست');
    }

    // --- the address they come back to -------------------------------------

    /**
     * **The callback returns to the domain the customer was on.**
     *
     * The shop answers to both `vikyplus.ir` and `www.vikyplus.ir`, and the
     * address sent to ZarinPal is built from the request that started the
     * payment rather than from a fixed setting — so somebody who shopped on
     * the `www` one comes back to the `www` one. Two consequences, both worth
     * a test rather than a memory: **both domains have to be approved on the
     * gateway** (an unapproved one is refused at `request.json`, for half the
     * customers, which is the half nobody notices), and both have to be in
     * `CENTRAL_HOSTS` or that domain 404s long before anybody reaches paying.
     *
     * If this ever has to become one fixed address, it is `URL::forceRootUrl`
     * at boot plus a redirect from the other domain — not a literal here.
     */
    public function test_the_callback_comes_back_to_the_domain_the_customer_shopped_on(): void
    {
        $order = $this->order();

        // One order, two attempts — which is the shape a customer who comes
        // back and tries the other address makes. Each attempt gets its own
        // authority because the column is unique: that uniqueness is the
        // idempotency key for the whole flow, so a test may not fake it away.
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::sequence()
                ->push(['data' => ['code' => 100, 'authority' => 'A00000000000000000000000000000000001'], 'errors' => []])
                ->push(['data' => ['code' => 100, 'authority' => 'A00000000000000000000000000000000002'], 'errors' => []]),
        ]);

        foreach (['vikyplus.ir', 'www.vikyplus.ir'] as $host) {
            $this->holding($order)->post("http://{$host}/orders/{$order->number}/pay")
                ->assertRedirect();

            Http::assertSent(function ($request) use ($host) {
                if (! str_contains($request->url(), 'payment/request.json')) {
                    return false;
                }

                return $request['callback_url'] === "http://{$host}/checkout/callback";
            });
        }
    }

    // --- what the order says about itself ----------------------------------

    /**
     * The order records the arrangement it was placed under.
     *
     * `payment_method` was written flat as `cash_on_delivery` while that was
     * the only way money arrived. Left that way, a shop with a gateway would
     * hand whoever packs the shoes an order that says «پرداخت در محل» on money
     * the customer has already paid by card — and the courier would ask for it
     * again.
     */
    public function test_an_order_placed_under_a_card_gateway_says_online(): void
    {
        $this->post('/cart', ['variant' => $this->aVariant()->id]);
        $this->post('/checkout', ['name' => 'سجاد', 'phone' => '09123456789', 'address' => 'خیابان ولیعصر، پلاک ۱']);

        $this->assertSame('online', Order::latest('id')->firstOrFail()->payment_method);
    }

    /** And a shop without one still says the courier takes it. */
    public function test_an_order_placed_without_a_gateway_still_says_at_the_door(): void
    {
        config()->set('services.payment.driver', 'at-the-door');
        $this->app->forgetInstance(Gateway::class);

        $this->post('/cart', ['variant' => $this->aVariant()->id]);
        $this->post('/checkout', ['name' => 'سجاد', 'phone' => '09123456789', 'address' => 'خیابان ولیعصر، پلاک ۱']);

        $this->assertSame('cash_on_delivery', Order::latest('id')->firstOrFail()->payment_method);
    }

    /**
     * The column accepts the second method the panel has been offering.
     *
     * `Order::methodLabels()` has listed «پرداخت اینترنتی» since the payments
     * work landed, the admin order screen prints those labels as the options
     * of a select, and the controller validates against the same list — but
     * the CHECK constraint on the column allowed one value, so choosing the
     * option the screen offered was a 500. Asserted at the database rather
     * than through the panel so it fails for the reason it is about.
     */
    public function test_an_order_may_be_marked_paid_online(): void
    {
        $order = $this->order();

        $order->forceFill(['payment_method' => 'online'])->save();

        $this->assertSame('online', $order->fresh()->payment_method);
        $this->assertSame('پرداخت اینترنتی', $order->fresh()->methodLabel());
    }

    /** Something the shop sells, to put an order behind. */
    private function aVariant(): Variant
    {
        return Product::where('slug', 'nike-v2k-run')->firstOrFail()->defaultVariant;
    }
}
