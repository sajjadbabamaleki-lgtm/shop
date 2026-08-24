<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A shopper's saved products — «لیست علاقمندی».
 *
 * Every route here is `auth:customer`, never the bare `auth`: the bare one
 * means "whichever guard is default", which is a runtime value, and a staff
 * session must never satisfy a shopper's check (§21). A guest is bounced to
 * `/account/enter` rather than the staff sign-in because these routes are named
 * `account.*` and `redirectGuestsTo` in bootstrap/app.php reads that name — so
 * the naming here is load-bearing, not decoration.
 *
 * Both writes are POSTs that redirect back, the same shape as the basket's, so
 * a refresh cannot save a shoe twice. It could not anyway — the unique key on
 * (customer_id, product_id) is what makes the button idempotent, rather than a
 * check-then-insert that two taps in the same instant can both pass.
 */
class WishlistController extends Controller
{
    /**
     * The list itself.
     *
     * **`purchasable()` is the filter and `pricedHere()` is only a column** —
     * the two are easy to mix up and this controller was written with them the
     * wrong way round for a round. `pricedHere()` adds `branch_price` via
     * `selectSub` and removes nothing; `purchasable()` is the one with the
     * `whereHas`, and what it asks is exactly the branch question: an active
     * variant with an active offer *here* and stock *here*, both through
     * branch-scoped relations. Filtering on the price column instead let a
     * franchise draw a card for a shoe it cannot price, and `shop.card` reads
     * `$offer->price` straight — so the page was a 500, not a blank.
     *
     * That is what answers the branch question the table deliberately does not
     * store: the list is the shopper's wherever they are, and a saved shoe this
     * branch does not carry is simply not on the page here. It is still on the
     * row, and still there at a shop that stocks it.
     */
    public function index(): View
    {
        $products = Auth::guard('customer')->user()
            ->wishlistProducts()
            ->purchasable()
            ->pricedHere()
            ->with(['brand', 'media', 'variants.offer', 'variants.stock', 'defaultVariant.offer', 'defaultVariant.stock'])
            ->get();

        return view('shop.wishlist', ['products' => $products]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = $this->product($request);

        // firstOrCreate rather than create: the unique key would reject the
        // second insert with a database error, and a shopper double-tapping a
        // heart should see the shoe saved, not a 500.
        Auth::guard('customer')->user()->wishlistItems()->firstOrCreate([
            'product_id' => $product->id,
        ]);

        return back()->with('status', 'به لیست علاقمندی اضافه شد.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $product = $this->product($request);

        Auth::guard('customer')->user()->wishlistItems()
            ->where('product_id', $product->id)
            ->delete();

        return back()->with('status', 'از لیست علاقمندی حذف شد.');
    }

    /**
     * The product being saved, and only if this branch actually sells it.
     *
     * `purchasable()` for the same reason the basket resolves a variant through
     * the branch's own offers: an id copied from another shop's page must be a
     * 404 here rather than a row pointing at something this storefront has
     * never offered.
     *
     * **Not `pricedHere()`, which filters nothing.** It only selects a
     * `branch_price` column, so a `findOrFail` behind it finds every product in
     * the catalogue and the guard this method exists to be does not exist. The
     * test that says a franchise cannot save what it does not sell is what
     * caught that, by getting a redirect where it wanted a 404.
     */
    private function product(Request $request): Product
    {
        $request->validate(['product' => ['required', 'integer']]);

        return Product::query()
            ->purchasable()
            ->findOrFail($request->integer('product'));
    }
}
