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
        'display_color', 'unit_price', 'compare_at_price', 'quantity', 'returned_quantity', 'line_total',
    ];

    /**
     * How many of this line the customer still has.
     *
     * The line's own `quantity` is what was bought and never changes — it is a
     * receipt. This is that minus what came back, and it is what the basket
     * sent to an instalment provider is built from.
     */
    public function remaining(): int
    {
        return max(0, (int) $this->quantity - (int) $this->returned_quantity);
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'compare_at_price' => 'integer',
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
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

    /**
     * The photograph of what was bought, or null.
     *
     * «از اونجایی که در سفارش مشتری امکان انتخاب رنگ فعلا غیره فعال هست باید
     * عکس محصول سفارش داده شده … نمایش داده بشه تا بدونیم کدوم محصولو سفارش
     * داده». The shop sells a shoe one colourway per product, and the titles
     * of those colourways are often identical, so the picture is what tells
     * the packer which box to take. The line's own colour first, then the
     * product's main shot — the same order the product page draws them in.
     *
     * Null for a line whose size has since been deleted: the receipt keeps
     * its words (`product_title`, `sku`, `size_value`) but the shoe is gone.
     */
    public function photoPath(): ?string
    {
        $product = $this->variant?->product;

        if ($product === null) {
            return null;
        }

        return $product->mediaFor($this->display_color ?: $this->variant->display_color)->first()?->path
            ?? $product->primaryMedia()?->path;
    }

    public function sellerName(): string
    {
        return $this->vendor?->name ?? 'ویکی پلاس';
    }
}
