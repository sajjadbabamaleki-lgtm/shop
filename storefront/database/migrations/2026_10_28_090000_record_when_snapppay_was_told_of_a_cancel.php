<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When اسنپ‌پی accepted a `cancel` for this payment.
 *
 * The panel's ordinary «لغو سفارش» cancelled an instalment order on this side
 * only — stock back, order «لغو شد» — and never called their `cancel`, so the
 * shopper's instalments kept running against an order nobody was sending.
 * That is what their certification found. Afterwards the order read exactly
 * like one cancelled properly, because nothing recorded the difference.
 *
 * This is the difference: a cancelled order whose settled اسنپ‌پی payment has
 * no stamp here is one the lender has not been told about, and the order
 * screen offers to tell them. Rows cancelled before this existed carry no
 * stamp either; asking for them asks `status` first, so one that was already
 * cancelled there is recorded rather than cancelled twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('gateway_cancelled_at')->nullable()->after('gateway_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('gateway_cancelled_at');
        });
    }
};
