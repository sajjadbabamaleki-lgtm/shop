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
        $sellers = collect();

        $branch = $variant->offer;

        if ($branch && $branch->status === 'active' && $variant->sellableStock() > 0) {
            $sellers->push(['vendor' => null, 'offer' => $branch, 'available' => $variant->sellableStock()]);
        }

        VendorOffer::query()
            ->with('vendor')
            ->where('variant_id', $variant->id)
            ->sellable()
            ->orderBy('price')
            ->get()
            ->each(fn (VendorOffer $offer) => $sellers->push([
                'vendor' => $offer->vendor,
                'offer' => $offer,
                'available' => $offer->sellable_stock,
            ]));

        return $sellers->sortBy(fn (array $seller) => $seller['offer']->price)->values();
    }

    /**
     * The offer a basket line or an order line is priced from.
     *
     * One place that answers "who is selling this", so the basket, the
     * checkout and the product page cannot disagree about it.
     */
    public function offerFor(Variant $variant, ?int $vendorId): ?object
    {
        if ($vendorId === null) {
            return $variant->offer;
        }

        return VendorOffer::query()
            ->with('vendor')
            ->where('variant_id', $variant->id)
            ->where('vendor_id', $vendorId)
            ->first();
    }

    public function availableFrom(Variant $variant, ?int $vendorId): int
    {
        if ($vendorId === null) {
            return $variant->sellableStock();
        }

        $offer = $this->offerFor($variant, $vendorId);

        return $offer instanceof VendorOffer && $offer->isSellable() ? $offer->sellable_stock : 0;
    }
}
