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
 * The number lives in `WHATSAPP_NUMBER` in `theme/make-rtl-page.js`, which
 * writes it into the generated preview page; `theme/make-blade.js` then ports
 * that page into `partials/whatsapp.blade.php` and the footer. One line,
 * several files downstream — so what these assert is that the number is right
 * and that every copy still says the same thing.
 *
 * **There is a second copy that the generator cannot reach.** `/contact` and
 * `/support` are PHP and read `storefront.contact.whatsapp_href`; the preview
 * page is static HTML and cannot read config at all. So the number is written
 * twice by necessity, and the last two tests here are what keep the two
 * halves in step.
 *
 * It has already gone wrong once in the other direction: the generator's own
 * comment claimed the number lived in one place while it was in fact typed out
 * in five separate literals in that file, so changing the one you found left
 * the other four dialling the old number.
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
    private const NUMBER = '989366659224';

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

        // **Two buttons share the handler**, since «پشتیبانی هم بالای واتسپ
        // شناور یه مربع پشتیبانی بزار» put a support square above this one.
        // They are revealed together — one appearing without the other reads
        // as a fault — so the selector names both and this asserts the whole
        // of it rather than the half that happens to be first.
        $this->assertStringContainsString(
            'querySelectorAll(".vp-whatsapp, .vp-support-fab")',
            $page,
            'Nothing on the page looks for the corner buttons, so the class that reveals them is never '
            .'set and both are hidden for good. The handler is in theme/make-rtl-page.js.'
        );

        $css = file_get_contents(public_path('assets/css/tweaks.css'));

        foreach (['.vp-whatsapp.show', '.vp-support-fab.show'] as $rule) {
            $this->assertStringContainsString(
                $rule,
                $css,
                "The stylesheet has no `{$rule}` rule, so setting the class does nothing."
            );
        }
    }

    /**
     * The support square goes to the support page, on both copies of the home
     * page — and that page is the reason it exists: `/support` was reachable
     * from the footer and nowhere else, seven thousand pixels down.
     */
    public function test_the_support_square_reaches_the_support_page(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="vp-support-fab"', $page);
        $this->assertStringContainsString(route('support'), $page);

        $this->get(route('support'))->assertOk();

        $preview = file_get_contents(base_path('../download-version/shoe-shop-rtl.html'));
        $this->assertStringContainsString('class="vp-support-fab"', $preview);
        $this->assertStringContainsString('href="support.html"', $preview);
    }

    /**
     * The support button introduces itself, once, and then closes.
     *
     * «اولش باید اون پشتیبانی یه مستطیل باشه که روش نوشته پشتیبانی ۲۴ ساعته چند
     * ثانیه باشه بعد جمع بشه تبدیل بشه به این چیزی که الان هست».
     *
     * Three things have to hold together and none of them shows on a
     * screenshot: the label is in the markup at all times (so the control's
     * accessible name never changes), the script names the class the
     * stylesheet opens the pill with, and it drops that class again. A rewrite
     * that removed any one of them would leave either a permanent rectangle in
     * the corner of every screen or a button that never says what it is — and
     * the pixel checks cannot see either, because both run with the page at
     * the top, where these buttons are hidden.
     */
    public function test_the_support_button_says_what_it_is_and_then_closes(): void
    {
        foreach ([$this->get('/')->assertOk()->getContent(),
            file_get_contents(base_path('../download-version/shoe-shop-rtl.html'))] as $page) {
            $this->assertStringContainsString('class="vp-support-fab-label"', $page);
            $this->assertStringContainsString('پشتیبانی ۲۴ ساعته', $page);

            // Opened by the scroll handler, and closed again by its own timer.
            $this->assertStringContainsString('classList.add("is-wide")', $page);
            $this->assertStringContainsString('classList.remove("is-wide")', $page);
        }
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

    /**
     * The copy the PHP pages read says the same thing as the generated one.
     *
     * `/contact` and `/support` print `storefront.contact.whatsapp_href`, and
     * nothing else compares it to the button's. Two numbers that disagree is a
     * shop whose support button and support page ring different telephones.
     */
    public function test_the_config_copy_dials_the_same_number(): void
    {
        $this->assertSame(
            'https://wa.me/'.self::NUMBER,
            (string) config('storefront.contact.whatsapp_href'),
            'storefront.contact.whatsapp_href and the generated button disagree. '.
            'Both are meant to be the shop\'s one WhatsApp number.'
        );
    }

    /**
     * And no page anywhere carries a third number.
     *
     * Read off what renders rather than the source: a hardcoded link in a
     * hand-owned partial is exactly the failure this is for, and grepping the
     * files would miss one built in a Blade expression.
     */
    public function test_no_page_dials_any_other_number(): void
    {
        $wrong = [];
        $seen = 0;

        foreach (['/', '/products', '/faq', '/contact', '/support', '/wholesale', '/cart'] as $path) {
            preg_match_all(
                '~https://wa\.me/[0-9]+~',
                $this->get($path)->assertOk()->getContent(),
                $found
            );

            foreach ($found[0] as $link) {
                $seen++;

                if ($link !== 'https://wa.me/'.self::NUMBER) {
                    $wrong[] = "{$path} dials {$link}";
                }
            }
        }

        $this->assertGreaterThan(0, $seen, 'No WhatsApp link on any page — the button has gone.');

        $this->assertSame([], $wrong, "These dial something other than the shop's number:\n  ".
            implode("\n  ", $wrong)."\nRe-run:\n".
            '  node theme/make-rtl-page.js && node theme/make-blade.js && node theme/sync-storefront-assets.js');
    }
}
