<?php

use App\Support\Catalogue\ReplacePhotos;
use Illuminate\Database\Migrations\Migration;

/**
 * The shop's own photographs of the silver woven mule.
 *
 * The ninth set the client sent, and the first that is not a Samba. It needed
 * no deciding: «اسلیپر حصیری» is the only backless woven style in the shop and
 * «نقره ای» is one of its five colours, so there is nothing else these four
 * photographs could be of.
 *
 * **The product is found by name, not by slug.** Every set before this one was
 * pointed at a slug read off the live listing; this one's slug has never been
 * seen from here, and a slug written from memory is the one mistake that ships
 * green and changes nothing — `ReplacePhotos::run()` skips what it cannot find,
 * by design, because the catalogue in this repository is five sneakers. So the
 * two words that cannot be wrong are given instead and
 * `theOneProductNamed()` folds both sides before comparing; see the note there.
 *
 * The first photograph is the side profile of one shoe, as with every set
 * before it — «عکس اول در همه موردا باید این عکسی باشه که از این زاویس نه عکس
 * دوتایی» — and the top-down flat lay is last.
 *
 * A migration and not a seeder: `catalogue:seed` runs only on an empty
 * catalogue, and production has not been one for weeks.
 */
return new class extends Migration
{
    private const DIRECTORY = 'slipper-woven-silver';

    public function up(): void
    {
        $slug = ReplacePhotos::theOneProductNamed('اسلیپر', 'نقره');

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
