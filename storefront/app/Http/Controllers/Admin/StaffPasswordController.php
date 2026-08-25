<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\Passwords;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Setting a member of staff's password, from the panel.
 *
 * Until this screen there was no change-password anywhere in the shop for
 * staff: the only way was `php artisan staff:password` on the server's own
 * console. For a shop with more than one person in it that means passwords are
 * never changed at all, which is what happened — an owner's password went out
 * in a photograph of a terminal and there was nothing but the console to
 * rotate it with.
 *
 * **Only the people who own the business.** «فقط مدیر شرکت باید بتونه رمز عوض
 * کنه حتی رمز ادمینهارو» — so this is `platform.password.manage`, which the
 * seeder grants to nobody except by way of `Role::FULL_ACCESS`. An `admin` does
 * not have it, and neither does a branch manager: `branch.staff.manage` lets
 * somebody say who works at their shop, and a manager who could also set an
 * administrator's password would simply be an administrator.
 */
class StaffPasswordController extends Controller
{
    /**
     * Six attempts an hour, per person, not per target.
     *
     * The thing being throttled is somebody sitting at a signed-in owner's
     * desk and walking down the staff list. It is deliberately generous for
     * ordinary use — nobody legitimately changes seven passwords in an hour —
     * and the limit is on the actor so switching targets does not reset it.
     */
    private const TRIES = 6;

    public function index(Request $request): View
    {
        return view('admin.passwords', [
            'people' => User::with('roles')->orderBy('name')->get(),
            'me' => $request->user(),
        ]);
    }

    public function update(Request $request, User $person): RedirectResponse
    {
        $input = $request->validate([
            // **The actor's own password, not the target's.** Nobody can know
            // somebody else's, so the only confirmation available is proof
            // that the person at the keyboard is still the person who signed
            // in. Without it an unlocked laptop is every password in the shop.
            'confirm' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'confirm.required' => 'برای تأیید، رمز خودت را وارد کن.',
            'password.min' => 'رمز تازه دست‌کم ۸ نویسه باشد.',
            'password.confirmed' => 'رمز تازه و تکرارش یکی نیستند.',
        ]);

        $me = $request->user();
        $key = 'set-password:'.$me->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, self::TRIES)) {
            throw ValidationException::withMessages([
                'confirm' => 'تعداد تغییر رمز در این ساعت پر شده. '
                    .ceil(RateLimiter::availableIn($key) / 60).' دقیقه دیگر دوباره تلاش کن.',
            ]);
        }

        // Through `Passwords::verified` for the same reason the sign-in goes
        // through it: a stored value that is not a bcrypt hash makes
        // `Hash::check` throw, and unhandled that answers this form with a 500
        // rather than «رمزت درست نیست». See that class — it happened here.
        if (! Passwords::check($input['confirm'], $me->password, $me->email)) {
            RateLimiter::hit($key, 3600);

            throw ValidationException::withMessages(['confirm' => 'رمز خودت درست نیست.']);
        }

        RateLimiter::hit($key, 3600);

        // The cast on the model hashes this. Never the query builder — see
        // `SetStaffPassword`, which exists because that mistake locked a live
        // account out from behind with a white page that named nothing.
        $person->password = $input['password'];

        // **And their remember-me cookies die with it.** A password change
        // that leaves an old browser signed in has not taken the account back,
        // which is the entire point when the reason for the change is that the
        // password got out.
        $person->setRememberToken(Str::random(60));
        $person->save();

        return redirect()
            ->route('admin.passwords')
            ->with('status', "رمز «{$person->name}» عوض شد. جلسه‌های «مرا به خاطر بسپار» او هم بسته شد.");
    }
}
