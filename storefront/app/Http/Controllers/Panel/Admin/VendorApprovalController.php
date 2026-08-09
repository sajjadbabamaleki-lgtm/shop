<?php

namespace App\Http\Controllers\Panel\Admin;

use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Services\VendorRegistrar;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vendor approval and commercial terms (§2).
 *
 * Approving a seller is what publishes their shop and issues their subdomain,
 * so it goes through the registrar rather than setting a column here — the
 * three things have to happen together.
 */
class VendorApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePlatform($request);

        return view('panel.admin.vendors', [
            'vendors' => Vendor::query()
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->withCount(['offers', 'orderItems'])
                ->orderByRaw("case status when 'pending' then 0 else 1 end")
                ->orderByDesc('created_at')
                ->paginate(30)
                ->withQueryString(),
            'status' => $request->string('status')->toString(),
            'defaultPercent' => config('marketplace.default_commission_bps') / 100,
        ]);
    }

    public function approve(Request $request, Vendor $vendor, VendorRegistrar $registrar): RedirectResponse
    {
        $this->authorizePlatform($request);

        $registrar->approve($vendor, $request->string('note')->toString() ?: null);

        return back()->with('status', "فروشگاه «{$vendor->name}» تأیید شد.");
    }

    public function reject(Request $request, Vendor $vendor, VendorRegistrar $registrar): RedirectResponse
    {
        $this->authorizePlatform($request);

        $registrar->reject($vendor, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);

        return back()->with('status', 'درخواست رد شد.');
    }

    public function suspend(Request $request, Vendor $vendor, VendorRegistrar $registrar): RedirectResponse
    {
        $this->authorizePlatform($request);

        $registrar->suspend($vendor, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);

        return back()->with('status', 'فروشگاه تعلیق شد. پنل فروشنده همچنان برای رسیدگی به سفارش‌های باز فعال است.');
    }

    /**
     * The negotiated rate for one seller, in basis points. Kept as its own
     * action because it is a commercial decision that has nothing to do with
     * approval, and merging the two would mean editing a rate reopened a
     * settled application.
     */
    public function commission(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizePlatform($request);

        $data = $request->validate([
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $vendor->forceFill([
            'commission_rate_bps' => isset($data['commission_percent'])
                ? (int) round($data['commission_percent'] * 100)
                : null,
        ])->save();

        return back()->with('status', 'نرخ کمیسیون ذخیره شد.');
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()->hasPermission('platform.vendors.manage'), 403);
    }
}
