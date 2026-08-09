<?php

namespace App\Http\Controllers\Store;

use App\Domains\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * The seller's own about and contact pages (§2, §3).
 *
 * These are the pages that make the mini store a shop rather than a product
 * list: the seller's own phone number, their own address, their own social
 * links. None of them is the platform's, which is the point — a customer here
 * is dealing with this seller.
 */
class StoreContactController extends Controller
{
    public function about(TenantContext $context): View
    {
        return view('store.about', ['tenant' => $context->currentOrFail()]);
    }

    public function show(TenantContext $context): View
    {
        return view('store.contact', ['tenant' => $context->currentOrFail()]);
    }
}
