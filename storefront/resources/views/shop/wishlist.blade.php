@extends('layouts.storefront')

@section('title', 'لیست علاقمندی')

{{--
    The saved products — «لیست علاقمندی».

    The grid and the card are the listing's own, quoted rather than reinvented:
    a saved shoe should look like the same shoe it looked like on the shelf, and
    a second card that was nearly `shop.card` would drift from it the first time
    either changed.

    **What is on this page is the branch's answer, not the account's.** The list
    itself is the shopper's across every storefront — the table has no branch
    column, deliberately — but a franchise can only show and price what it
    sells, so the controller reads it through `purchasable()`. A shoe saved at
    the main store and not carried here is not on this page and has not been
    lost; it is still on the row, and still there at a shop that stocks it.

    That filter is `purchasable()` and not `pricedHere()`, which sounds like the
    branch scope and is not one: it adds a price column and removes nothing. The
    difference shows up here rather than anywhere else, because `shop.card`
    reads `$offer->price` straight and a shoe this branch cannot price is a 500
    on this page.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">لیست علاقمندی</h1>
                    <p class="vp-shop-count">{{ fa_number($products->count()) }} کالا</p>
                </div>
                <a class="vp-cart-keep" href="{{ storefront_route('account') }}">حساب من</a>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            @if ($products->isEmpty())
                <div class="vp-empty">
                    <span class="vp-empty-mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48"><path d="M24 40S8 30 8 19a8 8 0 0 1 16-3 8 8 0 0 1 16 3c0 11-16 21-16 21Z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path></svg>
                    </span>
                    <p class="vp-empty-say">هنوز چیزی به لیست علاقمندی اضافه نکرده‌ای.</p>
                    <a class="vp-empty-out" href="{{ storefront_route('shop') }}">دیدن همه محصولات</a>
                </div>
            @else
                <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xxl-4 vp-shop-grid">
                    @foreach ($products as $product)
                        <div class="col">
                            @include('shop.card')

                            {{-- The way back out of the list, under the card
                                 rather than on it: `shop.card` is the listing's
                                 and the product page's as well, and a remove
                                 button belongs to this page alone. --}}
                            <form class="vp-fav-drop" method="post" action="{{ storefront_route('account.wishlist.remove') }}">
                                @csrf
                                <input type="hidden" name="product" value="{{ $product->id }}">
                                <button type="submit">حذف از لیست</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</section>
@endsection
