<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use App\Models\Settlement;
use App\Models\Vendor;
use App\Models\VendorOffer;
use App\Support\Marketplace\Settlements;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The platform's side of the marketplace: who may sell, what they may list,
 * what it costs them, and paying them.
 *
 * Nothing here is branch-scoped, because none of it belongs to a branch. It
 * sits inside the panel's route group anyway — the panel is where staff are —
 * and every action names the platform permission it needs, so a franchise
 * manager reaching these addresses is refused rather than shown a screen with
 * somebody else's business on it.
 */
class MarketplaceController extends Controller
{
    public function vendors(Request $request): View
    {
        return view('admin.vendors', [
            'vendors' => Vendor::withCount('offers')->orderBy('name')->paginate(30),
            'pendingOffers' => VendorOffer::with('vendor', 'variant.product')
                ->where('status', VendorOffer::PENDING)
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Approve, suspend, reject. Recorded by the model's own audit trail, so
     * «چه کسی این فروشنده را معلق کرد» has an answer (§29).
     */
    public function vendorStatus(Request $request, Vendor $vendor): RedirectResponse
    {
        $input = $request->validate([
            'status' => ['required', Rule::in([Vendor::APPROVED, Vendor::SUSPENDED, Vendor::REJECTED])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $vendor->update([
            'status' => $input['status'],
            'status_note' => $input['note'] ?? null,
            'approved_at' => $input['status'] === Vendor::APPROVED ? ($vendor->approved_at ?? now()) : $vendor->approved_at,
        ]);

        return redirect()->route('admin.vendors')->with('status', "«{$vendor->name}» {$vendor->statusLabel()}.");
    }

    /**
     * §4: a vendor's listing goes live when a person says so.
     */
    public function offerStatus(Request $request, VendorOffer $offer): RedirectResponse
    {
        $input = $request->validate([
            'status' => ['required', Rule::in([VendorOffer::ACTIVE, VendorOffer::REJECTED])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $offer->update(['status' => $input['status'], 'status_note' => $input['note'] ?? null]);

        return redirect()->route('admin.vendors')->with('status', 'وضعیت عرضه ثبت شد.');
    }

    public function commissions(): View
    {
        return view('admin.commissions', [
            'rules' => CommissionRule::orderBy('scope')->orderByDesc('priority')->get(),
            'vendors' => Vendor::orderBy('name')->get(),
        ]);
    }

    public function storeCommission(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'scope' => ['required', Rule::in([CommissionRule::GLOBAL, CommissionRule::VENDOR, CommissionRule::CATEGORY, CommissionRule::PRODUCT])],
            'scope_id' => ['nullable', 'integer'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        if ($input['scope'] !== CommissionRule::GLOBAL && ! $input['scope_id']) {
            return back()->withErrors(['scope_id' => 'برای این دامنه باید مشخص کنی روی چه چیزی اعمال می‌شود.']);
        }

        CommissionRule::create([
            'scope' => $input['scope'],
            'scope_id' => $input['scope'] === CommissionRule::GLOBAL ? null : $input['scope_id'],
            'type' => $input['type'],
            // Percent in basis points, fixed amounts in Rial — the two units
            // this column carries, converted once, here.
            'value' => $input['type'] === 'percent'
                ? (int) round($input['value'] * 100)
                : (int) $input['value'] * 10,
            'priority' => $input['priority'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->route('admin.commissions')->with('status', 'قاعده کارمزد ثبت شد.');
    }

    public function settlements(): View
    {
        return view('admin.settlements', [
            'settlements' => Settlement::with('vendor')->latest('id')->paginate(30),
        ]);
    }

    public function settlementAction(Request $request, Settlement $settlement, Settlements $settlements): RedirectResponse
    {
        $input = $request->validate([
            'action' => ['required', 'in:approve,pay,reject'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $user = $request->user();

        try {
            match ($input['action']) {
                'approve' => $this->must($user, 'marketplace.settlement.approve')
                    && $settlements->approve($settlement, $user),
                'pay' => $this->must($user, 'marketplace.settlement.pay')
                    && $settlements->pay($settlement, $user, $input['reference'] ?: 'no reference given'),
                'reject' => $this->must($user, 'marketplace.settlement.approve')
                    && $settlements->reject($settlement, $user, $input['note'] ?? ''),
            };
        } catch (RuntimeException $e) {
            return redirect()->route('admin.settlements')->withErrors(['settlement' => $e->getMessage()]);
        }

        return redirect()->route('admin.settlements')->with('status', 'ثبت شد.');
    }

    /**
     * Approving a payment and making one are separate permissions, so they are
     * checked separately here rather than once on the route.
     */
    private function must($user, string $permission): bool
    {
        if (! $user?->hasPermissionTo($permission)) {
            throw new RuntimeException('این کار را نداری.');
        }

        return true;
    }
}
