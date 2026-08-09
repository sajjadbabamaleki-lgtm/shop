<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where /panel lands.
 *
 * A person may run one shop, several, a branch, or the platform. Rather than
 * guessing, this shows what they hold — and sends them straight through when
 * there is only one thing to hold, which is the common case.
 */
class PanelEntryController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $tenants = $user->accessibleTenants();
        $isPlatformStaff = $user->hasPermission('platform.dashboard') || $user->isSuperAdmin();

        if ($tenants->count() === 1 && ! $isPlatformStaff) {
            $tenant = $tenants->first();

            return redirect()->route("panel.{$tenant->tenantKind()}.dashboard", $tenant);
        }

        return view('panel.entry', [
            'tenants' => $tenants,
            'isPlatformStaff' => $isPlatformStaff,
        ]);
    }
}
