<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One cheap, real, buyable product — for testing the payment gateway with a
 * card rather than with imagination.
 *
 *   php artisan demo:product                 # 100,000 Toman, one size, 20 on the shelf
 *   php artisan demo:product --toman=50000
 *   php artisan demo:product --remove
 *
 * A gateway cannot be tested against the catalogue as it stands: the cheapest
 * shoe in it is millions of Toman, and putting a real card through that to see
 * whether a callback lands is not a test anybody wants to run twice.
 *
 * It is a *real* product — a row in `products`, a variant, a branch offer and
 * stock, with a movement behind the stock like everything else. That is the
 * point: a fake that skipped any of those would exercise a path the shop does
 * not have. It is marked in its own title, its slug and its SKU so nobody
 * mistakes it for stock, and `--remove` takes it away completely.
 *
 * **It is published, so it is visible to customers.** That is what makes it
 * testable and it is also the reason to remove it afterwards. Said out loud
 * when it is made, and said again by the command's own output.
 */
class MakeDemoProduct extends Command
{
    protected $signature = 'demo:product
        {--toman=100000 : the price, in Toman}
        {--stock=20 : units on the shelf}
        {--branch= : the branch slug; defaults to the central shop}
        {--remove : delete the test product instead of making one}';

    protected $description = 'Put one cheap, buyable product in the shop so the payment gateway can be tested';

    /** The slug is the mark: it is how `--remove` finds it and how a person spots it. */
    public const SLUG = 'vp-test-item';

    public function handle(): int
    {
        $branch = $this->option('branch')
            ? Branch::where('slug', $this->option('branch'))->first()
            : Branch::central();

        if (! $branch) {
            $this->error('آن شعبه پیدا نشد.');

            return self::FAILURE;
        }

        // **Bound, or the branch-scoped models cannot find themselves.**
        // `BranchOffer` and `BranchInventory` fail closed: with no tenant in
        // the container their queries match nothing, so `updateOrCreate` found
        // no existing row and tried to insert a second one — straight into the
        // unique index on (branch_id, variant_id). The command looked like it
        // could only ever be run once.
        return app(TenantContext::class)->forBranch($branch, fn () => $this->option('remove')
            ? $this->remove($branch)
            : $this->make($branch));
    }

    private function make(Branch $branch): int
    {
        $toman = max(1000, (int) $this->option('toman'));
        $stock = max(1, (int) $this->option('stock'));

        // Prices are stored in Rial, everywhere, and the shop reads Toman. Ten
        // to one — the same conversion `toman()` prints with, in the opposite
        // direction. Getting this backwards is a price ten times wrong that
        // looks entirely normal on both sides.
        $rial = $toman * 10;

        $product = Product::updateOrCreate(['slug' => self::SLUG], [
            'title' => 'کالای آزمایشی، لطفاً نخرید',
            'short_title' => 'کالای آزمایشی',
            'status' => 'active',
            'description' => 'این کالا فقط برای آزمایش درگاه پرداخت ساخته شده و کالای واقعی نیست. '
                .'بعد از آزمایش حذف می‌شود.',
            'published_at' => now(),
        ]);

        // A product in no category is reachable by its own address and by
        // search, and by nothing else — which is enough to buy it, and keeps
        // it out of the category pages a customer browses.
        if ($category = Category::query()->first()) {
            $product->categories()->syncWithoutDetaching([$category->id]);
        }

        // A real size rather than the word «TEST»: the size chips render the
        // value, and a non-numeric one came out as «۰» on the product page —
        // an available size that looks like none. The SKU carries the mark
        // instead, where nothing renders it.
        // **Matched on the SKU, not on the size.** `--remove` retires this
        // variant rather than deleting it — an order that bought it keeps its
        // line — so a re-run has to find the same row again. Matching on
        // (product, size) made a *second* variant the first time the size
        // changed, and the unique index on `sku` caught it. The SKU is what
        // identifies this thing; the size is just what it wears.
        $variant = Variant::updateOrCreate(
            ['sku' => 'VP-TEST-ITEM'],
            [
                'product_id' => $product->id,
                'size_value' => '40',
                'display_color' => 'آزمایشی',
                'color_family' => 'other',
                'size_system' => 'EU',
                'status' => 'active',
            ]
        );

        DB::transaction(function () use ($branch, $variant, $rial, $stock) {
            BranchOffer::updateOrCreate(
                ['branch_id' => $branch->id, 'variant_id' => $variant->id],
                [
                    'price' => $rial,
                    // No struck-through price: a test item is not on sale, and
                    // a fake discount on it would be a fake discount on the
                    // shop front.
                    'compare_at_price' => null,
                    'status' => 'active',
                ]
            );

            $shelf = BranchInventory::updateOrCreate(
                ['branch_id' => $branch->id, 'variant_id' => $variant->id],
                ['stock_on_hand' => $stock, 'stock_reserved' => 0],
            );

            InventoryMovement::create([
                'branch_id' => $branch->id,
                'variant_id' => $variant->id,
                'type' => 'receipt',
                'quantity' => $shelf->stock_on_hand,
                'note' => 'کالای آزمایشی درگاه پرداخت',
            ]);
        });

        $product->forceFill(['default_variant_id' => $variant->id])->save();

        $this->info('ساخته شد: '.$product->title);
        $this->line('  قیمت: '.fa_number($toman).' تومان');
        $this->line('  موجودی: '.fa_number($stock));
        $this->line('  آدرس: /products/'.self::SLUG);
        $this->newLine();
        $this->warn('این کالا برای مشتری‌ها هم دیده می‌شود. بعد از آزمایش پاکش کنید:');
        $this->line('  php artisan demo:product --remove');

        $this->gateway();

        return self::SUCCESS;
    }

