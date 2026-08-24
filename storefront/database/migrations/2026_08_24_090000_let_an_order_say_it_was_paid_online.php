<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `payment_method` may now say «online».
 *
 * The column was written when the courier was the only way money arrived, and
 * it says so in the schema: a CHECK that the value is `cash_on_delivery` and
 * nothing else. Two things have grown past it since.
 *
 *  1. **The panel already offers the other one.** `Order::methodLabels()` has
 *     carried «پرداخت اینترنتی» since the payments work landed, the admin
 *     order screen prints those labels as the options of a `<select>`, and
 *     `Admin\OrderController::pay()` validates against the same list. So a
 *     staff member marking an order paid by card posts a value the request
 *     accepts and the database refuses — a 500 on a screen that looks correct.
 *     Nothing found it because no test posted the second option.
 *
 *  2. **A card gateway is being connected.** With one configured, every order
 *     is placed to be paid online, and every one of them would still have read
 *     «پرداخت در محل» in the panel — the one line on the screen that tells
 *     somebody packing shoes whether to ask the courier for money.
 *
 * `enum()` on PostgreSQL is a varchar with a CHECK constraint, not a database
 * type, so widening it is dropping the constraint and writing it again. The
 * name is the one Laravel's grammar generates: table, column, `_check`.
 *
 * **No row is rewritten.** Every order placed so far really was to be paid at
 * the door, whatever the shop switches to tomorrow.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_payment_method_check');
        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check
             CHECK (payment_method IN ('cash_on_delivery', 'online'))"
        );
    }

    public function down(): void
    {
        // Going back is only possible if nothing has been paid online yet.
        // Narrowing a CHECK under rows that violate it fails loudly, which is
        // the right outcome: the alternative is a migration that quietly
        // rewrites what an order says happened to it.
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_payment_method_check');
        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check
             CHECK (payment_method IN ('cash_on_delivery'))"
        );
    }
};
