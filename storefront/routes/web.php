<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\VendorApplicationController;
use App\Http\Controllers\WishlistController;
use App\Http\Middleware\ResolveTenant;
use App\Models\Enquiry;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The storefront
|--------------------------------------------------------------------------
|
| One set of routes, registered twice: at the site root for the main store,
| and again under /{branch} for every franchise.
|
| The specification asks for subdomains — shiraz.vikyplus.ir (§11, §17). This
| deliberately does not do that, at the client's instruction: a subdomain
| means a DNS record and a TLS certificate per branch, which is a cost and an
| operation every time a franchise opens, and a path costs nothing. The domain
| mapping is kept and still resolves the main store, so §34's custom domains
| remain a row in branch_domains rather than a rewrite.
|
| The branch group is registered last on purpose. Its {branch} segment would
| otherwise swallow every fixed page, and Laravel matches in registration
| order — so /cart is the cart, and a branch could never be named "cart"
| anyway (see Branch::RESERVED_SLUGS).
|
| Both registrations share one closure, so a page added to the storefront
| exists for every branch without being written twice. That is §12: one shared
| storefront codebase, and a feature developed once available to all branches.
|
*/

$storefront = function (): void {
    Route::get('/', HomeController::class)->name('home');

    // The listing, three ways in. One controller, because a category page and
    // a search result are the same page with a different opening filter, and
    // three controllers rendering three product grids is how the three stop
    // agreeing about what "in stock" means.
    Route::get('/products', ShopController::class)->name('shop');
    Route::get('/search', ShopController::class)->name('search');
    Route::get('/categories/{category}', ShopController::class)->name('category');

    Route::get('/products/{product}', ProductController::class)->name('product');

    // The basket. Everything that changes it is a POST, so a refresh cannot
    // add a second pair of shoes.
    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
    Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/discount', [CartController::class, 'discount'])->name('cart.discount');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.place');

    // Tracking is registered before the order page so that /orders is the
    // lookup form and /orders/VP-XXXX is one order — the fixed path has to
    // win, and Laravel matches in registration order.
    // «فروشنده شوید». Public, rate-limited, and it creates a pending vendor
    // and nothing else — §4's approval is not weakened by letting people
    // apply without being phoned first.
    Route::get('/vendors/apply', [VendorApplicationController::class, 'show'])->name('vendors.apply');
    Route::post('/vendors/apply', [VendorApplicationController::class, 'store'])
        ->middleware('throttle:6,60')
        ->name('vendors.apply.store');

    /*
     * A shopper's own account. The `customer` guard, never `web` — a shopper's
     * session must not satisfy a staff authorization check (§21).
     *
     * Registered inside the branch group like everything else, so a franchise's
     * customer signs in on that franchise's site. The identity behind it is one
     * identity across all of them; what is per-branch is which orders the
     * account page can show.
     *
     * Both forms are throttled. They take a phone number and say whether it
     * signs in, which is exactly the shape of thing that gets walked through a
     * list of numbers if nothing stops it.
     */
    Route::get('/account/enter', [AccountController::class, 'show'])->name('account.enter');

    /*
     * The number first, then either a password or a code. Every one of these is
     * throttled, and not all with the same number — they are different risks.
     *
     * Sending a code is the expensive one: every request is an SMS somebody
     * pays for, and a script pointed at it is both a bill and a way to flood
     * one person's phone. `LoginCode::nextSendAllowedAt` already holds each
     * number to one code every 90 seconds; this holds each *browser* to ten an
     * hour, for the case where the numbers are all different. `start` sends one
     * too, for a number with no password, so it carries the same limit.
     *
     * Verifying and the password are throttled against guessing. The password
     * has no minimum length by explicit instruction, so this limit is not a
     * belt beside a brace — it is most of what stands there.
     */
    Route::post('/account/start', [AccountController::class, 'start'])
        ->middleware('throttle:10,60')->name('account.start');
    Route::post('/account/code', [AccountController::class, 'code'])
        ->middleware('throttle:10,60')->name('account.code');
    Route::post('/account/password', [AccountController::class, 'password'])
        ->middleware('throttle:20,10')->name('account.password');
    Route::post('/account/verify', [AccountController::class, 'verify'])
        ->middleware('throttle:20,10')->name('account.verify');
    Route::post('/account/restart', [AccountController::class, 'restart'])->name('account.restart');

    Route::post('/account/logout', [AccountController::class, 'logout'])->name('account.logout');
    Route::get('/account', [AccountController::class, 'index'])
        ->middleware('auth:customer')->name('account');
    Route::post('/account/password/change', [AccountController::class, 'changePassword'])
        ->middleware('auth:customer')->name('account.password.change');

    /*
     * The content pages. Fixed paths, one controller, no parameters — the
     * segment is a route default rather than something a visitor supplies, so
     * /about is the only way to reach the about page and there is no
     * /pages/{anything} to walk.
     *
     * Registered before the tracking routes for no reason other than reading
     * order; none of these paths can collide with a branch, because every one
     * of them is in Branch::RESERVED_SLUGS.
     */
    foreach (PageController::PAGES as $path => $name) {
        Route::get("/{$path}", PageController::class)->defaults('page', $path)->name($name);
    }

    /*
     * «فروش عمده» and «اخذ نمایندگی». A page and a form each, one controller,
     * the kind fixed by the route rather than posted — a `kind` that arrived
     * in the request body is a franchise application filed as a wholesale
     * enquiry by anybody who edits a hidden field.
     *
     * Both throttled: they are public forms that write a row.
     */
    foreach (Enquiry::kinds() as $kind => $label) {
        Route::get("/{$kind}", [EnquiryController::class, 'show'])
            ->defaults('kind', $kind)->name($kind);

        Route::post("/{$kind}/enquiry", [EnquiryController::class, 'store'])
            ->defaults('kind', $kind)
            ->middleware('throttle:6,60')
            ->name("{$kind}.send");
    }

    /*
     * «لیست علاقمندی». Saved on the account, so it survives a new phone.
     *
     * Named `account.wishlist*` and not `wishlist*`, and that is not a
     * tidiness: `redirectGuestsTo` above decides which of the two sign-ins a
     * guest is sent to by matching `*account*` on the route name, so a shopper
     * who taps the heart without being signed in lands on the shopper's form.
     * Renaming these drops them into the staff sign-in, which is a dead end
     * their account cannot satisfy.
     *
     * `auth:customer` explicitly, never the bare `auth` — §21.
     */
    Route::middleware('auth:customer')->group(function (): void {
        Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('account.wishlist');
        Route::post('/account/wishlist', [WishlistController::class, 'store'])->name('account.wishlist.add');
        Route::post('/account/wishlist/remove', [WishlistController::class, 'destroy'])->name('account.wishlist.remove');
    });

    /*
     * §11's messages, the customer's side.
     *
     * Behind `auth:customer` and named `account.*` — both deliberate. The
     * guard, because a thread is a history with photographs in it and the
     * order page's «number plus telephone» is not a standard to open that at;
     * the name, because `redirectGuestsTo` picks between the two sign-ins by
     * matching `*account*`, so a shopper who taps «پیام‌های من» lands on the
     * shopper's form and not the staff one.
     */
    Route::middleware('auth:customer')->group(function (): void {
        Route::get('/account/messages', [MessageController::class, 'index'])->name('account.messages');
        Route::post('/account/messages', [MessageController::class, 'store'])
            ->middleware('throttle:20,60')->name('account.messages.store');
        Route::get('/account/messages/{conversation}', [MessageController::class, 'show'])->name('account.message');
        Route::post('/account/messages/{conversation}', [MessageController::class, 'reply'])
            ->middleware('throttle:40,60')->name('account.message.reply');
        Route::get('/account/messages/{conversation}/files/{attachment}', [MessageController::class, 'file'])
            ->name('account.message.file');
    });

    Route::get('/orders', [OrderController::class, 'track'])->name('orders.track');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('order');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('order.cancel');
};

/*
 * The panel goes on before the storefront, because the storefront's branch
 * group is a bare {branch} segment that would otherwise swallow /admin — and
 * Laravel matches in registration order. ("admin" is also in
 * Branch::RESERVED_SLUGS, so no franchise can ever be named it.)
 */
Route::prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
Route::prefix('vendor')->name('vendor.')->group(base_path('routes/vendor.php'));

Route::middleware(ResolveTenant::class)->group($storefront);

Route::prefix('{branch}')
    ->middleware(ResolveTenant::class)
    ->name('branch.')
    ->group($storefront);
