<?php

namespace App\Domains\Orders\Services;

use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Services\CommissionResolver;
use App\Domains\Marketplace\Services\Ledger;
use App\Domains\Orders\LineRequest;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Tenancy\Contracts\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a basket into an order (§2, §9, §10).
 *
 * One checkout, one order, one payment — the answer this build gives to the
 * third open decision in the architecture document. The split happens on the
 * items, where the critical design rule puts it: each line records its seller,
 * the commission rate in force at that moment, what that rate produced, and
 * what the seller is therefore owed.
 *
 * Placing an order *reserves* stock; it does not sell it. Selling happens in
 * `markPaid()`, and so does every ledger entry. An unpaid basket that expires
 * must leave no trace in the vendor's balance, and a vendor's payable must
 * never include money the customer has not handed over.
 */
class Checkout
{
    public function __construct(
        private readonly CommissionResolver $commissions,
        private readonly StockReservation $stock,
        private readonly Ledger $ledger,
    ) {}

    /**
     * @param  array<int, LineRequest>  $lines
     * @param  array<string, mixed>  $customer  name, phone, email, address, note
     * @param  Tenant|null  $origin  the storefront the customer came in by
     */
    public function place(array $lines, array $customer = [], ?Tenant $origin = null, ?User $user = null): Order
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('An order needs at least one line.');
        }

        return DB::transaction(function () use ($lines, $customer, $origin, $user) {
            $order = Order::create([
                'number' => $this->nextNumber(),
                'user_id' => $user?->id,
                'channel' => match ($origin?->tenantKind()) {
                    'vendor' => 'vendor_store',
                    'franchise' => 'franchise_store',
                    default => 'main',
                },
                'origin_type' => $origin?->tenantKind(),
                'origin_id' => $origin?->tenantId(),
                'status' => 'pending_payment',
                'customer_name' => $customer['name'] ?? $user?->name,
                'customer_phone' => $customer['phone'] ?? null,
                'customer_email' => $customer['email'] ?? $user?->email,
                'shipping_address' => $customer['address'] ?? null,
                'note' => $customer['note'] ?? null,
                'shipping_total' => (int) ($customer['shipping_total'] ?? 0),
                'placed_at' => now(),
            ]);

            $itemsTotal = 0;

            foreach ($lines as $line) {
                // Reserve first. A line that cannot be covered must not leave
                // an order item behind, and the transaction unwinds the rest.
                $this->stock->reserve($line, $order->id);

                $itemsTotal += $line->lineTotal();

                OrderItem::query()->withoutGlobalScopes()->create(
                    $this->attributesFor($line, $order->id)
                );
            }

            $order->forceFill([
                'items_total' => $itemsTotal,
                'grand_total' => $itemsTotal + $order->shipping_total - $order->discount_total,
            ])->save();

            return $order->fresh('items');
        });
    }

    /**
     * Payment cleared: the reservations become sales and the ledger is
     * written. Everything that touches money for the first time happens here,
     * and it is idempotent — a payment gateway that calls back twice must not
     * pay a vendor twice.
     */
    public function markPaid(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->paid_at !== null) {
                return $order;
            }

            foreach ($order->items()->withoutGlobalScopes()->get() as $item) {
                $this->stock->commit($this->lineFor($item), $order->id);
                $this->ledger->recordSale($item);
            }

            $order->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();

            return $order;
        });
    }

    /**
     * Release the held units for an order that will never be paid. The ledger
     * is untouched because nothing was ever posted to it.
     */
    public function cancelUnpaid(Order $order, string $reason = ''): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->paid_at !== null) {
                throw new \RuntimeException('A paid order is cancelled by refunding its lines, not by releasing them.');
            }

            foreach ($order->items()->withoutGlobalScopes()->get() as $item) {
                $this->stock->release($this->lineFor($item), $order->id);
                $item->update(['fulfillment_status' => 'cancelled']);
            }

            $order->forceFill([
                'status' => 'cancelled',
                'note' => trim($order->note."\n".$reason),
            ])->save();

            return $order;
        });
    }

    /** @return array<string, mixed> */
    private function attributesFor(LineRequest $line, int $orderId): array
    {
        $lineTotal = $line->lineTotal();
        $rateBps = 0;
        $split = ['commission' => 0, 'payable' => $lineTotal];

        if ($line->sellerKind === 'vendor') {
            $vendor = Vendor::query()->findOrFail($line->vendorOffer->vendor_id);

            // Copied onto the line, not looked up later. A settlement three
            // weeks from now has to reproduce the arithmetic on the invoice.
            $rateBps = $this->commissions->rateFor($vendor, $line->variant->product, $line->vendorOffer);
            $split = $this->commissions->split($lineTotal, $rateBps);
        }

        $variant = $line->variant;

        return [
            'order_id' => $orderId,
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'seller_kind' => $line->sellerKind,
            'vendor_id' => $line->vendorOffer?->vendor_id,
            'franchise_id' => $line->franchise?->id,
            'vendor_offer_id' => $line->vendorOffer?->id,
            'title' => $variant->product->title,
            'sku' => $variant->sku,
            'display_color' => $variant->display_color,
            'size_value' => $variant->size_value,
            'unit_price' => $line->unitPrice,
            'quantity' => $line->quantity,
            'line_total' => $lineTotal,
            'commission_rate_bps' => $rateBps,
            'commission_amount' => $split['commission'],
            'seller_payable' => $split['payable'],
        ];
    }

    /**
     * Rebuild the line a stored item came from, so stock moves back to the
     * same shelf it came off.
     */
    public function lineFor(OrderItem $item): LineRequest
    {
        $item->loadMissing(['variant', 'vendorOffer', 'franchise']);

        return match ($item->seller_kind) {
            'vendor' => LineRequest::fromVendorOffer($item->vendorOffer, $item->quantity),
            'franchise' => LineRequest::fromFranchise($item->franchise, $item->variant, $item->quantity, $item->unit_price),
            default => LineRequest::fromPlatform($item->variant, $item->quantity),
        };
    }

    private function nextNumber(): string
    {
        do {
            $number = 'VP-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
