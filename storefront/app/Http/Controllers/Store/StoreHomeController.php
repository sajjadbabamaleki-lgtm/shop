<?php

namespace App\Http\Controllers\Store;

use App\Domains\Tenancy\Services\StorefrontCatalog;
use App\Domains\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * A mini store's front page (§4).
 *
 * Nothing here reads a vendor id from the request. The tenant was resolved
 * before this ran, and the catalogue is asked for *its* products, which is why
 * changing an id in a URL cannot show another shop's goods.
 */
class StoreHomeController extends Controller
{
    public function __invoke(TenantContext $context, StorefrontCatalog $catalog): View
    {
        $tenant = $context->currentOrFail();
        $products = $catalog->products($tenant, perPage: 8);

        return view('store.home', [
            'tenant' => $tenant,
            'products' => $products,
            'prices' => $products->mapWithKeys(
                fn ($product) => [$product->id => $catalog->fromPriceFor($tenant, $product)]
            ),
        ]);
    }
}
