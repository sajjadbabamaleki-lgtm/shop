<?php

namespace App\Http\Controllers\Store;

use App\Domains\Tenancy\Services\StorefrontCatalog;
use App\Domains\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A product page inside a mini store.
 *
 * A product this store does not sell is a 404, not a page with no buy button.
 * Route-model binding finds the product by slug across the whole catalogue —
 * it has to, the catalogue is canonical — so the check that it belongs to this
 * shop is the controller's job and is the first thing it does.
 */
class StoreProductController extends Controller
{
    public function __invoke(Product $product, TenantContext $context, StorefrontCatalog $catalog): View
    {
        $tenant = $context->currentOrFail();
        $offers = $catalog->offersFor($tenant, $product);

        if ($offers->isEmpty() || $product->approval_status !== 'approved') {
            throw new NotFoundHttpException('This shop does not carry that product.');
        }

        return view('store.product', [
            'tenant' => $tenant,
            'product' => $product->load(['brand', 'media']),
            'offers' => $offers,
        ]);
    }
}
