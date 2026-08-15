<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two things the shop advertises and had no way of hearing about.
 *
 * **Wholesale.** «خرید تکی و عمده» has been on the front page's trust row and
 * in the footer's strap line since the template was dressed, and there was no
 * page, no price, no minimum and no form anywhere in the application. The shop
 * was making an offer on every page with nothing behind it.
 *
 * **Franchise.** The branch network is built — `branches`, per-branch prices
 * and stock, a per-branch panel, `php artisan branch:open` — and there was no
 * way for somebody to *ask* for one. Vendors have `/vendors/apply`; a
 * prospective franchisee had the telephone number and nothing else.
 *
 * Both are the same shape: somebody says who they are and what they want, and
 * a person telephones them back. So they are one table with a `kind`, not two
 * tables that would drift apart — and one screen in the panel rather than two.
 *
 * **This is not an account and does not become one.** A vendor application
 * creates a `users` row because a vendor signs in; these create a row somebody
 * reads. A franchise is opened by `branch:open` after a contract exists, and
 * nothing here shortcuts that.
 *
 * Not branch-scoped, deliberately, and for the same reason the marketplace is
 * not: a wholesale order and a franchise application are the platform's
 * business, not one shop's. Binding them to whichever branch the visitor
 * happened to be browsing would file half of them where nobody looks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();

            $table->enum('kind', ['wholesale', 'franchise']);

            $table->string('name', 120);

            // Normalised the way every other phone number in this application
            // is — App\Models\Customer::normalisePhone — so «۰۹۱۲…», «+98912…»
            // and «0912…» are one number here too. Not unique: somebody may
            // ask about wholesale in spring and a franchise in autumn, and
            // both are worth having.
            $table->string('phone', 20);

            $table->string('city', 60)->nullable();

            // The company, the shop, or the bazaar stand. Nullable because a
            // form that demands a registered company from somebody who wants
            // twenty pairs is a form they close.
            $table->string('organisation', 160)->nullable();

            $table->text('message')->nullable();

            // What has happened to it. `new` until a person picks it up; the
            // panel moves it, and nothing else does.
            $table->enum('status', ['new', 'contacted', 'closed'])->default('new');

            // Who dealt with it, kept as a nullable reference: staff leave and
            // an enquiry from last year is still a record.
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            // The panel's own query: one kind, unhandled first, newest first.
            $table->index(['kind', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
