<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dates §2 asks every order to carry, and the settings §10 asks the shop
 * to own.
 *
 * The point of the whole addendum is in its §19: «No shipping/deadline date is
 * hardcoded in UI» and «Estimated and actual shipment dates are distinct». So
 * an estimate and an event are different columns here, never the same one read
 * two ways — the moment they share a column, «when we said it would go» and
 * «when it went» become one number and the shop can no longer tell a kept
 * promise from a broken one.
 *
 * §3 also asks that the calculated dates be persisted «while retaining the
 * inputs/rules used to calculate them». `promise_basis` is that: a snapshot of
 * the preparation SLA, the transit range and the working calendar as they were
 * on the day the promise was made. Without it, changing the holiday calendar
 * next month silently rewrites what every past order was promised, which §10
 * forbids in as many words — «do not silently rewrite historical promises».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // The event the promise is measured from. Distinct from placed_at:
            // an order can sit unconfirmed, and the clock should not run while
            // it does.
            $table->timestamp('confirmed_at')->nullable()->after('placed_at');

            // Calculated. The deadline the shop gave itself.
            $table->date('estimated_ship_by')->nullable()->after('confirmed_at');

            // Actual. When the parcel was handed over.
            $table->timestamp('actual_shipped_at')->nullable()->after('estimated_ship_by');

            // Calculated, and recalculated from the actual handover when that
            // happens — §2's «recalculated from actual_shipped_at where the
            // carrier SLA permits».
            $table->date('estimated_delivery_from')->nullable()->after('actual_shipped_at');
            $table->date('estimated_delivery_to')->nullable()->after('estimated_delivery_from');
            $table->timestamp('actual_delivered_at')->nullable()->after('estimated_delivery_to');

            // §5: «'Delayed' should normally be a fulfillment condition/flag
            // while the order remains in Preparing or Ready to Ship.» A flag,
            // not a status, so the lifecycle stays unambiguous.
            $table->boolean('is_delayed')->default(false)->after('actual_delivered_at');
            $table->string('delay_reason')->nullable()->after('is_delayed');

            // Who is carrying it. §7's Mark-as-Shipped asks for both, and the
            // tracking number already exists from the previous migration.
            $table->string('carrier')->nullable()->after('tracking_number');
            $table->foreignId('shipping_method_id')->nullable()->after('carrier')
                ->constrained('shipping_methods')->nullOnDelete();

            // The rules as they stood when the promise was made.
            $table->json('promise_basis')->nullable()->after('shipping_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn([
                'confirmed_at', 'estimated_ship_by', 'actual_shipped_at',
                'estimated_delivery_from', 'estimated_delivery_to', 'actual_delivered_at',
                'is_delayed', 'delay_reason', 'carrier', 'promise_basis',
            ]);
        });
    }
};
