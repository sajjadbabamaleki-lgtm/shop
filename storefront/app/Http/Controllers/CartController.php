<?php

namespace App\Http\Controllers;

use App\Models\Variant;
use App\Support\Checkout\CartManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The basket.
 *
 * Everything that changes it is a POST and redirects back, so a refresh never
 * re-adds a pair of shoes.
 *
 * A variant only exists here if this branch sells it. The lookup goes through
 * the branch's own offers, so a variant id copied from another shop's page is
 * a 404 rather than a line in the basket at a price this shop never set.
 */
class CartController extends Controller
{
    public function __construct(private CartManager $carts) {}

    public function show(): View
    {
        $cart = $this->carts->current()->load('items.variant.product', 'items.variant.offer', 'items.variant.stock');

        return view('shop.cart', ['cart' => $cart]);
    }

    public function add(Request $request): RedirectResponse
    {
        $variant = $this->soldHere($request->integer('variant'));

        $added = $this->carts->add($variant, max(1, $request->integer('quantity', 1)));

        if ($added === null) {
            return redirect()
                ->to(storefront_route('cart'))
                ->withErrors(['cart' => 'این سایز در این شعبه موجود نیست.']);
        }

        return redirect()
            ->to(storefront_route('cart'))
            ->with('status', 'به سبد خرید اضافه شد.');
    }

    public function update(Request $request): RedirectResponse
    {
        $variant = $this->soldHere($request->integer('variant'));

        $this->carts->setQuantity($variant, $request->integer('quantity'));

        return redirect()->to(storefront_route('cart'));
    }

    public function remove(Request $request): RedirectResponse
    {
        $variant = $this->soldHere($request->integer('variant'));

        $this->carts->remove($variant);

        return redirect()->to(storefront_route('cart'));
    }

    /**
     * The variant, if this branch sells it — and nothing otherwise.
     *
     * `whereHas('offer')` is branch-scoped, so this is the check that stops a
     * posted id reaching into a shop's basket for something it does not
     * stock. Never trust an id from the browser (§30).
     */
    private function soldHere(int $id): Variant
    {
        return Variant::whereKey($id)->whereHas('offer')->first()
            ?? throw new NotFoundHttpException('This branch does not sell that.');
    }
}
