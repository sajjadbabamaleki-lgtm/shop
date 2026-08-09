<?php

namespace Tests\Feature;

use App\Domains\Marketplace\Models\LedgerEntry;
use App\Domains\Marketplace\Services\Ledger;
use App\Domains\Marketplace\Services\SettlementRunner;
use App\Domains\Orders\LineRequest;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Services\Checkout;
use App\Domains\Orders\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The financial ledger and the payout run (§9).
 *
 *   "Prefer a transaction ledger over a single mutable wallet balance. The
 *    payable vendor balance should be derived from auditable ledger entries."
 *
 * So the thing under test throughout is that no balance is stored anywhere,
 * and that every figure a vendor is shown can be re-derived from rows.
 */
class LedgerAndSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unpaid_order_puts_nothing_in_the_ledger(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 10), price: 10_000_000);

        app(Checkout::class)->place([LineRequest::fromVendorOffer($offer, 1)]);

        // Reserved is not sold. A vendor's payable must never include money
        // the customer has not handed over.
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, app(Ledger::class)->payableFor($vendor));
    }

    public function test_payment_writes_the_sale_the_commission_and_the_platform_mirror(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 10), price: 10_000_000);

        $checkout = app(Checkout::class);
        $checkout->markPaid($checkout->place([LineRequest::fromVendorOffer($offer, 1)]));

        $entries = LedgerEntry::query()->get()->groupBy('entry_type');

        $this->assertSame(10_000_000, (int) $entries['sale']->sum('amount'));
        $this->assertSame(-900_000, (int) $entries['commission']->sum('amount'));
        $this->assertSame(900_000, (int) $entries['commission_revenue']->sum('amount'));

        $ledger = app(Ledger::class);
        $this->assertSame(9_100_000, $ledger->payableFor($vendor));
        $this->assertSame(900_000, $ledger->platformRevenue());
    }

    public function test_paying_twice_pays_once(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 10), price: 10_000_000);

        $checkout = app(Checkout::class);
        $order = $checkout->place([LineRequest::fromVendorOffer($offer, 1)]);

        // A gateway that calls back twice must not pay the vendor twice.
        $checkout->markPaid($order);
        $checkout->markPaid($order);

        $this->assertSame(3, LedgerEntry::query()->count());
        $this->assertSame(9_100_000, app(Ledger::class)->payableFor($vendor));
    }

    public function test_a_ledger_entry_cannot_be_edited_or_deleted(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $entry = app(Ledger::class)->adjust($vendor, -500_000, 'جریمه دیرکرد');

        $this->assertThrows(fn () => $entry->update(['amount' => 0]), RuntimeException::class);
        $this->assertThrows(fn () => $entry->delete(), RuntimeException::class);

        $this->assertSame(-500_000, (int) $entry->fresh()->amount);
    }

    public function test_a_refund_returns_the_money_and_the_commission_that_came_with_it(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $variant = $this->makeVariant(stock: 10);
        $offer = $this->makeOffer($vendor, $variant, price: 10_000_000, stock: 10);

        $checkout = app(Checkout::class);
        $order = $checkout->place([LineRequest::fromVendorOffer($offer, 2)]);
        $checkout->markPaid($order);

        $item = $order->items()->withoutGlobalScopes()->first();
        $this->assertSame(18_200_000, app(Ledger::class)->payableFor($vendor));

        $refunds = app(RefundService::class);
        $refunds->complete($refunds->request($item, 1, 'wrong_size'));

        // One of two units back: 10,000,000 off the vendor, and the 900,000
        // commission that unit carried handed back to them.
        $this->assertSame(9_100_000, app(Ledger::class)->payableFor($vendor));
        $this->assertSame(0, app(Ledger::class)->platformRevenue() - 900_000);
        $this->assertSame(10_000_000, (int) $item->fresh()->refunded_amount);

        // And the unit went back on the seller's own shelf, not a shared pool.
        $this->assertSame(9, (int) $offer->fresh()->stock_on_hand);
    }

    public function test_a_settlement_takes_the_balance_and_leaves_a_trail(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 10), price: 10_000_000);

        $checkout = app(Checkout::class);
        $order = $checkout->place([LineRequest::fromVendorOffer($offer, 1)]);
        $checkout->markPaid($order);

        $runner = app(SettlementRunner::class);

        // Nothing is settleable yet: the line has not been delivered.
        $this->assertNull($runner->draftFor($vendor));

        $item = $order->items()->withoutGlobalScopes()->first();
        $item->forceFill([
            'fulfillment_status' => 'delivered',
            'delivered_at' => now()->subDays(30),
        ])->save();

        $settlement = $runner->draftFor($vendor);

        $this->assertNotNull($settlement);
        $this->assertSame(9_100_000, (int) $settlement->net_payable);
        $this->assertSame(10_000_000, (int) $settlement->gross_sales);
        $this->assertSame(900_000, (int) $settlement->commission_total);

        // The allocated entries leave the live balance, and the line is
        // stamped so the next run cannot pick it up again.
        $this->assertSame(0, app(Ledger::class)->payableFor($vendor));
        $this->assertSame($settlement->id, (int) $item->fresh()->settlement_id);
        $this->assertNull($runner->draftFor($vendor));

        $runner->approve($settlement);
        $runner->markPaid($settlement, 'SHEBA-001');

        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame(
            -9_100_000,
            (int) LedgerEntry::query()->where('entry_type', 'settlement')->sum('amount'),
        );
    }

    public function test_a_line_within_the_return_hold_is_not_settleable(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 10), price: 10_000_000);

        $checkout = app(Checkout::class);
        $order = $checkout->place([LineRequest::fromVendorOffer($offer, 1)]);
        $checkout->markPaid($order);

        $order->items()->withoutGlobalScopes()->first()->forceFill([
            'fulfillment_status' => 'delivered',
            'delivered_at' => now()->subDay(),
        ])->save();

        // Delivered yesterday, and the hold is seven days.
        $this->assertNull(app(SettlementRunner::class)->draftFor($vendor));
        $this->assertSame(0, OrderItem::query()->withoutGlobalScopes()->settleable()->count());
    }

    public function test_only_the_vendor_that_earned_it_is_credited(): void
    {
        $this->seedRoles();

        $one = $this->makeVendor('one');
        $two = $this->makeVendor('two');
        $variant = $this->makeVariant(stock: 40);

        $checkout = app(Checkout::class);
        $order = $checkout->place([
            LineRequest::fromVendorOffer($this->makeOffer($one, $variant, price: 10_000_000), 1),
            LineRequest::fromVendorOffer($this->makeOffer($two, $variant, price: 30_000_000), 1),
        ]);
        $checkout->markPaid($order);

        $ledger = app(Ledger::class);

        $this->assertSame(9_100_000, $ledger->payableFor($one));
        $this->assertSame(27_300_000, $ledger->payableFor($two));
    }
}
