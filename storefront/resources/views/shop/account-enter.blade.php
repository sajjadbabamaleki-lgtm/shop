@extends('layouts.storefront')

@section('title', 'ورود / ثبت‌نام')

{{--
    «ورود / ثبت‌نام».

    One page, two forms. Two pages would mean guessing which of them somebody
    wants, and the guess is wrong half the time: the shop's own customers arrive
    having bought something as a guest and do not know which one they are.

    Built out of the tracking page's materials — `.vp-shop-panel.vp-track` and
    `.vp-checkout` — because it is the same kind of thing: a short form on a
    pane, on a page with nothing else on it.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-track">

            <h1 class="vp-shop-title">ورود / ثبت‌نام</h1>
            <p class="vp-shop-count">با شماره موبایل، برای دیدن سفارش‌ها و خرید سریع‌تر.</p>

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            {{-- Radio buttons rather than script: the panel that shows is the
                 one whose radio is checked, in CSS. The page works with
                 JavaScript off, and nothing here has to wait for main.js. --}}
            <div class="vp-signin">
                <input class="vp-signin-pick" type="radio" name="vp-signin" id="vp-signin-login" @checked($tab === 'login')>
                <input class="vp-signin-pick" type="radio" name="vp-signin" id="vp-signin-register" @checked($tab === 'register')>

                <div class="vp-signin-tabs">
                    <label for="vp-signin-login">ورود</label>
                    <label for="vp-signin-register">ثبت‌نام</label>
                </div>

                <form class="vp-checkout vp-signin-pane vp-signin-login" method="post" action="{{ storefront_route('account.login') }}">
                    @csrf
                    <div class="vp-field">
                        <label for="in-phone">شماره موبایل</label>
                        <input id="in-phone" name="phone" value="{{ old('phone') }}" required maxlength="20" inputmode="tel" autocomplete="username" placeholder="۰۹۱۲۳۴۵۶۷۸۹">
                    </div>
                    <div class="vp-field">
                        <label for="in-pass">رمز</label>
                        <input id="in-pass" name="password" type="password" required autocomplete="current-password">
                    </div>
                    <label class="vp-admin-remember"><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label>
                    <button type="submit" class="vp-filter-apply vp-cart-go">ورود</button>
                </form>

                <form class="vp-checkout vp-signin-pane vp-signin-register" method="post" action="{{ storefront_route('account.register') }}">
                    @csrf
                    <div class="vp-field">
                        <label for="up-name">نام</label>
                        <input id="up-name" name="name" value="{{ old('name') }}" required maxlength="80" autocomplete="name">
                    </div>
                    <div class="vp-field">
                        <label for="up-phone">شماره موبایل</label>
                        <input id="up-phone" name="phone" value="{{ old('phone') }}" required maxlength="20" inputmode="tel" autocomplete="username" placeholder="۰۹۱۲۳۴۵۶۷۸۹">
                    </div>
                    <div class="vp-field">
                        <label for="up-pass">رمز</label>
                        <input id="up-pass" name="password" type="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="vp-field">
                        <label for="up-pass2">تکرار رمز</label>
                        <input id="up-pass2" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
                    </div>

                    {{-- Only shown once the phone has turned out to belong to
                         somebody who has already bought here. Setting a password
                         on that row hands over their order history, and a phone
                         number is not a secret — so it asks for a receipt, which
                         a stranger does not have. This becomes a code by SMS the
                         day the shop has an SMS provider. --}}
                    <div class="vp-field vp-signin-claim">
                        <label for="up-order">شماره سفارش</label>
                        <input id="up-order" name="order_number" value="{{ old('order_number') }}" maxlength="24" placeholder="VP-XXXXXXXX">
                        <small>اگر قبلاً از ما خرید کرده‌ای، شماره یکی از سفارش‌هایت را وارد کن تا حساب به اسم خودت ساخته شود.</small>
                    </div>

                    <button type="submit" class="vp-filter-apply vp-cart-go">ثبت‌نام</button>
                </form>
            </div>

            <p class="vp-signin-alt">
                سفارش داده‌ای و حساب نمی‌خواهی؟
                <a href="{{ storefront_route('orders.track') }}">سفارشت را با شماره‌اش پیگیری کن</a>
            </p>
        </div>
    </div>
</section>
@endsection
