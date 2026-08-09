<?php

namespace App\Domains\Orders;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Models\Variant;
use InvalidArgumentException;

/**
 * One line a customer is trying to buy, with the seller already decided.
 *
 * The seller is chosen before checkout runs, not during it. A basket holding
 * the same shoe from two vendors is two lines, and a customer who picked the
 * cheaper offer must get that offer even if a cheaper one appears while they
 * are typing their address.
 */
final class LineRequest
{
    private function __construct(
        public readonly string $sellerKind,
        public readonly Variant $variant,
        public readonly int $quantity,
        public readonly int $unitPrice,
        public readonly ?VendorOffer $vendorOffer = null,
        public readonly ?Franchise $franchise = null,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A line must be for at least one unit.');
        }
    }

    /** Sold by the platform itself, from the central catalogue. */
    public static function fromPlatform(Variant $variant, int $quantity): self
    {
        return new self('platform', $variant, $quantity, (int) $variant->price);
    }

    /** Sold by a marketplace vendor, at that vendor's price. */
    public static function fromVendorOffer(VendorOffer $offer, int $quantity): self
    {
        return new self('vendor', $offer->variant, $quantity, (int) $offer->price, $offer);
    }

    /**
     * Sold by a branch. The price is the branch's override when it has one and
     * the central price otherwise — read here rather than stored, so a branch
     * that never set a price keeps following ours.
     */
    public static function fromFranchise(Franchise $franchise, Variant $variant, int $quantity, ?int $priceOverride = null): self
    {
        return new self('franchise', $variant, $quantity, $priceOverride ?? (int) $variant->price, null, $franchise);
    }

    public function lineTotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }
}
