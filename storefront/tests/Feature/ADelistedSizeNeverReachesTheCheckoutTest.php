<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Checkout\CannotFulfil;
use App\Support\Checkout\CartManager;
use App\Support\Checkout\PlaceOrder;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A size the shop has stopped selling must not be offered, priced, or totalled.
 *
 * «من دیروز این بوتو اد کردم الان تو فروشگاه میاد بالا ولی اون ارور بالارو
 * میده» — a photograph of the basket holding one boot at ۳٬۹۸۰٬۰۰۰ تومان, a
 * total under it, an «ادامه» button, and at the top of the same screen
 * «… در این شعبه فروخته نمی‌شود». Every one of those four things was drawn by
 * this application about one line, in one request.
 *
 * **Two switches take a size off the shop and there were two answers to what
 * they mean.** `variants.status` is «بازنشسته کن» on the product screen;
 * `branch_offers.status` is the وضعیت select on `/admin/pricing`. `PlaceOrder`
 * read both, inside its transaction. `Sellers` — which the product page, the
 * basket's price, the basket's total and the basket's warning mark all go
 * through — read neither for the branch's own offer, so the shop went on
 * offering, pricing and adding up a size it had already refused to sell. The
 * customer met the refusal on the far side of the address form.
 *
 * Nothing else here could see it. `CheckoutTest` covers the refusal itself and
 * is right; `check-parity.js` counts pixels on a page whose catalogue is
 * healthy; and the panel's own screen said «فعال» either way. What follows is
 * the pair of screens agreeing, and the last two tests are the ones to read
 * before changing any of it: out-of-stock and delisted are **different**
 * states with different sentences, and folding them together would be this
 * same fault pointed the other way.
 */
class ADelistedSizeNeverReachesTheCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
        $this->tenant->set(Branch::central());
    }

    private function variant(string $slug): Variant
    {
        return Product::where('slug', $slug)->firstOrFail()->defaultVariant;
    }

    /** The size itself, retired from the product screen. */
    private function retireTheSize(Variant $variant): Variant
    {
        $variant->update(['status' => 'inactive']);

        return $variant->fresh();
    }

    /** The price row, switched off from /admin/pricing. */
    private function switchOffThePrice(Variant $variant): Variant
    {
        $variant->offer->update(['status' => 'inactive']);

        return $variant->fresh();
    }

    // --- the product page -------------------------------------------------

    public function test_a_retired_size_loses_its_chip(): void
    {
        $variant = $this->retireTheSize($this->variant('new-balance-530'));

        $this->get('/products/new-balance-530')
            ->assertOk()
            ->assertDontSee('name="variant" value="'.$variant->id.'"', false);
    }

    public function test_a_size_whose_price_is_switched_off_loses_its_chip(): void
    {
        $variant = $this->switchOffThePrice($this->variant('new-balance-530'));

        $this->get('/products/new-balance-530')
            ->assertOk()
            ->assertDontSee('name="variant" value="'.$variant->id.'"', false);
    }

    // --- adding one ------------------------------------------------------

    public function test_a_retired_size_cannot_be_added_and_is_told_why(): void
    {
        $variant = $this->retireTheSize($this->variant('new-balance-530'));

        $this->post('/cart', ['variant' => $variant->id])
            ->assertRedirect(route('cart'))
            ->assertSessionHasErrors(['cart' => 'این سایز دیگر در این شعبه فروخته نمی‌شود.']);

        $this->assertTrue(app(CartManager::class)->current()->fresh()->items->isEmpty());
    }

    public function test_a_size_whose_price_is_switched_off_cannot_be_added(): void
    {
        $variant = $this->switchOffThePrice($this->variant('new-balance-530'));

        $this->post('/cart', ['variant' => $variant->id]);

        $this->assertTrue(app(CartManager::class)->current()->fresh()->items->isEmpty());
    }

    // --- one that is already in a basket ----------------------------------

    /**
     * The photograph, reproduced: in the basket first, retired afterwards.
     *
     * The line is kept and marked — «a basket that quietly loses things is
     * worse than one that says what happened» — and everything that would
     * have invited the customer to pay for it is gone.
     */
    public function test_a_basket_holding_a_retired_size_says_so_and_offers_no_way_on(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id]);
        $this->retireTheSize($variant);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('این کالا دیگر در این شعبه فروخته نمی‌شود', false)
            ->assertSee('اول ردیف‌های مشخص‌شده را درست کن', false)
            ->assertDontSee('vp-cart-go', false);
    }

    public function test_a_retired_line_is_not_in_the_total(): void
    {
        $this->post('/cart', ['variant' => $this->variant('new-balance-530')->id]);
        $this->post('/cart', ['variant' => $variant = $this->variant('nike-v2k-run')->id]);

        $cart = app(CartManager::class)->current()->fresh()->load('items.variant.offer', 'items.variant.stock');
        $both = $cart->subtotal();

        $this->retireTheSize($this->variant('new-balance-530'));

        $cart = app(CartManager::class)->current()->fresh()->load('items.variant.offer', 'items.variant.stock');

        $this->assertSame(Variant::find($variant)->offer->price, $cart->subtotal());
        $this->assertLessThan($both, $cart->subtotal());
        $this->assertCount(1, $cart->problems());
    }

    public function test_the_same_holds_when_it_is_the_price_row_that_is_off(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id]);
        $this->switchOffThePrice($variant);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('این کالا دیگر در این شعبه فروخته نمی‌شود', false)
            ->assertDontSee('vp-cart-go', false);
    }

    // --- the checkout, which was always right -----------------------------

    /**
     * Unchanged, and the point of the change above: this is now the second
     * place to say no rather than the first. A size can still be retired
     * between the basket being drawn and this transaction running, which is
     * why the check stays here at all.
     */
    public function test_the_transaction_still_refuses_it_by_name(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id]);
        $this->retireTheSize($variant);

        $this->expectException(CannotFulfil::class);
        $this->expectExceptionMessage('در این شعبه فروخته نمی‌شود');

        app(PlaceOrder::class)(
            app(CartManager::class)->current()->fresh()->load('items.variant'),
            ['name' => 'سجاد', 'phone' => '09123456789', 'address' => 'تهران'],
        );
    }

    // --- the line that must not move --------------------------------------

    /**
     * **Out of stock is not delisted**, and the shop has to keep saying two
     * different things. A shoe the branch still sells and has run out of keeps
     * its price and its line and is told «فقط ۰ عدد موجود است»; the listing
     * still carries it — «نمیشه کفشی که موجودیش ۰ هست بیاد تو لیست فقط موجودی
     * بزنه ۰؟». Answering both with «دیگر فروخته نمی‌شود» would be this same
     * fault written backwards.
     */
    public function test_an_empty_shelf_still_keeps_its_price_and_its_own_sentence(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id]);

        $variant->stock->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('فقط ۰ عدد موجود است', false)
            ->assertDontSee('این کالا دیگر در این شعبه فروخته نمی‌شود', false);

        $cart = app(CartManager::class)->current()->fresh()->load('items.variant.offer', 'items.variant.stock');

        $this->assertNotNull($cart->lines()->first()['offer'], 'the branch still sells it');
        $this->assertSame(0, $cart->lines()->first()['available']);
    }

    public function test_a_shoe_with_an_empty_shelf_is_still_in_the_listing(): void
    {
        $variant = $this->variant('new-balance-530');
        $variant->stock->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

        $this->get('/products')->assertOk()->assertSee('کتونی نیوبالانس ۵۳۰', false);
    }

    /**
     * And a shoe whose every size is retired leaves the listing altogether —
     * `Product::listable()` has always said so, and this asserts the two
     * rules agree rather than each being right on its own.
     */
    public function test_a_shoe_with_every_size_retired_leaves_the_listing(): void
    {
        $product = Product::where('slug', 'new-balance-530')->firstOrFail();
        $product->variants()->update(['status' => 'inactive']);

        $this->get('/products')->assertOk()->assertDontSee('کتونی نیوبالانس ۵۳۰', false);
    }
}
