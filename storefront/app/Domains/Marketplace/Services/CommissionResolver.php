<?php

namespace App\Domains\Marketplace\Services;

use App\Domains\Marketplace\Models\CommissionRule;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Models\Product;
use DateTimeInterface;

/**
 * Decides the commission rate for one sale (§2: "Commission rules configurable
 * by vendor, category or product").
 *
 * The chain, most specific first:
 *
 *   1. an override on the offer itself
 *   2. a product rule for this vendor, then a product rule for everyone
 *   3. a category rule for this vendor, then a category rule for everyone
 *   4. a vendor rule
 *   5. the rate negotiated on the vendor row
 *   6. the platform default — 9%, in config/marketplace.php
 *
 * Ties inside one step are broken by priority, then by the most recently
 * created rule, so layering an exceptional rule over a standing one never
 * requires deleting the standing one.
 *
 * A product in several categories may match several category rules. The
 * highest priority wins, and among equal priorities the newest — deterministic
 * rather than "the cheapest for whoever asks", which would make an invoice
 * depend on the order the categories happened to load.
 */
class CommissionResolver
{
    /**
     * @return int basis points; 900 is 9%
     */
    public function rateFor(Vendor $vendor, Product $product, ?VendorOffer $offer = null, ?DateTimeInterface $at = null): int
    {
        if ($offer?->commission_rate_bps !== null) {
            return (int) $offer->commission_rate_bps;
        }

        $at ??= now();
        $categoryIds = $product->categories()->pluck('categories.id')->all();

        $rules = CommissionRule::query()
            ->inForce($at)
            ->where(function ($q) use ($vendor) {
                $q->whereNull('vendor_id')->orWhere('vendor_id', $vendor->id);
            })
            ->where(function ($q) use ($product, $categoryIds, $vendor) {
                $q->where(fn ($r) => $r->where('scope', 'product')->where('product_id', $product->id))
                    ->orWhere(fn ($r) => $r->where('scope', 'category')->whereIn('category_id', $categoryIds ?: [0]))
                    ->orWhere(fn ($r) => $r->where('scope', 'vendor')->where('vendor_id', $vendor->id));
            })
            ->get();

        foreach (['product', 'category', 'vendor'] as $scope) {
            $atScope = $rules->where('scope', $scope);

            if ($atScope->isEmpty()) {
                continue;
            }

            // Within a scope, a rule naming this vendor beats a rule that
            // names no vendor: "20% on boots, but 12% on boots from this
            // seller" has to resolve to 12%.
            $vendorSpecific = $atScope->whereNotNull('vendor_id');
            $winner = $this->best($vendorSpecific->isNotEmpty() ? $vendorSpecific : $atScope);

            if ($winner !== null) {
                return (int) $winner->rate_bps;
            }
        }

        return $vendor->commissionRateBps();
    }

    /**
     * The money split for one line, in integer Rial.
     *
     * The commission is truncated and the seller takes the remainder, so the
     * two always add back to the line exactly — which is what the database's
     * `order_items_split_adds_up` constraint checks on the way in. Rounding
     * both halves independently is how a payout period ends up a few Rial
     * short of the sales it was built from.
     *
     * @return array{commission: int, payable: int}
     */
    public function split(int $lineTotal, int $rateBps): array
    {
        $commission = intdiv($lineTotal * $rateBps, 10000);

        return [
            'commission' => $commission,
            'payable' => $lineTotal - $commission,
        ];
    }

    private function best($rules): ?CommissionRule
    {
        return $rules->sortByDesc(fn (CommissionRule $r) => [$r->priority, $r->id])->first();
    }
}
