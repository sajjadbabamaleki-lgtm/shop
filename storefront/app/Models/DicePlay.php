<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's throw.
 *
 * Branch-scoped like everything else a shop owns: a franchise runs its own
 * game with its own prize, and Shiraz's winners are not Tehran's.
 */
class DicePlay extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'customer_id', 'player_key',
        'first_die', 'second_die', 'won', 'discount_code_id',
    ];

    protected function casts(): array
    {
        return ['won' => 'boolean', 'first_die' => 'integer', 'second_die' => 'integer'];
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
