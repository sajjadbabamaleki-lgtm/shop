<?php

namespace App\Support\Checkout;

use App\Models\Variant;
use RuntimeException;

/**
 * The branch cannot supply a line as asked.
 *
 * Thrown from inside the checkout's transaction, which is the only place the
 * answer is trustworthy: every other check is a courtesy that somebody else's
 * purchase can invalidate a millisecond later.
 */
class CannotFulfil extends RuntimeException
{
    public function __construct(
        public readonly Variant $variant,
        public readonly int $wanted,
        public readonly int $available,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function soldOut(Variant $variant, int $wanted, int $available): self
    {
        $title = $variant->product?->title ?? $variant->sku;

        return new self($variant, $wanted, $available, $available > 0
            ? "از «{$title}» سایز {$variant->size_value} فقط ".fa_number($available).' عدد باقی مانده.'
            : "«{$title}» سایز {$variant->size_value} دیگر موجود نیست.");
    }

    public static function notSold(Variant $variant, int $wanted): self
    {
        $title = $variant->product?->title ?? $variant->sku;

        return new self($variant, $wanted, 0, "«{$title}» در این شعبه فروخته نمی‌شود.");
    }
}
