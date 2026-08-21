<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Auth\Passwords;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Signing staff in and out.
 *
 * The `web` guard, which is staff only — customers have a guard of their own
 * so that a shopper's session can never satisfy an authorization check meant
 * for somebody who runs a shop (§21, §24).
 *
 * One message for a wrong email and for a wrong password, because two would
 * tell somebody which addresses have accounts.
 */
class SessionController extends Controller
{
    public function show(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Through `Passwords::attempted` rather than bare: a stored value that
        // is not a bcrypt hash makes `Hash::check` throw, and unhandled that
        // answers a sign-in with a 500. See that class — it happened here.
        $signedIn = Passwords::attempted(
            fn (): bool => Auth::attempt($credentials, $request->boolean('remember')),
            $credentials['email'],
        );

        if (! $signedIn) {
            throw ValidationException::withMessages([
                'email' => 'ایمیل یا رمز درست نیست.',
            ]);
        }

        // A new session id on sign-in, so a session fixed before the login
        // cannot be used after it.
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
