<?php

namespace App\Http\Controllers\Marketplace;

use App\Domains\Marketplace\Models\Vendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The seller directory on the parent store.
 *
 * This is one-directional on purpose: the parent store links out to every mini
 * store, and no mini store links back. That is the shape the client asked for
 * — a seller can hand out their own address and the customer never learns
 * there is a parent — and it is why the link generation here is the only place
 * that knows both worlds.
 */
class VendorDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $vendors = Vendor::query()
            ->approved()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.$request->string('q').'%'))
            ->withCount(['offers' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('marketplace.directory', ['vendors' => $vendors]);
    }
}
