<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Marketplace\VendorDirectoryController;
use App\Http\Controllers\Marketplace\VendorRegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The parent store
|--------------------------------------------------------------------------
|
| Registered only when the request did not arrive at a mini store's hostname.
| The storefront itself is still the ThemeForest page in download-version/ and
| has not been ported into Blade yet — see HANDOFF.md. What is here is what the
| marketplace needs to function: a seller directory, the registration flow, and
| the sign-in that leads to the panels.
|
*/

Route::view('/', 'welcome')->name('home');

Route::get('/sellers', [VendorDirectoryController::class, 'index'])->name('vendors.index');

Route::get('/sell-with-us', [VendorRegistrationController::class, 'create'])->name('vendors.register');
Route::post('/sell-with-us', [VendorRegistrationController::class, 'store'])->middleware('throttle:10,1');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
