<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every icon this shop writes has a glyph in the font it ships.
 *
 * **This is the only thing that can see a missing icon.** The four icon fonts
 * are subsets — 1,123KB of FontAwesome cut to 16KB, because measured over nine
 * pages the whole site paints 27 glyphs and one 379KB file was being downloaded
 * to draw a single ✕. Subsetting is invisible when it goes wrong: add
 * `fa-cart-plus` to a template without re-running `theme/make-icon-fonts.js`
 * and the class is styled, the element is in the DOM, the layout is right, and
 * the glyph is an empty box. `check-parity.js` cannot see it either, because it
 * compares two copies of the page that both ship the same subset.
 *
 * So this reads the same three things the subsetter reads — the templates, the
 * preview page, and FontAwesome's own class-to-codepoint table — and asserts
 * the answer is in the manifest the subsetter wrote.
 *
 * **If this fails, the fix is to run the script, not to edit the manifest:**
 *
 *     node theme/make-icon-fonts.js && node theme/sync-storefront-assets.js
 */
class IconFontTest extends TestCase
{
    /** Class names that style an icon rather than name one. */
    private const NOT_GLYPHS = [
        'solid', 'regular', 'light', 'brands', 'thin', 'duotone', 'sharp',
        'fw', '2x', '3x', '4x', '5x', 'lg', 'sm', 'xs', 'xl',
        'spin', 'spin-pulse', 'spin-reverse', 'beat', 'fade', 'flip', 'shake', 'bounce',
        'border', 'stack', 'stack-1x', 'stack-2x', 'inverse', 'pull-left', 'pull-right',
        'rotate-90', 'rotate-180', 'rotate-270', 'flip-horizontal', 'flip-vertical',
    ];

    private function repo(string $path): string
    {
        return base_path('../'.$path);
    }

    public function test_every_icon_the_templates_use_is_in_the_font_that_ships(): void
    {
        $manifest = json_decode(
            file_get_contents($this->repo('download-version/assets/fonts/fontawesome/subset.json')),
            true,
        );

        $kept = array_flip($manifest['codepoints']);
        $codepoints = $this->codepoints();

        $missing = [];

        foreach ($this->classesInTemplates() as $class => $where) {
            $this->assertArrayHasKey(
                $class,
                $codepoints,
                "«fa-{$class}» in {$where} is not a FontAwesome icon — check the spelling.",
            );

            if (! isset($kept[$codepoints[$class]])) {
                $missing[] = "fa-{$class} (U+".strtoupper($codepoints[$class]).") in {$where}";
            }
        }

        $this->assertSame([], $missing, "These icons have no glyph in the subset fonts:\n  ".
            implode("\n  ", $missing)."\nRun: node theme/make-icon-fonts.js && node theme/sync-storefront-assets.js");
    }

    /**
     * The carets and dots the stylesheets draw without a class.
     *
     * The base layer writes `content: "\f105"` straight into a rule — the
     * paginator's arrows, the radio's dot — so those never appear as a class
     * anywhere and a scan of the templates alone would miss them.
     */
    public function test_every_glyph_the_stylesheets_draw_is_in_the_font_that_ships(): void
    {
        $manifest = json_decode(
            file_get_contents($this->repo('download-version/assets/fonts/fontawesome/subset.json')),
            true,
        );
        $kept = array_flip($manifest['codepoints']);

        $missing = [];

        foreach (['style.rtl.css', 'tweaks.css'] as $sheet) {
            $css = file_get_contents($this->repo('download-version/assets/css/'.$sheet));

            preg_match_all('/content:\s*["\']\\\\([0-9a-fA-F]{1,6})/', $css, $m);

            foreach (array_unique($m[1]) as $cp) {
                if (! isset($kept[strtolower($cp)])) {
                    $missing[] = 'U+'.strtoupper($cp)." in {$sheet}";
                }
            }
        }

        $this->assertSame([], $missing, "These glyphs are drawn by CSS but are not in the subset fonts:\n  ".
            implode("\n  ", $missing)."\nRun: node theme/make-icon-fonts.js && node theme/sync-storefront-assets.js");
    }

