<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §10's «Order Processing & Shipping Settings», as data rather than as code.
 *
 * The requirement is one sentence — «Changing configuration must not require
 * code deployment» — and it is the whole reason these are rows and columns
 * instead of an array in `config/`. A config file changes on a deploy; this
 * changes when somebody in the shop decides the post now takes four days.
 *
 * **Both are branch-scoped**, because everything operational in this
 * application is: Shiraz keeps its own shelves, its own prices and its own
 * opening hours, and it will keep its own holidays and its own preparation
 * time. A single platform-wide calendar would be a promise made in Tehran and
 * broken in Shiraz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('carrier')->nullable();

            // §2 asks for the transit estimate «preferably as a
            // minimum/maximum working-day range», which is what makes the
            // customer-facing «۳۱ مرداد تا ۳ شهریور» a range rather than a
            // single date nobody can keep.
            $table->unsignedSmallInteger('transit_min_days')->default(2);
            $table->unsignedSmallInteger('transit_max_days')->default(4);

            $table->unsignedBigInteger('price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::table('branches', function (Blueprint $table): void {
            // The preparation SLA, the working week, the holidays, the cut-off
            // and the delay policy — one document per branch. JSON rather than
            // a column each because the holiday list is a list and because §10
            // expects this set to grow; a new setting must not be a migration
            // every time somebody adds a rule.
            $table->json('fulfilment')->nullable()->after('business_hours');
        });

        // Every branch that already exists gets the defaults, so no order can
        // be promised against an empty configuration. A shop that has not
        // chosen its working week has one anyway — Saturday to Wednesday, the
        // ordinary Iranian week, with Thursday short and Friday closed. The
        // numbers are ISO-8601 weekdays: Saturday is 6, Friday is 5.
        $defaults = json_encode([
            'preparation_days' => 2,
            'working_days' => [6, 7, 1, 2, 3],
            'holidays' => [],
            'cutoff' => '14:00',
            'max_delay_days' => 7,
            'delays_enabled' => true,
        ], JSON_UNESCAPED_UNICODE);

        DB::table('branches')->whereNull('fulfilment')->update(['fulfilment' => $defaults]);

        // And one shipping method each, so the first order has something to be
        // promised against. Named for what it is; the shop renames it.
        foreach (DB::table('branches')->pluck('id') as $branchId) {
            DB::table('shipping_methods')->insert([
                'branch_id' => $branchId,
                'name' => 'پست پیشتاز',
                'carrier' => 'شرکت ملی پست',
                'transit_min_days' => 2,
                'transit_max_days' => 4,
                'price' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('fulfilment');
        });

        Schema::dropIfExists('shipping_methods');
    }
};
