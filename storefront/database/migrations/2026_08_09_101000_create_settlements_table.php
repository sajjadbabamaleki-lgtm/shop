<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor payout runs (architecture §2, §9).
 *
 * A settlement is a period, a set of order items and one net figure. It does
 * not hold the vendor's balance — the ledger does — and the numbers on it are
 * a snapshot of what the ledger said when the run closed, kept so a payout can
 * be reconciled long after the ledger has moved on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();

            $table->timestamp('period_start');
            $table->timestamp('period_end');

            $table->unsignedBigInteger('gross_sales')->default(0);
            $table->unsignedBigInteger('commission_total')->default(0);
            $table->unsignedBigInteger('refund_total')->default(0);
            // Signed: a period where refunds exceed sales owes us money.
            $table->bigInteger('adjustment_total')->default(0);
            $table->bigInteger('net_payable')->default(0);

            $table->enum('status', ['draft', 'approved', 'paid', 'failed'])->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
