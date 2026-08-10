<?php

namespace App\Http\Controllers;

use App\Models\Variant;
use App\Models\Vendor;
use App\Support\Checkout\CartManager;
use App\Support\Marketplace\Sellers;
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
    public function __construct(private CartManager $carts, private Sellers $sellers) {}

    public function show(): View
    {
        $cart = $this->carts->current()->load('items.variant.product', 'items.variant.offer', 'items.variant.stock');

        return view('shop.cart', ['cart' => $cart]);
    }

    public function add(Request $request): RedirectResponse
    {
        [$variant, $vendor] = $this->seller($request);

        $added = $this->carts->add($variant, max(1, $request->integer('quantity', 1)), $vendor);

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
        [$variant, $vendor] = $this->seller($request);

        $this->carts->setQuantity($variant, $request->integer('quantity'), $vendor);

        return redirect()->to(storefront_route('cart'));
    }

    public function remove(Request $request): RedirectResponse
    {
        [$variant, $vendor] = $this->seller($request);

        $this->carts->remove($variant, $vendor);

        return redirect()->to(storefront_route('cart'));
    }

    /**
     * The variant and the seller a posted form is talking about, or a 404.
     *
     * Both ids come from the browser and neither is trusted (§30). The variant
     * has to be one somebody here actually sells, and a vendor has to be
     * approved with a live offer for that variant — a vendor id copied from
     * anywhere else finds nothing.
     *
     * @return array{0: Variant, 1: ?Vendor}
     */
    private function seller(Request $request): array
    {
        $variant = Variant::whereKey($request->integer('variant'))->first()
            ?? throw new NotFoundHttpException('No such item.');

        $vendorId = $request->integer('vendor') ?: null;

        if ($vendorId === null) {
            // The branch's own line: branch-scoped, so a variant this shop does
            // not list is simply not there.
            return [
                Variant::whereKey($variant->id)->whereHas('offer')->first()
                    ?? throw new NotFoundHttpException('This branch does not sell that.'),
                null,
            ];
        }

        $offer = $this->sellers->offerFor($variant, $vendorId);

        if ($offer === null || ! $offer->vendor?->isApproved()) {
            throw new NotFoundHttpException('That seller does not offer this.');
        }

        return [$variant, $offer->vendor];
    }
}
