<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\Branches\BranchOpener;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The drawer that opens on a phone.
 *
 * It is worth its own file because of how long it was broken and how invisible
 * that was: the template's demo menu — «Electronics فروشگاه», «About Style 1»,
 * ten spellings of blog-grid, none of them a page this shop has — survived the
 * entire port. Nobody opens the mobile menu on a desktop, the parity check
 * cannot see it because the panel is parked off-screen, and every test passed.
 *
 * So these tests are about the two things a rendering check cannot notice:
 * that everything in it goes somewhere real, and that it says what this branch
 * actually sells.
 */
class PhoneDrawerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    private function drawer(string $path = '/'): string
    {
        $page = $this->get($path)->assertOk()->getContent();

        $open = strpos($page, '<div class="th-menu-wrapper">');
        $this->assertNotFalse($open, 'The page has no phone drawer.');

        $close = strpos($page, '<header', $open);

        return substr($page, $open, $close - $open);
    }

    /**
     * The template's own demo is gone. Every one of these was a live link in
     * the drawer on the day the site went up.
     */
    public function test_the_templates_demo_menu_is_gone(): void
    {
        $drawer = $this->drawer();

        foreach ([
            'blog-grid', 'About Style', 'Electronics', 'fashion-shop', 'grocery-shop',
            'coffee-shop', 'furniture-shop', 'Search Result', 'error.html',
        ] as $leftover) {
            $this->assertStringNotContainsString($leftover, $drawer);
        }
    }

    /**
     * Nothing in the drawer goes to '#'.
     *
     * A menu is a promise that the things in it exist. The old one broke that
     * promise on every row; this asserts the new one keeps it, which also means
     * a link added later cannot quietly point at nothing.
     */
    public function test_every_link_in_the_drawer_goes_somewhere(): void
    {
        $drawer = $this->drawer();

        preg_match_all('~<a[^>]+href="([^"]*)"~', $drawer, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $href) {
            $this->assertNotSame('#', $href, 'A drawer link points at nothing.');
            $this->assertNotSame('', $href);
        }
    }

    /**
     * The three shortcuts open the listing on a real question, and each one
     * answers differently from the others.
     */
    public function test_the_three_shortcuts_open_the_listing_on_a_real_filter(): void
    {
        $drawer = $this->drawer();

        foreach (['sale=1', 'sort=newest', 'sort=bestselling'] as $query) {
            $this->assertStringContainsString($query, $drawer);
        }

        $this->get('/products?sale=1')->assertOk();
        $this->get('/products?sort=newest')->assertOk();
        $this->get('/products?sort=bestselling')->assertOk();
    }

    public function test_the_drawer_lists_the_catalogues_own_categories(): void
    {
        $drawer = $this->drawer();

        // `show_in_nav` is the one thing the drawer's query asks that the front
        // page's does not — «اکسسوری از منو حذف بشه». The section is still on
        // the shop and still has a page; it is not in this menu.
        foreach (Category::orderBy('position')->get() as $category) {
            if (! $category->show_in_nav) {
                $this->assertStringNotContainsString('/categories/'.$category->slug, $drawer);

                continue;
            }

            $this->assertStringContainsString($category->name, $drawer);
            $this->assertStringContainsString('/categories/'.$category->slug, $drawer);
        }

        // And exactly one is out, so this is not passing on an empty menu.
        $this->assertSame(1, Category::where('show_in_nav', false)->count());
    }

    /**
     * The drawer and the front page name the same sections.
     *
     * This is the one that would catch the tempting mistake. "Only categories
     * with something in them" is the obvious rule for a menu, and it is wrong
     * here: seven of the eight seeded categories hold no products yet — the
     * catalogue is five shoes and all five are sneakers — so the strict rule
     * would offer one section in the drawer and eight on the front page, and a
     * visitor would be looking at two different shops on one screen.
     *
     * If that rule ever does change, it has to change in both places, and this
     * says so rather than letting one drift.
     *
     * **It has changed once, in one direction only.** «اکسسوری از منو حذف بشه»:
     * the drawer may drop a section the shopkeeper does not want in a menu, and
     * `show_in_nav` is the column that says which. So what is asserted is no
     * longer "the same list" but "the same list, minus exactly the ones marked
     * out of the nav, in the same order" — the drawer may not gain a section
     * the front page does not have, may not reorder them, and may not quietly
     * lose one that nobody marked.
     */
    public function test_the_drawer_and_the_front_page_name_the_same_sections(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $tiles = [];
        preg_match_all('~<span class="vp-category-label">([^<]+)</span>~', $page, $tiles);

        $rows = [];
        preg_match_all('~<span class="vp-cat-name">([^<]+)</span>~', $this->drawer(), $rows);

        $hidden = Category::where('show_in_nav', false)->pluck('name')->all();

        $expected = array_values(array_diff($tiles[1], $hidden));

        $this->assertNotEmpty($tiles[1]);
        $this->assertNotEmpty($expected);
        $this->assertSame($expected, $rows[1]);
    }

    /**
     * A category switched off is off everywhere.
     *
     * `is_active` is the control somebody actually has — a section that is not
     * ready yet — and it has to reach the menu, not only the tiles.
     */
    public function test_a_category_switched_off_leaves_the_drawer(): void
    {
        Category::where('slug', 'majlesi')->firstOrFail()->update(['is_active' => false]);

        $drawer = $this->drawer();

        $this->assertStringNotContainsString('/categories/majlesi', $drawer);
        $this->assertStringContainsString('/categories/sneaker', $drawer);
    }

    /**
     * A franchise gets the same sections — the catalogue is the platform's —
     * but its own basket and its own links under its own prefix.
     */
    public function test_a_franchises_drawer_stays_inside_its_own_branch(): void
    {
        app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $drawer = $this->drawer('/shiraz');

        $this->assertStringContainsString('/shiraz/categories/sneaker', $drawer);
        $this->assertStringContainsString('/shiraz/account/enter', $drawer);

        // And not one link back to the main store — that is how a franchise's
        // visitor ends up browsing somebody else's prices without noticing
        // they left. Every href in here starts with this branch's prefix.
        preg_match_all('~<a[^>]+href="([^"]+)"~', $drawer, $matches);

        foreach ($matches[1] as $href) {
            $this->assertStringStartsWith(url('/shiraz'), $href, "«{$href}» leaves the branch.");
        }
    }

    /**
     * The drawer is on every page, not only the home page, and it needs the
     * composer to have run on each of them.
     */
    public function test_the_drawer_is_composed_on_every_storefront_page(): void
    {
        foreach (['/', '/products', '/products/new-balance-530', '/cart', '/orders'] as $path) {
            $drawer = $this->drawer($path);

            $this->assertStringContainsString('vp-drawer-cats', $drawer, "No drawer categories on {$path}");
            $this->assertStringContainsString('ورود / ثبت‌نام', $drawer, "No way in on {$path}");
        }
    }

    /**
     * The static preview and this page have to carry the same drawer.
     *
     * partials/mobile-menu.blade.php is hand-owned now — make-blade.js does not
     * overwrite it — so the two copies can drift, and check-parity.js cannot
     * see it: the panel is parked off-screen at every width it renders at. This
     * compares the one thing that would actually differ.
     */
    public function test_the_static_preview_carries_the_same_categories(): void
    {
        $preview = base_path('../download-version/shoe-shop-rtl.html');

        if (! is_file($preview)) {
            $this->markTestSkipped('The static preview is not in this checkout.');
        }

        $names = function (string $html): array {
            $open = strpos($html, '<ul class="vp-drawer-cats">');
            $slice = substr($html, $open, strpos($html, '</ul>', $open) - $open);
            preg_match_all('~<span class="vp-cat-name">([^<]+)</span>~', $slice, $m);

            return $m[1];
        };

        $this->assertSame(
            $names(file_get_contents($preview)),
            $names($this->drawer()),
            'The preview and the application list different categories in the phone drawer.'
        );
    }

    /**
     * The tiles at the foot of the drawer, in the order they were asked for —
     * «از اون ۴ مستطیل پایین منو سوالات متداول باید حذف بشه پیگیری سفارش بره
     * جاش و اولین مورد بشه فروش عمده», and then «دوتا از اون مستطیل درازا بزار
     * که مستطیل های پایین بجای ۴ تا بشه ۶ تا».
     *
     * The order is the request, not a detail: the row is read right-to-left and
     * «فروش عمده» was asked to be first, so a list that holds the same six in a
     * different sequence is not what was asked for. Pinned as a sequence for
     * that reason, and because these live in two files — this one and
     * theme/make-rtl-page.js — that nothing else keeps in step.
     *
     * **The last two are the whole reason `/about` and `/terms` can be reached
     * on a telephone at all.** Both were in the footer and nowhere else, and the
     * footer on the home page is seven thousand pixels down: «من پیدا نکردم تو
     * نسخه موبایل مواردی که انجام دادیرو». Taking either off this row puts the
     * page back out of reach, which is why they are pinned here by name.
     */
    public function test_the_six_tiles_are_the_ones_asked_for_in_the_order_asked_for(): void
    {
        $this->assertSame(
            ['فروش عمده', 'فروشنده شوید', 'پیگیری سفارش', 'راهنمای سایز', 'تعهدات ما', 'شرایط ارسال و مرجوعی'],
            $this->tiles($this->drawer())
        );
    }

    /** And the two new ones go where they say they go. */
    public function test_the_two_new_tiles_reach_the_pages_the_footer_was_hiding(): void
    {
        $drawer = $this->drawer();

        foreach ([route('about'), route('terms')] as $url) {
            $this->assertStringContainsString($url, $drawer);
            $this->get($url)->assertOk();
        }
    }

    /**
     * «سوالات متداول» left the drawer and nothing else.
     *
     * The page was not removed — it is in the footer, in `/contact`, in
     * `/size-guide` and as the home page's own band — so the assertion is
     * narrow on purpose: gone from these four tiles, still reachable from the
     * shop. A test that simply searched the whole page for the words would
     * pass by deleting the page, which is the opposite of what was asked.
     */
    public function test_the_faq_left_the_tiles_and_stayed_in_the_shop(): void
    {
        $this->assertNotContains('سوالات متداول', $this->tiles($this->drawer()));

        $this->get('/')->assertOk()->assertSee('سوالات متداول');
        $this->get('/faq')->assertOk();
    }

    /** And the static preview carries the same four, in the same order. */
    public function test_the_static_preview_carries_the_same_tiles(): void
    {
        $preview = base_path('../download-version/shoe-shop-rtl.html');

        if (! is_file($preview)) {
            $this->markTestSkipped('The static preview is not in this checkout.');
        }

        $this->assertSame(
            $this->tiles($this->drawer()),
            $this->tiles(file_get_contents($preview)),
            'The preview and the application offer different tiles at the foot of the drawer.'
        );
    }

    /**
     * The labels of `.vp-drawer-links`, in document order.
     *
     * @return list<string>
     */
    private function tiles(string $html): array
    {
        $open = strpos($html, '<ul class="vp-drawer-links">');

        $this->assertNotFalse($open, 'The drawer has no tile row.');

        $slice = substr($html, $open, strpos($html, '</ul>', $open) - $open);

        preg_match_all('~<span>([^<]+)</span>~', $slice, $m);

        return $m[1];
    }
}
