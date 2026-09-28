<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An instalment order's plan: what was paid up front, and when each instalment
 * falls due.
 *
 * «اگ قسطیه تاریخ سر رسید قسطاش چ زمانیه و پیش پرداخت چقد داده» — asked for
 * the printed invoice. **Nothing this shop is told by اسنپ‌پی carries it**:
 * their `verify` and `status` answer whether the purchase went through, not
 * how the shopper will repay it, and the one sentence they send about the plan
 * is the button's description, which they forbid anybody to compute. So the
 * plan is written by whoever reads it off the lender's panel (or agrees it at
 * the counter) on the order's own screen, and the invoice prints exactly that.
 *
 * One JSON column rather than a table, because nothing else in this
 * application asks a question of a single instalment: it is read whole, on
 * one screen and one sheet of paper. Null means «no plan written», which on a
 * cash order is the truth and on an instalment one is a gap the invoice says
 * out loud rather than hides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->json('instalment_plan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('instalment_plan');
        });
    }
};
