<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Checking a password against a hash that might not be one.
 *
 * `Hash::check()` does not return false when the stored value is not a bcrypt
 * hash — it **throws**, with «This password does not use the Bcrypt
 * algorithm.» That guard is right: a value in the wrong format usually means
 * somebody wrote a password into the column without hashing it, and quietly
 * comparing strings there would turn a plaintext column into a working
 * sign-in.
 *
 * What is not right is what the throw does to the page. It leaves the request
 * as an unhandled exception, so the shop answers a sign-in attempt with a 500
 * — and the person at the form is told the site is broken rather than that
 * their password is wrong. It happened here, on the live shop: a password was
 * set with `User::where(...)->update(['password' => '…'])`, which writes the
 * column directly and skips the model's `hashed` cast, and from that moment
 * **every** attempt to sign in to the panel was a 500. The account was locked
 * out behind a white error page that named nothing, and finding out why took
 * an hour of reading stack traces off photographs of a screen.
 *
 * So: a hash that cannot be checked is a failed sign-in, which is the truth —
 * nobody can prove they own that account — and it is logged at warning so
 * whoever runs the shop can find out *why* instead of guessing from a
 * rejected password.
 *
 * `php artisan staff:password` exists so that setting one by hand no longer
 * requires knowing any of this.
 */
class Passwords
{
    /**
     * The same protection around a guard's own attempt.
     *
     * `Auth::attempt()` calls `Hash::check()` inside the user provider, where
     * nothing here can reach it, so the throw is caught around the whole call
     * instead. A refused attempt and an uncheckable hash look the same to the
     * person at the form, which is what they should look like.
     *
     * @param  callable(): bool  $attempt
     */
    public static function attempted(callable $attempt, string $who): bool
    {
        try {
            return (bool) $attempt();
        } catch (RuntimeException $e) {
            self::warn($who, $e);

            return false;
        }
    }

    public static function check(string $plain, ?string $hashed, string $who): bool
    {
        if ($hashed === null || $hashed === '') {
            return false;
        }

        try {
            return Hash::check($plain, $hashed);
        } catch (RuntimeException $e) {
            self::warn($who, $e);

            return false;
        }
    }

    private static function warn(string $who, RuntimeException $e): void
    {
        Log::warning('A stored password cannot be checked, so the sign-in was refused.', [
            'who' => $who,
            'why' => $e->getMessage(),
            'fix' => 'Set it with `php artisan staff:password`, which hashes. '
                .'A direct `update([...])` on the query builder does not.',
        ]);
    }
}
