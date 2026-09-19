<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Ten more pairs on every size the shop sells.
 *
 * «به کفش های موجود در فروشگاه برای هر کدام ده جفت اضافه کن» — and the unit is
 * the size, confirmed with the shop before this was written, because stock
 * here is stored per size and the size is what a customer can buy. A shoe with
 * five sizes gets fifty pairs; ten pairs of a shoe with no size named is not a
 * number this application can hold anywhere.
 *
 * **A migration, because nobody runs a command on the live site.** The deploy
 * runs `php artisan migrate --force` and nothing else, so this is the only
 * thing that reaches production — the same shape as
 * `2026_09_07_090000_take_the_demo_data_off_the_live_panel`. Production has
 * held one of each size since it opened, so this takes every shelf from 1 to
 * 11.
 *
 * **It calls `stock:add` rather than writing its own SQL**, for the reason
 * that migration gives at length: a shelf is not a column to be updated, it is
 * a movement, and a movement without its `inventory_movements` row is a shelf
 * that cannot explain itself six weeks later. The command reads each row under
 * `lockForUpdate()` and **adds to what it finds** rather than setting a number
 * worked out beforehand, so a pair sold between the deploy starting and this
 * line running is not quietly put back on the shelf.
 *
 * **Central only.** Stock is a branch's own property and a franchise did not
 * receive this delivery. If one should be restocked too, that is
 * `php artisan stock:add 10 --branch=<slug>` and a decision somebody makes
 * about that shop.
 *
 * **It never throws**, because `liara_pre_start.sh` runs under `set -eu` and a
 * migration that throws stops the shop from starting. The failure it is
 * guarding against is not hypothetical: `branch_inventory` carries three CHECK
 * constraints and `sellable_stock` is a generated column. Adding a positive
 * number can violate none of them — which is why this is safe to run at boot
 * at all — but a shop that will not start is a worse outcome than a shelf that
 * did not grow, so the catch is here and says so in the log where
 * `liara_pre_start.sh`'s own lines are.
 *
 * `down()` is deliberately empty. Taking stock back off a trading shop would
 * throw the moment any of it were reserved for an order, and a rollback that
 * quietly emptied shelves would be worse than the thing it undid.
 */
return new class extends Migration
{
    /** What the shop asked for, written out: a migration is frozen history. */
    private const PAIRS = 10;

    public function up(): void
    {
        // Migrations run before any seeder, so on a fresh database — every
        // test here, and a new install — there is no branch and no catalogue
        // yet, and there is nothing to add to. That is not a failure.
        if (! Branch::query()->where('type', Branch::CENTRAL)->exists()) {
            return;
        }

        try {
            Artisan::call('stock:add', [
                'units' => self::PAIRS,
                // Never gated: `ConfirmableTrait` would otherwise stop the
                // deploy at a prompt nobody is standing at.
                '--force' => true,
            ]);

            $this->say(trim(Artisan::output()));
        } catch (Throwable $e) {
            $this->say('could not add the new stock: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // Deliberately empty. See the note above.
    }

    private function say(string $message): void
    {
        Log::warning('put_ten_more_pairs_on_every_size: '.$message);

        // The same prefix `liara_pre_start.sh` uses, so it reads as one log.
        echo 'storefront: '.$message.PHP_EOL;
    }
};
