<?php

namespace App\Http\Controllers\Store;

use App\Domains\Tenancy\Services\StorefrontCatalog;
use App\Domains\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreCatalogController extends Controller
{
    public function index(Request $request, TenantContext $context, StorefrontCatalog $catalog): View
    {
        $tenant = $context->currentOrFail();
        $products = $catalog->products($tenant, $request->string('q')->toString());

        return view('store.catalog', [
            'tenant' => $tenant,
            'products' => $products,
            'search' => $request->string('q')->toString(),
            'prices' => $products->mapWithKeys(
                fn ($product) => [$product->id => $catalog->fromPriceFor($tenant, $product)]
            ),
        ]);
    }
}
