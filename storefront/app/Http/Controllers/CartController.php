<?php

namespace App\Http\Controllers;

use App\Models\Variant;
use App\Models\Vendor;
use App\Support\Checkout\CartManager;
use App\Support\Checkout\Discounts;
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
    public function __construct(
        private CartManager $carts,
        private Sellers $sellers,
        private Discounts $discounts,
    ) {}

    public function show(): View
    {
        $cart = $this->carts->current()->load('items.variant.product', 'items.variant.offer', 'items.variant.stock', 'items.vendor');

        $discount = $this->discounts->on($cart);

        // **The basket no longer quotes delivery, because it cannot know it.**
        // What delivery costs is now the shipping method's, and the method is
        // chosen on the next page: two of the three are پس‌کرایه and add
        // nothing, one is a fixed amount the shop sets. Quoting any single
        // figure here would be a number the checkout then contradicts, which
        // is the one thing a summary must never do — so this page adds up the
        // goods and says where the rest is decided.
        return view('shop.cart', [
            'cart' => $cart,
            'discount' => $discount,
        ]);
    }

    /**
     * Type a code, or clear it.
     *
     * Only the *text* is kept. Whether it applies, and for how much, is worked
     * out again on every page and once more inside the transaction that places
     * the order — a code that expires while somebody shops has to stop working,
     * and a number written into the basket could not do that.
     */
    public function discount(Request $request): RedirectResponse
    {
        $code = trim((string) $request->input('code'));

        $code === ''
            ? $this->discounts->forget()
            : $this->discounts->remember(mb_substr($code, 0, 32));

        // Back where it was typed. The field was on the basket page and is on
        // the checkout now, and a hard redirect to the basket would have thrown
        // the customer off the last screen before paying to tell them their
        // code worked. The fallback is the checkout, because that is the only
        // page the field is on — a request with no referer came from nowhere a
        // form could have been.
        return back(fallback: storefront_route('checkout'));
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

        // «خرید فوری» skips the basket. It is the same add — same stock check,
        // same reservation, same row — and only the destination differs, so
        // there is no second path through this that could drift from the
        // first. A shopper who pressed it wants the form, not a review of what
        // they already chose.
        if ($request->boolean('buy_now')) {
            return redirect()->to(storefront_route('checkout'));
        }

        // No «به سبد خرید اضافه شد» on the way in. The redirect lands on the
        // basket with the thing in it, so the banner said what the page was
        // already showing — and it is the first thing the eye meets above the
        // line it is announcing.
        return redirect()->to(storefront_route('cart'));
    }

    /**
     * Back where it was pressed, not to the basket page.
     *
     * These two are posted from two places now: the basket page's card, and the
     * same card inside the header's drawer. From the page, "back" *is* the
     * basket, so nothing there changes. From the drawer it is the page the
     * shopper was reading — and landing them on the basket because they nudged
     * a quantity from a panel that floats over the page is the drawer throwing
     * them out of whatever they were doing.
     *
     * The fallback is the basket, which is what a post with no referrer gets.
     */
    public function update(Request $request): RedirectResponse
    {
        [$variant, $vendor] = $this->seller($request);

        $this->carts->setQuantity($variant, $request->integer('quantity'), $vendor);

        return redirect()->back(fallback: storefront_route('cart'));
    }

    public function remove(Request $request): RedirectResponse
    {
        [$variant, $vendor] = $this->seller($request);

        $this->carts->remove($variant, $vendor);

        return redirect()->back(fallback: storefront_route('cart'));
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
