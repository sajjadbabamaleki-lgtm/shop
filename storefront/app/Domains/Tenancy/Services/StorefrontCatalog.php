<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Franchise\Models\FranchiseOffer;
use App\Domains\Franchise\Services\FulfillmentRouter;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Tenancy\Contracts\Tenant;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * What a mini store has for sale, and at what price.
 *
 * A mini store is not a filtered view of the parent catalogue — it is the
 * seller's own list, and the difference matters. A vendor's shop shows only
 * variants it has stocked, priced by the vendor. A branch's shop shows only
 * what it has taken into its assortment, priced centrally unless the branch
 * has overridden it within its limits.
 *
 * The two are answered by one class because the storefront views are the same
 * views. Where they differ — a vendor sells its own stock, a branch may fall
 * back to the central warehouse — the difference is a routing question and is
 * asked of FulfillmentRouter.
 */
class StorefrontCatalog
{
    public function __construct(private readonly FulfillmentRouter $fulfillment) {}

    public function products(Tenant $tenant, ?string $search = null, int $perPage = 24): LengthAwarePaginator
    {
        $query = Product::query()
            ->where('status', 'active')
            ->where('approval_status', 'approved')
            ->with(['brand', 'media']);

        if ($tenant instanceof Vendor) {
            $query->whereHas('variants.vendorOffers', fn ($q) => $q
                ->where('vendor_id', $tenant->id)
                ->where('status', 'active')
                ->where('sellable_stock', '>', 0)
            );
        } elseif ($tenant instanceof Franchise) {
            $query->whereHas('variants.franchiseOffers', fn ($q) => $q
                ->where('franchise_id', $tenant->id)
                ->where('is_active', true)
            );
        }

        if ($search !== null && $search !== '') {
            $query->where(fn ($q) => $q
                ->where('title', 'ilike', "%{$search}%")
                ->orWhere('short_title', 'ilike', "%{$search}%")
            );
        }

        return $query->orderByDesc('published_at')->paginate($perPage)->withQueryString();
    }

    /**
     * The colourways and sizes this store actually sells for one product, each
     * carrying the store's own price and availability.
     *
     * @return Collection<int, array{variant: Variant, price: int, compare_at: int|null, available: int, offer_id: int|null}>
     */
    public function offersFor(Tenant $tenant, Product $product): Collection
    {
        $product->loadMissing('variants');

        if ($tenant instanceof Vendor) {
            $offers = VendorOffer::query()->withoutGlobalScopes()
                ->where('vendor_id', $tenant->id)
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->with('variant')
                ->get();

            return $offers->map(fn (VendorOffer $o) => [
                'variant' => $o->variant,
                'price' => (int) $o->price,
                'compare_at' => $o->compare_at_price ? (int) $o->compare_at_price : null,
                'available' => (int) $o->sellable_stock,
                'offer_id' => $o->id,
            ])->values();
        }

        if ($tenant instanceof Franchise) {
            $offers = FranchiseOffer::query()->withoutGlobalScopes()
                ->where('franchise_id', $tenant->id)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->with('variant')
                ->get();

            return $offers->map(fn (FranchiseOffer $o) => [
                'variant' => $o->variant,
                'price' => $o->effectivePrice(),
                'compare_at' => $o->variant->compare_at_price ? (int) $o->variant->compare_at_price : null,
                'available' => $this->fulfillment->availableQuantity($tenant, $o->variant),
                'offer_id' => $o->id,
            ])->values();
        }

        return collect();
    }

    /** The cheapest thing this store sells the product for, for a listing card. */
    public function fromPriceFor(Tenant $tenant, Product $product): ?int
    {
        $offers = $this->offersFor($tenant, $product);

        return $offers->isEmpty() ? null : (int) $offers->min('price');
    }
}
