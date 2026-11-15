<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Observers\OrderMessages;
use App\Support\Checkout\SettleOrder;
use App\Support\Sms\Messages;
use App\Support\Sms\Sender;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The order and account messages: which one leaves, to whom, with which
 * values in which blank — and that an unset pattern sends nothing.
 *
 * The values' order is the contract with Melipayamak's approved patterns, so
 * every assertion here names them in order. Swapping two would put a
 * customer's name where their order number goes, and the provider would send
 * it without a word.
 */
class OrderMessagesTest extends TestCase
{
    use RefreshDatabase;

    private object $box;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());
        $this->withoutDefer();

        config([
            'services.sms.alert_to' => '09121111111',
            'services.sms.patterns' => [
                'reserved' => '100001',
                'new_order' => '100002',
                'shipped' => '100003',
                'cancelled' => '100004',
                'paid' => '100005',
                'welcome' => '100006',
            ],
        ]);

        $this->box = new class implements Sender
        {
            /** @var list<array{phone: string, message: string, args: list<string>, purpose: string}> */
            public array $sent = [];

            public function send(string $phone, string $message, array $args = [], string $purpose = self::CODE): void
            {
                $this->sent[] = compact('phone', 'message', 'args', 'purpose');
            }
        };

        $this->app->instance(Sender::class, $this->box);
    }

    private function place(): Order
    {
        $variant = Product::where('slug', 'nike-v2k-run')->firstOrFail()->defaultVariant;

        $this->post('/cart', ['variant' => $variant->id]);
        $this->post('/checkout', [
            'name' => 'سجاد',
            'phone' => '09123456789',
            'address' => 'تهران، خیابان آزادی',
            'shipping_method_id' => ShippingMethod::query()->value('id'),
        ]);

        return Order::latest('id')->firstOrFail();
    }

    /** @return list<array{phone: string, message: string, args: list<string>, purpose: string}> */
    private function sent(string $purpose): array
    {
        return array_values(array_filter($this->box->sent, fn ($m) => $m['purpose'] === $purpose));
    }

    public function test_a_payment_tells_the_shopper_and_the_shop(): void
    {
        $order = $this->place();
        $order->payments()->create([
            'gateway' => 'zarinpal', 'authority' => 'A1', 'amount' => $order->grand_total,
            'status' => Payment::PAID, 'ref_id' => '778899', 'paid_at' => now(),
        ]);

        app(SettleOrder::class)->paid($order->fresh());

        [$paid] = $this->sent(Sender::PAID);
        $this->assertSame('09123456789', $paid['phone']);
        $this->assertSame(['سجاد', $order->number, '778899'], $paid['args']);
        $this->assertStringContainsString('شماره پیگیری 778899', $paid['message']);

        [$alert] = $this->sent(Sender::NEW_ORDER);
        $this->assertSame('09121111111', $alert['phone']);
        $this->assertSame(['سجاد', '09123456789', fa_number(intdiv($order->grand_total, 10))], $alert['args']);
    }

    public function test_a_payment_recorded_by_hand_quotes_the_order_number(): void
    {
        $order = $this->place();

        app(SettleOrder::class)->paid($order);

        $this->assertSame($order->number, $this->sent(Sender::PAID)[0]['args'][2]);
    }

    public function test_shipping_and_cancelling_each_say_so(): void
    {
        $shipped = $this->place();
        app(SettleOrder::class)->paid($shipped);
        $shipped->update(['status' => Order::SHIPPED]);

        $this->assertSame([$shipped->number], $this->sent(Sender::SHIPPED)[0]['args']);

        $cancelled = $this->place();
        app(SettleOrder::class)->cancelled($cancelled);

        $this->assertSame(['سجاد', $cancelled->number], $this->sent(Sender::CANCELLED)[0]['args']);
    }

    public function test_the_reservation_names_what_was_bought(): void
    {
        $order = $this->place();
        $order->forceFill(['payment_method' => 'online'])->save();
        $this->box->sent = [];

        app(OrderMessages::class)->created($order);

        [$reserved] = $this->sent(Sender::RESERVED);
        $this->assertSame([$order->items()->value('product_title')], $reserved['args']);
        // The shop holds fifteen minutes; a message saying otherwise is false.
        $this->assertStringContainsString('۱۵ دقیقه', $reserved['message']);
    }

    public function test_an_unset_pattern_sends_nothing(): void
    {
        config(['services.sms.patterns' => []]);

        $order = $this->place();
        app(SettleOrder::class)->paid($order);
        $order->update(['status' => Order::SHIPPED]);

        $this->assertSame([], $this->box->sent);
    }

    public function test_a_sender_that_throws_does_not_undo_the_payment(): void
    {
        $this->app->instance(Sender::class, new class implements Sender
        {
            public function send(string $phone, string $message, array $args = [], string $purpose = self::CODE): void
            {
                throw new RuntimeException('provider down');
            }
        });

        $order = $this->place();
        app(SettleOrder::class)->paid($order);

        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_every_message_has_its_sentence_and_its_switch(): void
    {
        foreach ([Sender::RESERVED, Sender::NEW_ORDER, Sender::SHIPPED, Sender::CANCELLED, Sender::PAID, Sender::WELCOME] as $purpose) {
            $this->assertArrayHasKey($purpose, Messages::TEXTS);
            $this->assertArrayHasKey($purpose, config('services.sms.patterns'));
        }
    }
}
