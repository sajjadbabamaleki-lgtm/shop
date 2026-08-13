<?php

namespace App\Support\Checkout;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\DiscountRedemption;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Variant;
use App\Models\VendorOffer;
use App\Support\Marketplace\Sellers;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Turns a basket into an order, and takes the stock off the shelf while it
 * does.
 *
 * This is the piece the specification refuses to compromise on: §10 and §16.2
 * say two simultaneous attempts must not be able to sell the same last unit.
 * A check in PHP followed by an update loses that race — both requests read
 * "1 left", both pass, both write. So the check and the write happen inside
 * one transaction, over rows locked with SELECT … FOR UPDATE, and the database
 * holds the final line: branch_inventory carries a CHECK that a reservation
 * can never exceed what is on hand, so even a bug here cannot oversell.
 *
 * Everything is locked in one order — ascending variant id, then vendor —
 * because two baskets containing the same two shoes in opposite orders would
 * otherwise deadlock, and a deadlock under load looks exactly like the site
 * being down.
 *
 * A line may be the branch's or a vendor's, and the only difference is which
 * shelf is locked: branch_inventory, or the vendor's own offer. Both carry the
 * same CHECK that a reservation cannot exceed what is on hand.
 *
 * Stock is *reserved*, not sold. The units leave the sellable pool but stay on
 * hand until the order is actually paid for, which is what makes cancelling
 * one a matter of putting them back rather than of inventing them.
 */
class PlaceOrder
{
    public function __construct(
        private TenantContext $tenant,
        private CartManager $carts,
        private Sellers $sellers,
        private Discounts $discounts,
    ) {}

