@extends('layouts.storefront')

@section('title', 'حساب من')

{{--
    A shopper's own account.

    What an account is *for* here is the orders, so that is what the page is.
    There is nothing else true to put on it yet — an address book is a table
    that does not exist, and a wishlist is a feature nobody has asked for.

    The orders are this branch's, because `Order` is branch-scoped. A customer
    standing in the Shiraz shop sees what they bought in Shiraz, which is also
    the only list that branch's staff could help them with.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">{{ $customer->name ?: 'حساب من' }}</h1>
                    <p class="vp-shop-count">{{ $customer->phone }}</p>
                </div>
                <form method="post" action="{{ storefront_route('account.logout') }}">
                    @csrf
                    <button type="submit" class="vp-cart-keep">خروج</button>
                </form>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            <h2 class="vp-filter-title">سفارش‌های من</h2>

            @if ($orders->isEmpty())
                <p class="vp-note">هنوز سفارشی ثبت نکرده‌ای.</p>
                <a class="vp-cart-go vp-account-shop" href="{{ storefront_route('shop') }}">رفتن به فروشگاه</a>
            @else
                <ul class="vp-account-orders">
                    @foreach ($orders as $order)
                        <li>
                            <a href="{{ storefront_route('order', $order) }}">
                                <span class="vp-account-no">{{ $order->number }}</span>
                                <span class="vp-account-when">{{ fa_date($order->placed_at) }}</span>
                                <span class="vp-account-what">{{ fa_number($order->items_count) }} قلم</span>
                                <span class="vp-account-sum">{{ toman($order->grand_total) }} تومان</span>
                                <span class="vp-account-state">{{ $order->statusLabel() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="vp-shop-pages">{{ $orders->links('pagination.vikyplus') }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
