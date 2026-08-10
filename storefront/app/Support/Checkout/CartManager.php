<?php

namespace App\Support\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Variant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Finds the visitor's basket, and is the only thing that puts anything in it.
 *
 * One basket per visitor per branch. The token that identifies a guest's
 * basket is generated here and kept in the session — it is not the session id,
 * because a session id is a credential and copying one into a table turns
 * every read of a cart into somewhere a session could leak from.
 *
 * A basket is not carried between branches. It holds one shop's stock at one
 * shop's prices, and moving it would move a price that no longer applies.
 * Walking from /shiraz to the main store leaves the Shiraz basket where it is,
 * and it is still there on the way back.
 */
class CartManager
{
    public function __construct(
        private TenantContext $tenant,
        private Session $session,
    ) {}

    /**
     * The basket for this branch, made if it does not exist yet.
     */
    public function current(): Cart
    {
        $branch = $this->tenant->branchOrNull() ?? throw new RuntimeException(
            'A basket belongs to a branch, and no branch is bound for this request.'
        );

        $key = "cart.{$branch->id}";
        $token = $this->session->get($key);

        if (is_string($token) && $token !== '') {
            $cart = Cart::where('token', $token)->first();

            if ($cart) {
                return $cart;
            }
        }

        // Either there was no token or it names a basket that no longer
        // exists — an order was placed, or the row was cleaned up. Either way
        // the visitor gets a new empty one rather than an error.
        $cart = Cart::create(['branch_id' => $branch->id, 'token' => Str::random(40)]);

        $this->session->put($key, $cart->token);

        return $cart;
    }

    /**
     * Add units of one size, or raise the line that is already there.
     *
     * Returns null when the branch has none of that size — a basket line for
     * something the shop cannot supply is a promise it would have to break at
     * the checkout, and the page says so now instead.
     *
     * Capping at what is on the shelf is a courtesy, not the defence: between
     * this and the checkout somebody else can buy the last pair, which is why
     * the real guard is the lock in PlaceOrder.
     */
    public function add(Variant $variant, int $quantity = 1): ?CartItem
    {
        $available = $variant->sellableStock();

        if ($available < 1) {
            return null;
        }

        $cart = $this->current();

        $item = $cart->items()->firstOrNew(['variant_id' => $variant->id]);

        $item->quantity = min(($item->quantity ?? 0) + max(1, $quantity), $available);
        $item->save();

        return $item;
    }

    /**
     * Set a line to an exact quantity. Zero removes it, which is what a
     * quantity box set to nothing means — and so does a size that has since
     * sold out, because there is no quantity of it to hold.
     */
    public function setQuantity(Variant $variant, int $quantity): void
    {
        $cart = $this->current();
        $available = $variant->sellableStock();

        if ($quantity <= 0 || $available < 1) {
            $cart->items()->where('variant_id', $variant->id)->delete();

            return;
        }

        $item = $cart->items()->firstOrNew(['variant_id' => $variant->id]);
        $item->quantity = min($quantity, $available);
        $item->save();
    }

    public function remove(Variant $variant): void
    {
        $this->current()->items()->where('variant_id', $variant->id)->delete();
    }

    public function clear(): void
    {
        $this->current()->items()->delete();
    }
}
