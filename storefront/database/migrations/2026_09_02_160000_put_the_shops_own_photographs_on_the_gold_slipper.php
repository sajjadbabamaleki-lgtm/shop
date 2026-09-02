<?php

use App\Support\Catalogue\ReplacePhotos;
use Illuminate\Database\Migrations\Migration;

/**
 * The shop's own photographs of the gold woven mule.
 *
 * The tenth set, and the same shoe as the silver one an hour before it in a
 * different colour, so it needed no deciding either: «اسلیپر حصیری» is the only
 * backless woven style in the shop and «طلایی» one of its five colours.
 *
 * Found by name rather than by slug, for the reason set out on
 * `ReplacePhotos::theOneProductNamed()` — a slug written from memory is the one
 * mistake that deploys green and changes nothing.
 *
 * Side profile of one shoe first, top-down flat lay last.
 */
return new class extends Migration
{
    private const DIRECTORY = 'slipper-woven-gold';

    public function up(): void
    {
        $slug = ReplacePhotos::theOneProductNamed('اسلیپر', 'طلایی');

        if ($slug === null) {
            return;
        }

        $paths = [];

        for ($n = 1; is_file(public_path('assets/img/product/'.self::DIRECTORY."/{$n}.jpg")); $n++) {
            $paths[] = 'assets/img/product/'.self::DIRECTORY."/{$n}.jpg";
        }

        ReplacePhotos::run([$slug => $paths]);
    }

    public function down(): void
    {
        // Nothing. What these replaced are the supplier's own photographs, and
        // their rows are gone either way.
    }
};
