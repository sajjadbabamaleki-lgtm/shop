<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product a shopper has saved.
 *
 * Deliberately not branch-scoped — see the migration for why a wishlist belongs
 * to the person and not to the shop they happened to be looking at. Whether a
 * saved shoe can be bought at the branch being browsed is a question the
 * *reading* answers, through the same `pricedHere` scope as every other product
 * query, and not one this row should try to have an opinion about.
 */
class WishlistItem extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'product_id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
