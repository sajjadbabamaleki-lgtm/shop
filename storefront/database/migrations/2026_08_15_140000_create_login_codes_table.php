<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one-time codes a shopper signs in with.
 *
 * There is no password on a customer any more — «الف»: one way in, a phone
 * number and a code, for somebody who has an account and for somebody who does
 * not. The two used to be different forms answering the same question, and the
 * registration one had to ask for a receipt off an old order because a phone
 * number is not a secret and setting a password on a checkout-made customer
 * would have handed over their order history. A code sent to the number *is*
 * the proof, so that whole apparatus goes.
 *
 * **The code is a credential, so it is stored hashed** — the same reason the
 * password column was. A dump of this table must not let anybody sign in as
 * anybody, not even for the two minutes a code is alive.
 *
 * Not branch-scoped. A customer is one identity across every storefront (§21),
 * so a code sent from Shiraz signs the same person in at the main store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();

            // Normalised the way `customers.phone` is — Customer::normalisePhone
            // — or a code sent to «۰۹۱۲…» could never be found again by
            // somebody who typed «0912…» on the next screen.
            $table->string('phone', 20)->index();

            $table->string('code_hash');

            $table->timestamp('expires_at');

            // Wrong guesses. Five and the code is spent: a five-digit code is
            // 100,000 doors and a hundred guesses a minute would walk it in
            // under a day, which is a lot longer than two minutes but not long
            // enough to leave unbounded.
            $table->unsignedSmallInteger('attempts')->default(0);

            // Set the moment it works. A code is good once — without this the
            // same five digits keep signing people in until they expire, which
            // matters because they arrive by SMS and an SMS stays on a phone.
            $table->timestamp('consumed_at')->nullable();

            // Who asked. Not for the sign-in — for the rate limit, and for
            // afterwards, when somebody wants to know why one number was sent
            // forty codes.
            $table->string('ip', 45)->nullable();

            $table->timestamps();

            // The lookup the verify step makes: this phone's live codes,
            // newest first.
            $table->index(['phone', 'consumed_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
