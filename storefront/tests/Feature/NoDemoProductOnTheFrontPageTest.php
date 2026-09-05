<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FrontPagePlacement;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **No front-page band may be pointed at one of this repository's seeded
 * sneakers.**
 *
 * «چرا روش میزنم میبره به این صفحه فیک؟ هر کفش در فروشگاه یه صفحه مخصوص خود اون
 * محصولو داره.» The client clicked the special offer and landed on
 * `/products/on-cloudtilt` — seeded copy, seeded colourways, a price nobody
 * chose. The band had been pointed there by a migration written here, where
 * `on-cloudtilt` is the only On there is.
 *
 * **That has now cost three rounds.** «پیشنهاد روز» was on `new-balance-530`
 * and was moved off it for exactly this reason; the next change moved it onto
 * `on-cloudtilt`; the round after that had to move it again. Every time, the
 * cause is the same: this repository seeds five sneakers, those five are what
 * a session can see and test against, and production is a shop with hundreds
 * of real ones. Reaching for a seed is the path of least resistance and it
 * always ships a fake page.
 *
 * So this reads the migrations themselves rather than the database, because
 * the database in a test has only the seeds in it and could never tell the
 * difference. A migration that writes a placement naming a seeded slug fails
 * here, at the moment it is written, which is the only moment anybody is
 * looking.
 *
 * **It is not a ban on the slugs.** A migration may rename a seeded product,
 * reprice it, retire it or read it — `2026_09_04_180000` renames one and must
 * go on doing so. What it may not do is put one on the front page.
 */
class NoDemoProductOnTheFrontPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The five this repository seeds a fresh install with.
     *
     * Taken from `CatalogueSeeder` rather than typed, so a sixth seed cannot be
     * added without this list growing to match it.
     */
    private function seededSlugs(): array
    {
        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());

        return Product::query()->pluck('slug')->all();
    }

    /**
     * **The ones that already shipped, and this list may only ever shrink.**
     *
     * Every file here writes a front-page placement naming a seeded sneaker.
     * They have all run in production, so they are the record of what happened
     * and must not be edited — a migration that has already run is history, and
     * rewriting it changes nothing on the live site while making the next
     * incident unexplainable. They are corrected by a *later* migration, not by
     * editing these.
     *
     * The list is here rather than the test being deleted because it is the
     * thing that stops an eighth: adding a file to it is a deliberate act with
     * this comment attached, and the failure message says what to do instead.
     *
     * What is still wrong on the live shop, and is the reason this list is not
     * empty: three of the six best-seller tiles and the tiles' padding still
     * point at seeds. That is a separate fix and needs the real slugs read off
     * the shop first.
     */
    private const ALREADY_SHIPPED = [
        '2026_09_02_190000_put_the_six_shoes_the_client_chose_on_the_best_sellers.php',
        '2026_09_04_120000_make_the_best_sellers_six_different_shoes.php',
        '2026_09_04_140000_put_the_chicago_jordan_in_the_hero_and_the_row.php',
        '2026_09_04_160000_call_the_on_brand_by_its_name_and_move_the_hero.php',
        '2026_09_05_140000_the_on_running_is_the_special_offer_at_twenty_off.php',
        // Names `on-cloudtilt` only to find the row it is replacing — it is the
        // migration that takes the demo product *off* two bands.
        '2026_09_05_170000_the_on_running_bands_point_at_the_shop_s_own_shoe.php',
    ];

    public function test_no_migration_puts_a_seeded_sneaker_on_a_front_page_band(): void
    {
        $seeded = $this->seededSlugs();

        $this->assertNotEmpty($seeded, 'The catalogue seeder produced nothing, so this case cannot fail.');

        $offenders = [];

        foreach (File::files(database_path('migrations')) as $file) {
            if (in_array($file->getFilename(), self::ALREADY_SHIPPED, true)) {
                continue;
            }

            $source = $file->getContents();

            // Only migrations that actually write a placement are of interest.
            // A rename, a reprice or a retirement may name a seed all it likes.
            if (! str_contains($source, 'FrontPagePlacement')) {
                continue;
            }

            // The bands whose rows carry a product a customer can click
            // through to. `.claude` aside, every band on `FrontPage::BANDS` is
            // one, so this looks for any placement write at all.
            if (! str_contains($source, "'band'") && ! str_contains($source, 'band')) {
                continue;
            }

            foreach ($seeded as $slug) {
                // The slug has to appear as a PHP string for it to be a slug
                // and not, say, a word inside a comment about the incident.
                if (str_contains($source, "'".$slug."'")) {
                    $offenders[] = $file->getFilename().' names '.$slug;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A migration writes a front-page placement and names one of this repository's seeded sneakers.\n".
            "Those five exist only in a fresh install; production is a shop with hundreds of real products,\n".
            "and a band pointed at a seed sends every click to a page the shop does not own.\n".
            "Read the real slug off the live shop and name that instead.\n".
            'Offenders: '.implode('; ', $offenders)
        );
    }

    /** A name in the history list that no longer exists is a stale exemption. */
    public function test_every_exempted_migration_is_really_there(): void
    {
        foreach (self::ALREADY_SHIPPED as $name) {
            $this->assertFileExists(
                database_path('migrations/'.$name),
                'The exemption list names a migration that is not here. Take the line out.'
            );
        }
    }

    /**
     * And the shape that made the last one invisible: a placement whose product
     * is not in the database at all is skipped, so a slug written from memory
     * is a silent no-op rather than an error.
     *
     * This does not fix that — it cannot, from here — but it pins the behaviour
     * so nobody later reads a green deploy as proof the row was written.
     */
    public function test_a_placement_for_a_product_that_is_not_here_writes_nothing(): void
    {
        $this->seededSlugs();

        $this->assertNull(
            Product::query()->where('slug', 'کتونی-آن-رانینگ-ON-Running-رنگ-مشکی')->value('id'),
            'The live shop\'s On Running is in this database, so this case is not testing anything.'
        );

        $before = FrontPagePlacement::query()->count();

        (require database_path(
            'migrations/2026_09_05_170000_the_on_running_bands_point_at_the_shop_s_own_shoe.php'
        ))->up();

        $this->assertSame($before, FrontPagePlacement::query()->count());
    }
}
