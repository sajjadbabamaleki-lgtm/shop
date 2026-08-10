<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A basket at one shop.
 *
 * It holds quantities, not prices. Every total on this class is computed from
 * the branch's live offers when it is asked for, so there is no stored number
 * anywhere that can be stale, and nothing the browser sends can influence what
 * anything costs.
 */
class Cart extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'customer_id', 'token'];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The lines, each with the branch's price and what it can actually supply.
     *
     * A line whose variant the branch has stopped selling, or has run out of,
     * stays in the basket and is reported rather than silently dropped: the
     * customer put it there, and a basket that quietly loses things is worse
     * than one that says what happened.
     *
     * @return Collection<int, array{item: CartItem, offer: ?BranchOffer, available: int, quantity: int, line_total: int}>
     */
    public function lines(): Collection
    {
        return $this->items
            ->sortBy('id')
            ->map(function (CartItem $item) {
                // Who is selling it decides the price and the shelf. Null is
                // the branch; a vendor id is somebody else's stock at
                // somebody else's price.
                $offer = $item->offer();
                $available = $item->available();

                return [
                    'item' => $item,
                    'offer' => $offer,
                    'available' => $available,
                    'quantity' => $item->quantity,
                    'line_total' => ($offer?->price ?? 0) * $item->quantity,
                ];
            })
            ->values();
    }

    /**
     * What the sellable part of the basket comes to, in Rial.
     */
    public function subtotal(): int
    {
        return (int) $this->lines()
            ->filter(fn (array $line) => $line['offer'] !== null)
            ->sum('line_total');
    }

    public function count(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Lines this branch cannot supply as asked — delisted, or short.
     *
     * Checkout refuses while this is not empty, and the basket page says which
     * line and why.
     *
     * @return Collection<int, array{item: CartItem, offer: ?BranchOffer, available: int, quantity: int, line_total: int}>
     */
    public function problems(): Collection
    {
        return $this->lines()->filter(
            fn (array $line) => $line['offer'] === null || $line['available'] < $line['quantity']
        )->values();
    }
}
