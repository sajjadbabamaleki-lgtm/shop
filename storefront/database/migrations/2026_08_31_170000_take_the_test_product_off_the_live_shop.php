<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes the test product off the shop.
 *
 * «کالای آزمایشی که قبلا تو سایت گذاشته بودیم هنوز هست باید حذف بشه» — it was
 * put there by `php artisan demo:product` so a real card could be put through
 * the payment gateway without sending a real shoe's price through it, and it is
 * published, so customers can see it.
 *
 * **A migration and not the command.** `demo:product --remove` is the command
 * that does this, and nobody runs a command on the live site: the deploy runs
 * `php artisan migrate --force` and nothing else. So this is that command's
 * `remove()` path, statement for statement — see `MakeDemoProduct::remove()`,
 * which stays the way to do it on a developer's machine.
 *
 * **Retired, not deleted, and that is the whole shape of it.** An order that
 * bought this keeps its line, so the row cannot be deleted out from under one.
 * The offer goes inactive, the shelf is emptied, the variants go inactive and
 * the product is archived with its `published_at` cleared. Every record that
 * mentions it stays readable; nothing on the shop can reach it.
 *
 * «archived» and not «inactive» on the product: a product's status is one of
 * draft/active/archived and a variant's is active/inactive, two vocabularies,
 * and the CHECK constraint on `products` is what said so the first time this
 * used the wrong one.
 *
 * Every branch. `BranchOpener` copies an offer to a franchise when it opens, so
 * a scoped write would leave the test item on sale at every shop but the
 * central one. There is no tenant bound during a migration in any case.
 *
 * `down()` does not put it back. Re-publishing a test item on a live shop is
 * not something a rollback should do on its own; `php artisan demo:product` is
 * one line and makes a fresh one.
 */
return new class extends Migration
{
    private const SLUG = 'vp-test-item';

    public function up(): void
    {
        $product = DB::table('products')->where('slug', self::SLUG)->first();

        if ($product === null) {
            return;
        }

        $variants = DB::table('variants')->where('product_id', $product->id)->pluck('id');

        if ($variants->isNotEmpty()) {
            DB::table('branch_offers')->whereIn('variant_id', $variants)
                ->update(['status' => 'inactive']);

            DB::table('branch_inventory')->whereIn('variant_id', $variants)
                ->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

            DB::table('variants')->whereIn('id', $variants)
                ->update(['status' => 'inactive']);
        }

        DB::table('products')->where('id', $product->id)
            ->update(['status' => 'archived', 'published_at' => null]);
    }

    public function down(): void
    {
        // Deliberately empty. See the note above: a rollback must not publish a
        // test product on a live shop, and `demo:product` makes a new one.
    }
};
