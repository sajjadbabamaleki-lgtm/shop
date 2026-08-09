<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions to tables that existed before the marketplace did.
 *
 * 1. Products gain an author and a review state. The catalogue stays canonical
 *    — a vendor's offer still attaches to a platform variant — but a vendor
 *    that stocks something we have never carried has to be able to propose it,
 *    or the marketplace only ever sells what we already sell. A proposal is a
 *    normal product row with `approval_status = 'pending_review'`, invisible
 *    to customers until someone approves it. Rows that existed before this
 *    migration are ours and are approved by definition.
 *
 * 2. Inventory movements gain the seller that owns the stock they moved. The
 *    existing table already explains every change to a central variant's
 *    stock; vendor and branch stock must be explainable the same way, and one
 *    ledger with a nullable owner beats three tables with identical columns.
 *    Null owner still means the central warehouse, so no existing row changes
 *    meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('created_by_vendor_id')->nullable()->after('brand_id')
                ->constrained('vendors')->nullOnDelete();
            $table->enum('approval_status', ['draft', 'pending_review', 'approved', 'rejected'])
                ->default('approved')->after('status');
            $table->text('rejection_reason')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('rejection_reason');

            $table->index(['approval_status', 'created_by_vendor_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('vendor_offer_id')->nullable()->after('variant_id')
                ->constrained('vendor_offers')->cascadeOnDelete();
            $table->foreignId('franchise_id')->nullable()->after('vendor_offer_id')
                ->constrained('franchises')->cascadeOnDelete();

            $table->index(['vendor_offer_id', 'created_at']);
            $table->index(['franchise_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['vendor_offer_id']);
            $table->dropForeign(['franchise_id']);
            $table->dropColumn(['vendor_offer_id', 'franchise_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['created_by_vendor_id']);
            $table->dropColumn(['created_by_vendor_id', 'approval_status', 'rejection_reason', 'approved_at']);
        });
    }
};
