<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shopper's saved products — «لیست علاقمندی».
 *
 * Kept on the account rather than in the browser, which is what was asked for:
 * a list saved on one phone and gone from the next is not a list somebody will
 * trust with anything they mean to come back to.
 *
 * -----------------------------------------------------------------------------
 * Two decisions worth reading before changing this table.
 *
 * **It hangs off the product, not the variant.** Wanting a shoe is not wanting
 * a size — a shopper saves the pair and picks 41 at the basket. Keying it on
 * `variants` would also mean a saved row disappearing when a size is retired,
 * which reads as the shop losing something the shopper put there.
 *
 * **It is not branch-scoped, on purpose.** `branch_offers` and
 * `branch_inventory` are, because a price and a shelf belong to one shop. A
 * customer is one identity across every franchise — the routes file says so
 * where it registers the sign-in — and so is the list of things they like. What
 * is per-branch is whether the saved shoe can be bought *here*, and that is
 * answered when the list is drawn, by the same `pricedHere` scope every other
 * product query goes through. Adding a `branch_id` here would fork one person's
 * list into one list per shop they happen to open.
 *
 * The unique key is what makes the button idempotent: tapping «افزودن» twice —
 * or twice at once, from a double tap on a phone — is one row, enforced by the
 * database rather than by a check-then-insert that two requests can both pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();

            // Both cascade: a deleted account keeps nothing, and a product that
            // leaves the catalogue leaves every list it was saved to. Neither
            // is a row anybody could act on afterwards.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);

            // The list is always read for one customer, newest first.
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
