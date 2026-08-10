@extends('layouts.storefront')

{{--
    The basket.

    Prices here are read live from the branch's offers every time the page is
    drawn — the basket stores quantities and nothing else — so a total on this
    page is never yesterday's.

    A line the shop cannot supply is marked and kept, not dropped. The customer
    put it there; a basket that quietly loses things is worse than one that
    says what happened.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">سبد خرید</h1>
                    <p class="vp-shop-count">{{ fa_number($cart->count()) }} کالا</p>
                </div>
                <a class="vp-cart-keep" href="{{ storefront_route('shop') }}">ادامه خرید</a>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            @if ($cart->isEmpty())
                <p class="vp-shop-empty">
                    سبد خریدت خالی است.
                    <a href="{{ storefront_route('shop') }}">رفتن به فروشگاه</a>
                </p>
            @else
                @php $lines = $cart->lines(); @endphp

                <div class="vp-cart">
                    <div class="vp-cart-lines">
                        @foreach ($lines as $line)
                            @php
                                $variant = $line['item']->variant;
                                $short = $line['offer'] === null || $line['available'] < $line['quantity'];
                            @endphp
                            <div class="vp-cart-line{{ $short ? ' is-short' : '' }}">
                                <a class="vp-cart-shot" href="{{ storefront_route('product', $variant->product) }}">
                                    @if ($variant->product?->primaryMedia())
                                        <img src="{{ asset($variant->product->primaryMedia()->path) }}" alt="" loading="lazy">
                                    @endif
                                </a>

                                <div class="vp-cart-what">
                                    <a class="vp-cart-name" href="{{ storefront_route('product', $variant->product) }}">{{ $variant->product?->title }}</a>
                                    <span class="vp-cart-meta">سایز {{ fa_number((int) $variant->size_value) }} — فروشنده: {{ $line['item']->sellerName() }}</span>
                                    @if ($line['offer'] === null)
                                        <span class="vp-cart-warn">این کالا دیگر در این شعبه فروخته نمی‌شود</span>
                                    @elseif ($line['available'] < $line['quantity'])
                                        <span class="vp-cart-warn">فقط {{ fa_number($line['available']) }} عدد موجود است</span>
                                    @endif
                                </div>

                                <form class="vp-cart-qty" method="post" action="{{ storefront_route('cart.update') }}">
                                    @csrf
                                    <input type="hidden" name="variant" value="{{ $variant->id }}">
                                    <input type="hidden" name="vendor" value="{{ $line['item']->vendor_id }}">
                                    <label class="visually-hidden" for="qty-{{ $line['item']->id }}">تعداد</label>
                                    <input id="qty-{{ $line['item']->id }}" type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="{{ max(1, $line['available']) }}" inputmode="numeric">
                                    <button type="submit">به‌روزرسانی</button>
                                </form>

                                <div class="vp-cart-money">
                                    @if ($line['offer'])
                                        <strong>{{ toman($line['line_total']) }} <span>تومان</span></strong>
                                        @if ($line['quantity'] > 1)
                                            <span class="vp-cart-each">هر عدد {{ toman($line['offer']->price) }}</span>
                                        @endif
                                    @else
                                        <strong>—</strong>
                                    @endif
                                </div>

                                <form method="post" action="{{ storefront_route('cart.remove') }}">
                                    @csrf
                                    <input type="hidden" name="variant" value="{{ $variant->id }}">
                                    <input type="hidden" name="vendor" value="{{ $line['item']->vendor_id }}">
                                    <button type="submit" class="vp-cart-drop" aria-label="حذف {{ $variant->product?->title }}">&times;</button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    <aside class="vp-cart-sum">
                        <h2 class="vp-filter-title">جمع سبد</h2>

                        <div class="vp-cart-row"><span>جمع کالاها</span><span>{{ toman($cart->subtotal()) }} تومان</span></div>

                        @if ($discount['amount'] > 0)
                            <div class="vp-cart-row"><span>تخفیف ({{ $discount['code']->code }})</span><span>− {{ toman($discount['amount']) }}</span></div>
                        @endif

                        {{-- Delivery is not decided here and the page says so rather than
                             quoting a number that the checkout might then change. --}}
                        <div class="vp-cart-row"><span>هزینه ارسال</span><span>در مرحله بعد</span></div>

                        {{-- The code is text the customer typed; whether it applies is
                             worked out again on every page and once more when the order
                             is placed. --}}
                        <form class="vp-code" method="post" action="{{ storefront_route('cart.discount') }}">
                            @csrf
                            <label class="visually-hidden" for="vp-code">کد تخفیف</label>
                            <input id="vp-code" name="code" value="{{ $discount['code']?->code }}" placeholder="کد تخفیف" maxlength="32">
                            <button type="submit">اعمال</button>
                        </form>

                        @if ($discount['problem'])
                            <p class="vp-code-note">{{ $discount['problem'] }}</p>
                        @elseif ($discount['amount'] > 0)
                            <p class="vp-code-note is-good">{{ $discount['code']->describe() }} اعمال شد.</p>
                        @endif

                        @if ($cart->problems()->isNotEmpty())
                            <p class="vp-note is-bad">اول ردیف‌های مشخص‌شده را درست کن.</p>
                        @else
                            <a class="vp-filter-apply vp-cart-go" href="{{ storefront_route('checkout') }}">ادامه و ثبت سفارش</a>
                        @endif
                    </aside>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
