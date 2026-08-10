<?php

namespace App\Support\Checkout;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Variant;
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
 * Everything is locked in one order — ascending variant id — because two
 * baskets containing the same two shoes in opposite orders would otherwise
 * deadlock, and a deadlock under load looks exactly like the site being down.
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
            $items = $cart->items()->with('variant.product')->get()->sortBy('variant_id')->values();

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

            $shipping = $this->shipping($subtotal);

            $order = Order::create([
                'branch_id' => $branch->id,
                'customer_id' => $this->customer($contact)->id,
                'number' => Order::newNumber(),
                'status' => Order::PLACED,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'shipping_total' => $shipping,
                'grand_total' => $subtotal + $shipping,
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

            $this->carts->clear();

            return $order;
        });
    }

    /**
     * Take one line's units out of the sellable pool, or refuse the order.
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
        // and it is decided from the branch's own offer.
        $offer = $variant?->offer;

        if ($variant === null || $offer === null || $offer->status !== 'active' || $variant->status !== 'active') {
            throw CannotFulfil::notSold($variant ?? $item->variant, $item->quantity);
        }

        $inventory = BranchInventory::where('branch_id', $branch->id)
            ->where('variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        if ($inventory === null || $inventory->sellable_stock < $item->quantity) {
            throw CannotFulfil::soldOut($variant, $item->quantity, $inventory?->sellable_stock ?? 0);
        }

        // The row is locked, so nothing can have moved between the check above
        // and this write. The CHECK constraint is still the last word.
        $inventory->stock_reserved += $item->quantity;
        $inventory->save();

        return [
            'variant' => $variant,
            'quantity' => $item->quantity,
            'line_total' => $offer->price * $item->quantity,
            'attributes' => [
                'variant_id' => $variant->id,
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
