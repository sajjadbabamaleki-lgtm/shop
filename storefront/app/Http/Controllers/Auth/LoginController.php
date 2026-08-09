<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * One sign-in for the whole platform.
 *
 * Sellers, branch staff and customers all authenticate here. What a person may
 * then reach is decided by role assignments and store membership, not by which
 * form they signed in on, which is what keeps a single account able to be a
 * customer on the parent store and a shop assistant at one branch.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'ایمیل یا گذرواژه درست نیست.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
