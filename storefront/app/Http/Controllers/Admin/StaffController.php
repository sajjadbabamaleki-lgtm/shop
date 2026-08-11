<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Who works at this branch.
 *
 * The branch is the bound tenant, so every row here is this shop's — a manager
 * cannot see or change who works anywhere else, and there is no branch id on
 * any form for them to edit.
 *
 * Three rules that are not obvious and are all about not locking a shop out of
 * itself, or letting somebody quietly promote themselves:
 *
 * 1. **Only branch-scoped roles.** Granting «مدیر پلتفرم» from a branch screen
 *    would be a franchise handing out authority over every other franchise.
 * 2. **Nobody edits their own row.** Otherwise the way to become an owner is
 *    to be staff and press a button.
 * 3. **The last owner cannot be removed.** A branch with nobody who may manage
 *    staff is a branch that needs a developer to fix it.
 */
class StaffController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        return view('admin.staff', [
            'staff' => BranchUser::with('user', 'role')->get()->sortBy('user.name'),
            'roles' => Role::where('scope', Role::SCOPE_BRANCH)->orderBy('id')->get(),
        ]);
    }

    /**
     * Add somebody. An existing account is attached; a new one is created with
     * a password shown once, because there is no mail configured to send it
     * with and a password on a page is a password in a browser's history.
     */
    public function store(Request $request, TenantContext $tenant): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'role' => ['required', Rule::exists('roles', 'slug')->where('scope', Role::SCOPE_BRANCH)],
        ], [], ['name' => 'نام', 'email' => 'ایمیل', 'role' => 'نقش']);

        $role = Role::where('slug', $input['role'])->firstOrFail();
        $password = Str::password(14, symbols: false);

        $user = User::where('email', $input['email'])->first();

        if ($user && BranchUser::where('user_id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'این نفر همین حالا در این شعبه کار می‌کند.']);
        }

        $created = $user === null;

        DB::transaction(function () use (&$user, $input, $password, $role, $tenant) {
            $user ??= User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $password,
            ]);

            BranchUser::create([
                'branch_id' => $tenant->id(),
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);
        });

        return redirect()->route('admin.staff')->with(
            $created ? 'password' : 'status',
            $created ? $password : 'به کارکنان این شعبه اضافه شد. رمزش را از قبل دارد.',
        );
    }

    public function update(Request $request, BranchUser $membership): RedirectResponse
    {
        $input = $request->validate([
            'role' => ['required', Rule::exists('roles', 'slug')->where('scope', Role::SCOPE_BRANCH)],
        ]);

        if ($membership->user_id === $request->user()->id) {
            return back()->withErrors(['role' => 'نقش خودت را از اینجا نمی‌توانی عوض کنی.']);
        }

        $membership->update(['role_id' => Role::where('slug', $input['role'])->firstOrFail()->id]);

        return redirect()->route('admin.staff')->with('status', 'نقش عوض شد.');
    }

    public function destroy(Request $request, BranchUser $membership): RedirectResponse
    {
        if ($membership->user_id === $request->user()->id) {
            return back()->withErrors(['staff' => 'خودت را از شعبه حذف نکن؛ راه برگشتی نداری.']);
        }

        // The account stays. Removing somebody from a shop is not the same as
        // deleting a person, and their name is still on last month's orders.
        $membership->delete();

        return redirect()->route('admin.staff')->with('status', 'از کارکنان این شعبه حذف شد.');
    }
}
