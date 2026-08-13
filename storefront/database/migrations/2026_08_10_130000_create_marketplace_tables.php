<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The second business line: other people selling on this platform.
 *
 * §1 is explicit that a Vendor and a Franchise are different things and must
 * not be mixed, and the schema says so by what is absent. **Nothing here is
 * branch-scoped.** A franchise is a place the brand's own goods are sold from;
 * a vendor is a company selling its own goods across the whole platform, and
 * giving one a branch_id would be modelling it as a shop of ours.
 *
 * Where the two meet is the order. An order belongs to the storefront that
 * took it — a branch, as before — and each *line* may belong to a vendor, who
 * fulfils it from their own stock and is owed for it (§9). That is the whole
 * join between the halves: one nullable column on order_items.
 *
 * Money is the other half, and it is a ledger rather than a balance column.
 * §7 asks for an append-only record of every sale, commission, refund and
 * settlement, and a vendor's balance is the sum of it. A stored balance is a
 * number that can disagree with its own history, and when it does there is no
 * way to find out which one is lying.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * A company selling here.
         *
         * `status` is the whole of §4's approval: a vendor is pending until a
         * person says otherwise, and only an approved one's offers are ever
         * shown. Suspension is a separate state from rejection because they
         * mean different things — one was never let in, the other was and is
         * not now.
         */
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('national_id', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            $table->enum('status', ['pending', 'approved', 'suspended', 'rejected'])->default('pending');
            $table->text('status_note')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Where a settlement is paid to. Nullable because a vendor can be
            // approved before their bank details arrive; a settlement cannot
            // be paid without them, and that is checked where it is paid.
            $table->string('iban', 34)->nullable();
            $table->string('account_holder')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        Schema::create('vendor_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained();
            $table->timestamps();

            $table->unique(['vendor_id', 'user_id']);
            $table->index('user_id');
        });

        /*
         * A vendor's price and stock for one variant of the canonical product.
         *
         * §13 and §27: one product record, many offers against it. A vendor
         * never creates a second «Nike V2K Run» — they attach a price to ours,
         * which is what makes a product page able to show several sellers of
         * the same shoe rather than several shoes.
         *
         * The stock columns are the same shape as branch_inventory's, with the
         * same invariants, because a vendor's shelf obeys the same physics as
         * a branch's.
         */
        Schema::create('vendor_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();

            $table->string('vendor_sku')->nullable();

            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('compare_at_price')->nullable();

            $table->integer('stock_on_hand')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->integer('sellable_stock')->storedAs('stock_on_hand - stock_reserved');

            // draft   — the vendor is still writing it
            // pending — submitted, waiting on §4's approval
            // active  — approved and on sale
            // rejected— refused, with a reason the vendor can read
            $table->enum('status', ['draft', 'pending', 'active', 'rejected'])->default('draft');
            $table->text('status_note')->nullable();

            $table->timestamps();

            $table->unique(['vendor_id', 'variant_id']);
            $table->index(['variant_id', 'status']);
        });

        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_on_hand_not_negative CHECK (stock_on_hand >= 0)');
        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_reserved_not_negative CHECK (stock_reserved >= 0)');
        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_reserved_within_on_hand CHECK (stock_reserved <= stock_on_hand)');
        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_compare_at_above_price CHECK (compare_at_price IS NULL OR compare_at_price >= price)');

        /*
         * What the platform charges a vendor, and on what.
         *
         * Four scopes, most specific first: one product, then a category, then
         * one vendor, then the platform's default. `priority` breaks ties
         * inside a scope, so two category rules that both match are still
         * decided by something written down rather than by insertion order.
         *
         * A rule is either a percentage or a fixed amount per unit — §7 asks
         * for both, and a marketplace that only has percentages cannot charge
         * a listing fee.
         */
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['global', 'vendor', 'category', 'product']);

            // Which vendor / category / product. Null for the global rule,
            // which is the reason this is not a foreign key.
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->enum('type', ['percent', 'fixed']);

            // Percent is stored in basis points — 1250 is 12.5% — so a rate is
            // an integer like every other number here and no rounding happens
            // before the money is computed.
            $table->unsignedInteger('value');

            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'scope_id', 'is_active']);
        });

        DB::statement("ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_percent_within_range CHECK (type <> 'percent' OR value <= 10000)");
        DB::statement("ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_global_has_no_target CHECK (scope <> 'global' OR scope_id IS NULL)");
        DB::statement("ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_scoped_has_target CHECK (scope = 'global' OR scope_id IS NOT NULL)");

        /*
         * Everything the platform owes a vendor, or the vendor owes it.
         *
         * Append-only — the model refuses updates and deletes, the way audits
         * do. A correction is another row, not an edit, so the history of an
         * account is the account.
         *
         * `amount` is signed integer Rial from the vendor's point of view: a
         * sale is positive, the commission on it is negative, a settlement
         * payment is negative because it discharges what was owed. The balance
         * is SUM(amount) and is never stored.
         */
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            $table->enum('type', [
                'sale', 'commission', 'refund', 'adjustment',
                'settlement', 'charge', 'bonus', 'reversal',
            ]);

            $table->bigInteger('amount');
            $table->string('currency', 3)->default('IRR');

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        /*
         * A payment out, and which entries it discharges.
         *
         * The state machine is deliberately three steps and not one: the
         * person who approves a payment and the person who makes it are not
         * always the same, and a marketplace where they are is one where a
         * single mistake moves money.
         */
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            $table->enum('status', ['requested', 'approved', 'paid', 'rejected'])->default('requested');
            $table->unsignedBigInteger('amount');

            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            // The bank's reference for the transfer, so a vendor asking «کی
            // پرداخت شد» gets an answer that can be checked.
            $table->string('payment_reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_entry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // A ledger entry can be settled once. This is the index that stops
            // two overlapping settlement requests from paying for the same
            // sale twice.
            $table->unique('ledger_entry_id');
        });

        /*
         * A basket line has to say *who from*.
         *
         * The same shoe in the same size can be on sale from the branch and
         * from two vendors at three prices, so «add to basket» is a choice of
         * seller as well as of size. Null still means the branch, which is
         * every line placed so far.
         *
         * NULLS NOT DISTINCT because the default would let (cart, variant,
         * NULL) exist twice — Postgres treats two NULLs as different values,
         * and the one duplicate this index has to prevent is exactly the
         * branch's own line.
         */
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('variant_id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['cart_id', 'variant_id']);
        });

        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_one_line_per_seller UNIQUE NULLS NOT DISTINCT (cart_id, variant_id, vendor_id)');

        /*
         * The join between the two halves of the platform.
         *
         * Null means the branch sold it, which is every order so far. A value
         * means a vendor did, fulfils it, and is owed for it.
         */
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('variant_id')->constrained()->nullOnDelete();
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropColumn('vendor_id');
        });

        DB::statement('ALTER TABLE cart_items DROP CONSTRAINT IF EXISTS cart_items_one_line_per_seller');

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
            $table->unique(['cart_id', 'variant_id']);
        });

        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('vendor_offers');
        Schema::dropIfExists('vendor_users');
        Schema::dropIfExists('vendors');
    }
};