    /** The shipped fonts are the subsets, not the originals put back by hand. */
    public function test_the_shipped_fonts_are_the_small_ones(): void
    {
        foreach (['fa-light-300', 'fa-regular-400', 'fa-solid-900', 'fa-brands-400'] as $face) {
            $shipped = public_path("assets/fonts/fontawesome/{$face}.woff2");

            $this->assertFileExists($shipped);

            // The originals are 103KB to 379KB; every subset is under 8KB. A
            // number in between would mean somebody re-subset a subset, which
            // the script guards against by always reading `.full.woff2`.
            $this->assertLessThan(
                8 * 1024,
                filesize($shipped),
                "{$face}.woff2 is ".round(filesize($shipped) / 1024).'KB — the full font is back. '.
                'Run: node theme/make-icon-fonts.js && node theme/sync-storefront-assets.js',
            );
        }
    }

    /**
     * The stylesheet is a subset too, and it can lose an icon on its own.
     *
     * The font and the stylesheet are cut from the same keep set, but they are
     * two files: a class whose rule was dropped draws nothing even when the
     * glyph is in the font, and — like the font — it fails silently. So this
     * asks the shipped stylesheet for a `content` rule for every class the
     * templates write, which is the same question as the first test with the
     * other half of the pair as the answer.
     */
    public function test_every_icon_the_templates_use_still_has_a_rule_in_the_stylesheet(): void
    {
        $css = file_get_contents(public_path('assets/css/fontawesome.min.css'));

        // The subset is a few KB; the original is 444KB. Anything in between
        // means somebody put the full file back by hand.
        $this->assertLessThan(
            40 * 1024,
            strlen($css),
            'fontawesome.min.css is '.round(strlen($css) / 1024).'KB — the full stylesheet is back. '.
            'Run: node theme/make-icon-fonts.js && node theme/sync-storefront-assets.js',
        );

        $missing = [];

        foreach ($this->classesInTemplates() as $class => $where) {
            if (! preg_match('/\.fa-'.preg_quote($class, '/').':before[,{]/', $css)) {
                $missing[] = "fa-{$class} in {$where}";
            }
        }

        $this->assertSame([], $missing, "These icons have no rule in the stylesheet that ships:\n  ".
            implode("\n  ", $missing)."\nRun: node theme/make-icon-fonts.js && node theme/sync-storefront-assets.js");
    }

    /** @return array<string, string> class => the file it was found in */
    private function classesInTemplates(): array
    {
        $files = [$this->repo('download-version/shoe-shop-rtl.html')];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $files[] = $f->getPathname();
            }
        }

        $skip = array_flip(self::NOT_GLYPHS);
        $found = [];

        foreach ($files as $file) {
            preg_match_all('/\bfa-([a-z0-9-]+)/', file_get_contents($file), $m);

            foreach ($m[1] as $class) {
                if (! isset($skip[$class]) && ! isset($found[$class])) {
                    $found[$class] = basename($file);
                }
            }
        }

        return $found;
    }

    /**
     * FontAwesome's own class-to-codepoint table.
     *
     * Its aliases share one grouped selector — `.fa-add:before,.fa-plus:before`
     * — so the group is read rather than each rule, which is how `fa-plus` was
     * found to be U+002B, a two-digit codepoint that a three-digit pattern
     * silently skipped.
     *
     * @return array<string, string>
     */
    private function codepoints(): array
    {
        // The *full* stylesheet. `fontawesome.min.css` is the subset that
        // ships and no longer holds the table — it names 52 codepoints, not
        // 3,312 — so reading it here would make every unused icon look like a
        // misspelling.
        $css = file_get_contents($this->repo('download-version/assets/css/fontawesome.full.min.css'));

        preg_match_all('/((?:\.fa-[a-z0-9-]+:before,?)+)\{content:"\\\\([0-9a-f]{1,6})"\}/', $css, $rules, PREG_SET_ORDER);

        $out = [];

        foreach ($rules as $rule) {
            preg_match_all('/\.fa-([a-z0-9-]+):before/', $rule[1], $names);

            foreach ($names[1] as $name) {
                $out[$name] ??= $rule[2];
            }
        }

        return $out;
    }
}
