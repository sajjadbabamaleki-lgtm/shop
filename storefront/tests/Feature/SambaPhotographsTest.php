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

    /** Every photograph the migration names is on disk, and is a photograph. */
    public function test_all_twenty_photographs_are_committed(): void
    {
        foreach (self::SETS as $set) {
            for ($n = 1; $n <= 5; $n++) {
                $path = public_path("assets/img/product/{$set}/{$n}.jpg");

                $this->assertFileExists($path, "{$set}/{$n}.jpg is missing.");

                $size = @getimagesize($path);

                $this->assertIsArray($size, "{$set}/{$n}.jpg is not an image.");
                $this->assertSame($size[0], $size[1], "{$set}/{$n}.jpg is not square.");
                $this->assertGreaterThanOrEqual(
                    1200,
                    $size[0],
                    "{$set}/{$n}.jpg is smaller than a product photograph should be."
                );
            }
        }
    }

    /** And the four sets are four different shoes, not the same one copied. */
    public function test_the_four_sets_are_four_different_shoes(): void
    {
        $firsts = array_map(
            fn (string $set) => md5_file(public_path("assets/img/product/{$set}/1.jpg")),
            self::SETS,
        );

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
