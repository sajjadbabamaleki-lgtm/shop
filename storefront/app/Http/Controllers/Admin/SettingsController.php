<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The branch's own record, and which branch is being looked at.
 *
 * The slug is not editable here. It is the shop's address — vikyplus.ir/shiraz
 * — and changing it silently breaks every link anybody has ever been sent.
 * Renaming a branch is a decision with consequences outside this panel, so it
 * belongs to platform administration rather than to the branch itself.
 */
class SettingsController extends Controller
{
    public function edit(TenantContext $tenant): View
    {
        return view('admin.settings', ['branch' => $tenant->branch()]);
    }

    public function update(Request $request, TenantContext $tenant): RedirectResponse
    {
        $branch = $tenant->branch();

        $branch->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:120'],
            'presence' => ['required', 'in:physical,online,both'],
        ]));

        return redirect()->route('admin.settings')->with('status', 'تنظیمات شعبه ثبت شد.');
    }

    /**
     * Look at another branch.
     *
     * The choice is kept in the session and never read from a request
     * parameter afterwards, and it is only ever accepted for a branch the user
     * genuinely has authority at — a posted id from somewhere else is refused
     * rather than obeyed (§18, §30).
     */
    public function switchBranch(Request $request): RedirectResponse
    {
        $branch = Branch::findOrFail($request->integer('branch'));

        if (! $request->user()->hasPermissionToAt($branch, 'branch.view')) {
            return redirect()->route('admin.dashboard')->withErrors([
                'branch' => 'دسترسی به این شعبه را نداری.',
            ]);
        }

        $request->session()->put('admin.branch', $branch->id);

        return redirect()->route('admin.dashboard');
    }
}
