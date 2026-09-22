<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Models\VariantMedia;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two things the panel could not tell anybody, both reported the same evening.
 *
 * **«وقتی پرداختی صورت میگیره مشخص نیست که این پرداخت با اسنپ پی بوده یا با
 * زرین پال. این باید تو پنل مشخص باشه.»** The gateway has been on the
 * `payments` row since the second provider was connected and no screen read
 * it: the order page printed `payment_status` (paid/unpaid) and
 * `orders.payment_method` (online/at-the-door), which are the same two words
 * whichever provider took the money. A day's takings are reconciled against
 * two different statements, so «پرداخت‌شده / پرداخت اینترنتی» is not an answer.
 *
 * **«چون ما عکس هامون از باسلام برداشته شده رنگشون مشخص نیست، پس تو پنل ادمین
 * باید با عکس خود کفش به ما نشون بده چه کفشی سفارش داده تا بفهمیم از رو عکس که
 * رنگش چیه.»** The supplier's titles do not carry a colour anybody can pack
 * from, and every variant in this catalogue is still `color_family =
 * unspecified` — so the words on an order line cannot say which shoe is in the
 * box and the photograph can.
 *
 * Both are invisible to every other check here: nothing in this repository
 * asks what the *panel* shows, and `check-parity.js` counts pixels on the
 * storefront's home page.
 */
