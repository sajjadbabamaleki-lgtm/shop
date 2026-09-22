<?php

namespace App\Support\Marketplace;

use App\Models\Variant;
use App\Models\Vendor;
use App\Models\VendorOffer;
use Illuminate\Support\Collection;

/**
 * Who is selling one variant, and for how much.
 *
 * The branch first, then every approved vendor with stock, cheapest first.
 * Both kinds of offer answer `price`, `compare_at_price` and `sellableStock()`,
 * so a template can render a row of sellers without asking which kind each one
 * is — the difference between "our shop" and "somebody else's" is a label and
 * a vendor id on the form, not two code paths.
 *
 * @phpstan-type Seller array{vendor: ?\App\Models\Vendor, offer: object, available: int}
 */
class Sellers
{
    /**
     * @return Collection<int, array{vendor: ?Vendor, offer: object, available: int}>
     */
    public function for(Variant $variant): Collection
    {
        return $this->forMany(collect([$variant]))->get($variant->id) ?? collect();
    }

    /**
     * The same answer for every size of a shoe, in **one** query rather than
     * one per size.
     *
     * The product page asks this of each variant in turn, and it used to be
     * this class's only entry point — so a shoe listed in eight sizes ran eight
     * `vendor_offers` queries, each with the `whereHas('vendor')` subquery
     * `sellable()` carries. That is invisible here and expensive there: a query
     * costs about 0.9ms in a development container and **9–11ms on the live
     * machine**, which is the ratio recorded in CLAUDE.md, so the eight cost
     * roughly 80ms of the second that page spends. Measured on the seeded
     * catalogue: the product page went from 35 queries to 34 on a two-size
     * shoe, and the saving grows by one query per size.
     *
     * That mattered enough to fix because of *when* the page is asked for. A
     * crawler walking the sitemap asks for every product page in a row, and the
     * container is CPU-bound — see the note in CLAUDE.md about the machine
     * being 13–16× slower than this one. Requests that overlap past the worker
     * pool are answered by Liara with **502**, which is what a visitor arriving
     * from ترب was shown.
     *
     * **The branch's own offer is not queried here at all** and must not start
     * being: `$variant->offer` and `$variant->stock` are eager-loaded by the
     * caller, so reading them costs nothing. Only the vendors need the
     * database, and they need it once.
     *
     * Keyed by variant id, with an entry for **every** variant asked about —
     * a size nobody sells maps to an empty collection rather than being left
     * out, so a caller can tell "no sellers" from "not asked". Deciding what
     * that means stays with the caller: `ProductController` filters the empty
     * ones out, and that is what decides whether a size gets a chip.
     *
     * @param  Collection<int, Variant>  $variants
     * @return Collection<int, Collection<int, array{vendor: ?Vendor, offer: object, available: int}>>
     */
    public function forMany(Collection $variants): Collection
    {
        $vendorOffers = $variants->isEmpty()
            ? collect()
            : VendorOffer::query()
                ->with('vendor')
                ->whereIn('variant_id', $variants->pluck('id')->all())
                ->sellable()
                ->orderBy('price')
                ->get()
                ->groupBy('variant_id');

        return $variants
            ->mapWithKeys(function (Variant $variant) use ($vendorOffers): array {
                $sellers = collect();

                // `isSellable()` and not the offer's status alone. The two
                // are not the same question and the difference is what put a
                // retired size back on a product page: «بازنشسته کن» writes
                // `variants.status`, which this used to read straight past —
                // so the chip stayed, the basket priced it, and only the
                // checkout's transaction said «در این شعبه فروخته نمی‌شود»,
                // after the customer had typed their address. Nothing here
                // queries: `offer` and `stock` are eager-loaded by the caller,
                // which is the whole reason this method exists.
                if ($variant->isSellable()) {
                    $sellers->push(['vendor' => null, 'offer' => $variant->offer, 'available' => $variant->sellableStock()]);
                }

                $vendorOffers->get($variant->id, collect())
                    ->each(fn (VendorOffer $offer) => $sellers->push([
                        'vendor' => $offer->vendor,
                        'offer' => $offer,
                        'available' => $offer->sellable_stock,
                    ]));

                return [$variant->id => $sellers->sortBy(fn (array $seller) => $seller['offer']->price)->values()];
            });
    }

    /**
     * The offer a basket line or an order line is priced from, and **null
     * when that seller is not offering it**.
     *
     * One place that answers "who is selling this", so the basket, the
     * checkout and the product page cannot disagree about it. They did:
     * this used to hand back the row whatever its switch said, so a size
     * whose variant had been retired — or whose price row had been turned off
     * on `/admin/pricing` — still had a price in the basket, was still added
     * into the total, and still got an «ادامه» button, while `PlaceOrder`
     * refused it inside the transaction with «… در این شعبه فروخته نمی‌شود».
     * The customer met that sentence after filling in their address, and the
     * basket page, which has a mark for exactly this line, never drew it.
     *
     * Stock is deliberately **not** asked here: a seller who has run out is
     * still the seller, and the basket has a different sentence for that.
     * `availableFrom()` is the stock half.
     */
    public function offerFor(Variant $variant, ?int $vendorId): ?object
    {
        if ($vendorId === null) {
            return $variant->isListed() ? $variant->offer : null;
        }

        $offer = VendorOffer::query()
            ->with('vendor')
            ->where('variant_id', $variant->id)
            ->where('vendor_id', $vendorId)
            ->first();

        return $offer?->isListed() ? $offer : null;
    }

    /**
     * How many that seller can supply, and none at all when they are not
     * offering it — so a delisted size cannot be added, and the basket's
     * stepper cannot raise one that is already there.
     */
    public function availableFrom(Variant $variant, ?int $vendorId): int
    {
        if ($vendorId === null) {
            return $variant->isListed() ? $variant->sellableStock() : 0;
        }

        $offer = $this->offerFor($variant, $vendorId);

        return $offer instanceof VendorOffer && $offer->isSellable() ? $offer->sellable_stock : 0;
    }
}
