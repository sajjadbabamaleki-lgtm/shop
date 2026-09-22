<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When اسنپ‌پی's `update` service was last called about this payment.
 *
 * Their merchant instructions put a floor under how often it may be called —
 * «حتما هم بین هر آپدیت حداقل باید ۳۰ ثانیه صبر کنید» — and nothing in this
 * application could have honoured that, because nothing knew when the last
 * one went. Two مرجوعی rows typed a few seconds apart would have sent two
 * updates a few seconds apart, and the failure is theirs to define: the
 * second call races the first and the basket they hold is whichever landed
 * last, which is not necessarily the smaller one.
 *
 * **It is stamped on the attempt and not on the success**, because the rule is
 * about the spacing of the calls rather than of the outcomes — a call that
 * timed out was still made, and retrying it a second later is the thing being
 * forbidden.
 *
 * It is `payments` and not `orders` because the token the call is made with
 * lives here, and an order can hold more than one payment row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('gateway_updated_at')->nullable()->after('gateway_token');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('gateway_updated_at');
        });
    }
};
