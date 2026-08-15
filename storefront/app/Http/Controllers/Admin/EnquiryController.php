<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Where the wholesale and franchise enquiries are read.
 *
 * There is no mail provider in this application, so the row *is* the delivery
 * — which makes this screen part of the feature rather than an extra. A public
 * form whose submissions nobody can see is the dead link again with a database
 * behind it.
 *
 * Platform-scoped, not branch-scoped, like the marketplace next door: somebody
 * asking to open a branch in Shiraz is not Shiraz's enquiry to answer, and a
 * wholesale order is the shop's business rather than one shelf's.
 *
 * Unhandled first and newest first — the order somebody working through them
 * wants. `handled_by` records who picked it up, so two people do not telephone
 * the same person on the same afternoon.
 */
class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $kind = $request->query('kind');

        return view('admin.enquiries', [
            'enquiries' => Enquiry::query()
                ->when(array_key_exists((string) $kind, Enquiry::kinds()), fn ($query) => $query->where('kind', $kind))
                ->with('handler')
                ->workList()
                ->paginate(25)
                ->withQueryString(),
            'kind' => array_key_exists((string) $kind, Enquiry::kinds()) ? $kind : null,
            'waiting' => Enquiry::where('status', Enquiry::NEW)->count(),
        ]);
    }

    public function update(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $input = $request->validate([
            'status' => ['required', Rule::in(array_keys(Enquiry::statusLabels()))],
        ]);

        $enquiry->update([
            'status' => $input['status'],
            // Who moved it, and when. Cleared again if it goes back to «جدید»,
            // so the column never claims somebody dealt with something that is
            // sitting in the queue.
            'handled_by' => $input['status'] === Enquiry::NEW ? null : Auth::id(),
            'handled_at' => $input['status'] === Enquiry::NEW ? null : now(),
        ]);

        return back()->with('status', 'وضعیت درخواست به‌روز شد.');
    }
}
