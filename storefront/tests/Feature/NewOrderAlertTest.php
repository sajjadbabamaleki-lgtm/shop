<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Checkout\SettleOrder;
use App\Support\Sms\Sender;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * A new order puts a text message on the shop's phone.
 *
 * «نمیشه وقتی یک سفارش ثبت میشه به واتسپ پیام بیاد؟» — asked after a session
 * that could not answer «سفارش جدید ثبت شده؟» at all, because the panel is the
 * only place an order shows up and somebody has to go and look.
 *
 * Most of what is worth holding here is about **when it stays quiet**, because
 * every one of those cases is invisible from the outside:
 *
 *   - an order paid by card is announced when the money lands, not when the
 *     customer is sent to the gateway, or the phone buzzes for every abandoned
 *     basket and the first shoe packed against one is worse than the noise;
 *   - one order is one message, however many times it changes hands after;
 *   - an order whose transaction rolled back was never an order;
 *   - the demo command makes eight of them and must ring nobody;
 *   - and none of it may ever break a checkout, because a customer who paid
 *     and then saw a 500 has money gone and no order page to show for it.
 */
class NewOrderAlertTest extends TestCase
{
    use RefreshDatabase;

    private Branch $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->central = Branch::central();

        app(TenantContext::class)->set($this->central);

        config(['services.sms.alert_to' => '09121161311']);

