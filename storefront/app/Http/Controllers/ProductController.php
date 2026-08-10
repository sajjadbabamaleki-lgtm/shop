<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One shoe, at this branch.
 *
 * A product the branch does not sell is a 404 here, not a page with no price
 * on it. That is the honest answer: the shoe exists, but not in this shop, and
 * a page that renders anyway would be a listing for something nobody can buy.
 */
class ProductController extends Controller
{
    public function __invoke(Product $product): View
    {
        $product->load([
            'brand', 'categories', 'media',
            'variants.offer', 'variants.stock',
            'defaultVariant.offer', 'defaultVariant.stock',
        ]);

        // Route model binding finds the product by slug across the whole
        // catalogue — products are not branch-owned, only their prices are.
        // So the branch's own question gets asked here.
        if ($product->status !== 'active' || $product->offerHere() === null) {
            throw new NotFoundHttpException('This branch does not sell that.');
        }

        $sellable = $product->variants->filter(fn (Variant $v) => $v->isSellable())->values();

        return view('shop.product', [
            'product' => $product,
            'offer' => $product->offerHere(),
            'colorways' => $product->colorways(),
            'sizes' => $sellable->sortBy('size_value', SORT_NATURAL)->values(),
            'gallery' => $product->media,
            'related' => $this->related($product),
        ]);
    }

    /**
     * Four more from the same categories, priced here like everything else.
     *
     * From the categories rather than the brand: somebody looking at a boot
     * wants another boot more than they want another shoe by the same maker.
     *
     * @return Collection<int, Product>
     */
    private function related(Product $product): Collection
    {
        $categories = $product->categories->pluck('id');

        if ($categories->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->purchasable()
            ->pricedHere()
            ->whereKeyNot($product->id)
            ->whereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $categories))
            ->with(['brand', 'media', 'defaultVariant.offer'])
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();
    }
}
