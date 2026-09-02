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
        'samba-sandal-pink',
    ];

    /**
     * The photograph the client chose to lead each set, by content.
     *
     * **This replaced a measurement that did not survive contact.** The rule —
     * «عکس اول در همه موردا باید این عکسی باشه که از این زاویس نه عکس دوتایی» —
     * looked measurable: mask the shoe off the ground, take its bounding box,
     * and a single shoe in profile is 2.65–2.89 as wide as it is tall while a
     * pair is at or under 1.76. That held for four sets and then failed on the
     * fifth: the pink profile shot reads 1.84 against 1.81 for a pair shot in
     * the same set, because it is framed smaller on a ground with more of a
     * gradient. A classifier tuned until it agrees is not a measurement, and a
     * test that rejects a photograph the shop wants to sell with is worse than
     * no test.
     *
     * So this pins the file instead. It is exact, it cannot be wrong about a
     * legitimate photograph, and it fails loudly the one way that matters: if
     * a directory is re-sorted and a pair shot ends up leading, the hash at
     * position 1 changes and the set is named.
     *
     * @var array<string, string>
     */
    private const LEADS = [
        'samba-sandal-black' => '1b14596f3d0a07765d228fb273895e87',
        'samba-sandal-brown' => '58dcd98b23146fd46eaa4ecc0c8a7ecc',
        'samba-sandal-navy' => 'd43abe2a30b0f1f7478160794a33e0b4',
        'samba-sandal-lightblue' => 'c2db486d8ca31292efaf3ebe42815395',
        'samba-sandal-pink' => 'c5853aec47b1d9f44657325716aceb27',
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

    /** Each set still leads with the photograph the client picked for it. */
    public function test_each_set_leads_with_the_photograph_the_client_chose(): void
    {
        foreach (self::SETS as $set) {
            $this->assertSame(
                self::LEADS[$set],
                md5_file($this->filesIn($set)[0]),
                "The first photograph of {$set} is not the one that was chosen for it."
            );
        }
    }

    /** And the sets are different shoes, not one copied about. */
    public function test_the_sets_are_all_different_shoes(): void
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

        $this->assertSame([
            'assets/img/product/samba-sandal-black/1.jpg',
            'assets/img/product/samba-sandal-black/2.jpg',
        ], $product->media()->pluck('path')->all(), 'The supplier’s photograph is still there.');
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
