<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The photograph a hero slide draws.
 *
 * **A hero shot has to be background-free and a catalogue shot does not.**
 * «عکس های فروشگاه بکگراند دارن و مواردی که ما تو هیرو میزاریم باید بی بکگراند
 * باشن که بشینن رو شیشه هیرو» — the slide's picture sits on the glass pane with
 * nothing behind it, so a photograph carrying the studio's ground draws a grey
 * rectangle on the pane. The catalogue's photographs are right everywhere else
 * and must not be cut for this, so `hero.photos` is an override rather than a
 * second catalogue.
 *
 * Nothing else can see this go wrong. `check-parity.js` compares the two copies
 * of the home page against each other, so a slide pointed at the wrong file is
 * a pair that still matches; and a slug whose file is missing renders an `<img>`
 * with a src that 404s, which is a broken picture on the largest thing on the
 * front page and a green test run.
 */
class HeroPhotographTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());
    }

    /** @return list<array{product: Product, photo: string}> */
    private function slides(): array
    {
        return $this->get('/')->assertOk()->viewData('heroSlides');
    }

    public function test_a_slide_with_an_override_draws_the_cut_out_and_not_the_catalogues_photograph(): void
    {
        $photos = config('storefront.hero.photos');

        $this->assertNotEmpty($photos, 'nothing overrides its photograph, so this test is not testing anything');

        foreach ($this->slides() as $slide) {
            $slug = $slide['product']->slug;

            if (! isset($photos[$slug])) {
                continue;
            }

            $this->assertSame($photos[$slug], $slide['photo'], "{$slug}'s slide is not drawing its cut-out.");
            $this->assertNotSame(
                $slide['product']->imagePath(),
                $slide['photo'],
                "{$slug}'s override names the same file the catalogue does, which makes it pointless."
            );
        }
    }

    /** And a slide without one falls back to the product's own, as every slide did before. */
    public function test_a_slide_with_no_override_still_draws_the_products_own_photograph(): void
    {
        $photos = config('storefront.hero.photos');
        $checked = 0;

        foreach ($this->slides() as $slide) {
            if (isset($photos[$slide['product']->slug])) {
                continue;
            }

            $this->assertSame($slide['product']->imagePath(), $slide['photo']);
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'every slide is overridden, so the fallback is untested');
    }

    /**
     * Every file the override names is on disk.
     *
     * A src that 404s is a broken picture on the largest thing on the front
     * page, and nothing else here would notice: the test suite never fetches
     * the image, and the two copies of the page would name the same missing
     * file.
     */
    public function test_every_photograph_the_override_names_exists(): void
    {
        foreach (config('storefront.hero.photos') as $slug => $path) {
            $this->assertFileExists(public_path($path), "{$slug}'s hero photograph is not in public/.");
        }
    }

    /**
     * And it is a cut-out, which is the whole point of the override.
     *
     * A WebP with no alpha channel cannot be background-free, so this catches
     * the one mistake that would look deliberate: somebody pointing the
     * override at an ordinary catalogue shot.
     */
    public function test_every_photograph_the_override_names_is_background_free(): void
    {
        foreach (config('storefront.hero.photos') as $slug => $path) {
            $image = @imagecreatefromwebp(public_path($path));

            $this->assertNotFalse($image, "{$slug}'s hero photograph could not be read as WebP.");

            // The corner is background if the shot is a cut-out. imagecolorat
            // packs alpha into bits 24-30, where 127 is fully transparent.
            $alpha = (imagecolorat($image, 0, 0) >> 24) & 0x7F;
            imagedestroy($image);

            $this->assertSame(127, $alpha, "{$slug}'s hero photograph has a background behind the shoe.");
        }
    }

    /** The deck is what config asks for, in the order it asks for it. */
    public function test_the_deck_is_the_three_the_file_names(): void
    {
        $slugs = array_keys(config('storefront.hero.products'));

        $this->assertSame(
            $slugs,
            collect($this->slides())->take(count($slugs))->pluck('product.slug')->all()
        );
    }
}
