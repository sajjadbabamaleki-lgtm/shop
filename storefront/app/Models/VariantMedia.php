<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image bound to a colourway rather than to a single variant, because all
 * sizes of one colour share the same photography.
 */
class VariantMedia extends Model
{
    use HasFactory;

    protected $table = 'variant_media';

    protected $fillable = [
        'product_id', 'color_family', 'display_color', 'path', 'alt', 'position', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /**
     * The design's own cut-outs — a shoe on nothing, drawn for the front
     * page — as against a photograph of a shoe on a surface.
     *
     * The two need opposite framing: a cut-out is fitted inside the frame,
     * because cropping one takes the toe or the heel off the silhouette; a
     * photograph fills it, because fitting a picture that already has its
     * own margin puts a margin inside a margin — the grey band «عکس ها …
     * کل قابشونو پوشش نداد» was about.
     *
     * **Decided by the file, not by who made the product.** It used to be
     * `products.source`, which only `basalam:import` writes — so every shoe
     * added in the panel, with an ordinary photograph uploaded from a phone,
     * was framed as a cut-out and drawn small in a grey box. The cut-outs are
     * the seeded few and they all live in one directory; everything uploaded,
     * imported or supplied by the shop is a photograph.
     */
    public const CUTOUTS = 'assets/img/hero/';

    public function isCutout(): bool
    {
        return str_starts_with((string) $this->path, self::CUTOUTS);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
