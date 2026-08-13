<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discount codes — the second kind of promotion this shop has.
 *
 * The first is the stepped sale, which is a *price* that falls on a schedule
 * and lives on the offer. This is different in kind: a code is something a
 * customer types, it applies to a basket rather than to a shoe, and it has to
 * be counted, limited and expired. Trying to express one as the other is how a
 * promotions system becomes impossible to reason about, so they stay two
 * things.
 *
 * Two decisions worth reading before extending this.
 *
 * **A code discounts only the branch's own lines.** A marketplace vendor's
 * price is theirs, and a discount on it would be the platform spending
 * somebody else's money — or the vendor being paid less than they listed for,
 * depending on who absorbed it. Neither is a decision this shop has made, so
 * the code simply does not apply there and the basket says so.
 *
 * **Every use is a row.** `discount_redemptions` is what makes "50 uses" and
 * "once per customer" true rather than approximately true, and it is what lets
 * somebody afterwards ask what a campaign actually cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();

            // Stored upper-case and compared upper-case. A customer typing
            // «noruz» and a poster reading NORUZ are the same code.
            $table->string('code', 32)->unique();
            $table->string('description')->nullable();

            $table->enum('type', ['percent', 'fixed']);

            // Percent in basis points (1500 is 15%), fixed in integer Rial —
            // the same two units commission_rules carries, for the same
            // reason: no fractions before the money is computed.
            $table->unsignedInteger('value');

            // Null means the whole platform. A branch's own code belongs to
            // that branch and works nowhere else, which is what lets a
            // franchise run its own campaign without asking.
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('min_subtotal')->default(0);

            // A ceiling for percentage codes: 20% off, up to 500,000 Toman.
            // Without one, a percentage code on a large basket is an open
            // cheque.
            $table->unsignedBigInteger('max_discount')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'branch_id']);
        });

        DB::statement("ALTER TABLE discount_codes ADD CONSTRAINT discount_codes_percent_within_range CHECK (type <> 'percent' OR value <= 10000)");
        DB::statement('ALTER TABLE discount_codes ADD CONSTRAINT discount_codes_window_ordered CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)');
        DB::statement('ALTER TABLE discount_codes ADD CONSTRAINT discount_codes_value_positive CHECK (value > 0)');

        /*
         * One row per use.
         *
         * The order is unique here: an order carries one discount, and two
         * codes on one basket is a rule nobody has asked for and every
         * marketplace regrets adding.
         */
        Schema::create('discount_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // What it actually took off, which is not the code's value: a
            // percentage depends on the basket and a fixed amount is capped by
            // it. This is the number a campaign is judged on.
            $table->unsignedBigInteger('amount');

            $table->timestamp('created_at')->useCurrent();

            $table->unique('order_id');
            $table->index(['discount_code_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_redemptions');
        Schema::dropIfExists('discount_codes');
    }
};
