<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The financial ledger (architecture §9).
 *
 *   "Prefer a transaction ledger over a single mutable wallet balance. The
 *    payable vendor balance should be derived from auditable ledger entries."
 *
 * So there is no balance column anywhere in this schema. A vendor's payable is
 * `SUM(amount)` over its unsettled entries, and every Rial of it is traceable
 * to the order item that produced it. Amounts are signed: a sale credits the
 * vendor, the commission debits it, a refund debits it, a settlement debits it
 * to zero. The mirrored platform rows make the platform's commission revenue
 * auditable on the same terms.
 *
 * Entries are append-only. Nothing corrects an entry by editing it; a
 * correction is another entry, which is the property that makes a dispute
 * three months later answerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            // 'platform' carries a null account_id — there is one platform.
            $table->enum('account_type', ['platform', 'vendor', 'franchise']);
            $table->unsignedBigInteger('account_id')->nullable();

            $table->enum('entry_type', [
                'sale',                 // + gross line credited to the seller
                'commission',           // - platform's cut off the seller
                'commission_revenue',   // + the same cut, on the platform
                'refund',               // - returned to the customer
                'commission_reversal',  // + commission given back on a refund
                'adjustment',           // signed, manual, always with a memo
                'settlement',           // - paid out, closing the balance
            ]);

            // Signed integer Rial. The sign is the whole meaning of the row.
            $table->bigInteger('amount');

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();

            $table->string('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // No updated_at: entries are never updated.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_type', 'account_id', 'settlement_id']);
            $table->index(['order_item_id']);
            $table->index(['entry_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
