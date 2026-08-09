<?php

use App\Http\Controllers\Store\StoreCatalogController;
use App\Http\Controllers\Store\StoreContactController;
use App\Http\Controllers\Store\StoreHomeController;
use App\Http\Controllers\Store\StoreProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mini store (architecture §4)
|--------------------------------------------------------------------------
|
| The whole of a seller's shop, and nothing else. There is no route here to
| the parent store's home page, catalogue, account area or panel, because the
| requirement was not "hide the links" — it was that a customer given this
| address has no way through to vikyplus.ir.
|
| This file is registered exactly once per request, either at the root of a
| mini store's own hostname or under /s/{slug} and /f/{slug} when no hostname
| is bound. Route names are the same either way, so nothing that generates a
| link needs to know which.
|
*/

Route::middleware(['tenant', 'ministore.isolated'])->name('store.')->group(function () {
    Route::get('/', StoreHomeController::class)->name('home');

    Route::get('/products', [StoreCatalogController::class, 'index'])->name('catalog');
    Route::get('/products/{product:slug}', StoreProductController::class)->name('product');

    Route::get('/about', [StoreContactController::class, 'about'])->name('about');
    Route::get('/contact', [StoreContactController::class, 'show'])->name('contact');
});
