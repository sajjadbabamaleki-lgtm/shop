<?php

namespace Tests\Feature;

use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The five marks in the phone footer's social row are images, and stay images.
 *
 * **This is a guard against a tidy-up, not against a typo.** Three of them were
 * Font Awesome glyphs, which is the obvious way to draw a brand mark on a page
 * that already loads Font Awesome — and it is why they were glyphs for months.
 * The reason they are not any more does not show up in a diff: a glyph rides a
 * baseline inside a line box, `line-height: 1` makes that box shorter than the
 * font's own metrics, and where the ink lands is left to the engine. Measured
 * on one build, Chromium centred them exactly and WebKit drew them 1.0–1.2px
 * high, so no single correction could be right on both. Cut to SVG with the
 * ink's own bounding box as the viewBox, the file's edges are the mark's edges
 * and every engine centres the same thing.
 *
 * Somebody will eventually see five `<img>`s where an icon font would do. The
 * test is the note that says why, and it fails before the row goes crooked
 * again on a phone nobody here is holding.
 */
class FooterSocialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_every_social_mark_is_an_image(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        foreach (['whatsapp', 'telegram', 'instagram', 'bale', 'rubika'] as $service) {
            $this->assertMatchesRegularExpression(
                "/<a class=\"vp-foot-m-soc is-{$service}\"[^>]*>\\s*<img /",
                $page,
                "The {$service} mark in the footer's social row is not an image."
            );
        }
    }

    /**
     * And none of them is a glyph. Named separately from the test above so a
     * failure says which of the two things went wrong: a mark that lost its
     * image, or a mark that gained a font.
     */
    public function test_no_social_mark_is_a_font_glyph(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<a class="vp-foot-m-soc[^>]*>\s*<(i|b) /',
            $page,
            'A mark in the social row is drawn from an icon font again — '
            .'see theme/make-brand-marks.js for why that will not stay centred.'
        );
    }
}