    /**
     * @param  array{name: string, phone: string, province?: ?string, city?: ?string, address: string, postal_code?: ?string, note?: ?string}  $contact
     *
     * @throws CannotFulfil
     */
    public function __invoke(Cart $cart, array $contact): Order
    {
        $branch = $this->tenant->branch();

        return DB::transaction(function () use ($cart, $contact, $branch) {
            $items = $cart->items()
                ->with('variant.product', 'vendor')
                ->get()
                // Locked in one order, always, so two baskets holding the same
                // two shoes cannot deadlock. Vendor id breaks the tie when the
                // same variant is being bought from two sellers at once.
                ->sortBy(fn (CartItem $item) => [$item->variant_id, $item->vendor_id ?? 0])
                ->values();

            if ($items->isEmpty()) {
                throw new \RuntimeException('An empty basket cannot become an order.');
            }

            $lines = [];
            $subtotal = 0;

            foreach ($items as $item) {
                $line = $this->reserve($branch, $item);

                $lines[] = $line;
                $subtotal += $line['line_total'];
            }

            $customer = $this->customer($contact);

            // The code is resolved *here*, inside the transaction that is
            // taking the stock, and not from anything the browser posted. A
            // code that expired or ran out while somebody was typing their
            // address stops applying at exactly this moment, which is the only
            // moment where the answer is worth anything.
            $discount = $this->discounts->on($cart, $customer->id);

            $shipping = $this->shipping($subtotal);
            $off = $discount['amount'];

            $order = Order::create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'number' => Order::newNumber(),
                'status' => Order::PLACED,
                'subtotal' => $subtotal,
                'discount_total' => $off,
                'shipping_total' => $shipping,
                'grand_total' => $subtotal - $off + $shipping,
                'payment_method' => 'cash_on_delivery',
                'payment_status' => 'unpaid',
                'contact_name' => $contact['name'],
                'contact_phone' => Customer::normalisePhone($contact['phone']),
                'province' => $contact['province'] ?? null,
                'city' => $contact['city'] ?? null,
                'address' => $contact['address'],
                'postal_code' => $contact['postal_code'] ?? null,
                'note' => $contact['note'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line['attributes']);

                // inventory_movements is the *branch's* stock ledger. A vendor
                // line moves nothing on the branch's shelf, so it writes
                // nothing here — the vendor's own count lives on their offer,
                // and what they are owed lives in ledger_entries.
                if ($line['attributes']['vendor_id'] !== null) {
                    continue;
                }

                InventoryMovement::create([
                    'branch_id' => $branch->id,
                    'variant_id' => $line['variant']->id,
                    'type' => 'reservation',
                    'quantity' => $line['quantity'],
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'note' => "Reserved for order {$order->number}.",
                ]);
            }

            if ($discount['code'] !== null && $off > 0) {
                // One row per use — this is what makes "fifty uses" and "once
                // per customer" true rather than approximately true, and what
                // lets somebody afterwards ask what the campaign cost.
                DiscountRedemption::create([
                    'discount_code_id' => $discount['code']->id,
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'amount' => $off,
                ]);
            }

            $this->carts->clear();
            $this->discounts->forget();

            return $order;
        });
    }

    /**
     * Take one line's units out of the sellable pool, or refuse the order.
     *
     * Which pool depends on who is selling: the branch's shelf, or the
     * vendor's own. Both are locked the same way and checked under the lock,
     * and both have the same CHECK constraint behind them.
     *
     * @return array{variant: Variant, quantity: int, line_total: int, attributes: array<string, mixed>}
     *
     * @throws CannotFulfil
     */
    private function reserve(Branch $branch, CartItem $item): array
    {
        $variant = $item->variant;

        // Read the price inside the transaction too. A basket holds no prices,
        // so this is the first and only time this order's money is decided,
        // and it is decided from the seller's own offer.
        $offer = $variant === null ? null : $this->sellers->offerFor($variant, $item->vendor_id);

        if ($variant === null || $offer === null || $variant->status !== 'active') {
            throw CannotFulfil::notSold($variant ?? $item->variant, $item->quantity);
        }

        if ($item->vendor_id === null) {
            if ($offer->status !== 'active') {
                throw CannotFulfil::notSold($variant, $item->quantity);
            }

            $this->holdBranchStock($branch, $variant, $item->quantity);
        } else {
            if (! $offer->isSellable()) {
                throw CannotFulfil::notSold($variant, $item->quantity);
            }

            $this->holdVendorStock($offer, $variant, $item->quantity);
        }

        return [
            'variant' => $variant,
            'quantity' => $item->quantity,
            'line_total' => $offer->price * $item->quantity,
            'attributes' => [
                'variant_id' => $variant->id,
                'vendor_id' => $item->vendor_id,
                'product_title' => $variant->product?->title ?? $variant->sku,
                'sku' => $variant->sku,
                'size_value' => $variant->size_value,
                'display_color' => $variant->display_color,
                'unit_price' => $offer->price,
                'compare_at_price' => $offer->hasActivePromotion() ? $offer->compare_at_price : null,
                'quantity' => $item->quantity,
                'line_total' => $offer->price * $item->quantity,
            ],
        ];
    }

    /**
     * @throws CannotFulfil
     */
    private function holdBranchStock(Branch $branch, Variant $variant, int $quantity): void
    {
        $inventory = BranchInventory::where('branch_id', $branch->id)
            ->where('variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        if ($inventory === null || $inventory->sellable_stock < $quantity) {
            throw CannotFulfil::soldOut($variant, $quantity, $inventory?->sellable_stock ?? 0);
        }

        // The row is locked, so nothing can have moved between the check above
        // and this write. The CHECK constraint is still the last word.
        $inventory->stock_reserved += $quantity;
        $inventory->save();
    }

    /**
     * @throws CannotFulfil
     */
    private function holdVendorStock(VendorOffer $offer, Variant $variant, int $quantity): void
    {
        $locked = VendorOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();

        if ($locked->sellable_stock < $quantity) {
            throw CannotFulfil::soldOut($variant, $quantity, $locked->sellable_stock);
        }

        $locked->stock_reserved += $quantity;
        $locked->save();
    }

    /**
     * Delivery, flat and free over a threshold.
     *
     * Both numbers are in config and both are placeholders until the client
     * says what they should be — see config/storefront.php. They are a
     * starting policy, not a measured one, and they are in one place so that
     * changing them is one line.
     */
    private function shipping(int $subtotal): int
    {
        $free = (int) config('storefront.checkout.free_shipping_above');
        $flat = (int) config('storefront.checkout.shipping_flat');

        return $free > 0 && $subtotal >= $free ? 0 : $flat;
    }

    /**
     * The customer, by phone.
     *
     * §21: one account across the main store, every franchise and the
     * marketplace, so this is deliberately not branch-scoped — a shopper who
     * bought in Shiraz and then buys from the main store is one person with
     * one history, not two rows.
     *
     * @param  array<string, mixed>  $contact
     */
    private function customer(array $contact): Customer
    {
        $phone = Customer::normalisePhone((string) $contact['phone']);

        $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $contact['name']]);

        // Fill a name in if the account has never had one, but never overwrite
        // one the customer set themselves.
        if (blank($customer->name) && filled($contact['name'])) {
            $customer->update(['name' => $contact['name']]);
        }

        return $customer;
    }
}
