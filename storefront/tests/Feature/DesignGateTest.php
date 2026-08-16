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
 * Two pieces answer it, and each is invisible without the other:
 *
 *   - `tweaks.css` ends with `--vp-design: ok`, a property that cannot be read
 *     unless the whole file arrived;
 *   - the head's gate reads it before <body> is parsed and, if it is missing,
 *     hides the document, reloads once, and then says the site is updating
 *     rather than letting the template through.
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
