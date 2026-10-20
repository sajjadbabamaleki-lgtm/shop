<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's birth date — asked for when an account is made.
 *
 * «زمانی که مشتری رفت حساب کاربری تشکیل بده اسم شماره تلفن و تاریخ تولدشو
 * بپرسیم». Nullable: every customer checkout has already made has none, and
 * inventing one would be a date the shop then sends a greeting on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('birth_date');
        });
    }
};
