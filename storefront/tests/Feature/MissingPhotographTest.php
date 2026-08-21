<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A product with no photograph must not take a page down.
 *
 * This is an ordinary state, not an edge case: the panel creates a product
 * first and its pictures afterwards, so between those two steps every product
 * in this shop has no photograph. `primaryMedia()` returns null for one, and
 * three views on the **home page** — the hero, the stepped sale and the daily
 * deal — read `->path` off it with no check. The first unphotographed product
 * the catalogue hands to any of those three would 500 the front page for
 * everybody, over «عکس نذاشتم».
 *
 * It was invisible because every seeded product has photographs, so no test
 * and no screenshot could ever reach it. It was found by cloning the catalogue
 * tenfold and rendering the page again.
 */
class MissingPhotographTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
    }

    /** Take every photograph away — the state a fresh catalogue is in. */
    private function stripEveryPhotograph(): void
    {
        app(TenantContext::class)->forBranch(Branch::central(), function (): void {
            foreach (Product::with('media')->get() as $product) {
                $product->media()->delete();
            }
        });
    }

    public function test_the_home_page_survives_a_catalogue_with_no_photographs(): void
    {
        $this->stripEveryPhotograph();

        $this->get('/')->assertOk();
    }

    public function test_every_shop_page_survives_it_too(): void
    {
        $this->stripEveryPhotograph();

        foreach (['/products', '/products/new-balance-530', '/cart', '/categories/sneaker'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    /**
     * And what they show instead is an image, not a hole.
     *
     * The placeholder is a file that has to exist: a path returned by
     * `imagePath()` that 404s would trade a 500 for a broken image on every
     * card, which is quieter and still wrong.
     */
    public function test_the_placeholder_is_a_file_that_is_really_there(): void
    {
        $this->stripEveryPhotograph();

        $product = Product::firstOrFail();
        $path = $product->imagePath();

        $this->assertSame('assets/img/product-placeholder.svg', $path);
        $this->assertFileExists(public_path($path));
    }

    /** With a photograph, nothing changes: the real one is still used. */
    public function test_a_product_with_a_photograph_still_shows_it(): void
    {
        $product = Product::with('media')->whereHas('media')->firstOrFail();

        $this->assertSame($product->primaryMedia()->path, $product->imagePath());
        $this->assertStringNotContainsString('placeholder', $product->imagePath());
    }

    /**
     * **No view reads the nullable one directly any more.**
     *
     * Six call sites had this shape and three of them had their own `@if`,
     * which is how the other three looked deliberate. Asserted as a rule over
     * the views rather than as four page requests, because the next view to be
     * written is the one this is for.
     */
    public function test_no_view_reads_the_nullable_media_path_directly(): void
    {
        $views = shell_exec('grep -rl -- "primaryMedia()->path" '.escapeshellarg(resource_path('views')).' 2>/dev/null');

        $this->assertSame('', trim((string) $views), "these views read a nullable path directly:\n".$views);
    }
}
