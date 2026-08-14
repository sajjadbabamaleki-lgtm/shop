<?php

namespace Tests\Feature;

use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The WhatsApp button in the page's corner.
 *
 * It replaced the template's scroll-to-top ring — «بجای این باید یه آیکون
 * واتسپ بیاری» — and it is worth a test of its own for one reason: it is the
 * only thing on this site that sends a customer somewhere the site does not
 * control. Every other link either resolves inside the app or 404s loudly. A
 * wrong digit here does not fail, does not look broken, and does not show up
 * in a rendering check; it just quietly hands the shop's customers to a
 * stranger's phone.
 *
 * `check-parity.js` cannot catch it either. It compares pixels, and two
 * buttons with different `href`s are the same picture.
 *
 * The number lives in `theme/make-rtl-page.js`, which writes it into the
 * generated preview page; `theme/make-blade.js` then ports that page into
 * `partials/whatsapp.blade.php`. One line, two files downstream — so what
 * these assert is that the number is right and that the two copies still say
 * the same thing.
 */
class WhatsAppButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The home page is branch-scoped and fails closed, so an unseeded
        // request 404s rather than rendering an empty shop.
        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    /**
     * The number, written out once here on purpose.
     *
     * If this test fails after a deliberate change, the number is meant to be
     * updated in `theme/make-rtl-page.js` and here — in that order, and not
     * here alone. Editing only the test makes it agree with whatever the page
     * says, which is the one thing it exists not to do.
     */
    private const NUMBER = '989918905993';

    public function test_the_button_is_on_the_page_and_points_at_the_shops_number(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="vp-whatsapp"', $page);
        $this->assertStringContainsString('https://wa.me/'.self::NUMBER, $page);
    }

    /**
     * `wa.me` takes the international form: no plus, no leading zero, and
     * digits only. A local 09... in that URL resolves to nobody.
     */
    public function test_the_number_is_in_the_form_wa_me_accepts(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        preg_match('~https://wa\.me/([^"\']+)~', $page, $m);

        $this->assertNotEmpty($m, 'The page has no wa.me link.');
        $this->assertMatchesRegularExpression('~^\d+$~', $m[1], 'The number has something other than digits in it.');
        $this->assertStringStartsWith('98', $m[1], 'The number is not in international form.');
        $this->assertStringNotContainsString('+', $m[1]);
    }

    /**
     * It opens in a new tab, and safely.
     *
     * `target="_blank"` without `rel="noopener"` hands the opened page a live
     * handle on this one through `window.opener`.
     */
    public function test_the_link_opens_out_of_the_shop_without_handing_over_the_window(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $open = strpos($page, 'class="vp-whatsapp"');
        $button = substr($page, $open, strpos($page, '</a>', $open) - $open);

        $this->assertStringContainsString('target="_blank"', $button);
        $this->assertStringContainsString('rel="noopener"', $button);
        $this->assertStringContainsString('aria-label=', $button);
    }

    /**
     * The static preview and the Blade still say the same number.
     *
     * The two are generated from one line, so they can only disagree if
     * somebody hand-edited one of them — which is exactly the edit that would
     * survive every other check in this repo.
     */
    public function test_the_preview_page_and_the_blade_agree(): void
    {
        $preview = base_path('../download-version/shoe-shop-rtl.html');

        if (! is_file($preview)) {
            $this->markTestSkipped('The preview page is not in this checkout.');
        }

        $this->assertStringContainsString(
            'https://wa.me/'.self::NUMBER,
            file_get_contents($preview),
            'The preview page and the storefront disagree about the WhatsApp number.'
        );
    }

    /**
     * Something can still reveal it.
     *
     * The button is `opacity: 0; visibility: hidden` until the page scrolls —
     * «آیکون واتسپ وقتی اولین اسکرول شروع میشه باید ظاهر بشه» — and the class
     * that reveals it is set by the scroll handler that used to drive the
     * template's ring. That makes a hidden default and a JS selector the two
     * halves of one thing, and nothing else in this repo checks either half:
     * PHPUnit does not run the script, `check-parity.js` compares the two
     * pages against *each other* so it stays at zero if both go blank, and
     * `check-overflow.js` only asks whether the page scrolls sideways.
     *
     * So if the selector in `theme/make-rtl-page.js` ever drifts, the button
     * is invisible on every page, forever, and every check still passes. This
     * asserts the two halves still name the same thing.
     */
    public function test_the_scroll_handler_can_still_find_the_button(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            'querySelector(".vp-whatsapp")',
            $page,
            'Nothing on the page looks for the WhatsApp button, so the class that reveals it is never set '
            .'and the button is hidden for good. The handler is in theme/make-rtl-page.js.'
        );

        $css = public_path('assets/css/tweaks.css');
        $this->assertStringContainsString(
            '.vp-whatsapp.show',
            file_get_contents($css),
            'The stylesheet has no rule for the revealed state, so setting the class does nothing.'
        );
    }

    /**
     * The ring it replaced is gone, on both copies.
     *
     * «بجای این» — instead of, not beside. A leftover `.scroll-top` would sit
     * in the same corner under the same z-index and take the taps.
     */
    public function test_the_scroll_to_top_ring_is_gone(): void
    {
        $this->assertStringNotContainsString('class="scroll-top"', $this->get('/')->assertOk()->getContent());

        $preview = base_path('../download-version/shoe-shop-rtl.html');

        if (is_file($preview)) {
            $this->assertStringNotContainsString('class="scroll-top"', file_get_contents($preview));
        }
    }
}
