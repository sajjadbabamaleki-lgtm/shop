@extends('layouts.storefront')

{{--
    «پیگیری سفارش».

    The number and the phone number together, because the number alone is a
    link anybody can forward and the page behind it carries a home address.
    One message for "no such order" and for "wrong number", so that nobody can
    use this to find out whether a number is real.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-track">

            <h1 class="vp-shop-title">پیگیری سفارش</h1>

            @if ($notFound)
                <p class="vp-note is-bad">سفارشی با این شماره و این موبایل پیدا نشد.</p>
            @endif

            <form class="vp-checkout" method="get" action="{{ storefront_route('orders.track') }}">
                <div class="vp-field">
                    <label for="tr-number">شماره سفارش</label>
                    <input id="tr-number" name="number" value="{{ $number }}" required maxlength="24" placeholder="VP-XXXXXXXX">
                </div>
                <div class="vp-field">
                    <label for="tr-phone">شماره موبایل</label>
                    <input id="tr-phone" name="phone" required maxlength="20" inputmode="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹">
                </div>
                <button type="submit" class="vp-filter-apply vp-cart-go">پیدا کن</button>
            </form>
        </div>
    </div>
</section>
@endsection
