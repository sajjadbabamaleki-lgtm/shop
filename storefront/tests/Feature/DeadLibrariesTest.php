<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The eight libraries the page no longer loads, and the markup that would
 * need them back.
 *
 * The template ships eleven scripts because it is eleven demos in one
 * download. This shop calls three — jQuery, Swiper and GSAP — and the other
 * eight bound to markup that exists in none of the pages we serve: 187KB
 * fetched, parsed and thrown away, over eight round trips before `load` could
 * fire, on a site whose complaint was «سایت با فیلترشکن خیلی کند باز میشه».
 * They come off in `DEAD_LIBRARIES` in `theme/make-rtl-page.js`.
 *
 * **This test is what makes that removal safe.** The calls are still in
 * `main.js`, each behind `if ($.fn.…)`, so nothing throws — which is exactly
 * why the loss would be silent. Write `class="popup-video"` into a template a
 * year from now and the lightbox would simply never open: no error, no red,
 * nothing to search for. So this reads the templates for the hooks each
 * library binds to and fails naming the script to put back.
 *
 * It is not a style rule. If one of these features is genuinely wanted, the
 * answer is to restore that one `<script>` in `make-rtl-page.js`, re-run the
 * generator, and take its hook out of the list below.
 */
class DeadLibrariesTest extends TestCase
{
    /**
     * hook in the markup => the library that would have to come back.
     *
     * The hooks are read off the call sites in `main.js`; each is the
     * selector that library is handed there.
     *
     * @var array<string, string>
     */
    private const HOOKS = [
        'popup-image' => 'assets/js/jquery.magnific-popup.min.js (and its stylesheet)',
        'popup-video' => 'assets/js/jquery.magnific-popup.min.js (and its stylesheet)',
        'popup-content' => 'assets/js/jquery.magnific-popup.min.js (and its stylesheet)',
        'counter-number' => 'assets/js/jquery.counterup.min.js',
        'tilt-active' => 'assets/js/tilt.jquery.min.js',
        'filter-active' => 'assets/js/isotope.pkgd.min.js + imagesloaded.pkgd.min.js',
        'masonary-active' => 'assets/js/isotope.pkgd.min.js + imagesloaded.pkgd.min.js',
        'data-sec-pos' => 'assets/js/imagesloaded.pkgd.min.js',
        'price_slider' => 'assets/js/jquery-ui.min.js',
        'nice-select' => 'assets/js/nice-select.min.js',
        'data-bs-' => 'assets/js/bootstrap.min.js',
    ];

    public function test_no_template_asks_for_a_library_the_page_no_longer_loads(): void
    {
        $found = [];

        foreach ($this->templates() as $file) {
            $markup = file_get_contents($file);

            foreach (self::HOOKS as $hook => $library) {
                if (str_contains($markup, $hook)) {
                    $found[] = "«{$hook}» in ".basename($file)." needs {$library}";
                }
            }
        }

        $this->assertSame([], $found, "Markup here binds to a library the page stopped loading:\n  ".
            implode("\n  ", $found)."\nEither drop the markup, or restore that script in ".
            "theme/make-rtl-page.js (DEAD_LIBRARIES) and re-run:\n".
            '  node theme/make-rtl-page.js && node theme/make-blade.js && node theme/sync-storefront-assets.js');
    }

    /**
     * The scripts that do ship, still ship.
     *
     * The other half of the same mistake: a generator change that drops one
     * script too many takes the sliders or the whole of `main.js` with it, and
     * the page still renders — badly, and only below the fold.
     */
    public function test_the_three_libraries_the_shop_does_call_are_still_loaded(): void
    {
        $scripts = file_get_contents(resource_path('views/partials/scripts.blade.php'));

        foreach ([
            'assets/js/vendor/jquery-3.7.1.min.js' => 'jQuery — main.js is one long jQuery closure',
            'assets/js/swiper-bundle.min.js' => 'Swiper — the hero deck, the stories and the brand row',
            'assets/js/gsap.min.js' => 'GSAP — the drag cursor on .slider-drag-cursor',
            'assets/js/main.js' => 'the template behaviour the page is built on',
        ] as $file => $why) {
            $this->assertStringContainsString($file, $scripts, "{$file} is gone from the page: {$why}");
            $this->assertFileExists(public_path($file));
        }
    }

    /** @return list<string> */
    private function templates(): array
    {
        $files = [base_path('../download-version/shoe-shop-rtl.html')];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $files[] = $f->getPathname();
            }
        }

        return $files;
    }
}
