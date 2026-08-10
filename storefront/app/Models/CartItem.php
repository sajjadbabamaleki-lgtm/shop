<?php

namespace App\Models;

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

    protected $fillable = ['cart_id', 'variant_id', 'quantity'];

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
}
