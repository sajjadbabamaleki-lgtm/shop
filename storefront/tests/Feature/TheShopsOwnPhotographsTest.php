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
 * The shop's own photographs, and the machinery that put them up.
 *
 * The migrations themselves do nothing here — they name products from the live
 * shop and the seeded catalogue is five sneakers — so the two halves are tested
 * apart: `ReplacePhotos` against products this file makes, and the files
 * against the disk.
 *
 * **The file check is the one that matters.** A migration that points a product
 * at a photograph nobody committed goes green in CI and shows a broken image on
 * every card, and the shop has been bitten by exactly that shape of failure
 * before — a manifest naming a file that was not there.
 *
 * It was `SambaPhotographsTest` while every set was a Samba. The ninth was not,
 * and the checks were never about the shoe.
 */
class TheShopsOwnPhotographsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every set a migration puts on a product.
     *
     * Sets that are on disk but *not* wired to anything are deliberately out:
     * three arrived without it being clear which shoe they are of, and are
     * waiting on the client to say. A test that pinned them would be holding
     * a decision nobody has made.
     */
    private const SETS = [
        'samba-sandal-black',
        'samba-sandal-brown',
        'samba-sandal-navy',
        'samba-sandal-lightblue',
        'samba-sandal-pink',
        'slipper-woven-silver',
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
     * **`theme/make-product-photos.js` changes these**, because cutting a
     * photograph to 1400 re-encodes it. That is the pin's one cost and it is
     * paid on purpose: it cannot tell a re-cut from a re-order, so a run that
     * touched a wired set means reading the six hashes again. The script says
     * so when it finishes.
     *
     * @var array<string, string>
     */
    private const LEADS = [
        'samba-sandal-black' => '1b14596f3d0a07765d228fb273895e87',
        'samba-sandal-brown' => 'ddf66bc609f7f1a5337da4be5600fd2e',
        'samba-sandal-navy' => 'dc58693da199053eaa5133885ebbc930',
        'samba-sandal-lightblue' => '1e0f3831140c8bdb1c2a15e5570c71c8',
        'samba-sandal-pink' => 'aa61959c90de8697c9ff5c2cb6c65f3f',
        'slipper-woven-silver' => '747159e85393dcb4534f42d97007a628',
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
     * No product photograph is wider than the site can draw.
     *
     * **Every set on disk, not only the wired ones**, because the cost is paid
     * the moment a file is committed and a set is wired later without anybody
     * looking at it again. The studio files arrive at 2560 — 0.6 to 1.1MB each,
     * 3.4MB for one shoe — and the largest box this shop has for a photograph
     * is about 600 CSS pixels, so a 2x screen can use 1200. A JPEG is already
     * compressed, so the server's gzip does nothing about the rest.
     *
     * `theme/make-product-photos.js` is the fix, and it is resize only. 1400 is
     * a ceiling and not a size: the black profile shot is 1125 and is fine.
     */
    public function test_no_product_photograph_is_wider_than_the_site_can_draw(): void
    {
        foreach (glob(public_path('assets/img/product/*/*.jpg')) as $path) {
            $size = getimagesize($path);
            $name = basename(dirname($path)).'/'.basename($path);

            $this->assertLessThanOrEqual(
                1400,
                $size[0],
                "{$name} is {$size[0]} wide. Run node theme/make-product-photos.js."
            );
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

    // --- finding the product by its name ------------------------------------

    /** The shop's five woven mules, as it names them. */
    private function theFiveSlippers(): void
    {
        foreach (['سفید', 'طلایی', 'مشکی', 'نقره ای', 'کرم'] as $colour) {
            Product::create([
                'slug' => 'اسلیپر-حصیری-رنگ-'.str_replace(' ', '-', $colour),
                'title' => "اسلیپر حصیری رنگ {$colour}",
                'status' => 'active',
                'published_at' => now()->subDay(),
            ]);
        }
    }

    public function test_it_finds_the_one_product_whose_name_carries_every_word(): void
    {
        $this->theFiveSlippers();

        $this->assertSame(
            'اسلیپر-حصیری-رنگ-نقره-ای',
            ReplacePhotos::theOneProductNamed('اسلیپر', 'نقره')
        );
    }

    /**
     * And it finds it however the name was typed.
     *
     * The colour is «نقره ای» on one keyboard and «نقره‌ای» — with a zero-width
     * non-joiner — on another, and they are the same word to a reader and two
     * different strings to `LIKE`. This is exactly the case a slug written from
     * memory gets wrong, silently.
     */
    public function test_a_zero_width_joiner_in_the_name_does_not_hide_the_product(): void
    {
        Product::create([
            'slug' => 'a-slipper',
            'title' => "اسلیپر حصیری رنگ نقره\u{200C}ای",
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->assertSame('a-slipper', ReplacePhotos::theOneProductNamed('اسلیپر', 'نقره'));
    }

    /** A word that matches two shoes names neither of them. */
    public function test_it_returns_nothing_rather_than_guessing_between_two(): void
    {
        $this->theFiveSlippers();

        $this->assertNull(ReplacePhotos::theOneProductNamed('اسلیپر'));
    }

    /** And a word the shop does not use at all finds nothing. */
    public function test_it_returns_nothing_when_no_product_matches(): void
    {
        $this->theFiveSlippers();

        $this->assertNull(ReplacePhotos::theOneProductNamed('اسلیپر', 'یاسی'));
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
