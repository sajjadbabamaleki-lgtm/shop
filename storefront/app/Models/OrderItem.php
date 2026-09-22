<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a receipt.
 *
 * Every word and every number here was copied at the moment of purchase. The
 * variant reference is for reporting and may become null. Renaming a product
 * or changing its price must not change what an order from last month says.
 *
 * **`photoPath()` is the one thing that deliberately reads through the
 * variant**, and it is not a hole in that rule. A photograph is not a fact
 * about the purchase — no money, no name and no number comes off it — it is a
 * picture to recognise the shoe by while somebody is packing it. So it is
 * live, it changes when the shop re-photographs the shoe, and when the
 * product has been deleted there simply is no picture. Every word of the
 * receipt is still written here and still reads the same.
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

    /**
     * The shoe's own photograph, for the panel — or null.
     *
     * «چون ما عکس هامون از باسلام برداشته شده رنگشون مشخص نیست، پس تو پنل
     * ادمین باید با عکس خود کفش به ما نشون بده چه کفشی سفارش داده تا بفهمیم
     * از رو عکس که رنگش چیه.» The supplier's titles do not say the colour in
     * a way anybody can pack from, and the line's own `display_color` is
     * «نامشخص» on most of this catalogue, so the picture is the only thing on
     * the screen that answers it.
     *
     * **The colourway's own photographs first.** `mediaFor()` scopes to this
     * line's stored colour and falls back to the product-wide ones, and that
     * fallback is safe *on this catalogue* for a reason worth stating:
     * `basalam:import` makes one product per supplier listing and a supplier
     * lists each colour separately, so a product here is a colourway and its
     * photographs are of that colour. `primaryMedia()` is the last resort for
     * a product whose photographs all carry a colour this line's does not
     * match.
     *
     * Null when the shoe has been deleted, when it has no photographs yet, or
     * when this is a vendor's line — all ordinary, and the screen draws a
     * blank rather than a broken image.
     */
    public function photoPath(): ?string
    {
        $product = $this->variant?->product;

        if ($product === null) {
            return null;
        }

        return $product->mediaFor($this->display_color)->first()?->path
            ?? $product->primaryMedia()?->path;
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
