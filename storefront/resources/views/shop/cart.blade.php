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
                <div class="vp-empty">
                    <span class="vp-empty-mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48"><path d="M10 16 h28 l-3 22 a3 3 0 0 1 -3 3 h-16 a3 3 0 0 1 -3 -3 z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path><path d="M18 16 a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
                    </span>
                    <p class="vp-empty-say">سبد خریدت خالی است.</p>
                    <a class="vp-empty-out" href="{{ storefront_route('shop') }}">رفتن به فروشگاه</a>
                </div>
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

                                {{-- A stepper, not a number box and an update button.
                                     Two one-button forms posting the quantity either
                                     side of the current one: no script, and nothing to
                                     press afterwards to make it count. Minus stops at 1
                                     — taking the last one out is the × at the end of the
                                     line, which says what it does. Plus stops at what
                                     the branch has on the shelf. --}}
                                <div class="vp-cart-qty">
                                    <form method="post" action="{{ storefront_route('cart.update') }}">
                                        @csrf
                                        <input type="hidden" name="variant" value="{{ $variant->id }}">
                                        <input type="hidden" name="vendor" value="{{ $line['item']->vendor_id }}">
                                        <input type="hidden" name="quantity" value="{{ $line['quantity'] - 1 }}">
                                        <button type="submit" class="vp-cart-less" aria-label="یکی کمتر" @disabled($line['quantity'] <= 1)>&minus;</button>
                                    </form>
                                    <span class="vp-cart-count" aria-label="تعداد">{{ fa_number($line['quantity']) }}</span>
                                    <form method="post" action="{{ storefront_route('cart.update') }}">
                                        @csrf
                                        <input type="hidden" name="variant" value="{{ $variant->id }}">
                                        <input type="hidden" name="vendor" value="{{ $line['item']->vendor_id }}">
                                        <input type="hidden" name="quantity" value="{{ $line['quantity'] + 1 }}">
                                        <button type="submit" class="vp-cart-more" aria-label="یکی بیشتر" @disabled($line['quantity'] >= $line['available'])>+</button>
                                    </form>
                                </div>

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

                        {{-- The line the customer is actually being asked to agree to,
                             which the panel did not state: it listed the parts and left
                             the adding up to them. Delivery is not in it and the row
                             above says so. --}}
                        <div class="vp-cart-row is-total">
                            <span>جمع کل</span>
                            <span>{{ toman($cart->subtotal() - $discount['amount']) }} تومان</span>
                        </div>

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
