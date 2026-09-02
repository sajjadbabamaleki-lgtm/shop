<?php

use App\Support\Catalogue\ReplacePhotos;
use Illuminate\Database\Migrations\Migration;

/**
 * The shop's own photographs of four Samba sandals.
 *
 * «برو تو سامباها این رنگو پیدا کن و این عکسارو بزار جاش» — four sets, sent one
 * after another, of the shop's own studio shots.
 *
 * **Why they were needed.** The photographs these replace came from the
 * supplier with the shoe already running out of its frame — the toe and the
 * heel flat against the edges. That was measured rather than guessed: Chromium
 * on the live listing reported 100.0% of the file's width and height on screen
 * for every card, so nothing here was cropping them. The pixels were missing
 * from the file, and the only remedy for that is a new photograph.
 *
 * **Which set went on which product.** The colour is in every slug, and the
 * four sets are unambiguous — black, brown, navy, light blue. The one real
 * question was «قهوه ای» against «کرم قهوه ای», two names for two browns, and
 * the measurement settled it: on the darkest tenth of the frame the client's
 * brown reads r−b +15, «قهوه ای» +11 and «کرم قهوه ای» +8, so the warmer of
 * the two is the match.
 *
 * **The first photograph is the side profile of one shoe** — «عکس اول در همه
 * موردا باید این عکسی باشه که از این زاویس نه عکس دوتایی». That is the one
 * every card draws at 177px, and a single shoe from the side is the shape the
 * eye reads at that size; a pair at three-quarters is two small shoes and a
 * gap. The rest follow in the order they arrived, with the top-down flat lay
 * last.
 *
 * `SambaPhotographsTest` holds that leading photograph by its content rather
 * than by measuring it. Measuring was tried — the shoe's bounding box, where a
 * profile runs 2.65–2.89 wide for one tall against 1.76 or less for a pair —
 * and it worked for four sets and then read 1.84 against 1.81 on the fifth,
 * whose ground has more of a gradient. See the note on `LEADS` there.
 *
 * The black set is six rather than five: its profile shot came after the other
 * five had been placed, so it went in front of them.
 *
 * A migration and not a seeder — `catalogue:seed` runs only on an empty
 * catalogue and this shop has 148 products. And not the panel, only because
 * the files had to reach the server first; anything the panel *can* do belongs
 * in the panel.
 */
return new class extends Migration
{
    /** @var array<string, string> slug suffix => the directory the files are in */
    private const SETS = [
        'رنگ-سفید-مشکی' => 'samba-sandal-black',
        'رنگ-قهوه-ای' => 'samba-sandal-brown',
        'رنگ-سفید-سرمه-ای' => 'samba-sandal-navy',
        'رنگ-سفید-آبی-روشن' => 'samba-sandal-lightblue',
        'رنگ-سفید-صورتی' => 'samba-sandal-pink',
    ];

    private const BASE = 'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-';

    public function up(): void
    {
        $photographs = [];

        foreach (self::SETS as $suffix => $directory) {
            // Counted off the disk rather than fixed at five: the black set is
            // six, because its profile shot arrived after the other five and
            // went in front of them. A number written here would have shipped
            // a product missing its last photograph and gone green doing it.
            $paths = [];

            for ($n = 1; is_file(public_path("assets/img/product/{$directory}/{$n}.jpg")); $n++) {
                $paths[] = "assets/img/product/{$directory}/{$n}.jpg";
            }

            $photographs[self::BASE.$suffix] = $paths;
        }

        ReplacePhotos::run($photographs);
    }

    public function down(): void
    {
        // Nothing. The photographs this replaced are the supplier's cropped
        // ones; putting them back would restore the fault the client asked to
        // have fixed, and their rows are gone either way.
    }
};
