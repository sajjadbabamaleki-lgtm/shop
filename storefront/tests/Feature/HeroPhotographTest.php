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

    /**
     * **The button on a slide buys the shoe on that slide.**
     *
     * «کارتهای هیرو لینک میشن به فروشگاه، این اشتباهه، باید لینک بشن به همون
     * محصول.» It said «خرید محصول» and went to the listing — the whole grid —
     * so somebody who wanted the shoe in front of them had to go and find it
     * again among hundreds. The photograph, the heading and the button all come
     * off `$slide['product']` now, so the three cannot disagree about which
     * shoe a slide is.
     *
     * Asserted on the rendered page and not on the view data: the href is the
     * whole point, and view data would pass with the old `page_url('shop.html')`
     * still in the markup.
     */
    public function test_each_slide_buys_the_shoe_it_shows(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $buttons = preg_match_all(
            '/<div class="btn-group"[^>]*><a href="([^"]+)" class="th-btn th-icon">/',
            $page,
            $found
        );

        $slides = $this->slides();

        $this->assertSame(
            count($slides),
            $buttons,
            'The hero has a different number of buy buttons than it has slides.'
        );

        foreach ($slides as $i => $slide) {
            $this->assertSame(
                storefront_route('product', $slide['product']),
                html_entity_decode($found[1][$i]),
                "Slide {$i} shows {$slide['product']->slug} and its button goes somewhere else."
            );
        }

        // And the listing is not among them, which is the exact thing that was
        // wrong: a button that went to /products was indistinguishable from one
        // that worked until somebody pressed it.
        $this->assertNotContains(storefront_route('shop'), array_map('html_entity_decode', $found[1]));
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

    /**
     * And the phone-sized copy of it is there too.
     *
     * `photo_srcset()` offers a 700-wide copy of any photograph the manifest
     * lists, and it lists these — they go through `make-photo-sizes.js` like
     * every other shot. But **`sync-storefront-assets.js` copies what the page
     * references**, and a photograph chosen in a config file is referenced by
     * neither copy of the home page: the crawl cannot see it, so the file sat
     * in `download-version/` and only `storefront/` is deployed. The large one
     * failing is a grey box on the live front page; the small one failing is a
     * grey box on a telephone only, which is worse, because that is the device
     * nobody here is looking at.
     *
     * Both maps, because both are read the same way and neither is on the page.
     */
    public function test_every_config_photograph_is_on_disk_at_both_sizes(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('assets/img/photo-sizes.json')),
            true
        )['photos'] ?? [];

        $named = array_merge(
            config('storefront.hero.photos', []),
            config('storefront.placeholders.best_sellers.photos', []),
        );

        $this->assertNotEmpty($named, 'nothing is overridden, so this test is not testing anything');

        foreach ($named as $slug => $path) {
            $this->assertFileExists(public_path($path), "{$slug}'s photograph is not in public/.");

            if (isset($manifest[$path]['small'])) {
                $this->assertFileExists(
                    public_path($manifest[$path]['small']),
                    "{$slug}'s photograph is offered at 700 wide and that copy is not in public/."
                );
            }
        }
    }

    /**
     * Every photograph the deck can draw has a mark tone behind it.
     *
     * The blurred shape behind the hero takes its colour from the slide's
     * photograph, through a map of filename ⇒ tint that lives in the Blade —
     * and **in `theme/make-rtl-page.js` as well**, because the static preview
     * draws the same deck. Two copies of one table is the arrangement, and it
     * drifted the first time a photograph was renamed: the storefront looked
     * up a filename its map did not have, `tone` came back undefined, and the
     * shape simply kept the colour of whichever slide came before — pink glow
     * behind a black shoe. Nothing threw, nothing logged, and it took
     * `check-parity.js` reporting 48,441 pixels to find it.
     *
     * So: every file `hero.photos` names, and every catalogue photograph a
     * slide falls back to, must appear in the Blade's map.
     */
    public function test_every_hero_photograph_has_a_mark_tone(): void
    {
        $blade = (string) file_get_contents(resource_path('views/home/hero.blade.php'));

        $this->assertTrue(
            (bool) preg_match('/var tones = (\{.*?\});/s', $blade, $found),
            'the hero no longer declares its mark tones the way this test reads them'
        );

        $tones = json_decode($found[1], true);

        $this->assertNotEmpty($tones, 'the tone map did not parse as JSON');

        foreach ($this->slides() as $slide) {
            $file = basename($slide['photo']);

            $this->assertArrayHasKey(
                $file,
                $tones,
                "{$slide['product']->slug}'s slide draws {$file}, and the mark behind it has no tone — "
                .'it will keep the previous slide\'s colour.'
            );
        }

        foreach (config('storefront.hero.photos', []) as $slug => $path) {
            $this->assertArrayHasKey(
                basename($path),
                $tones,
                "{$slug}'s cut-out has no mark tone."
            );
        }
    }

    /**
     * The heading is the shoe, not the shoe and its colourway.
     *
     * «اسم جردن و گلدن گوس هم طولانی هست، نباید بزنی ساق بلند یا رنگ مشکی» —
     * the shop's titles carry the height and the colour because eight New
     * Balances and ten Air Jordans differ by nothing else on a listing. In the
     * hero, where one shoe stands alone and its colour is in the photograph,
     * they are three lines of type.
     *
     * The live titles are not in this database, so the rule is exercised
     * directly against the shapes they have.
     */
    public function test_the_heading_drops_the_height_and_the_colourway(): void
    {
        $cases = [
            'کتونی نایک جردن وان ساق بلند' => 'کتونی نایک جردن وان',
            'کتونی گلدن گوس رنگ مشکی' => 'کتونی گلدن گوس',
            'کتونی نیوبالانس رنگ سفید مشکی' => 'کتونی نیوبالانس',
            'نایک جردن تراویس اسکات رنگ یشمی' => 'نایک جردن تراویس اسکات',
            // Nothing to take off, so nothing comes off.
            'کتونی آن رانینگ' => 'کتونی آن رانینگ',
        ];

        foreach ($cases as $title => $expected) {
            $this->assertSame($expected, (new Product(['title' => $title]))->frontPageName());
        }
    }

    /**
     * And the listing keeps them, which is the other half of the rule.
     *
     * A card is one of a grid, and the shop carries eight New Balance 530s that
     * differ only in the words the front page takes off. Trimming there would
     * be eight identical names.
     */
    public function test_the_listing_keeps_the_colourway(): void
    {
        $product = new Product(['title' => 'کتونی نیوبالانس رنگ سفید مشکی']);

        $this->assertStringContainsString('رنگ', $product->cardName(6));
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
