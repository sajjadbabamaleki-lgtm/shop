<?php

namespace App\Support\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Variant;
use App\Models\Vendor;
use App\Support\Marketplace\Sellers;
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
    /**
     * The basket this request has already found, per branch.
     *
     * **Not a cache — a memo for the length of one request.** The header, the
     * phone drawer and the mini basket are three view composers that each need
     * the basket, and none of them can be given it by a controller: they are
     * on every storefront page and no controller owns them. Before this, the
     * front page asked the database for the same basket **four times** — the
     * header's count, the drawer's count, and the mini basket's own read —
     * which counted, was six of its nineteen queries.
     *
     * **Keyed by the basket's own token, not by the branch.** Keyed by branch
     * it was wrong, and two tests said so within the hour: they sign a second
     * shopper in with `flushSession()` and buy again, and a branch-keyed memo
     * handed the second shopper the first one's basket — with the first one's
     * discount still on it. The token *is* the identity of a basket, so a new
     * session cannot collide with an old entry however this class is resolved.
     *
     * Nothing is memoised for a visitor with no token yet: there is no basket
     * to remember, and `count()` answers zero without asking anybody.
     *
     * @var array<string, Cart>
     */
    private array $found = [];

    /** @var array<string, int> */
    private array $counted = [];

    public function __construct(
        private TenantContext $tenant,
        private Session $session,
        private Sellers $sellers,
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
            if (isset($this->found[$token])) {
                return $this->found[$token];
            }

            $cart = Cart::where('token', $token)->first();

            if ($cart) {
                return $this->found[$token] = $cart;
            }
        }

        // Either there was no token or it names a basket that no longer
        // exists — an order was placed, or the row was cleaned up. Either way
        // the visitor gets a new empty one rather than an error.
        $cart = Cart::create(['branch_id' => $branch->id, 'token' => Str::random(40)]);

        $this->session->put($key, $cart->token);

        return $this->found[$cart->token] = $cart;
    }

    /**
     * Forget what this request has already looked up.
     *
     * The memo above is right for a page being rendered and wrong the moment
     * something writes: adding a line and then asking for the count in the
     * same request would otherwise answer with the number from before. Every
     * method here that changes a basket calls this.
     */
    private function changed(): void
    {
        $this->found = [];
        $this->counted = [];
    }

    /**
     * How many items are in the basket, without making one.
     *
     * The header asks this on every page, including for visitors who never
     * add anything — so it must not be `current()`, which would write a cart
     * row for every person who ever loads the home page.
     */
    public function count(): int
    {
        $branch = $this->tenant->branchOrNull();

        if ($branch === null) {
            return 0;
        }

        $token = $this->session->get("cart.{$branch->id}");

        if (! is_string($token) || $token === '') {
            return 0;
        }

        if (array_key_exists($token, $this->counted)) {
            return $this->counted[$token];
        }

        // One query. It used to be two: `withCount('items')` loaded a number
        // that was then thrown away, and `->items()->sum(...)` went back for
        // the real one. What the badge shows is the sum of the quantities —
        // three of one shoe is three — so that is the only thing asked for.
        return $this->counted[$token] = (int) Cart::where('token', $token)
            ->join('cart_items', 'cart_items.cart_id', '=', 'carts.id')
            ->sum('cart_items.quantity');
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
    public function add(Variant $variant, int $quantity = 1, ?Vendor $vendor = null): ?CartItem
    {
        $this->changed();

        $available = $this->sellers->availableFrom($variant, $vendor?->id);

        if ($available < 1) {
            return null;
        }

        $cart = $this->current();

        // A line is a size *from a seller*: the same shoe from the branch and
        // from a vendor are two lines at two prices, not one line that has to
        // pick.
        $item = $cart->items()->firstOrNew(['variant_id' => $variant->id, 'vendor_id' => $vendor?->id]);

        $item->quantity = min(($item->quantity ?? 0) + max(1, $quantity), $available);
        $item->save();

        return $item;
    }

    /**
     * Set a line to an exact quantity. Zero removes it, which is what a
     * quantity box set to nothing means — and so does a size that has since
     * sold out, because there is no quantity of it to hold.
     */
    public function setQuantity(Variant $variant, int $quantity, ?Vendor $vendor = null): void
    {
        $this->changed();

        $cart = $this->current();
        $available = $this->sellers->availableFrom($variant, $vendor?->id);

        if ($quantity <= 0 || $available < 1) {
            $this->remove($variant, $vendor);

            return;
        }

        $item = $cart->items()->firstOrNew(['variant_id' => $variant->id, 'vendor_id' => $vendor?->id]);
        $item->quantity = min($quantity, $available);
        $item->save();
    }

    public function remove(Variant $variant, ?Vendor $vendor = null): void
    {
        $this->changed();

        $this->current()->items()
            ->where('variant_id', $variant->id)
            ->where(fn ($q) => $vendor ? $q->where('vendor_id', $vendor->id) : $q->whereNull('vendor_id'))
            ->delete();
    }

    public function clear(): void
    {
        $this->changed();

        $this->current()->items()->delete();
    }
}
