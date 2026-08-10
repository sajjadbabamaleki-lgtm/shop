@extends('layouts.storefront')

{{--
    One order, as its customer reads it.

    Everything on this page comes off the order's own rows, not the catalogue.
    A product renamed or repriced next month must not change what this says
    happened today.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">سفارش {{ $order->number }}</h1>
                    <p class="vp-shop-count">{{ $order->statusLabel() }} — {{ fa_date($order->placed_at) }}</p>
                </div>
                <a class="vp-cart-keep" href="{{ storefront_route('shop') }}">ادامه خرید</a>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            @if ($order->status === \App\Models\Order::PLACED)
                <p class="vp-note">سفارشت ثبت شد. کالاها برایت کنار گذاشته شده و پرداخت هنگام تحویل انجام می‌شود.</p>
            @endif

            <div class="vp-cart">
                <div class="vp-cart-lines">
                    @foreach ($order->items as $item)
                        <div class="vp-cart-line">
                            <div class="vp-cart-what">
                                <span class="vp-cart-name">{{ $item->product_title }}</span>
                                <span class="vp-cart-meta">سایز {{ fa_number((int) $item->size_value) }} — کد {{ $item->sku }}</span>
                            </div>
                            <div class="vp-cart-qty-static">{{ fa_number($item->quantity) }} عدد</div>
                            <div class="vp-cart-money"><strong>{{ toman($item->line_total) }} <span>تومان</span></strong></div>
                        </div>
                    @endforeach
                </div>

                <aside class="vp-cart-sum">
                    <h2 class="vp-filter-title">جمع سفارش</h2>
                    <div class="vp-cart-row"><span>جمع کالاها</span><span>{{ toman($order->subtotal) }}</span></div>
                    <div class="vp-cart-row"><span>هزینه ارسال</span><span>{{ $order->shipping_total === 0 ? 'رایگان' : toman($order->shipping_total) }}</span></div>
                    <div class="vp-cart-row is-total"><span>قابل پرداخت</span><span>{{ toman($order->grand_total) }} تومان</span></div>

                    <h2 class="vp-filter-title vp-order-to">تحویل به</h2>
                    <p class="vp-order-address">
                        {{ $order->contact_name }}<br>
                        {{ $order->contact_phone }}<br>
                        @if ($order->province || $order->city){{ $order->province }} {{ $order->city }}<br>@endif
                        {{ $order->address }}
                        @if ($order->postal_code)<br>کد پستی {{ $order->postal_code }}@endif
                    </p>

                    @if ($order->isCancellable())
                        <form method="post" action="{{ storefront_route('order.cancel', $order) }}">
                            @csrf
                            <button type="submit" class="vp-order-cancel">لغو سفارش</button>
                        </form>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</section>
@endsection
