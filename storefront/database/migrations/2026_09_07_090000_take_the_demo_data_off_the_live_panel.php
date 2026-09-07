<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Takes the pretend orders — and the test product, if it came back — off the
 * live panel.
 *
 * «دیتاهای فیک از پنل ادمین حذف بشه چون از امروز بصورت واقعی کار پروژه شروع
 * میشه، نباید با دیتای واقعی قاطی بشن اون دیتای فیک موجود.» The shop starts
 * trading for real today, and from today every figure in `/admin` is supposed
 * to be a figure somebody sold. `demo:orders` put eight orders in every state
 * the shop can produce so the panel could be judged against something other
 * than zero; they have done that job, and one more day of them is a dashboard
 * that adds pretend money to real money.
 *
 * **A migration and not the command.** Nobody runs a command on the live site:
 * the deploy runs `php artisan migrate --force` and nothing else. Same shape
 * as `2026_08_31_170000_take_the_test_product_off_the_live_shop`.
 *
 * **It calls the command rather than copying its statements, and that is the
 * one decision here worth defending.** The test product's migration is
 * `remove()` rewritten statement for statement, which is right for five
 * updates that touch no ledger. This is not that: taking a demo order away
 * puts stock back on a shelf, and stock in this application moves in exactly
 * two places — `PlaceOrder` reserves it, `SettleOrder` sells or releases it.
 * Anything else that writes `branch_inventory` is an oversell waiting to
 * happen. `demo:orders --remove` goes back through `SettleOrder::cancelled`,
 * walking a shipped or delivered order to «paid» first so the sale reverses
 * rather than the reservation, then takes back the units the demo borrowed by
 * reading them off `inventory_movements`. Hand-written SQL here would be a
 * third place that moves stock, and getting it wrong leaves the shelf short
 * for orders that no longer exist — silently, on the day the shop starts
 * selling.
 *
 * **Every branch, by slug.** `--remove` is scoped to one branch on purpose —
 * one shop must never clear another's rows — so the loop is what makes this
 * cover the whole platform. There is no tenant bound during a migration; the
 * command binds its own.
 *
 * **It cannot fail the deploy.** `liara_pre_start.sh` runs `migrate --force`
 * under `set -eu`, so a migration that throws does not fail a tidy-up: it
 * stops the shop from starting. A branch that refuses is reported and the
 * rest still run. The same trade as the sign-in alert's `try/catch` — an
 * outage is worse than an unfinished chore, and the chore says so out loud.
 *
 * `down()` does not put any of it back. A rollback that invented eight sales
 * on a trading shop would be worse than the thing it undid, and
 * `php artisan demo:orders` is one line on a developer's machine.
 */
return new class extends Migration
{
    /** `MakeDemoProduct::SLUG`, written out: a migration is frozen history. */
    private const TEST_PRODUCT = 'vp-test-item';

    public function up(): void
    {
        $this->clearTheDemoOrders();
        $this->retireTheTestProduct();
    }

    public function down(): void
    {
        // Deliberately empty. See the note above.
    }

    /**
     * The orders, their payments, their pretend customers, their baskets and
     * the stock they were holding — all of it through the command, so the
     * shelf is put back by the class that owns the shelf.
     */
    private function clearTheDemoOrders(): void
    {
        // Not `Branch::central()`: a franchise's panel has its own demo in it,
        // and a franchise opened later than the demo would keep its orders
        // for good if this only cleared head office.
        foreach (DB::table('branches')->orderBy('id')->pluck('slug') as $slug) {
            try {
                Artisan::call('demo:orders', ['--remove' => true, '--branch' => $slug]);
            } catch (Throwable $e) {
                $this->say("could not clear the demo orders at «{$slug}»: {$e->getMessage()}");
            }
        }
    }

    /**
     * The test product, retired again if a gateway test put it back.
     *
     * `2026_08_31_170000` did this once and production has been deployed many
     * times since; `php artisan demo:product` publishes a fresh one whenever
     * somebody needs to put a real card through something that does not cost
     * millions, and the card gateway was being connected in the weeks between.
     * These are that migration's statements again, and on a shop where nobody
     * ran it they are a no-op.
     *
     * Retired, not deleted: an order that bought it keeps its line.
     */
    private function retireTheTestProduct(): void
    {
        $product = DB::table('products')->where('slug', self::TEST_PRODUCT)->first();

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

    /**
     * Said where a deploy shows it and written where it can be found later.
     * A tidy-up that quietly did nothing is the failure nobody would look for.
     */
    private function say(string $message): void
    {
        Log::warning('take_the_demo_data_off_the_live_panel: '.$message);

        // The same prefix `liara_pre_start.sh` uses, so it reads as one log.
        echo 'storefront: '.$message.PHP_EOL;
    }
};
