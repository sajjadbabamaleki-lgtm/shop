<?php

namespace Tests\Feature;

use App\Domains\Franchise\Models\FranchiseInventory;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Orders\LineRequest;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Services\Checkout;
use App\Domains\Orders\Services\StockReservation;
use App\Models\InventoryMovement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Overselling, on all three shelves (§7).
 *
 * The catalogue specification treats overselling as the unacceptable failure,
 * and the marketplace adds two more places for it to happen: a vendor's own
 * stock and a branch's local stock. The tests below check the application path
 * *and* the database constraint underneath it, because the constraint is what
 * catches the code path nobody has written yet.
 */
class StockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_vendor_cannot_sell_more_than_it_holds(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 100), stock: 2);

        $this->assertThrows(
            fn () => app(Checkout::class)->place([LineRequest::fromVendorOffer($offer, 3)]),
            RuntimeException::class,
        );

        // And the failed attempt left nothing behind.
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->withoutGlobalScopes()->count());
        $this->assertSame(2, (int) $offer->fresh()->stock_on_hand);
        $this->assertSame(0, (int) $offer->fresh()->stock_reserved);
    }

    public function test_the_database_refuses_a_reservation_larger_than_the_shelf(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(), stock: 2);

        // Straight past the service, as a future code path might. The check
        // constraint is the backstop that makes forgetting the lock loud.
        $this->assertThrows(
            fn () => VendorOffer::query()->withoutGlobalScopes()
                ->whereKey($offer->id)
                ->update(['stock_reserved' => 5]),
            QueryException::class,
        );
    }

    public function test_stock_never_goes_negative_on_any_shelf(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $franchise = $this->makeFranchise('branch');
        $variant = $this->makeVariant();
        $offer = $this->makeOffer($vendor, $variant, stock: 1);

        $row = FranchiseInventory::query()->withoutGlobalScopes()->create([
            'franchise_id' => $franchise->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 1,
        ]);

        $this->assertThrows(
            fn () => VendorOffer::query()->withoutGlobalScopes()->whereKey($offer->id)->update(['stock_on_hand' => -1]),
            QueryException::class,
        );

        $this->assertThrows(
            fn () => FranchiseInventory::query()->withoutGlobalScopes()->whereKey($row->id)->update(['stock_on_hand' => -1]),
            QueryException::class,
        );
    }

    public function test_the_last_unit_is_sold_once(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 100), stock: 1);
        $checkout = app(Checkout::class);

        $checkout->place([LineRequest::fromVendorOffer($offer->fresh(), 1)]);

        // The unit is held, not yet sold — and the second customer cannot have
        // it either way.
        $this->assertSame(1, (int) $offer->fresh()->stock_reserved);
        $this->assertSame(0, (int) $offer->fresh()->sellable_stock);

        $this->assertThrows(
            fn () => $checkout->place([LineRequest::fromVendorOffer($offer->fresh(), 1)]),
            RuntimeException::class,
        );
    }

    public function test_cancelling_an_unpaid_order_gives_the_units_back(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 100), stock: 5);
        $checkout = app(Checkout::class);

        $order = $checkout->place([LineRequest::fromVendorOffer($offer, 2)]);
        $this->assertSame(3, (int) $offer->fresh()->sellable_stock);

        $checkout->cancelUnpaid($order, 'سبد رها شد');

        $this->assertSame(5, (int) $offer->fresh()->sellable_stock);
        $this->assertSame(0, (int) $offer->fresh()->stock_reserved);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_a_paid_order_cannot_be_cancelled_by_releasing_stock(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 100), stock: 5);
        $checkout = app(Checkout::class);

        $order = $checkout->place([LineRequest::fromVendorOffer($offer, 1)]);
        $checkout->markPaid($order);

        $this->assertThrows(fn () => $checkout->cancelUnpaid($order), RuntimeException::class);
    }

    public function test_every_movement_is_written_to_the_one_inventory_ledger(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $franchise = $this->makeFranchise('branch');
        $variant = $this->makeVariant(stock: 100);
        $offer = $this->makeOffer($vendor, $variant, stock: 5);

        FranchiseInventory::query()->withoutGlobalScopes()->create([
            'franchise_id' => $franchise->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 5,
        ]);

        $checkout = app(Checkout::class);
        $checkout->markPaid($checkout->place([
            LineRequest::fromVendorOffer($offer, 1),
            LineRequest::fromFranchise($franchise, $variant, 1),
        ]));

        // One table explains all three shelves, and each movement says whose
        // shelf it moved.
        $this->assertSame(2, InventoryMovement::query()->where('vendor_offer_id', $offer->id)->count());
        $this->assertSame(2, InventoryMovement::query()->where('franchise_id', $franchise->id)->count());
        $this->assertSame(
            ['reservation', 'sale'],
            InventoryMovement::query()->where('vendor_offer_id', $offer->id)->orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_a_branch_sells_from_its_own_shelf_not_the_warehouse(): void
    {
        $this->seedRoles();

        $franchise = $this->makeFranchise('branch');
        $variant = $this->makeVariant(stock: 100);

        FranchiseInventory::query()->withoutGlobalScopes()->create([
            'franchise_id' => $franchise->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 3,
        ]);

        app(StockReservation::class)->reserve(LineRequest::fromFranchise($franchise, $variant, 2));

        $local = FranchiseInventory::query()->withoutGlobalScopes()
            ->where('franchise_id', $franchise->id)->first();

        $this->assertSame(2, (int) $local->stock_reserved);
        // The central warehouse is untouched.
        $this->assertSame(100, (int) $variant->fresh()->stock_on_hand);
        $this->assertSame(0, (int) $variant->fresh()->stock_reserved);
    }
}
