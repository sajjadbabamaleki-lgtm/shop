<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Catalogue\ProductByOldAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An address the shop published before this site existed.
 *
 * **The previous website addressed a product as `/product/<category>/<slug>`**
 * — singular, with the section in the path — and this one serves
 * `/products/{slug}`. Every one of those old addresses has been a 404 since
 * the day this site went up, and ترب are still holding about thirty-eight of
 * them: «۳۸ تا از محصولات ما در دسترس نیستن». Measured from a runner on
 * 2026-09-21, the one they named 404s in all three shapes while the shoe
 * itself is on sale.
 *
 * That is the same fault as `golden-goose` in its effect — an aggregator reads
 * the 404 as «کالا وجود ندارد» — and a different fault in its cause. The
 * golden-goose address was this site's own and the row behind it had been
 * retired; these addresses were never this site's, and the rows behind them
 * are on the shelf right now under a different slug.
 *
 * **Two doors, one rule.** This answers the old scheme, and it also answers
 * `/products/{slug}` when no row has that slug — because the old site's slug
 * for a shoe is a different string from this one's, so an old address that
 * loses only its `/product/<category>` prefix is still not a slug here.
 * `ProductByOldAddress` carries the matching and its fences.
 *
 * **302 and not 301**, the same reasoning as the retired shoe's redirect: a
 * permanent redirect claims this old address *is* that product for ever, which
 * rests on a title match, and it is the one kind of wrong a crawler will not
 * let the shop take back.
 *
 * A slug that resolves to nothing is still a 404. This makes old addresses
 * work; it does not make every string under `/product` answer 200, which would
 * tell a crawler the shop has infinitely many pages.
 */
class OldAddressController extends Controller
{
    /** The previous site's `/product/<category>/<slug>`, at any depth. */
    public function __invoke(Request $request): RedirectResponse
    {
        $slug = $this->slugOf($request);

        // The old address may happen to carry a slug this shop still uses —
        // then it is only the path that is old, and the product's own page
        // decides what to do with it (it may be retired, and that page has its
        // own answer).
        $itsOwn = Product::query()->where('slug', $slug)->first();

        if ($itsOwn !== null) {
            return redirect(storefront_route('product', $itsOwn), 302);
        }

        return $this->toTheShoeItNames($slug);
    }

    /** `/products/{slug}` where no row carries that slug. */
    public function missing(Request $request): RedirectResponse
    {
        return $this->toTheShoeItNames($this->slugOf($request));
    }

    private function toTheShoeItNames(string $slug): RedirectResponse
    {
        $found = ProductByOldAddress::find($slug);

        if ($found === null) {
            throw new NotFoundHttpException('No shoe of that name.');
        }

        return redirect(storefront_route('product', $found), 302);
    }

    /**
     * The last segment of the path, decoded.
     *
     * Read off the path rather than the route's parameter: this runs for a
     * binding that *failed*, and for a wildcard that swallowed the category
     * as well, so the parameter is either absent or holds more than the slug.
     */
    private function slugOf(Request $request): string
    {
        return rawurldecode(Str::afterLast(trim($request->path(), '/'), '/'));
    }
}
