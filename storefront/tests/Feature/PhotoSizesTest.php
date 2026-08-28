<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every photograph on the page is offered at the size the screen can show.
 *
 * The product photographs are 1400 wide, which is right for a 2x desktop and
 * 2.6 times what a phone can render. Measured at 390 through a throttled line
 * they were 655KB of a 2.5MB page, and they are the part gzip cannot help.
 * `theme/make-photo-sizes.js` cuts a 700-wide copy of each; `photo_srcset()`
 * offers both and lets the browser choose — 655KB becomes 217KB on a phone.
 *
 * **This fails silently in both directions, which is why it is a test.** An
 * `<img>` that forgets `photo_srcset()` still renders — it just quietly
 * downloads the large one, which is how «پرفروش‌ترین‌ها» was found still
 * pulling four full-size photographs after the hero had been fixed. And a
 * manifest naming a file that was never synced is a 404 per photograph, which
 * a browser draws as an empty box and no test but this one would see.
 */
class PhotoSizesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());
    }

    /** @return array<string, array{small: string, smallWidth: int, width: int}> */
    private function manifest(): array
    {
        $file = public_path('assets/img/photo-sizes.json');

        $this->assertFileExists($file, 'The photograph manifest is not in public/. '.
            'Run: node theme/make-photo-sizes.js && node theme/sync-storefront-assets.js');

        return json_decode(file_get_contents($file), true)['photos'];
    }

    public function test_every_small_copy_the_manifest_names_is_on_disk(): void
    {
        foreach ($this->manifest() as $original => $photo) {
            $this->assertFileExists(
                public_path($photo['small']),
                "{$photo['small']} is named for {$original} but is not in public/. ".
                'Run: node theme/make-photo-sizes.js && node theme/sync-storefront-assets.js',
            );

            $this->assertFileExists(public_path($original));
            $this->assertLessThan(
                filesize(public_path($original)),
                filesize(public_path($photo['small'])),
                "{$photo['small']} is not smaller than the photograph it stands in for.",
            );
        }
    }

    /**
     * The pages a shopper meets a photograph on, and the markup that has to
     * offer both sizes there.
     */
    public function test_no_page_serves_a_photograph_at_one_size_only(): void
    {
        $photos = array_keys($this->manifest());

        foreach (['/', '/products', '/products/new-balance-530'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            preg_match_all('/<img\b[^>]*>/', $html, $tags);

            foreach ($tags[0] as $tag) {
                if (! preg_match('/\bsrc="([^"]+)"/', $tag, $src)) {
                    continue;
                }

                $file = ltrim(parse_url($src[1], PHP_URL_PATH) ?? '', '/');

                if (! in_array($file, $photos, true)) {
                    continue;
                }

                $this->assertStringContainsString(
                    'srcset=',
                    $tag,
                    "A photograph on {$path} is offered at one size only:\n  {$tag}\n".
                    'Write {!! photo_srcset($path) !!} after its src.',
                );
            }
        }
    }

    /**
     * **The two copies of the home page have to choose the same file.**
     *
     * `check-parity.js` renders the preview page and the Laravel page and
     * expects zero differing pixels. If the two disagree about `sizes` they
     * pick different candidates at some width and every photograph on the page
     * differs — by a re-encoding, not by anything anybody changed, which is
     * the most confusing possible way to spend an afternoon.
     */
    public function test_the_preview_page_and_the_app_ask_for_the_same_widths(): void
    {
        $preview = file_get_contents(base_path('../download-version/shoe-shop-rtl.html'));

        preg_match_all('/<img\b[^>]*\bsizes="([^"]+)"/', $preview, $m);

        $this->assertNotEmpty($m[1], 'No photograph in the preview page carries a `sizes`. '.
            'Run: node theme/make-photo-sizes.js && node theme/make-rtl-page.js');

        $ours = photo_srcset('assets/img/hero/vikyplus-hero-nb530.webp');
        preg_match('/sizes="([^"]+)"/', $ours, $mine);

        foreach (array_unique($m[1]) as $theirs) {
            $this->assertSame(
                $mine[1],
                $theirs,
                'The preview page and photo_srcset() ask for different widths, so the two '.
                'copies of the home page will choose different files and check-parity.js will '.
                'report every photograph as changed.',
            );
        }
    }
}
