<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Models\Branch;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The six content pages, and the footer that has been linking to them since
 * the template arrived.
 *
 * What is actually worth guarding here is not that a page of copy renders. It
 * is the thing that was wrong for the whole port and that no visual check
 * could see: **the footer linking to pages that do not exist.** `page_url()`
 * quietly resolves an unmapped filename to '#', which is a link that looks
 * completely normal until somebody clicks it — 21 of the footer's 47 links
 * were one. A test that counts them is the only thing that notices when the
 * next one is added.
 */
class ContentPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function pages(): array
    {
        return [
            ['/about', 'درباره ویکی پلاس'],
            ['/contact', 'تماس با ما'],
            ['/size-guide', 'راهنمای سایز'],
            ['/faq', 'سوالات متداول'],
            ['/terms', 'قوانین و مقررات'],
            ['/privacy', 'حریم خصوصی'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_content_page_renders(string $path, string $heading): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee($heading, false);
    }

    /**
     * The whole point of building them: no footer link to a *page* goes to '#'.
     *
     * '#' is what `page_url()` returns for a filename with no route behind it,
     * so this counts the failure mode directly rather than naming the pages
     * that used to be missing. Add a footer item pointing at an unbuilt page
     * and this fails, which is the only warning anybody gets.
     *
     * The four social marks are exempt, and only those. Their '#' is a
     * different decision with its own reason — theme/make-rtl-page.js has it:
     * one of the four is a mark this could not identify with confidence, and
     * all four are waiting on the client for the accounts. That is a link with
     * nowhere to point yet, not a page nobody built.
     */
    public function test_no_footer_link_goes_nowhere(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $footer = substr($html, (int) strpos($html, '<footer'));

        preg_match_all('/<a\b[^>]*href="#"[^>]*>/', $footer, $found);

        $pages = array_values(array_filter(
            $found[0],
            fn (string $tag): bool => ! str_contains($tag, 'vp-foot-m-soc'),
        ));

        $this->assertSame([], $pages, implode("\n", [
            'A footer link resolves to "#". That is page_url() saying the page it names',
            'has no route — the state the six content pages were built to end. Either map',
            'the filename in config/storefront.php or point the link somewhere real.',
        ]));
    }

    /**
     * The contact page and the footer must not disagree about where the shop
     * is.
     *
     * The footer is generated markup — theme/make-rtl-page.js holds its copy of
     * the address and the telephone, and cannot read config. So the two are
     * kept in step by this test rather than by hoping: correct one and forget
     * the other and the suite fails, instead of the site carrying two
     * addresses.
     */
    public function test_the_contact_details_match_the_footer(): void
    {
        $home = $this->get('/')->assertOk()->getContent();
        $contact = $this->get('/contact')->assertOk()->getContent();

        foreach (['address', 'phone'] as $detail) {
            $value = (string) config("storefront.contact.{$detail}");

            $this->assertStringContainsString($value, $contact);
            $this->assertStringContainsString($value, $home, implode("\n", [
                "The footer no longer carries the {$detail} that config/storefront.php holds.",
                'The footer is generated: change theme/make-rtl-page.js and re-run',
                'node theme/make-rtl-page.js && node theme/make-blade.js.',
            ]));
        }
    }

    /**
     * The FAQ quotes the delivery charge, and the checkout charges it. They
     * read the same config key, and this is what says so — a page of copy that
     * names a price is a page that can go out of date silently.
     */
    public function test_the_faq_quotes_the_charge_the_checkout_applies(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertSee(toman((int) config('storefront.checkout.shipping_flat')), false)
            ->assertSee(toman((int) config('storefront.checkout.free_shipping_above')), false);
    }

    /**
     * A franchise's visitor stays in their franchise.
     *
     * The content pages are registered inside the storefront's route closure
     * like every other page, so /shiraz/faq exists — and its links have to be
     * Shiraz's, or reading the size guide is how somebody gets tipped out into
     * the central store's prices without noticing.
     */
    public function test_a_branch_has_the_content_pages_too(): void
    {
        app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );

        app(TenantContext::class)->forget();

        $this->get('/shiraz/size-guide')
            ->assertOk()
            ->assertSee('راهنمای سایز', false)
            ->assertSee('/shiraz/products', false);
    }

    /**
     * Every path the content pages answer on is refused as a branch slug.
     *
     * The fixed routes are registered before the branch group and would win, so
     * a franchise called "faq" would simply never be reachable — a silent
     * failure found by a franchisee rather than by us.
     */
    public function test_the_content_paths_cannot_be_taken_by_a_branch(): void
    {
        foreach (array_keys(PageController::PAGES) as $path) {
            $this->assertContains($path, Branch::RESERVED_SLUGS, "«{$path}» is a page and must be a reserved slug.");
        }
    }
}
