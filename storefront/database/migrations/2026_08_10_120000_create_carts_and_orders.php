<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baskets and orders — the point where the site starts handling money.
 *
 * Everything here belongs to a branch. A cart is a cart *at a shop*: it holds
 * that shop's stock and is priced from that shop's offers, so carrying one
 * from Shiraz to the main store would be carrying a price that no longer
 * applies. §18's isolation is the same rule it always was, now over the rows
 * that matter most.
 *
 * Two decisions worth reading before changing anything here.
 *
 * **A cart line stores a quantity and nothing else.** No price. The price is
 * always the branch's live offer, so a basket cannot quietly hold yesterday's
 * number, and there is no cached total anywhere to fall out of step. §6.1 and
 * §10 both say the server computes money and never accepts it; this is that,
 * expressed as an absence.
 *
 * **An order line stores everything.** Title, SKU, size, unit price, what it
 * was struck through from — copied at the moment of purchase, because an
 * order is a record of what happened and must not change when somebody edits
 * a product name or a price two months later. The variant reference is kept
 * for reporting and is allowed to become null; the words on the receipt are
 * not.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * One basket per visitor per branch.
         *
         * A guest is identified by a token this application generated and put
         * in their session; a signed-in customer by their id. Both columns
         * exist because a cart starts as one and becomes the other when
         * somebody signs in mid-shop.
         */
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Not the session id. A session id is a credential; copying it
            // into a table makes every place a cart is read a place a session
            // can be stolen from. This is a value of our own that means
            // nothing anywhere else.
            $table->string('token', 64);

            $table->timestamps();

            $table->unique(['branch_id', 'token']);
            $table->index(['branch_id', 'customer_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            // One line per size. Adding the same shoe twice raises the
            // quantity rather than making a second line nobody expects.
            $table->unique(['cart_id', 'variant_id']);
        });

        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity > 0)');

        /*
         * An order.
         *
         * `number` is what a customer quotes on the phone, so it is a value of
         * its own rather than the primary key: an id in a URL tells anybody
         * who looks how many orders the shop has taken, and guessing the next
         * one is trivial.
         */
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number', 24)->unique();

            /*
             * placed    — taken, stock reserved, not yet paid for
             * paid      — money received; the reservation has become a sale
             * shipped   — handed to the courier
             * delivered — done
             * cancelled — released back to the shelf
             */
            $table->enum('status', ['placed', 'paid', 'shipped', 'delivered', 'cancelled'])->default('placed');

            // Integer Rial, every one of them. No column here is ever a float.
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('grand_total');

            // Only one method today, and it is named rather than assumed:
            // paying the courier at the door. A gateway is a separate piece of
            // work and pretending otherwise in an enum would be a lie the
            // checkout page would then have to tell.
            $table->enum('payment_method', ['cash_on_delivery'])->default('cash_on_delivery');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');

            // Where it goes. Flat on the order rather than a foreign key to an
            // address book: an address book entry can be edited, and an
            // order's delivery address is a fact about a day in the past.
            $table->string('contact_name');
            $table->string('contact_phone', 20);
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->text('address');
            $table->string('postal_code', 20)->nullable();
            $table->text('note')->nullable();

            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'placed_at']);
            $table->index(['customer_id', 'placed_at']);
        });

        // The totals have to add up. This is the one arithmetic mistake that
        // would be invisible in every report and wrong in the bank.
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_totals_add_up CHECK (grand_total = subtotal - discount_total + shipping_total)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_discount_within_subtotal CHECK (discount_total <= subtotal)');

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Kept for reporting, allowed to disappear. The receipt below does
            // not depend on it.
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();

            // The receipt, written at the moment of purchase.
            $table->string('product_title');
            $table->string('sku');
            $table->string('size_value')->nullable();
            $table->string('display_color')->nullable();

            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');

            $table->timestamps();

            $table->index('order_id');
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_total_matches CHECK (line_total = unit_price * quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
