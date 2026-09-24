<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The sandals back on the shop, with nothing on the shelf.
 *
 * «ببین من میخواستم موجودیشونو ۰ کنم نمیخواستم کلا تو سرچ و فیلتر نشون داده
 * نشن» — the same morning `retire_the_sandals_for_the_winter` took them off.
 * «بازنشسته» was read as «off the shop»; what was meant was «not for sale»,
 * which on this shop are two different questions: **listing and stock are
 * separate** (see `Variant::isListed()` beside `isSellable()`). A shoe at
 * nought stays in the listing, the search and the filters with its price and
 * «ناموجود», and cannot be put in a basket. So this undoes the retirement
 * exactly and moves the other switch instead.
 *
 * **What it puts back** is precisely what that migration took: a sandal that
 * is `archived` with its `published_at` still set. That migration kept the
 * date on purpose and nothing else on this shop archives that way — the
 * payment-test product was archived with its date cleared — so the
 * fingerprint cannot catch a retirement somebody else made. The section loses
 * its «به‌زودی» card with them, since it is no longer empty.
 *
 * **What it zeroes** is `stock_on_hand`, down to what orders are holding and
 * no further. `branch_inventory` refuses `stock_reserved > stock_on_hand`, and
 * those reserved units belong to orders already placed, which still have to be
 * sent. So a size with two pairs reserved lands at 2 on hand, 0 sellable —
 * which is what «موجودی ۰» means to a shopper. Every branch, because a
 * franchise's shelf of the same sandal is the same season.
 *
 * Every change is an `adjustment` in `inventory_movements`, the same row the
 * panel's count writes, so each shelf can say where its pairs went and the
 * spring restock can read how many there were. Locked row by row, like that
 * count, so a sale landing mid-deploy is not written over.
 *
 * **It never throws.** `migrate --force` runs under `set -eu` at boot, and a
 * tidy-up that fails must not keep the shop from starting.
 */
return new class extends Migration
{
    private const SECTION = 'sandal';

    /** `CategoriseByName::BY_FIRST_WORD`'s sandal openings, on 2026-09-24. */
    private const OPENINGS = ['صندل', 'اسلیپر'];

    private const NOTE = 'Sandals out of season — shelf zeroed, 2026-09-24.';

    public function up(): void
    {
        try {
            $restored = $this->sandals()
                ->where('status', 'archived')
                ->whereNotNull('published_at')
                ->pluck('id');

            if ($restored->isNotEmpty()) {
                DB::table('products')->whereIn('id', $restored)->update(['status' => 'active']);
            }

            DB::table('categories')->where('slug', self::SECTION)->update(['coming_soon' => false]);

            $variants = DB::table('variants')
                ->whereIn('product_id', $this->sandals()->pluck('id'))
                ->pluck('id');

            $shelves = DB::table('branch_inventory')->whereIn('variant_id', $variants)->pluck('id');

            foreach ($shelves as $id) {
                DB::transaction(function () use ($id) {
                    $shelf = DB::table('branch_inventory')->where('id', $id)->lockForUpdate()->first();
                    $removed = $shelf->stock_on_hand - $shelf->stock_reserved;

                    if ($removed <= 0) {
                        return;
                    }

                    DB::table('branch_inventory')->where('id', $id)
                        ->update(['stock_on_hand' => $shelf->stock_reserved, 'updated_at' => now()]);

                    DB::table('inventory_movements')->insert([
                        'branch_id' => $shelf->branch_id,
                        'variant_id' => $shelf->variant_id,
                        'type' => 'adjustment',
                        'quantity' => -$removed,
                        'note' => self::NOTE,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
            }
        } catch (Throwable $e) {
            Log::error('Could not put the sandals back at nought: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // Deliberately empty. The pairs taken off are in `inventory_movements`
        // under NOTE; putting them back is a restock, and that is a decision.
    }

    /** Every product in the sandal section or named as one, whatever its status. */
    private function sandals(): Builder
    {
        $section = DB::table('categories')->where('slug', self::SECTION)->value('id');

        $filed = $section === null ? collect() : DB::table('product_category')
            ->where('category_id', $section)
            ->pluck('product_id');

        $openings = array_map('fold_persian', self::OPENINGS);

        $named = DB::table('products')->get(['id', 'title'])
            ->filter(function (object $product) use ($openings): bool {
                $first = explode(' ', trim(preg_replace('/\s+/u', ' ', fold_persian($product->title)) ?? ''))[0];

                return in_array($first, $openings, true);
            })
            ->pluck('id');

        return DB::table('products')->whereIn('id', $filed->merge($named)->unique()->values()->all());
    }
};
