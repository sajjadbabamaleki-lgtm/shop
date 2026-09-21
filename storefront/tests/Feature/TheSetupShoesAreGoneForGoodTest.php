<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The five setup shoes deleted outright, not archived.
 *
 * «اون پنج کفش راه اندازی لعنتیرو از سایت حذف کن جوری که انگار هیچوقت وجود
 * نداااااشتن.»
 *
 * They were retired on 07 Sept instead, and the migration that did it said
 * why: «an order that bought one keeps its line, and a product row that
 * vanishes takes an invoice's line with it». **The first half is a real
 * requirement and the second half is false**, which is the whole reason this
 * file exists — a wrong belief about a foreign key left five products on a
 * trading shop for two weeks and cost four ترب tickets.
 * `test_an_invoice_that_bought_one_still_reads` is the measurement.
 */
class TheSetupShoesAreGoneForGoodTest extends TestCase
{
    use RefreshDatabase;

    /** `CatalogueSeeder`'s five. */
    private const SETUP = ['golden-goose', 'on-cloudtilt', 'new-balance-530', 'nike-v2k-run', 'jordan-one-air'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_21_160000_delete_the_five_setup_shoes_for_good.php');
    }

    /**
     * A shoe of the shop's own, so the guard lets the migration fire.
     *
     * Title and slug are passed separately because on this shop they really
     * are different strings — the title is terse and the slug carries the
     * make, the Latin name and the colour. Assuming one can be derived from
     * the other is what made the first old-address fix ship green and answer
     * 404 on the live site.
     */
    private function theShopsOwnShoe(string $title, ?string $slug = null): Product
    {
        $product = Product::create([
            'slug' => $slug ?? trim(preg_replace('~[^\p{L}\p{N}]+~u', '-', $title) ?? '', '-'),
            'title' => $title,
            'short_title' => mb_substr($title, 0, 20),
            'status' => 'active',
            'published_at' => now(),
        ]);

        $variant = $product->variants()->create([
            'sku' => 'VP-OWN-'.strtoupper(mb_substr(md5($product->slug), 0, 8)),
            'size_value' => '41',
            'size_system' => 'EU',
            'display_color' => 'مشکی',
            'color_family' => 'black',
            'status' => 'active',
        ]);

        BranchOffer::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'price' => 5_000_000,
            'status' => 'active',
        ]);

        BranchInventory::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 4,
            'stock_reserved' => 0,
        ]);

        return $product;
    }

    /**
     * **The receipt survives the product, and this is the assertion the 07
     * Sept migration was written in fear of.**
     *
     * `order_items.variant_id` is `nullable()->constrained()->nullOnDelete()`
     * and the line carries its own `product_title`, `sku`, `size_value`,
     * `unit_price`, `quantity` and `line_total`, with a CHECK tying the last
     * three together. So deleting the shoe nulls one column and takes nothing
     * else: whoever bought it can still read what they bought and what they
     * paid.
     */
    public function test_an_invoice_that_bought_one_still_reads(): void
    {
        $this->theShopsOwnShoe('کتونی نایک ایر مکس رنگ مشکی');

        $shoe = Product::query()->withoutGlobalScopes()->where('slug', 'golden-goose')->firstOrFail();
        $variant = $shoe->variants()->firstOrFail();

        $customer = Customer::create(['phone' => '09120000000', 'name' => 'خریدار']);

        $order = Order::create([
            'branch_id' => Branch::central()->id,
            'customer_id' => $customer->id,
            'number' => 'VP-100001',
            'status' => 'paid',
            'subtotal' => 7_500_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 7_500_000,
            'contact_name' => 'خریدار',
            'contact_phone' => '09120000000',
            'address' => 'نشانی آزمایشی',
            'placed_at' => now(),
        ]);

        $line = OrderItem::create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'product_title' => $shoe->title,
            'sku' => $variant->sku,
            'size_value' => $variant->size_value,
            'unit_price' => 7_500_000,
            'quantity' => 1,
            'line_total' => 7_500_000,
        ]);

        $this->migration()->up();

        $line->refresh();

        $this->assertNull($line->variant_id, 'the foreign key should be nulled, not cascade');
        $this->assertSame('کتونی گلدن گوس', $line->product_title);
        $this->assertSame(7_500_000, (int) $line->unit_price);
        $this->assertSame(1, (int) $line->quantity);
        $this->assertSame(7_500_000, (int) $line->line_total);
        $this->assertSame(7_500_000, (int) $order->fresh()->grand_total);
    }

    /** On a shop with a catalogue of its own, all five rows are gone. */
    public function test_all_five_rows_are_deleted(): void
    {
        $own = $this->theShopsOwnShoe('کتونی نایک ایر مکس رنگ مشکی');

        $this->migration()->up();

        foreach (self::SETUP as $slug) {
            $this->assertNull(
                Product::query()->withoutGlobalScopes()->where('slug', $slug)->first(),
                "{$slug} is still a row on the shop",
            );
        }

        $this->assertSame(1, Product::query()->withoutGlobalScopes()->count());
        $this->get('/products/'.$own->slug)->assertOk();
    }

    /**
     * **Everything hanging off them goes with them**, by the schema's own
     * cascades rather than by anything written here. A row left behind
     * pointing at a product that is gone is what «انگار هیچوقت وجود نداشتن»
     * rules out.
     */
    public function test_nothing_is_left_pointing_at_them(): void
    {
        $this->theShopsOwnShoe('کتونی نایک ایر مکس رنگ مشکی');

        $variants = DB::table('variants')
            ->whereIn('product_id', DB::table('products')->whereIn('slug', self::SETUP)->pluck('id'))
            ->pluck('id');

        $this->assertNotEmpty($variants, 'the fixture has no variants to lose');

        $this->migration()->up();

        $this->assertSame(0, DB::table('variants')->whereIn('id', $variants)->count());
        $this->assertSame(0, DB::table('branch_offers')->whereIn('variant_id', $variants)->count());
        $this->assertSame(0, DB::table('branch_inventory')->whereIn('variant_id', $variants)->count());
        $this->assertSame(0, DB::table('inventory_movements')->whereIn('variant_id', $variants)->count());
    }

    /**
     * **A shop whose whole catalogue is the five is left exactly as it is**,
     * the same guard the 07 Sept migration carries and for the same reason:
     * they are there «برای اینکه سایت خالی نباشه». This is every other test in
     * this suite and both copies of the home page.
     */
    public function test_it_leaves_a_shop_that_has_nothing_else(): void
    {
        $this->migration()->up();

        $this->assertSame(5, Product::query()->listable()->count());
        $this->get('/')->assertOk();
    }

    /**
     * **The addresses still reach the shop**, which is the half that would
     * otherwise be a regression: the «دیگر عرضه نمی‌شود» page only exists for
     * a row that is still there, so deleting these makes them ordinary
     * unknown slugs and `ProductByOldAddress` is what answers them.
     *
     * ترب hold `nike-v2k-run` and asked for it by name on 2026-09-21.
     */
    public function test_the_addresses_they_leave_behind_reach_the_live_shoe(): void
    {
        // Slugs as the live shop really writes them, read off its sitemap on
        // 2026-09-21: one terse title per shoe, the colour only in the slug.
        $shop = [
            'کتونی نایک وی تو کی' => [
                'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-مشکی',
                'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-موکا',
                'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-قهوه-ای',
            ],
            'کتونی گلدن گوس' => [
                'کتونی-گلدن-گوس-رنگ-صورتی-Golden-Goose',
                'کتونی-گلدن-گوس-رنگ-مشکی-Golden-Goose',
            ],
            'کتونی نیوبالانس' => [
                'کتونی-نیوبالانس-New-balance-530-رنگ-سفید',
                'کتونی-نیوبالانس-New-balance-530-رنگ-مشکی',
            ],
        ];

        foreach ($shop as $title => $slugs) {
            foreach ($slugs as $slug) {
                $this->theShopsOwnShoe($title, $slug);
            }
        }

        $this->migration()->up();

        // Each old address names a make and no colour, and every candidate at
        // the top is that one shoe — so it is answered rather than refused,
        // and answered with the same shoe on every request.
        foreach (['nike-v2k-run' => 'کتونی نایک وی تو کی',
            'golden-goose' => 'کتونی گلدن گوس',
            'new-balance-530' => 'کتونی نیوبالانس'] as $old => $title) {
            $first = $this->get('/products/'.$old);

            $first->assertRedirect();

            $went = Product::query()->where('slug', urldecode(basename((string) $first->headers->get('Location'))))->firstOrFail();

            $this->assertSame($title, $went->title, "{$old} landed on the wrong shoe");

            // Stable: a second request must not pick a different colourway,
            // or ترب and a shopper are told two different things.
            $this->get('/products/'.$old)->assertRedirect(storefront_route('product', $went));
        }
    }

    /**
     * **`jordan-one-air` is still refused, and now for the stated reason.**
     *
     * This shop sells Jordan One in «ساق کوتاه» and «ساق بلند» — two shoes,
     * not two colours — and the address names neither. The client decided that
     * a guess there is worse than no answer, and the rule reaches that
     * decision on its own rather than from a list.
     */
    public function test_an_address_that_names_two_different_shoes_is_still_refused(): void
    {
        $this->theShopsOwnShoe('کتونی نایک جردن وان ساق کوتاه', 'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-سفید');
        $this->theShopsOwnShoe('کتونی نایک جردن وان ساق بلند', 'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-قرمز');

        $this->migration()->up();

        $this->get('/products/jordan-one-air')->assertNotFound();
    }
}
