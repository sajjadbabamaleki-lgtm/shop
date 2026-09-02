<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\VariantMedia;
use App\Support\Catalogue\ReplacePhotos;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop's own Samba photographs, and the machinery that put them up.
 *
 * The migration itself does nothing here — it names four products from the live
 * shop and the seeded catalogue is five sneakers — so the two halves are tested
 * apart: `ReplacePhotos` against products this file makes, and the twenty files
 * against the disk.
 *
 * **The file check is the one that matters.** A migration that points a product
 * at a photograph nobody committed goes green in CI and shows a broken image on
 * every card, and the shop has been bitten by exactly that shape of failure
 * before — a manifest naming a file that was not there.
 */
class SambaPhotographsTest extends TestCase
{
    use RefreshDatabase;

    /** The four sets, as the migration names them. */
    private const SETS = [
        'samba-sandal-black',
        'samba-sandal-brown',
        'samba-sandal-navy',
        'samba-sandal-lightblue',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());
    }

    private function product(string $slug): Product
    {
        return Product::create([
            'slug' => $slug,
            'title' => 'صندل ادیداس سامبا چسبی',
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);
    }

    // --- the twenty files ---------------------------------------------------

    /** @return list<string> the set's photographs, in the order it offers them */
    private function filesIn(string $set): array
    {
        $files = [];

        for ($n = 1; is_file($p = public_path("assets/img/product/{$set}/{$n}.jpg")); $n++) {
            $files[] = $p;
        }

        return $files;
    }

    /**
     * Every photograph is on disk, is a photograph, and is shaped like one.
     *
     * **Not exactly square, and not 1400.** Those were this file's first two
     * assertions and both were house rules rather than facts about the goods:
     * the black profile shot the client sent is 1125×1116, which is 0.8% off
     * square and perfectly fine in a square tile. A test that fails on a real
     * photograph the shop wants to sell with is a test that will be deleted.
     * What is worth holding is that the file exists, opens, and is not some
     * banner-shaped thing that would sit in the tile with bands round it.
     */
    public function test_every_photograph_is_committed_and_shaped_like_a_product_shot(): void
    {
        foreach (self::SETS as $set) {
            $files = $this->filesIn($set);

            $this->assertGreaterThanOrEqual(4, count($files), "{$set} has almost no photographs.");

            foreach ($files as $path) {
                $name = basename(dirname($path)).'/'.basename($path);
                $size = @getimagesize($path);

                $this->assertIsArray($size, "{$name} is not an image.");

                $ratio = $size[0] / $size[1];

                $this->assertGreaterThan(0.95, $ratio, "{$name} is much taller than it is wide.");
                $this->assertLessThan(1.05, $ratio, "{$name} is much wider than it is tall.");
                $this->assertGreaterThanOrEqual(
                    1000,
                    min($size[0], $size[1]),
                    "{$name} is too small to be a product photograph."
                );
            }
        }
    }

    /**
     * **The first photograph of every set is one shoe from the side.**
     *
     * «عکس اول در همه موردا باید این عکسی باشه که از این زاویس نه عکس دوتایی» —
     * and the difference is measurable rather than a matter of taste. Mask the
     * shoe off the studio ground and take the bounding box: a single shoe in
     * profile is 2.65–2.89 as wide as it is tall, and every pair shot in these
     * four sets is at or under 1.76. The gap between those is not close.
     *
     * This is the assertion that would catch somebody re-sorting a directory
     * and putting the pair back in front, which is exactly what happened once
     * already and had to be corrected by hand.
     */
    public function test_the_first_photograph_of_each_set_is_a_single_shoe_in_profile(): void
    {
        foreach (self::SETS as $set) {
            $first = $this->filesIn($set)[0];

            $this->assertGreaterThan(
                2.2,
                $this->shoeAspect($first),
                "The first photograph of {$set} is not a single shoe from the side."
            );
        }
    }

    /**
     * How wide the shoe is against how tall, in its own frame.
     *
     * The ground is the average of the four corners; anything far from it is
     * shoe. That is the same instrument used to tell these sets apart in the
     * first place, and it is the only one that survived — a fixed threshold
     * against the corner colour alone reported "all four edges" for every
     * studio shot, because the ground is a gradient.
     */
    private function shoeAspect(string $path): float
    {
        $im = imagecreatefromjpeg($path);
        $small = imagescale($im, 96, 96);
        imagedestroy($im);

        $at = function (int $x, int $y) use ($small): array {
            $rgb = imagecolorat($small, $x, $y);

            return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
        };

        $corners = [$at(0, 0), $at(95, 0), $at(0, 95), $at(95, 95)];
        $ground = array_map(
            fn (int $i) => (int) round(array_sum(array_column($corners, $i)) / 4),
            [0, 1, 2],
        );

        $minX = 96;
        $maxX = -1;
        $minY = 96;
        $maxY = -1;

        for ($y = 0; $y < 96; $y++) {
            for ($x = 0; $x < 96; $x++) {
                $p = $at($x, $y);
                $far = abs($p[0] - $ground[0]) + abs($p[1] - $ground[1]) + abs($p[2] - $ground[2]);

                if ($far > 70) {
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }
        }

        imagedestroy($small);

        if ($maxX < 0) {
            return 0.0;
        }

        return ($maxX - $minX + 1) / ($maxY - $minY + 1);
    }

    /** And the four sets are four different shoes, not the same one copied. */
    public function test_the_four_sets_are_four_different_shoes(): void
    {
        $firsts = array_map(fn (string $set) => md5_file($this->filesIn($set)[0]), self::SETS);

        $this->assertSame(count($firsts), count(array_unique($firsts)));
    }

    // --- what the replacement does ------------------------------------------

    public function test_it_replaces_the_old_photographs_rather_than_adding_to_them(): void
    {
        $product = $this->product('a-samba');

        VariantMedia::create([
            'product_id' => $product->id,
            'path' => 'storage/basalam/old-one.jpg',
            'position' => 0,
            'is_primary' => true,
        ]);

        ReplacePhotos::run(['a-samba' => [
            'assets/img/product/samba-sandal-black/1.jpg',
            'assets/img/product/samba-sandal-black/2.jpg',
        ]]);

        $paths = $product->media()->pluck('path')->all();

        $this->assertSame([
            'assets/img/product/samba-sandal-black/1.jpg',
            'assets/img/product/samba-sandal-black/2.jpg',
        ], $paths, 'The supplier’s photograph is still there.');
    }

    /**
     * The first one is what every card draws.
     *
     * `imagePath()` reads the primary, and a set written with none would send
     * the whole shop to the placeholder.
     */
    public function test_the_first_photograph_is_the_one_the_cards_show(): void
    {
        $product = $this->product('another-samba');

        ReplacePhotos::run(['another-samba' => [
            'assets/img/product/samba-sandal-navy/1.jpg',
            'assets/img/product/samba-sandal-navy/2.jpg',
            'assets/img/product/samba-sandal-navy/3.jpg',
        ]]);

        $this->assertSame(
            'assets/img/product/samba-sandal-navy/1.jpg',
            $product->fresh()->imagePath()
        );

        $this->assertSame([0, 1, 2], $product->media()->pluck('position')->all());
    }

    /** A slug the shop does not have is skipped, not thrown on. */
    public function test_a_slug_that_is_not_in_the_catalogue_is_skipped(): void
    {
        $written = ReplacePhotos::run(['no-such-shoe' => ['assets/img/product/samba-sandal-black/1.jpg']]);

        $this->assertSame([], $written);
    }

    /** And it touches nothing but the products it is given. */
    public function test_it_leaves_every_other_product_alone(): void
    {
        $other = Product::where('slug', 'new-balance-530')->firstOrFail();
        $before = $other->media()->pluck('path')->all();

        $this->product('yet-another-samba');
        ReplacePhotos::run(['yet-another-samba' => ['assets/img/product/samba-sandal-brown/1.jpg']]);

        $this->assertSame($before, $other->fresh()->media()->pluck('path')->all());
    }
}
