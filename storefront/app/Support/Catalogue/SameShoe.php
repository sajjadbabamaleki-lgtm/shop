<?php

namespace App\Support\Catalogue;

use App\Models\Product;

/**
 * The shoe the shop still sells under a retired product's name.
 *
 * **This rule is asked twice and must answer identically both times**, which
 * is the whole reason it is a class and not a private method. The product
 * page uses it to decide where `/products/golden-goose` sends a shopper;
 * `TorobFeedController` uses it to decide what that same address means in the
 * data the shop sends ترب. It lived in `ProductController` first and the feed
 * did not consult it at all — which is exactly how the shop ended up telling a
 * visitor «this way to the pink one» and telling ترب, about the same address
 * in the same minute, nothing at all.
 *
 * **The rule is containment and nothing looser.** A live product matches when
 * its folded title *contains the whole of* the retired one's — which is
 * «the same name with a colour added», and is how this supplier names a
 * colourway. `basalam:import` makes one product per supplier listing and the
 * supplier lists each colour separately, so the retired `کتونی گلدن گوس` sits
 * beside a live `کتونی گلدن گوس رنگ صورتی Golden Goose` and six more.
 *
 * It is deliberately **not** the fuzzy matching that `TorobFeedController`'s
 * `product_group_id` comment refuses for this same catalogue. That one has to
 * decide two *different* names are one shoe; this one only has to recognise
 * its own name inside a longer one.
 *
 * **Same brand, so containment cannot reach across the shop**, and a retired
 * product with no brand gets no successor at all: without that fence a short
 * retired name could swallow half the catalogue, and a wrong answer here is
 * worse than an honest dead end — it points a shopper, and an aggregator, at
 * the wrong shoe.
 *
 * `fold_persian()` on both sides, because «کتونی» is typed with ی on one
 * keyboard and ي on another and the two are the same word.
 */
final class SameShoe
{
    /**
     * The same shoe as the retired one, still listed here, or nothing.
     *
     * `listable()` is branch-scoped through the offer it asks for, so this
     * answers for whichever branch is bound — central for the feed, the
     * visitor's own for the page. With no branch bound it correctly finds
     * nothing rather than everything.
     */
    public static function stillOnSale(Product $retired): ?Product
    {
        if ($retired->brand_id === null) {
            return null;
        }

        $name = fold_persian(trim($retired->title));

        if ($name === '') {
            return null;
        }

        return Product::query()
            ->listable()
            ->where('brand_id', $retired->brand_id)
            ->whereKeyNot($retired->id)
            ->with(['variants.offer', 'variants.stock'])
            // What is on the shelf first, and the oldest of those — so the
            // answer is stable between two requests that arrive a second
            // apart, which a redirect and a feed both need it to be.
            ->inStockFirst()
            ->orderBy('id')
            ->get()
            ->first(fn (Product $other) => str_contains(fold_persian($other->title), $name));
    }
}
