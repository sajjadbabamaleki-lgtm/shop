<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shoppers, kept apart from the `users` table that holds staff.
 *
 * Spec §21: one customer account works across the main store, every franchise
 * storefront and the marketplace, while the commercial context stays
 * separate. That is an argument for one customers table with no tenant column
 * on it at all — the identity is global, and what is per-branch is the cart,
 * the order and the price, which reference this row rather than duplicating
 * it.
 *
 * Separate from `users` because the two are not the same kind of thing. Staff
 * carry roles and permissions over other people's data; a customer carries an
 * order history and nothing else. Sharing one table means every authorization
 * check has to remember which kind it is holding, and one forgotten check is a
 * customer reading somebody else's order.
 *
 * The phone number is the handle. Iranian shops are entered by phone, not by
 * email, so `phone` is unique and required and `email` is neither — the
 * reverse of Laravel's default, on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();

            // Stored normalised — see Customer::normalisePhone(). Uniqueness
            // on a phone number is only meaningful if 0912…, +98912… and
            // 0098912… cannot all exist side by side.
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable()->unique();

            // Nullable: a customer who has only ever signed in with a one-time
            // code has no password, and inventing one for them would be a lie
            // about how the account is entered.
            $table->string('password')->nullable();

            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
