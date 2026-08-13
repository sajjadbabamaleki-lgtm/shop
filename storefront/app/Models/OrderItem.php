<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a receipt.
 *
 * Every word and every number here was copied at the moment of purchase. The
 * variant reference is for reporting and may become null; nothing displayed
 * reads through it. Renaming a product or changing its price must not change
 * what an order from last month says.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'variant_id', 'vendor_id', 'product_title', 'sku', 'size_value',
        'display_color', 'unit_price', 'compare_at_price', 'quantity', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'compare_at_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * Who fulfils it and is owed for it. Null is the branch (§9).
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function sellerName(): string
    {
        return $this->vendor?->name ?? 'ویکی پلاس';
    }
}