        // The shop this repository ships has no card gateway configured, which
        // is what `at-the-door` means now that paying at the door is off the
        // site — so this is the «nothing is ever going to arrive on its own»
        // half of the rule, and the tests that want the other half say so.
        config(['services.payment.driver' => 'at-the-door']);
    }

    /** @return object{messages: list<array{phone: string, message: string, args: list<string>, purpose: string}>} */
    private function catchSms(): object
    {
        $box = new class implements Sender
        {
            /** @var list<array{phone: string, message: string, args: list<string>, purpose: string}> */
            public array $messages = [];

            /** @param  list<string>  $args */
            public function send(string $phone, string $message, array $args = [], string $purpose = self::CODE): void
            {
                $this->messages[] = ['phone' => $phone, 'message' => $message, 'args' => $args, 'purpose' => $purpose];
            }
        };

        $this->app->instance(Sender::class, $box);

        return $box;
    }

    /** The shop with a card gateway in front of it. */
    private function withCardGateway(): void
    {
        config(['services.payment.driver' => 'zarinpal']);
        config(['services.payment.zarinpal.merchant_id' => str_repeat('a', 36)]);
    }

    private function variant(string $slug): Variant
    {
        return Product::where('slug', $slug)->firstOrFail()->defaultVariant;
    }

    /**
     * One pair of shoes, bought the way a customer buys them.
     *
     * Through the real basket and the real checkout, because the whole feature
     * hangs off `PlaceOrder` and a test that called it directly would not
     * notice the day the controller stopped reaching it.
     */
    private function buy(string $slug = 'nike-v2k-run', string $at = ''): Order
    {
        $this->post($at.'/cart', ['variant' => $this->variant($slug)->id, 'quantity' => 1]);

        $this->post($at.'/checkout', [
            'name' => 'سجاد',
            'phone' => '09123456789',
            'address' => 'خیابان ولیعصر، پلاک ۱',
            'shipping_method_id' => $this->shipping($at === '' ? $this->central : Branch::where('slug', 'shiraz')->sole())->id,
        ]);

        return Order::acrossAllBranches()->latest('id')->firstOrFail();
    }

    private function shipping(Branch $branch): ShippingMethod
    {
        return app(TenantContext::class)->forBranch(
            $branch,
            fn () => ShippingMethod::where('is_active', true)->orderBy('id')->firstOrFail()
        );
    }

    // --- the two moments --------------------------------------------------

    /**
     * With no gateway in front of the shop, being placed is the only moment
     * this order will ever have, so that is the one that speaks.
     */
    public function test_an_order_the_gateway_will_never_settle_is_announced_when_it_is_placed(): void
    {
        $box = $this->catchSms();

        $this->buy();

        $this->assertCount(1, $box->messages);
        $this->assertSame('09121161311', $box->messages[0]['phone']);
        $this->assertSame(Sender::ORDER, $box->messages[0]['purpose']);
    }

    /** Everything somebody needs to decide whether to get up. */
    public function test_the_message_carries_the_shop_the_number_the_money_and_the_buyer(): void
    {
        $box = $this->catchSms();

        $order = $this->buy();
        $message = $box->messages[0]['message'];

        $this->assertStringContainsString('سفارش جدید', $message);
        $this->assertStringContainsString('ویکی پلاس', $message);
        $this->assertStringContainsString($order->number, $message);
        $this->assertStringContainsString(toman($order->grand_total), $message);
        $this->assertStringContainsString('سجاد', $message);

        // The same five facts as values, in the order a registered pattern
        // would expect them — a door that sends a pattern never sees the
        // sentence, and digging the number back out of the Persian is the
        // mistake the Sender contract exists to prevent.
        $this->assertCount(5, $box->messages[0]['args']);
        $this->assertSame($order->number, $box->messages[0]['args'][1]);
    }

    /**
     * **The one that matters.** A card order is written before it is paid for,
     * and a good half of them are never paid for at all.
     */
    public function test_a_card_order_says_nothing_until_the_money_arrives(): void
    {
        $this->withCardGateway();

        $box = $this->catchSms();

        $order = $this->buy();

        $this->assertSame('online', $order->payment_method);
        $this->assertSame([], $box->messages, 'A checkout that has only reached the gateway is not a sale.');

        app(TenantContext::class)->forBranch($this->central, fn () => app(SettleOrder::class)->paid($order));

        $this->assertCount(1, $box->messages);
        $this->assertStringContainsString($order->number, $box->messages[0]['message']);
    }

    /**
     * One order is one message.
     *
     * This one is placed with no gateway — so it is announced straight away —
     * and then marked paid by hand in the panel, which is the other event. The
     * rule that keeps it to one message is that each order's `payment_method`
     * chooses exactly one of the two moments, so there is no marker to write
     * and nothing to expire.
     */
    public function test_an_order_is_announced_once_and_only_once(): void
    {
        $box = $this->catchSms();

        $order = $this->buy();

        $this->assertCount(1, $box->messages);

        app(TenantContext::class)->forBranch($this->central, fn () => app(SettleOrder::class)->paid($order));

        $this->assertCount(1, $box->messages, 'Being paid for is not a second order.');
    }

    /**
     * An order whose transaction rolled back was never an order.
     *
     * `PlaceOrder` runs over locked inventory rows and can still fail on the
     * last line — a CHECK constraint, a deadlock. The events are
     * `ShouldDispatchAfterCommit` for exactly this: a text message about a sale
     * that did not happen cannot be taken back, and nothing would explain it.
     */
    public function test_an_order_that_rolls_back_is_never_announced(): void
    {
        $box = $this->catchSms();

        try {
            DB::transaction(function (): void {
                $this->buy();

                throw new RuntimeException('the last line would not reserve');
            });
        } catch (RuntimeException) {
            // The point is what is *not* on the phone.
        }

        $this->assertSame([], $box->messages);
    }

    /** A franchise's order says which shop it is, because the owner has several. */
    public function test_a_franchise_order_names_its_own_shop(): void
    {
        app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $box = $this->catchSms();

        $this->buy('nike-v2k-run', '/shiraz');

        $this->assertStringContainsString('ویکی پلاس شیراز', $box->messages[0]['message']);
    }

    // --- the ways it must stay quiet, and the ways it must not break -------

    /** Eight pretend orders are not eight sales. */
    public function test_the_demo_orders_ring_nobodys_phone(): void
    {
        app(TenantContext::class)->forBranch($this->central, function (): void {
            BranchInventory::query()->update(['stock_on_hand' => 40]);
        });

        $box = $this->catchSms();

        $this->artisan('demo:orders')->assertSuccessful();

        $this->assertGreaterThan(0, Order::acrossAllBranches()->count());
        $this->assertSame([], $box->messages);
    }

    /** With no number set, the shop simply does not send. */
    public function test_no_number_means_no_message(): void
    {
        config(['services.sms.alert_to' => '']);

        $box = $this->catchSms();

        $this->buy();

        $this->assertSame([], $box->messages);
    }

    /**
     * And with the feature switched off, no sender is even asked for — which
     * is the state a shop with no SMS account is in, where asking for one
     * throws.
     */
    public function test_no_number_means_no_sender_is_even_asked_for(): void
    {
        config(['services.sms.alert_to' => '']);

        $this->app->bind(Sender::class, function (): Sender {
            throw new RuntimeException('SMS_DRIVER is «log», which delivers nothing.');
        });

        $order = $this->buy();

        $this->assertSame(Order::PLACED, $order->status);
    }

    /**
     * **A gateway that is down must not cost the shop the order.**
     *
     * The Sender contract says an ordinary refusal must not throw, and a
     * timeout or a DNS failure is not an ordinary refusal.
     */
    public function test_a_sender_that_throws_does_not_break_the_checkout(): void
    {
        $this->app->instance(Sender::class, new class implements Sender
        {
            /** @param  list<string>  $args */
            public function send(string $phone, string $message, array $args = [], string $purpose = self::CODE): void
            {
                throw new RuntimeException('the gateway is on fire');
            }
        });

        $order = $this->buy();

        $this->assertSame(Order::PLACED, $order->status);
        $this->assertSame(1, $order->items()->count());
    }

    /**
     * The other half: a sender that cannot be *built*. `SmsServiceProvider`
     * refuses to build one when the driver is still «log» in production, and
     * this is the shape that took the sign-in down once already.
     */
    public function test_a_sender_that_cannot_be_built_does_not_break_the_checkout(): void
    {
        $this->app->bind(Sender::class, function (): Sender {
            throw new RuntimeException('SMS_DRIVER is «log», which delivers nothing.');
        });

        $order = $this->buy();

        $this->assertSame(Order::PLACED, $order->status);
    }

    /**
     * A payment that fails is not a sale either, and this is the path where
     * the order row already exists — so nothing but the money may announce it.
     */
    public function test_a_card_order_that_is_never_paid_is_never_announced(): void
    {
        $this->withCardGateway();

        $box = $this->catchSms();

        $order = $this->buy();

        app(TenantContext::class)->forBranch(
            $this->central,
            fn () => app(SettleOrder::class)->cancelled($order, 'the customer walked away')
        );

        $this->assertSame([], $box->messages);
    }
}
