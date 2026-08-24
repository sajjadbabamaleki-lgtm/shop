<?php

namespace Tests\Feature;

use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The نماد اعتماد الکترونیکی on the strip under the footer.
 *
 * **eNamad's markup is not decoration and cannot be paraphrased.** Their check
 * fetches the picture from `trustseal.enamad.ir` and their script reads the
 * `code` attribute off the image; a copy of the artwork served from our own
 * `public/` would look identical on the page and count as not installed, and
 * an `<img>` without that attribute is a picture rather than a seal. So what
 * is asserted here is the literal thing they issued, not «a logo is present».
 *
 * It also has to be on **every** page rather than the home page alone, which
 * is what the second test is for: it lives in the shell's footer, and a shell
 * that lost it on the content pages would be a seal the shop shows only where
 * somebody happened to look.
 *
 * The third test is the standing rule of this repository — the static preview
 * page and the Laravel page are two copies of one design, and every check we
 * have compares their *pixels*. The seal's picture comes off a server neither
 * of them can reach from CI, so those pixels are identical whether the id is
 * right, wrong, or somebody else's. This is the one check that would notice.
 */
class TrustSealTest extends TestCase
{
    use RefreshDatabase;

    /** The two halves of what eNamad issued, and the id they belong to. */
    private const ID = 'id=696411';

    private const CODE = 'oyQ6picRwm2lLEPobQWLuNSW37WIf7mV';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_the_home_page_carries_the_seal_as_enamad_issued_it(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        // From their server, not ours — the whole check rests on this.
        $this->assertStringContainsString(
            'https://trustseal.enamad.ir/logo.aspx?'.self::ID.'&Code='.self::CODE,
            $page
        );

        // The estelam page behind the picture, so the seal can be clicked
        // through to eNamad's own record of this shop.
        $this->assertStringContainsString(
            "href='https://trustseal.enamad.ir/?".self::ID.'&Code='.self::CODE."'",
            $page
        );

        // The attribute their script reads. Losing it leaves a picture.
        $this->assertStringContainsString("code='".self::CODE."'", $page);
    }

    public function test_the_seal_is_on_the_other_pages_too(): void
    {
        foreach (['/about', '/faq', '/products'] as $path) {
            $this->assertStringContainsString(
                self::CODE,
                $this->get($path)->assertOk()->getContent(),
                "The seal is missing from {$path}."
            );
        }
    }

    public function test_the_static_preview_page_carries_the_same_seal(): void
    {
        $preview = base_path('../download-version/shoe-shop-rtl.html');

        $this->assertStringContainsString(
            self::CODE,
            (string) file_get_contents($preview),
            'The preview page and the shop disagree about the seal — '
            .'re-run node theme/make-rtl-page.js and node theme/make-blade.js.'
        );
    }
}
