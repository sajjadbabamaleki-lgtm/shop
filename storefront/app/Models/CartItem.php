<?php

namespace App\Models;

use App\Support\Marketplace\Sellers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One size of one shoe, and how many of it.
 *
 * No price column, deliberately — see Cart. Not branch-scoped either: it
 * reaches its branch through the cart, and a second branch_id on the line
 * would be a second answer to the same question.
 */
class CartItem extends Model
{
    use HasFactory;

    protected $fillable = ['cart_id', 'variant_id', 'vendor_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * Who is selling it. Null is the branch, which is most lines.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * The offer this line is priced from — the branch's or a vendor's.
     *
     * Resolved rather than stored, like every other price in the basket, so a
     * line cannot hold a number that has since changed.
     */
    public function offer(): ?object
    {
        return $this->variant === null
            ? null
            : app(Sellers::class)->offerFor($this->variant, $this->vendor_id);
    }

    public function available(): int
    {
        return $this->variant === null
            ? 0
            : app(Sellers::class)->availableFrom($this->variant, $this->vendor_id);
    }

    public function sellerName(): string
    {
        return $this->vendor?->name ?? 'ویکی پلاس';
    }
}
