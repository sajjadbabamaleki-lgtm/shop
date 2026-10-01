<?php

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Two cheap test items on the live shop, at the owner's request: «دو تا محصول
 * تستی توی سایت بذار که قیمتشون 85000 تومان باشه ولی یکیشون باید 90000 تومان
 * باشه که با تخفیفی که خورده شده 85000 تومان».
 *
 * One at 85,000 Toman plain, one at 85,000 with 90,000 struck through — so
 * both the ordinary card and the discounted one can be put through the
 * gateways for little money. A migration because nobody runs a command on the
 * live site; `demo:product` makes only one, with no discount.
 *
 * Their slugs and SKUs carry «vp-test» so they are recognised, and they are
 * taken off the same way `demo:product --remove` takes its own: retired, not
 * deleted, since an order that bought one keeps its line.
 *
 * **Only where the shop has a catalogue of its own** — the test suite migrates
 * an empty database before seeding it, and two extra products there would move
 * every count in it. It never throws: `migrate --force` runs under `set -eu`,
 * and a test item is not worth a shop that will not start.
 */
return new class extends Migration
{
    private const ITEMS = [
        'vp-test-item-a' => ['sku' => 'VP-TEST-A', 'title' => 'کالای آزمایشی ۱، لطفاً نخرید', 'price' => 85000, 'was' => null],
        'vp-test-item-b' => ['sku' => 'VP-TEST-B', 'title' => 'کالای آزمایشی ۲ (تخفیف‌دار)، لطفاً نخرید', 'price' => 85000, 'was' => 90000],
    ];

    public function up(): void
    {
        try {
            if (! Product::query()->exists()) {
                return;
            }

            $branch = Branch::central();

            if ($branch === null) {
                return;
            }

            app(TenantContext::class)->forBranch($branch, function () use ($branch) {
                foreach (self::ITEMS as $slug => $item) {
                    $this->put($branch, $slug, $item);
                }
            });
        } catch (Throwable $e) {
            Log::error("The two test items could not be put on the shop: {$e->getMessage()}");
        }
    }

    /**
     * @param  array{sku: string, title: string, price: int, was: ?int}  $item
     */
    private function put(Branch $branch, string $slug, array $item): void
    {
        DB::transaction(function () use ($branch, $slug, $item) {
            $product = Product::updateOrCreate(['slug' => $slug], [
                'title' => $item['title'],
                'short_title' => 'کالای آزمایشی',
                'status' => 'active',
                'description' => 'این کالا فقط برای آزمایش درگاه پرداخت ساخته شده و کالای واقعی نیست.',
                'published_at' => now(),
            ]);

            if ($category = Category::query()->where('coming_soon', false)->orderBy('id')->first()) {
                $product->categories()->syncWithoutDetaching([$category->id]);
            }

            $variant = Variant::updateOrCreate(['sku' => $item['sku']], [
                'product_id' => $product->id,
                'size_value' => '40',
                'display_color' => 'آزمایشی',
                'color_family' => 'other',
                'size_system' => 'EU',
                'status' => 'active',
            ]);

            // Rial: the shop stores ten to the Toman.
            BranchOffer::withoutGlobalScopes()->updateOrCreate(
                ['branch_id' => $branch->id, 'variant_id' => $variant->id],
                [
                    'price' => $item['price'] * 10,
                    'compare_at_price' => $item['was'] !== null ? $item['was'] * 10 : null,
                    'status' => 'active',
                ],
            );

            $shelf = BranchInventory::withoutGlobalScopes()->firstOrNew(
                ['branch_id' => $branch->id, 'variant_id' => $variant->id],
            );

            if (! $shelf->exists) {
                $shelf->fill(['stock_on_hand' => 10, 'stock_reserved' => 0])->save();

                InventoryMovement::create([
                    'branch_id' => $branch->id,
                    'variant_id' => $variant->id,
                    'type' => 'receipt',
                    'quantity' => 10,
                    'note' => 'کالای آزمایشی درگاه پرداخت',
                ]);
            }

            $product->forceFill(['default_variant_id' => $variant->id])->save();
        });
    }

    public function down(): void
    {
        // Retired by hand when the testing is done; see the docblock.
    }
};
