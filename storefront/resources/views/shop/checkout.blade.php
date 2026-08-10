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
        <div class="vp-shop-panel">

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

                    <div class="vp-field">
                        <span class="vp-field-label">روش پرداخت</span>
                        <p class="vp-checkout-pay">پرداخت در محل، هنگام تحویل. درگاه بانکی هنوز وصل نشده.</p>
                    </div>

                    <button type="submit" class="vp-filter-apply vp-cart-go">ثبت سفارش</button>
                </form>

                @php
                    $subtotal = $cart->subtotal();
                    $free = (int) config('storefront.checkout.free_shipping_above');
                    $shipping = $free > 0 && $subtotal >= $free ? 0 : (int) config('storefront.checkout.shipping_flat');
                @endphp

                <aside class="vp-cart-sum">
                    <h2 class="vp-filter-title">سفارش تو</h2>

                    @foreach ($cart->lines() as $line)
                        <div class="vp-cart-row">
                            <span>{{ $line['item']->variant->product?->title }} × {{ fa_number($line['quantity']) }}</span>
                            <span>{{ toman($line['line_total']) }}</span>
                        </div>
                    @endforeach

                    <div class="vp-cart-row"><span>جمع کالاها</span><span>{{ toman($subtotal) }} تومان</span></div>
                    <div class="vp-cart-row">
                        <span>هزینه ارسال</span>
                        <span>{{ $shipping === 0 ? 'رایگان' : toman($shipping).' تومان' }}</span>
                    </div>
                    <div class="vp-cart-row is-total"><span>قابل پرداخت</span><span>{{ toman($subtotal + $shipping) }} تومان</span></div>
                </aside>
            </div>
        </div>
    </div>
</section>
@endsection
