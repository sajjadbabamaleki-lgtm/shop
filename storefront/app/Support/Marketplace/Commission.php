<?php

namespace App\Support\Marketplace;

use App\Models\CommissionRule;
use App\Models\Variant;
use App\Models\Vendor;

/**
 * What the platform takes on one line.
 *
 * Most specific rule wins: a rule about this product beats one about its
 * category, which beats one about this vendor, which beats the platform's
 * default. Inside a scope, the highest `priority` wins, and the newest of
 * those — so two rules that both match are still resolved by something written
 * down rather than by whichever row came back first.
 *
 * No rule at all means no commission. That is deliberate: a marketplace with
 * nothing configured should take nothing, not silently invent a rate.
 */
class Commission
{
    public function ruleFor(Vendor $vendor, Variant $variant): ?CommissionRule
    {
        $product = $variant->product;
        $categories = $product?->categories->pluck('id')->all() ?? [];

        foreach (CommissionRule::SPECIFICITY as $scope) {
            $rule = CommissionRule::query()
                ->where('is_active', true)
                ->where('scope', $scope)
                ->when($scope === CommissionRule::PRODUCT, fn ($q) => $q->where('scope_id', $product?->id))
                ->when($scope === CommissionRule::CATEGORY, fn ($q) => $q->whereIn('scope_id', $categories ?: [0]))
                ->when($scope === CommissionRule::VENDOR, fn ($q) => $q->where('scope_id', $vendor->id))
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * The commission on one order line, in integer Rial.
     */
    public function on(Vendor $vendor, Variant $variant, int $lineTotal, int $quantity): int
    {
        $rule = $this->ruleFor($vendor, $variant);

        if ($rule === null) {
            return 0;
        }

        // Never more than the line itself. A fixed fee larger than a cheap
        // item would otherwise leave the vendor owing money for having sold
        // something, which is not what any of these rules mean.
        return min($rule->amountOn($lineTotal, $quantity), $lineTotal);
    }
}