class AnOrderSaysWhoPaidAndShowsTheShoeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();
    }

    private function admin(): User
    {
        $user = User::create(['name' => 'مدیر', 'email' => 'who-paid@vikyplus.test', 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    /** An order of one shoe, paid through the gateway named. */
    private function order(string $gateway, string $status = Payment::PAID): Order
    {
        return app(TenantContext::class)->forBranch($this->branch, function () use ($gateway, $status): Order {
            $variant = Variant::whereHas('product', fn ($q) => $q->whereNotNull('published_at'))->firstOrFail();

            $order = Order::create([
                'branch_id' => $this->branch->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => Order::PAID,
                'payment_status' => 'paid',
                'payment_method' => 'online',
                'subtotal' => 1_000_000, 'discount_total' => 0, 'shipping_total' => 0,
                'grand_total' => 1_000_000,
                'contact_name' => 'مریم رضایی',
                'contact_phone' => '09121112233',
                'address' => 'تهران',
                'placed_at' => now(),
                'paid_at' => now(),
            ]);

            $order->items()->create([
                'variant_id' => $variant->id,
                'product_title' => $variant->product->title,
                'sku' => $variant->sku,
                'size_value' => $variant->size_value,
                'display_color' => $variant->display_color,
                'unit_price' => 1_000_000, 'quantity' => 1, 'line_total' => 1_000_000,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'gateway' => $gateway,
                'authority' => strtoupper(substr($gateway, 0, 3)).mt_rand(1000000, 9999999),
                'amount' => 1_000_000,
                'status' => $status,
                'ref_id' => 'REF-'.mt_rand(1000, 9999),
                'paid_at' => $status === Payment::PAID ? now() : null,
            ]);

            return $order->fresh('items');
        });
    }

    // --- which provider took the money -------------------------------------

    public function test_the_order_screen_names_the_gateway(): void
    {
        $card = $this->order('zarinpal');
        $lender = $this->order('snapppay');
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->get("/admin/orders/{$card->number}")
            ->assertOk()
            ->assertSee('درگاه', false)
            ->assertSee('زرین‌پال', false)
            ->assertDontSee('اسنپ‌پی', false);

        $this->actingAs($admin, 'web')->get("/admin/orders/{$lender->number}")
            ->assertOk()
            ->assertSee('اسنپ‌پی (اقساطی)', false);
    }

    public function test_the_orders_list_names_the_gateway(): void
    {
        $this->order('zarinpal');
        $this->order('snapppay');

        $this->actingAs($this->admin(), 'web')->get('/admin/orders')
            ->assertOk()
            ->assertSee('زرین‌پال', false)
            ->assertSee('اسنپ‌پی (اقساطی)', false);
    }

    /**
     * **A refused attempt names its provider too.** «چرا این سفارش دو بار
     * پرداخت شد» is usually a card that was refused and instalments that were
     * not, and that sentence cannot be read when both attempts say «ناموفق»
     * and nothing else.
     */
    public function test_a_refused_attempt_says_which_gateway_refused(): void
    {
        $order = $this->order('snapppay');

        $order->payments()->create([
            'gateway' => 'zarinpal',
            'authority' => 'A'.mt_rand(1000000, 9999999),
            'amount' => 1_000_000,
            'status' => Payment::FAILED,
        ]);

        $this->actingAs($this->admin(), 'web')->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('زرین‌پال', false)
            ->assertSee('اسنپ‌پی (اقساطی)', false);
    }

    /**
     * Money taken in the panel is not a gateway, and must not be dressed as
     * one — `OrderController::pay()` writes `panel` for exactly that reason.
     */
    public function test_money_recorded_by_hand_is_not_called_a_gateway(): void
    {
        $order = $this->order('panel');

        $this->actingAs($this->admin(), 'web')->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('ثبت دستی در پنل', false)
            ->assertDontSee('زرین‌پال', false);
    }

    /** A provider connected later is visible rather than blank. */
    public function test_an_unknown_gateway_falls_back_to_its_own_name(): void
    {
        $this->assertSame('behpardakht', (new Payment(['gateway' => 'behpardakht']))->gatewayLabel());
    }

    // --- the shoe's own photograph -----------------------------------------

    public function test_the_order_screen_draws_the_shoes_photograph(): void
    {
        $order = $this->order('zarinpal');
        $item = $order->items->first();
        $product = $item->variant->product;

        VariantMedia::create([
            'product_id' => $product->id,
            'display_color' => $item->display_color,
            'path' => 'assets/img/product/moka-suede.webp',
            'position' => 1,
            'is_primary' => true,
        ]);

        $this->actingAs($this->admin(), 'web')->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('assets/img/product/moka-suede.webp', false)
            // And it opens the shoe's own screen, where the gallery is.
            ->assertSee(route('admin.product.edit', $product), false);
    }

    /**
     * **The colourway's photograph, not the product's first.** A product here
     * is usually one colour — `basalam:import` writes them that way — but the
     * panel can hold several on one shoe, and the whole point of the picture
     * is the colour.
     */
    public function test_it_picks_the_photograph_of_the_colour_that_was_bought(): void
    {
        $order = $this->order('zarinpal');
        $item = $order->items->first();
        $product = $item->variant->product;

        VariantMedia::create([
            'product_id' => $product->id,
            'display_color' => 'مشکی',
            'path' => 'assets/img/product/wrong-colour.webp',
            'position' => 1,
            'is_primary' => true,
        ]);
        VariantMedia::create([
            'product_id' => $product->id,
            'display_color' => $item->display_color,
            'path' => 'assets/img/product/right-colour.webp',
            'position' => 2,
        ]);

        $this->assertSame('assets/img/product/right-colour.webp', $item->fresh()->photoPath());

        $this->actingAs($this->admin(), 'web')->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('right-colour.webp', false)
            ->assertDontSee('wrong-colour.webp', false);
    }

    /**
     * A shoe the shop has deleted leaves the receipt intact and the picture
     * absent — which is the whole of what `photoPath()` promises. The line's
     * own words are still there, because they were copied at the moment of
     * purchase and never read through the variant.
     */
    public function test_a_deleted_shoe_leaves_the_line_readable_and_the_photograph_blank(): void
    {
        $order = $this->order('zarinpal');
        $item = $order->items->first();
        $title = $item->product_title;

        app(TenantContext::class)->forBranch($this->branch, fn () => Product::whereKey($item->variant->product_id)->firstOrFail()->delete());

        $this->assertNull($item->fresh()->photoPath());

        $this->actingAs($this->admin(), 'web')->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee($title, false)
            ->assertSee('vp-adm-shot is-empty', false);
    }

    /**
     * A shoe with no photographs yet is the same blank, not a broken image —
     * an ordinary state, because the panel makes the product first and the
     * pictures after.
     */
    public function test_a_shoe_with_no_photographs_draws_nothing(): void
    {
        $order = $this->order('zarinpal');
        $item = $order->items->first();

        // The seeder photographs everything it builds, so this is the state
        // being tested rather than the state that happened to be there.
        VariantMedia::where('product_id', $item->variant->product_id)->delete();

        $this->assertNull($item->fresh()->photoPath());

        $this->actingAs($this->admin(), 'web')->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('vp-adm-shot is-empty', false);
    }
}
