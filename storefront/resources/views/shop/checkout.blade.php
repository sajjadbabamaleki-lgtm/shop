@extends('layouts.storefront')

{{--
    Checkout: who, and where.

    Nothing on this form decides money. The totals beside it are computed from
    the branch's own offers, and they are computed again inside the transaction
    that takes the stock — what is posted from here is a name and an address
    and nothing else.

    One payment method, named honestly. There is no gateway yet, and a
    checkout that implied there was would be the page telling a lie about the
    next screen.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel is-narrow">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">ثبت سفارش</h1>
                    <p class="vp-shop-count">{{ fa_number($cart->count()) }} کالا در سبد</p>
                </div>
                <a class="vp-cart-keep" href="{{ storefront_route('cart') }}">بازگشت به سبد</a>
            </div>

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            <div class="vp-cart">
                <form class="vp-checkout" method="post" action="{{ storefront_route('checkout.place') }}">
                    @csrf

                    <div class="vp-field">
                        <label for="co-name">نام و نام خانوادگی</label>
                        <input id="co-name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
                    </div>

                    <div class="vp-field">
                        <label for="co-phone">شماره موبایل</label>
                        <input id="co-phone" name="phone" value="{{ old('phone') }}" required maxlength="20" inputmode="tel" autocomplete="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹">
                    </div>

                    <div class="vp-field-row">
                        <div class="vp-field">
                            <label for="co-province">استان</label>
                            <input id="co-province" name="province" value="{{ old('province') }}" maxlength="60">
                        </div>
                        <div class="vp-field">
                            <label for="co-city">شهر</label>
                            <input id="co-city" name="city" value="{{ old('city') }}" maxlength="60">
                        </div>
                    </div>

                    <div class="vp-field">
                        <label for="co-address">نشانی</label>
                        <textarea id="co-address" name="address" rows="3" required maxlength="500">{{ old('address') }}</textarea>
                    </div>

                    <div class="vp-field-row">
                        <div class="vp-field">
                            <label for="co-postal">کد پستی</label>
                            <input id="co-postal" name="postal_code" value="{{ old('postal_code') }}" maxlength="20" inputmode="numeric">
                        </div>
                        <div class="vp-field">
                            <label for="co-note">توضیح برای پیک</label>
                            <input id="co-note" name="note" value="{{ old('note') }}" maxlength="500">
                        </div>
                    </div>

                    {{-- The delivery choice, required.
                         Radios rather than a select: three options that differ
                         in what they cost are a comparison, and a select hides
                         two thirds of it behind a tap. `required` on the first
                         is enough for the group — the browser will not submit
                         until one of the name-sharing inputs is checked — and
                         the server checks it again against this branch's own
                         live methods, because a form can post anything. --}}
                    <div class="vp-field">
                        <span class="vp-field-label">روش ارسال</span>

                        <ul class="vp-ship-list">
                            @foreach ($methods as $i => $method)
                                <li>
                                    <label class="vp-ship" for="co-ship-{{ $method->id }}">
                                        <input id="co-ship-{{ $method->id }}" type="radio" name="shipping_method_id"
                                               value="{{ $method->id }}"
                                               data-ship-cost="{{ $method->costAtCheckout() }}"
                                               @checked((int) old('shipping_method_id') === $method->id)
                                               @if ($i === 0) required @endif>
                                        <span class="vp-ship-name">{{ $method->name }}</span>
                                        <span class="vp-ship-cost{{ $method->isCollect() ? ' is-collect' : '' }}">
                                            {{ $method->chargeLabel() }}
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>

                        @error('shipping_method_id')
                            <p class="vp-code-note">{{ $message }}</p>
                        @enderror

                        <p class="vp-ship-note">«پس‌کرایه» یعنی هزینهٔ ارسال را هنگام تحویل به شرکت حمل می‌پردازید.</p>
                    </div>

                    <div class="vp-field">
                        <span class="vp-field-label">روش پرداخت</span>
                        <p class="vp-checkout-pay">پرداخت اینترنتی. بعد از ثبت سفارش به درگاه بانکی می‌روید.</p>
                    </div>

                    <button type="submit" class="vp-filter-apply vp-cart-go">ثبت سفارش</button>
                </form>

                @php
                    $subtotal = $cart->subtotal();
                    $off = $discount['amount'];

                    // What the summary opens on. Nothing is chosen yet, so the
                    // delivery row says so rather than quoting one of the three
                    // — a total that changes the moment somebody picks is worse
                    // than one that waits to be told.
                    $chosen = $methods->firstWhere('id', (int) old('shipping_method_id'));
                    $shipping = $chosen?->costAtCheckout() ?? 0;
                @endphp

                <aside class="vp-cart-sum">
                    <h2 class="vp-filter-title">سفارش تو</h2>

                    @foreach ($cart->lines() as $line)
                        <div class="vp-cart-row">
                            <span>{{ $line['item']->variant->product?->title }} × {{ fa_number($line['quantity']) }}</span>
                            <span>{{ toman($line['line_total']) }}</span>
                        </div>
                    @endforeach

                    {{-- The discount field lives here now. It was on the basket
                         page and came off it at the client's word; this is the last
                         screen before paying, which is where a code is usually typed
                         anyway. Whether it applies is still worked out on every page
                         and again inside the order transaction — nothing about it is
                         stored on the basket. --}}
                    <form class="vp-code" method="post" action="{{ storefront_route('cart.discount') }}">
                        @csrf
                        <label class="visually-hidden" for="vp-code">کد تخفیف</label>
                        <input id="vp-code" name="code" value="{{ $discount['code']?->code }}" placeholder="کد تخفیف" maxlength="32">
                        <button type="submit">اعمال</button>
                    </form>

                    @if ($discount['problem'])
                        <p class="vp-code-note">{{ $discount['problem'] }}</p>
                    @elseif ($off > 0)
                        <p class="vp-code-note is-good">{{ $discount['code']->describe() }} اعمال شد.</p>
                    @endif

                    <div class="vp-cart-row"><span>جمع کالاها</span><span>{{ toman($subtotal) }} تومان</span></div>
                    @if ($off > 0)
                        <div class="vp-cart-row"><span>تخفیف ({{ $discount['code']->code }})</span><span>− {{ toman($off) }}</span></div>
                    @endif
                    <div class="vp-cart-row">
                        <span>هزینه ارسال</span>
                        <span data-ship-line>
                            @if (! $chosen)
                                — روش ارسال را انتخاب کنید
                            @else
                                {{ $chosen->chargeLabel() }}
                            @endif
                        </span>
                    </div>
                    <div class="vp-cart-row is-total">
                        <span>قابل پرداخت</span>
                        <span data-ship-total data-ship-base="{{ $subtotal - $off }}">{{ toman($subtotal - $off + $shipping) }} تومان</span>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>

{{-- The summary follows the choice.
     Server-side this is settled anyway — `PlaceOrder` prices the order from
     the method it is handed, never from anything the browser computed — so
     this script is a courtesy, not a source of truth. Written to fail quiet:
     with no JavaScript the delivery row keeps saying «روش ارسال را انتخاب
     کنید» and the order still comes out priced correctly, because the total
     that matters is the one the server writes. --}}
<script>
    (function () {
        var line = document.querySelector('[data-ship-line]');
        var total = document.querySelector('[data-ship-total]');
        var radios = document.querySelectorAll('input[name="shipping_method_id"]');

        if (!line || !total || !radios.length) return;

        var base = Number(total.getAttribute('data-ship-base')) || 0;

        // Toman for the eye, Rial in the data — the same ten-to-one the rest of
        // the shop keeps, and the reason this divides rather than prints what
        // it was given.
        function toman(rial) {
            return (rial / 10).toLocaleString('fa-IR');
        }

        function show() {
            var picked = document.querySelector('input[name="shipping_method_id"]:checked');
            if (!picked) return;

            var cost = Number(picked.getAttribute('data-ship-cost')) || 0;
            var label = picked.closest('.vp-ship').querySelector('.vp-ship-cost');

            line.textContent = label ? label.textContent.trim() : '';
            total.textContent = toman(base + cost) + ' تومان';
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', show);
        });

        show();
    }());
</script>
@endsection