    /**
     * What the shop will actually do at checkout.
     *
     * Said here because the whole point of this command is to test a gateway,
     * and on `at-the-door` there is no gateway to reach — the order is taken
     * and the courier collects. Somebody would buy the item, see no bank page,
     * and reasonably conclude the gateway was broken.
     */
    private function gateway(): void
    {
        $driver = (string) config('services.payment.driver', 'at-the-door');

        $this->newLine();
        $this->line('درگاه فعال: '.$driver);

        if ($driver === 'at-the-door') {
            $this->warn('این یعنی «پرداخت در محل»؛ هیچ درگاه بانکی‌ای در کار نیست.');
            $this->line('برای آزمایش کارت، در پنل لیارا این‌ها را بگذارید:');
            $this->line('  PAYMENT_DRIVER=zarinpal');
            $this->line('  ZARINPAL_MERCHANT_ID=<شناسه ۳۶ کاراکتری از پنل زرین‌پال>');
            $this->line('بعدش: php artisan config:cache');

            return;
        }

        if (config('services.payment.zarinpal.sandbox')) {
            $this->error('ZARINPAL_SANDBOX روشن است؛ پرداخت‌ها واقعی نیستند و نباید در سایت زنده روشن باشد.');
        }
    }

    private function remove(Branch $branch): int
    {
        $product = Product::where('slug', self::SLUG)->first();

        if (! $product) {
            $this->info('کالای آزمایشی‌ای برای پاک کردن نبود.');

            return self::SUCCESS;
        }

        $variants = Variant::where('product_id', $product->id)->pluck('id');

        // An order that bought it keeps its line, so the row cannot simply be
        // deleted out from under one. Retiring the offer and emptying the shelf
        // takes it off the shop while leaving every record that mentions it
        // intact — which is what «حذف» has to mean for anything sellable.
        DB::transaction(function () use ($product, $variants) {
            BranchOffer::withoutGlobalScopes()->whereIn('variant_id', $variants)
                ->update(['status' => 'inactive']);

            BranchInventory::withoutGlobalScopes()->whereIn('variant_id', $variants)
                ->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

            Variant::whereIn('id', $variants)->update(['status' => 'inactive']);

            // «archived», not «inactive». A product's status is one of
            // draft/active/archived and a variant's is active/inactive — two
            // different vocabularies, and the CHECK constraint on `products`
            // is what said so when this used the wrong one.
            $product->forceFill(['status' => 'archived', 'published_at' => null])->save();
        });

        $this->info('کالای آزمایشی از فروشگاه برداشته شد.');
        $this->line('سفارش‌هایی که آن را خریده‌اند دست‌نخورده‌اند.');

        return self::SUCCESS;
    }
}
