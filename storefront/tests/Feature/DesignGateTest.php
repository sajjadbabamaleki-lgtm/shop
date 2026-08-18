<?php

namespace Tests\Feature;

use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The template does not paint on its own.
 *
 * This page is the ThemeForest stylesheet with `tweaks.css` on top of it, and
 * they are two files. Take the second away and the first still styles the page
 * completely — the mint hero (#D3FBD9), the red buttons, the template's
 * layout — carrying this shop's photographs and Persian. It does not look like
 * a page that failed to load. It looks like the old template came back, and
 * that is how the client read it: «قبلا یکبار بهت گفته بودم هر اثری از قالب
 * قبلی تو این کد لعنتی هستو بکلی پاک کن ... پس چرا الان دوباره وقتی سایت داشت
 * آپدیت میشد برای چند لحظه اینارو نمایش داد؟».
 *
 * During a deploy, because that is when Liara replaces the container and a
 * request can be answered by nothing. The visitor's other six stylesheets come
 * from their own disk cache and cannot fail; `tweaks.css` is fingerprinted with
 * the md5 of its contents, so a deploy that touched it has changed its URL —
 * making the one file the shop's entire appearance lives in the one file that
 * must come over the network at exactly the wrong moment.
 *
 * Three pieces answer it, and each is invisible without the others:
 *
 *   - `tweaks.css` ends with `--vp-design: ok`, a property that cannot be read
 *     unless the whole file arrived;
 *   - an inline style **above the first <link>** hides the document before any
 *     stylesheet has even been requested;
 *   - the head's gate reads the property before <body> is parsed and either
 *     reveals the page, or reloads once, and then says the site is updating
 *     rather than letting the template through.
 *
 * The hold is the third round's doing. The gate alone answers a stylesheet
 * that **fails**; it says nothing about one that is merely **slow**, and it
 * relied on the browser blocking on a pending <link> — which Chromium does and
 * Safari need not. The client saw the template a third time on an iPhone,
 * half an hour after a deploy, with the site barely answering:
 * «چرا باز دوباره اثرات قالب قبلیو دیدم چند لحظه؟؟؟». Hidden is the default
 * now and visible is earned, so no browser's rendering choice can lose it.
 *
 * Nothing else can see either of them. `check-parity.js` renders a page where
 * the CSS is served, so the gate opens and it measures zero; `check-overflow.js`
 * measures the same page. Both pieces are generated — the head by
 * `theme/make-rtl-page.js`, the shipped stylesheet by
 * `theme/sync-storefront-assets.js` — so both would come off silently in a
 * regeneration, and the symptom would not appear until the next deploy, on the
 * client's phone.
 */
class DesignGateTest extends TestCase
{
    use RefreshDatabase;

    /** The property the design signs itself with, and its file. */
    private const SIGNATURE = ':root{--vp-design:ok;}';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_the_storefront_carries_the_gate(): void
    {
        foreach (['/', '/products', '/size-guide'] as $path) {
            $this->assertGated($this->get($path)->assertOk()->getContent(), $path);
        }
    }

    /**
     * The panel and the marketplace borrow the storefront's head, and a person
     * working in the panel is looking at the same two files.
     */
    public function test_the_panel_carries_the_gate(): void
    {
        $this->assertGated($this->get('/admin/login')->assertOk()->getContent(), '/admin/login');
    }

    /**
     * The error shell is its own layout and would be the one to miss it.
     */
    public function test_the_error_pages_carry_the_gate(): void
    {
        app(TenantContext::class)->forget();

        $this->assertGated($this->get('/no-such-page')->assertNotFound()->getContent(), 'the 404');
    }

    /**
     * The signature has to be the last thing in the file. A rule added after it
     * still works, but the gate would vouch for a stylesheet it had not read to
     * the end — which is the whole failure, one rule smaller.
     */
    public function test_the_shipped_stylesheet_signs_itself_last(): void
    {
        $this->assertSignedLast(public_path('assets/css/tweaks.css'));
    }

    /**
     * The preview page the design is still argued on is a copy of this one, and
     * a copy that has drifted is how the last two of these started.
     */
    public function test_the_preview_carries_the_same_gate(): void
    {
        $preview = base_path('../download-version/shoe-shop-rtl.html');

        $this->assertFileExists($preview);
        $this->assertGated(file_get_contents($preview), 'the preview page');
        $this->assertSignedLast(base_path('../download-version/assets/css/tweaks.css'));
    }

    /**
     * The gate has to come after the stylesheet it is checking: a script in the
     * head runs only once every <link> above it has finished, which is what
     * makes its answer decisive and what makes it run before anything paints.
     */
    private function assertGated(string $html, string $where): void
    {
        // The <link> itself, not the word: the gate's own comment names the
        // file too, and a search for the string would find that instead.
        $found = preg_match(
            '~<link[^>]+assets/css/tweaks\.css~i', $html, $matches, PREG_OFFSET_CAPTURE
        );
        $tweaks = $found ? $matches[0][1] : false;
        $gate = strpos($html, '--vp-design');

        $this->assertNotFalse($tweaks, "{$where} does not load tweaks.css at all.");

        $this->assertNotFalse($gate, implode("\n", [
            "{$where} has no design gate, so a dropped tweaks.css paints the template.",
            'The head is generated — fix theme/make-rtl-page.js and re-run',
            'node theme/make-rtl-page.js && node theme/make-blade.js.',
        ]));

        $this->assertGreaterThan($tweaks, $gate, implode("\n", [
            "{$where} runs the design gate before tweaks.css has loaded, so it",
            'answers for a stylesheet that is still on its way and hides a page',
            'that was going to be fine. It belongs after the last <link>.',
        ]));
    }

    /**
     * The hold: nothing paints before the design has signed itself.
     *
     * Measured in Chromium with `tweaks.css` held back four seconds — without
     * this, `html` is `visible` and the template's own peach hero
     * (rgb(255,226,181), the exact colour off the client's screenshot) is on
     * the screen; with it, `html` is `hidden` and the body is not even parsed.
     */
    public function test_nothing_paints_before_the_design_has_signed_itself(): void
    {
        foreach (['/', '/admin/login'] as $path) {
            $html = $this->get($path)->getContent();

            $hold = strpos($html, '<style>html{visibility:hidden}</style>');
            $noscript = strpos($html, '<noscript><style>html{visibility:visible}</style></noscript>');

            $found = preg_match('~<link[^>]+rel="stylesheet"~i', $html, $m, PREG_OFFSET_CAPTURE);
            $firstSheet = $found ? $m[0][1] : false;

            $this->assertNotFalse($hold, implode("\n", [
                "{$path} does not hide itself before its stylesheets load, so a",
                'browser that paints while tweaks.css is still coming shows the',
                'template. The head is generated — fix theme/make-rtl-page.js.',
            ]));

            $this->assertNotFalse($noscript, "{$path} would stay hidden for ever without JavaScript.");
            $this->assertNotFalse($firstSheet, "{$path} loads no stylesheets at all.");

            $this->assertLessThan($firstSheet, $hold, implode("\n", [
                "{$path} hides itself after it has already asked for a stylesheet.",
                'The hold has to be above every <link> — below one, there is a',
                'window where the template can paint.',
            ]));
        }
    }

    /**
     * And every way out of the gate has to end with the page on the screen.
     * A document left hidden is worse than one showing the wrong thing.
     */
    public function test_every_path_out_of_the_gate_reveals_the_page(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $gate = substr($html, (int) strpos($html, '--vp-design'));
        $gate = substr($gate, 0, (int) strpos($gate, '</script>'));

        $this->assertSame(
            2,
            substr_count($gate, 'show()'),
            implode("\n", [
                'The gate has a path that neither reveals the page nor replaces it',
                'with the notice: both bail-outs — the browser too old to have',
                'custom properties, and the design arriving intact — must call',
                'show(), or that visitor is left looking at a hidden document.',
            ])
        );
    }

    private function assertSignedLast(string $file): void
    {
        $this->assertFileExists($file);

        // Comments are two thirds of this file and none of them are rules.
        $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents($file));
        $compact = preg_replace('~\s+~', '', $css);

        $this->assertStringEndsWith(self::SIGNATURE, $compact, implode("\n", [
            $file.' does not end with '.self::SIGNATURE.'.',
            'That declaration is how the page knows the design arrived whole.',
            'Anything appended below it is a rule the gate cannot vouch for —',
            'put it above the signature instead.',
        ]));
    }
}
