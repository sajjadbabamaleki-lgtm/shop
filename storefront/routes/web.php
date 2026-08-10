<?php

use App\Http\Controllers\HomeController;
use App\Http\Middleware\ResolveTenant;
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
};

Route::middleware(ResolveTenant::class)->group($storefront);

Route::prefix('{branch}')
    ->middleware(ResolveTenant::class)
    ->name('branch.')
    ->group($storefront);
