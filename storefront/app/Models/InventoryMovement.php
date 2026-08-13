<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The stock ledger. Every receipt, sale, adjustment, return, reservation and
 * release is appended here so a branch's stock can always be explained from
 * its history (spec 4.4).
 *
 * Branch-scoped, like the stock it explains. A ledger that answered across
 * every shop would let one branch read another's sales volume from its own
 * panel, which is exactly what §18 is about.
 */
class InventoryMovement extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'variant_id', 'type', 'quantity', 'reference_type', 'reference_id', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
