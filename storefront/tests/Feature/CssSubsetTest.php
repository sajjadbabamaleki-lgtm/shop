<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every class this shop writes still has its rules in the stylesheets it ships.
 *
 * **The three bought stylesheets are subsets.** The bundle they came from is
 * eleven demos in one download, and this shop is one of them: of the base
 * sheet's 4,757 rules, 581 can ever match here; of Bootstrap's 2,337, 171. Cut
 * to what this shop can reach, the three go from 859KB to 108KB — 116KB to
 * 20KB on the wire — and every one of those bytes is render-blocking, which is
 * why it was worth doing at all. `theme/make-css-subset.js` does the cutting.
 *
 * **A cut stylesheet fails silently, exactly like a cut font.** Write
 * `class="th-btn style3"` into a template a month from now without re-running
 * the script and the element is there, the markup is right, nothing throws —
 * the button is simply unstyled. `check-parity.js` cannot see it: it compares
 * two copies of the same page and both ship the same subset. The browser
 * checks are not in CI. So this is the guard that runs on every push.
 *
 * It does not try to re-decide in PHP what the cut should have kept — two
 * implementations of one rule is how a guard ends up guarding nothing. It
 * asks a smaller question that catches the same case: **was the cut made
 * against the markup that is here now?** `subset.json` records the fingerprint
 * of the vocabulary the script read, and this rebuilds it from the same file
 * list. A class added to a template since the last cut changes it.
 *
 * If this fails, the fix is to run the script, not to edit a stylesheet:
 *
 *     node theme/make-css-subset.js
 *     node theme/check-css-subset.js      # pixels, against the full sheets
 *     node theme/sync-storefront-assets.js
 */
class CssSubsetTest extends TestCase
{
    /** shipped sheet => the untouched original it was cut from. */
    private const SHEETS = [
        'style.rtl.css' => 'style.rtl.full.css',
        'bootstrap.rtl.min.css' => 'bootstrap.rtl.full.min.css',
        'swiper-bundle.rtl.min.css' => 'swiper-bundle.rtl.full.min.css',
    ];

    private function repo(string $path): string
    {
        return base_path('../'.$path);
    }

    /**
     * The full sheets are the source and are never served.
     *
     * They are 859KB of stylesheet whose only reader is the subsetter, and
     * they live in `theme/` rather than beside the files they are cut from —
     * because `download-version/` is what Netlify publishes and **the base
     * sheet's own header names where the base layer came from**, which
     * CLAUDE.md says may never appear in anything a person outside this
     * repository reads. `sync-storefront-assets.js` copies only what the page
     * references, so nothing has to be excluded from the app by hand either;
     * this asserts both stay true.
     */
    public function test_the_originals_are_kept_and_are_not_deployed(): void
    {
        foreach (self::SHEETS as $live => $full) {
            $this->assertFileExists(
                $this->repo("theme/base-stylesheets/{$full}"),
                "{$full} is the only copy of the rules {$live} was cut from. Without it the subset ".
                'cannot be widened again, and re-running the script would cut an already cut sheet.',
            );

            $this->assertFileDoesNotExist(
                public_path("assets/css/{$full}"),
                "{$full} is in public/ and would be deployed. It is the subsetter's source, not a served file.",
            );
        }
    }

    /** The shipped sheet is genuinely the cut one, not the original under its name. */
    public function test_what_ships_is_the_cut_sheet(): void
    {
        $manifest = $this->manifest();

        foreach (self::SHEETS as $live => $full) {
            $this->assertArrayHasKey($live, $manifest['sheets'], "subset.json does not mention {$live}.");

            $shipped = strlen(file_get_contents(public_path("assets/css/{$live}")));
            $cut = $manifest['sheets'][$live]['is'];

            // The served copy has had its comments taken out on the way, so it
            // is the cut sheet or smaller, never larger.
            $this->assertLessThanOrEqual(
                $cut,
                $shipped,
                "public/assets/css/{$live} is {$shipped} bytes where the cut sheet is {$cut} — the app is being ".
                'served something other than the cut. Run node theme/sync-storefront-assets.js.',
            );
        }
    }

    /**
     * The cut still matches the markup — the one this whole file is for.
     *
     * `make-css-subset.js` decides what to drop from one thing: the set of
     * words in the templates and the scripts. So the manifest records the
     * fingerprint of that set, and this rebuilds it from the same file list and
     * compares. **A new class in a template changes the fingerprint; rewording
     * a Persian sentence does not** — which is what makes this bearable to
     * live with, since the copy on these pages changes weekly and none of it
     * can affect a stylesheet.
     *
     * When it fails, nothing is wrong with the code: the stylesheets are one
     * markup change behind, and re-running the script is the whole fix.
     */
    public function test_the_cut_was_made_against_the_markup_that_is_here_now(): void
    {
        $manifest = $this->manifest();

        $words = [];

        foreach ($manifest['read'] as $relative) {
            $file = $this->repo($relative);

            $this->assertFileExists($file, "subset.json says the cut read {$relative}, and it is gone. ".
                'Re-run node theme/make-css-subset.js.');

            // The same expression `make-css-subset.js` uses. If these two ever
            // disagree this test passes while guarding nothing, so they are
            // written the same way on purpose.
            preg_match_all('/[A-Za-z_][A-Za-z0-9_-]+/', file_get_contents($file), $found);
            foreach ($found[0] as $word) {
                $words[$word] = true;
            }
        }

        $words = array_keys($words);
        sort($words, SORT_STRING);

        $this->assertSame(
            $manifest['vocabulary'],
            hash('sha256', implode("\n", $words)),
            'The stylesheets were cut against different markup from what is here now (the cut knew '.
            $manifest['words'].' words; the templates hold '.count($words)." today).\n".
            'Nothing is broken in the code — the three bought stylesheets are simply one markup change behind, '.
            "and a class added since then has no rules in what ships. Re-cut and re-sync:\n".
            "  node theme/make-css-subset.js\n".
            "  node theme/check-css-subset.js      # pixels, against the full sheets\n".
            '  node theme/sync-storefront-assets.js',
        );
    }

    /** @return array{vocabulary: string, words: int, read: list<string>, sheets: array<string, array{is: int}>} */
    private function manifest(): array
    {
        $path = $this->repo('theme/base-stylesheets/subset.json');

        $this->assertFileExists($path, 'subset.json is the record of what the stylesheet cut was made from. '.
            'Run node theme/make-css-subset.js.');

        return json_decode(file_get_contents($path), true);
    }
}
