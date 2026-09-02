<?php

namespace App\Providers;

use App\Listeners\TellTheOwnerSomebodySignedIn;
use App\Models\Category;
use App\Models\Product;
use App\Support\Checkout\CartManager;
use App\Support\FrontPage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request. Every query scope, policy and view has to answer
        // "which branch is this?" the same way, and they can only do that if
        // they are all asking the same object — spec §17.
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * A text message to the owner every time somebody signs in to the
         * panel.
         *
         * Registered by name rather than left to event discovery, which this
         * application does not switch on: a listener that runs because of
         * where its file happens to sit is a listener that stops running when
         * somebody moves it, and nothing would go red. The same reason the SMS
         * driver is named in a map instead of guessed from which credentials
         * are filled in.
         */
        Event::listen(Login::class, TellTheOwnerSomebodySignedIn::class);

        /*
         * The header's basket badge.
         *
         * It was the template's «5» — a number that never moved however full
         * the basket was, which is worse than no number at all. A composer
         * rather than a variable passed from every controller: the header is
         * on every storefront page and there is no controller that owns it.
         *
         * `count()` never creates a basket. `current()` would, and the header
         * runs for every visitor who has not put anything in one.
         */
        View::composer('partials.header', function ($view): void {
            $view->with('basketCount', app(CartManager::class)->count());
        });

        /*
         * The drawer that opens on a phone.
         *
         * Same reason as the header: it is on every storefront page and no
         * controller owns it. It needs the catalogue's categories and the
         * basket's count, and getting either from every controller in the
         * application would mean the drawer breaks on whichever page somebody
         * forgets.
         *
         * The **same** query as the home page's category row — deliberately,
         * and not the listing's stricter one.
         *
         * The obvious rule here is "only categories with something to sell in
         * them", and it is the wrong one, because the row of tiles under the
         * hero does not follow it. Seven of the eight seeded categories hold no
         * products yet — the whole catalogue is five shoes and all five are
         * sneakers — so the strict query leaves the drawer offering one section
         * while the front page offers eight. A visitor would be looking at two
         * different shops on one screen.
         *
         * These eight are the shop's sections, which is a decision, not a
         * count; they fill in as the catalogue does. If that rule ever changes
         * it changes in both places at once, and a test asserts the two agree.
         */
        /*
         * The mini basket behind the header's basket button.
         *
         * `current()` rather than a count, because this panel draws the lines
         * themselves. It is on every storefront page and no controller owns
         * it, same as the two above — and the panel is parked off-screen, so a
         * page that forgot to pass it would not look broken, it would look
         * empty. A composer is the only way that cannot happen.
         */
        View::composer('partials.mini-cart', function ($view): void {
            $view->with('miniCart', app(CartManager::class)->current());
        });

        View::composer('partials.mobile-menu', function ($view): void {
            $view->with([
                /*
                 * `show_in_nav` is the one thing this query asks that the home
                 * page's does not, and it is the column's whole purpose — it
                 * had existed since the first migration and had never been
                 * read. «اکسسوری از منو حذف بشه»: the section keeps its tile
                 * under the hero and its place in the listing's strip, and
                 * leaves the menu.
                 *
                 * So the note above is now half true and worth restating: the
                 * two lists are still built to the same rule about what a
                 * *section* is, and the drawer alone may drop one the
                 * shopkeeper does not want in a menu. That is a decision with a
                 * column behind it rather than a divergence, and
                 * `PhoneDrawerTest` asserts exactly which slug is out.
                 */
                'drawerCategories' => Category::query()
                    ->where('is_active', true)
                    ->where('show_in_nav', true)
                    ->orderBy('position')
                    ->get(),
                'basketCount' => app(CartManager::class)->count(),
            ]);
        });

        /*
         * The stories.
         *
         * **They are products, not categories.** They were the catalogue's
         * first five sections, and «اصلا استوری ها نباید دسته بندی باشن باید
         * همون ماهیت استوری اینستاگرامو داشته باشن» ended that: a story is a
         * thing you look at and can buy, which is what the two buttons under
         * the picture now do. A category has no price, no stock and no variant,
         * so «افزودن به سبد خرید» on one had nothing to add.
         *
         * A composer for the same reason the drawer has one: the strip is a
         * partial included by two pages — the listing, where it is shown, and
         * the home page, where it is still parked — and a variable passed from
         * one controller is a strip that breaks on the other.
         *
         * `purchasable()` and `pricedHere()` are what make the buttons honest:
         * every story is a shoe this branch can actually sell today, so the
         * basket button never offers something the checkout would refuse. The
         * eager loads are what `addableVariant()`, `offerHere()` and
         * `primaryMedia()` read — without them the strip is five products and
         * about twenty queries.
         */
        View::composer('home.stories', function ($view): void {
            /*
             * Five, and *which* five is `front_page.story_products` when that
             * list is set. It was «the newest five», which is the right answer
             * for a shop of five shoes and the wrong one for a shop with an
             * imported supplier in it — the rings would become whatever landed
             * last, in insertion order, on a strip whose photographs are part
             * of the design. Named products are still products: purchasable(),
             * so a ring never offers a basket button the checkout would refuse.
             */
            $named = app(FrontPage::class)->slugs('stories');

            $load = ['brand', 'media', 'variants.offer', 'variants.stock', 'defaultVariant.offer', 'defaultVariant.stock'];

            $stories = Product::query()
                ->purchasable()
                ->pricedHere()
                ->with($load)
                ->when($named !== [], fn ($q) => $q->whereIn('slug', $named))
                ->latest('id')
                ->take(5)
                ->get();

            /*
             * **A named ring that has sold out is still one of the five.**
             * «تو صفحه فروشگاه استوری های بالای بخش فروشگاه پنج تا بودن چرا
             * چارتا شدن» — one shoe ran out of every size and the strip came
             * back four circles wide.
             *
             * `purchasable()` is right about *buying* and wrong about *being
             * there*, and here it was doing more damage than a missing circle:
             * the campaign photographs are positional
             * (`config('storefront.stories.photos')[$loop->index]`), so losing
             * the third product does not leave a gap — it slides the fourth and
             * fifth pictures one place along and shows two shoes under artwork
             * chosen for two others.
             *
             * So a named slug that is still listed here comes back on
             * `listable()`, exactly as the hero deck's does and for the same
             * reason: a band the client chose by name is a decision, not a
             * query result. Nothing about the button moves — the viewer already
             * disables «سبد خرید» when a circle carries no addable variant, so
             * an empty shelf can be seen and still cannot be sold.
             */
            if ($named !== [] && $stories->count() < count($named)) {
                $missing = array_diff($named, $stories->pluck('slug')->all());

                $stories = $stories
                    ->concat(Product::query()->listable()->whereIn('slug', $missing)->with($load)->get())
                    ->sortByDesc('id')
                    ->take(5)
                    ->values();
            }

            $view->with('stories', $stories);
        });
    }
}
