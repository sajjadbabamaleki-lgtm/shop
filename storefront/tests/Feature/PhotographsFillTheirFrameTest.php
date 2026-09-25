<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\VariantMedia;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «عکس ها تو فروشگاه بعضی هاشون ناقص افتادن و کل قابشونو پوشش نداد تو قسمت
 * جزئیات کفش هم ناقص افتادن … و همچنین این مشکلو حل کن دیگه پیش نیاد».
 *
 * A photograph fills its frame; only the design's own cut-outs are fitted
 * inside one. That used to be decided by `products.source`, which only the
 * supplier import writes, so every shoe added in the panel was framed as a
 * cut-out: small, in a grey box. It is decided by the photograph now, and
 * this holds it for the one path that had it wrong — a shoe made in the
 * panel with nothing but a `source` of null.
 */
class PhotographsFillTheirFrameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    private function panelProduct(string $path): Product
    {
        // A seeded shoe, stripped of anything that says where it came from,
        // with an uploaded photograph as its main one — which is exactly what
        // a product made in the panel looks like.
        $product = Product::where('slug', 'golden-goose')->firstOrFail();
        $product->forceFill(['source' => null, 'source_id' => null])->save();
        $product->media()->update(['is_primary' => false]);
        $product->media()->create(['path' => $path, 'position' => 0, 'is_primary' => true, 'alt' => '']);

        return $product->fresh('media');
    }

    public function test_a_photograph_uploaded_in_the_panel_fills_its_frame(): void
    {
        $product = $this->panelProduct('storage/products/boot.jpg');

        $this->assertNull($product->source);
        $this->assertTrue($product->fillsFrame());

        $this->get('/products/'.$product->slug)->assertOk()->assertSee('vp-pdp-shot is-supplied', false);
        $this->get('/products')->assertOk()->assertSee('vp-card-shot is-supplied', false);
    }

    public function test_the_designs_own_cut_outs_are_still_fitted(): void
    {
        $product = Product::where('slug', 'golden-goose')->with('media')->firstOrFail();

        $this->assertTrue($product->primaryMedia()->isCutout());
        $this->assertFalse($product->fillsFrame());
    }

    public function test_a_product_with_no_photograph_is_fitted_placeholder(): void
    {
        $product = Product::where('slug', 'golden-goose')->firstOrFail();
        $product->media()->delete();

        $this->assertFalse($product->fresh('media')->fillsFrame());
    }

    public function test_every_path_but_the_cut_out_directory_is_a_photograph(): void
    {
        foreach (['storage/products/a.jpg', 'assets/img/product/nike/1.jpg', 'https://cdn.basalam.com/x.jpg'] as $path) {
            $this->assertFalse((new VariantMedia(['path' => $path]))->isCutout(), $path);
        }

        $this->assertTrue((new VariantMedia(['path' => VariantMedia::CUTOUTS.'x.webp']))->isCutout());
    }
}
