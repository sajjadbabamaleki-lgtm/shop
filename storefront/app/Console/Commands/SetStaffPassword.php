<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Sets a member of staff's password, correctly.
 *
 * This exists because of an hour lost on a live shop. There is no
 * change-password screen in the panel yet, so the only way to set one was a
 * console one-liner — and the obvious one-liner is wrong:
 *
 *     User::where('email', '…')->update(['password' => 'secret'])   // WRONG
 *
 * That goes through the query builder, which does not run the model's `hashed`
 * cast, so the plaintext lands in the column. `Hash::check` then refuses to
 * compare it and **throws**, and until `App\Support\Auth\Passwords` was written
 * that answered every sign-in with a 500. The account was locked out from
 * behind, by a white page that named nothing.
 *
 * So the safe way is a command rather than a sentence somebody has to type
 * correctly at three in the morning:
 *
 *   php artisan staff:password ali@vikyplus.ir            # generates one
 *   php artisan staff:password ali@vikyplus.ir --password=…
 *
 * It refuses an address it does not know rather than creating an account —
 * granting somebody a panel login is `staff:invite`'s job, and it asks for a
 * role. This one only changes a password.
 */
class SetStaffPassword extends Command
{
    protected $signature = 'staff:password
        {email : the address of the account}
        {--password= : the new password; generated and printed when omitted}';

    protected $description = "Set a staff account's password (hashed properly)";

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("There is no staff account for «{$this->argument('email')}».");
            $this->line('`php artisan staff:invite` creates one; this only changes a password.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));

        // On the model and saved, not `->update()` on a query: this is the
        // path the `hashed` cast is on, and the whole point of the command.
        $user->password = $password;
        $user->save();

        // A password set from here also invalidates any "remember me" cookie
        // still out there, which is what changing a password is usually for.
        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->info("«{$user->name}» can now sign in at /admin with:");
        $this->newLine();
        $this->line($password);
        $this->newLine();
        $this->comment('Shown once. Nothing stores it.');

        return self::SUCCESS;
    }
}
