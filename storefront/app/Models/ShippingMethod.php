<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * How a parcel travels, and how long that takes — §10's shipping methods.
 *
 * Branch-scoped like everything operational here: the post takes a different
 * number of days out of Shiraz than out of Tehran, and a single platform-wide
 * transit estimate is a promise made in one city and broken in another.
 */
class ShippingMethod extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'name', 'carrier',
        'transit_min_days', 'transit_max_days', 'price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'transit_min_days' => 'integer',
            'transit_max_days' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
