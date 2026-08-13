<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Checkout\CartManager;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mini basket behind the header's basket button.
 *
 * It has its own file for the reason `PhoneDrawerTest` does. What this panel
 * held until now was the ThemeForest demo — «فروشگاهping Cart», five Nike and
 * Adidas shoes, `$39.00`, remove links pointing at '#' — and it survived the
 * entire port, on every page, at every width. The panel is parked off-screen,
 * so `check-parity.js` renders it and sees nothing, `check-overflow.js` cannot
 * reach it, and every test passed.
 *
 * So these tests watch the two things no rendering check can: that the demo is
 * gone, and that the panel says what is actually in this visitor's basket at
 * this branch's prices.
 */
class MiniCartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    private function panel(string $path = '/'): string
    {
        $page = $this->get($path)->assertOk()->getContent();

        $open = strpos($page, '<div class="sidemenu-wrapper sidemenu-cart">');
        $this->assertNotFalse($open, 'The page has no mini basket.');

        // The layout draws chrome, then this panel, then the phone drawer, so
        // the drawer's opening tag is where this one ends. `popup-search-box`
        // looks like the natural end and is not: it lives in chrome, which is
        // rendered *before* the panel.
        $close = strpos($page, '<div class="th-menu-wrapper">', $open);
        $this->assertNotFalse($close, 'The mini basket has no end.');

        return substr($page, $open, $close - $open);
    }

    private function variant(string $slug): Variant
    {
        return Product::where('slug', $slug)->firstOrFail()->defaultVariant;
    }

    /**
     * The template's demo is gone. Every one of these was on the page the day
     * the site went up, behind the button a customer presses to see what they
     * are buying.
     */
    public function test_no_template_leftovers_in_the_mini_basket(): void
    {
        $panel = $this->panel();

        foreach ([
            'فروشگاهping',          // the half-translated «Shopping cart»
            'Nike Renew Serenity',
            'Adidas Plastic Rebar',
            'Subtotal:',
            '$',                    // every price in it was in dollars
            'woocommerce',
            'View cart',
            'product_12_',          // the demo's own photographs
        ] as $leftover) {
            $this->assertStringNotContainsString($leftover, $panel);
        }
    }

    /**
     * Nothing in it goes nowhere. The demo's remove links were all '#', which
     * is the failure that looks exactly like a working panel.
     */
    public function test_every_link_in_the_mini_basket_goes_somewhere(): void
    {
        $this->post('/cart', ['variant' => $this->variant('new-balance-530')->id, 'quantity' => 1]);

        preg_match_all('~<a[^>]+href="([^"]*)"~', $this->panel(), $matches);

        $this->assertNotEmpty($matches[1], 'The mini basket has no links at all.');

        foreach ($matches[1] as $href) {
            $this->assertNotSame('#', $href, 'A mini basket link points at nothing.');
            $this->assertNotSame('', $href);
        }
    }

    /** An empty basket says so, and offers the way out of being empty. */
    public function test_an_empty_basket_says_it_is_empty(): void
    {
        $panel = $this->panel();

        $this->assertStringContainsString('سبد خریدت خالی است.', $panel);
        $this->assertStringContainsString(route('shop'), $panel);
        $this->assertStringNotContainsString('تسویه حساب', $panel);
    }

    /**
     * A basket with something in it shows that thing, at this branch's price,
     * and both ways on from there.
     */
    public function test_a_full_basket_shows_its_own_lines_and_total(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id, 'quantity' => 2]);

        // Read the total off the basket rather than multiplying the price by
        // the quantity that was asked for: `add()` clamps to what the branch
        // actually has, so the line may hold 1 of a size with 1 on the shelf.
        // The panel has to agree with the basket, which is the thing under
        // test — not with the request.
        $cart = app(CartManager::class)->current()->fresh();

        $panel = $this->panel();

        $this->assertStringContainsString($variant->product->title, $panel);
        $this->assertStringContainsString(toman($cart->subtotal()), $panel);
        $this->assertStringContainsString(fa_number($cart->count()).' × سایز', $panel);
        $this->assertStringContainsString('جمع کالاها', $panel);
        $this->assertStringContainsString(route('cart'), $panel);
        $this->assertStringContainsString(route('checkout'), $panel);
        $this->assertStringNotContainsString('سبد خریدت خالی است.', $panel);
    }

    /**
     * The panel is on every storefront page, not only the home page. It is
     * drawn by a view composer for exactly this reason — a page that had to
     * pass it in would render an *empty* panel when somebody forgot, which
     * looks like an empty basket rather than like a bug.
     */
    public function test_the_mini_basket_is_on_the_other_pages_too(): void
    {
        $this->post('/cart', ['variant' => $this->variant('new-balance-530')->id, 'quantity' => 1]);

        foreach (['/products', '/cart'] as $path) {
            $this->assertStringContainsString('جمع کالاها', $this->panel($path));
        }
    }

    /** A line can be taken out from the panel itself, not only from the page. */
    public function test_a_line_can_be_removed_from_the_panel(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id, 'quantity' => 1]);
        $this->assertStringContainsString($variant->product->title, $this->panel());

        $this->post('/cart/remove', ['variant' => $variant->id]);

        $this->assertStringContainsString('سبد خریدت خالی است.', $this->panel());
    }

    /**
     * A franchise's panel prices at that franchise, and its links stay inside
     * it. The basket is the branch's, so this is the same isolation the rest of
     * the storefront has — asserted here because a panel on every page is a
     * good place for a branch to leak out of.
     */
    public function test_a_franchise_panel_stays_inside_its_own_branch(): void
    {
        app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', markupPercent: 20, openingStock: 5);

        $this->get('/shiraz')->assertOk();

        $variant = $this->variant('new-balance-530');

        $this->post('/shiraz/cart', ['variant' => $variant->id, 'quantity' => 1]);

        $panel = $this->panel('/shiraz');

        $this->assertStringContainsString('/shiraz/cart', $panel);
        $this->assertStringContainsString('/shiraz/checkout', $panel);
    }
}
