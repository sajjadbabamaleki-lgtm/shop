<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail spec §29 asks for: actor, action, resource, what it was,
 * what it became, when.
 *
 * Append-only by discipline — nothing in the application updates or deletes a
 * row here, and the model refuses to. The events §29 names are the ones that
 * decide money or access: a commission rate changed, a branch price changed,
 * stock adjusted by hand, a settlement approved or paid, a franchise
 * permission changed, a vendor suspended, an order edited.
 *
 * The actor is a morph rather than a user_id because the actor is not always a
 * staff user: a customer cancels their own order, and a scheduled job expires
 * a stock reservation. An actor of null means the system did it, which is a
 * real answer and better than attributing it to nobody in particular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();

            $table->nullableMorphs('actor');
            $table->string('action');
            $table->nullableMorphs('auditable');

            // Only the attributes that changed, not whole rows: an audit trail
            // that copies every column is one nobody reads.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Where it happened — branch, vendor, IP, request id. Kept loose
            // because the interesting context differs per action, and pinning
            // it to columns now would mean a migration per new kind of event.
            $table->json('context')->nullable();

            // No updated_at: a row here is never updated.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
