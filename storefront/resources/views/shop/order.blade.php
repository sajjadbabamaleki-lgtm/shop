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

            {{-- What the shop is actually waiting for.

                 Two different sentences, because they are two different
                 arrangements and the customer has to know which one they are
                 in: with a card gateway configured there is a button and the
                 order is waiting for money; without one the courier takes it
                 at the door and there is nothing to do. Printing «پرداخت هنگام
                 تحویل» under a shop that has a gateway would be telling
                 somebody not to pay. --}}
            @if ($order->status === \App\Models\Order::PLACED)
                @if ($canPayOnline)
                    <p class="vp-note">سفارشت ثبت شد و کالاها برایت کنار گذاشته شده. برای نهایی شدن، مبلغ را پرداخت کن.</p>

                    <form class="vp-order-pay" method="post" action="{{ storefront_route('order.pay', $order) }}">
                        @csrf
                        <button type="submit" class="vp-filter-apply vp-cart-go">
                            پرداخت {{ toman($order->grand_total) }} تومان
                        </button>
                    </form>
                @else
                    <p class="vp-note">سفارشت ثبت شد. کالاها برایت کنار گذاشته شده و پرداخت هنگام تحویل انجام می‌شود.</p>
                @endif
            @endif

            {{-- The receipt, once there is one. The reference number is what a
                 customer quotes on the telephone, so it is on their own page
                 rather than only in the panel. --}}
            @if ($receipt)
                <p class="vp-note is-good">
                    پرداخت شد — شماره پیگیری <bdi dir="ltr">{{ $receipt->ref_id }}</bdi>
                    @if ($receipt->card_pan)
                        · کارت <bdi dir="ltr">{{ $receipt->card_pan }}</bdi>
                    @endif
                </p>
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
                    @if ($order->discount_total > 0)
                        <div class="vp-cart-row"><span>تخفیف</span><span>− {{ toman($order->discount_total) }}</span></div>
                    @endif
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
