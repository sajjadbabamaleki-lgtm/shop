<?php

namespace App\Http\Controllers\Panel\Franchise;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Franchise\Models\FranchiseSetting;
use App\Domains\Tenancy\StoreUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A branch's contact details and the controlled part of its look (§3).
 *
 * "Controlled logo, banner and visual configuration" — so a branch edits its
 * address, its phone number, its opening hours and a tagline, and does not
 * edit its name, its code, its price limits or its inventory mode. Those are
 * head office's, and they are simply not in the validated set.
 */
class FranchiseProfileController extends Controller
{
    public function edit(Franchise $franchise): View
    {
        return view('panel.franchise.profile', [
            'franchise' => $franchise,
            'storeUrl' => StoreUrl::home($franchise),
            'hours' => $franchise->setting('working_hours'),
        ]);
    }

    public function update(Request $request, Franchise $franchise): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'working_hours' => ['nullable', 'string', 'max:200'],
        ]);

        $franchise->fill([
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'storefront_settings' => array_merge($franchise->storefrontSettings(), [
                'tagline' => $data['tagline'] ?? null,
                'accent' => $data['accent'] ?? null,
            ]),
        ])->save();

        FranchiseSetting::updateOrCreate(
            ['franchise_id' => $franchise->id, 'key' => 'working_hours'],
            ['value' => $data['working_hours'] ?? null],
        );

        return back()->with('status', 'اطلاعات شعبه ذخیره شد.');
    }
}
