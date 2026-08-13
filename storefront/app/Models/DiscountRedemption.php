<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One use of one code.
 *
 * This is what makes "fifty uses" and "once per customer" true rather than
 * approximately true — the limits are counted from these rows, inside the same
 * transaction that places the order — and it is what lets somebody afterwards
 * ask what a campaign actually cost.
 */
class DiscountRedemption extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['discount_code_id', 'order_id', 'customer_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'created_at' => 'datetime'];
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
