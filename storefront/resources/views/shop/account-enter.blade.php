@extends('layouts.storefront')

@section('title', 'ورود / ثبت‌نام')

{{--
    «ورود / ثبت‌نام».

    One page, two forms. Two pages would mean guessing which of them somebody
    wants, and the guess is wrong half the time: the shop's own customers arrive
    having bought something as a guest and do not know which one they are.

    **Redrawn, second pass.** The first version was the tracking page's
    materials borrowed whole — a 24px title, a subtitle, a grey tab slab, then
    labels stacked over empty boxes — and the client's verdict was «خیلی بد و
    قدیمی طور و نامنظمه». Fair: a label over an empty rounded box is the
    dated part, and it was three-quarters of the screen.

    What it is now, all of it out of the shop's own kit:

    - the mark in its gold tile, the way the drawer and the footer carry it,
      so the page opens on the shop rather than on a heading;
    - one line of type instead of a title and a subtitle under it;
    - a segmented control that reads as a control — the unchosen half on the
      pane's tint, the chosen half in the gold gradient;
    - **fields with their glyph inside and no label above.** The placeholder
      says what the field is; the icon says it again at a glance. This is the
      single biggest difference between this page and the old one, and it is
      why the form is now shorter than the screen;
    - an eye on the password, which every phone keyboard expects in 2026 and
      which is the difference between typing a password on a phone once and
      typing it three times.

    The eye is the only thing here that needs a script, and it is an
    enhancement: with JavaScript off the field stays a password field and the
    form still submits. Everything else — including which tab shows — is CSS
    over a pair of radios.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-track vp-enter-card">

            <div class="vp-enter-head">
                <span class="vp-enter-mark" aria-hidden="true">
                    <img src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="">
                </span>
                <h1 class="vp-enter-title">به ویکی پلاس خوش آمدی</h1>
                <p class="vp-enter-say">با شماره موبایل وارد شو — سفارش‌هایت یک‌جا جمع می‌شود و خرید بعدی سریع‌تر است.</p>
            </div>

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

                <form class="vp-signin-pane vp-signin-login" method="post" action="{{ storefront_route('account.login') }}">
                    @csrf

                    <div class="vp-inp">
                        <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                        <input id="in-phone" name="phone" value="{{ old('phone') }}" required maxlength="20" inputmode="tel" autocomplete="username" placeholder="شماره موبایل">
                        <label class="visually-hidden" for="in-phone">شماره موبایل</label>
                    </div>

                    <div class="vp-inp">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="in-pass" name="password" type="password" required autocomplete="current-password" placeholder="رمز">
                        <label class="visually-hidden" for="in-pass">رمز</label>
                        <button type="button" class="vp-inp-eye" data-vp-eye="in-pass" aria-label="نمایش رمز">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <label class="vp-admin-remember"><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label>

                    <button type="submit" class="vp-enter-go">ورود</button>
                </form>

                <form class="vp-signin-pane vp-signin-register" method="post" action="{{ storefront_route('account.register') }}">
                    @csrf

                    <div class="vp-inp">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        <input id="up-name" name="name" value="{{ old('name') }}" required maxlength="80" autocomplete="name" placeholder="نام و نام خانوادگی">
                        <label class="visually-hidden" for="up-name">نام</label>
                    </div>

                    <div class="vp-inp">
                        <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                        <input id="up-phone" name="phone" value="{{ old('phone') }}" required maxlength="20" inputmode="tel" autocomplete="username" placeholder="شماره موبایل">
                        <label class="visually-hidden" for="up-phone">شماره موبایل</label>
                    </div>

                    <div class="vp-inp">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="up-pass" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="رمز — دست‌کم ۸ نویسه">
                        <label class="visually-hidden" for="up-pass">رمز</label>
                        <button type="button" class="vp-inp-eye" data-vp-eye="up-pass" aria-label="نمایش رمز">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="vp-inp">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="up-pass2" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" placeholder="تکرار رمز">
                        <label class="visually-hidden" for="up-pass2">تکرار رمز</label>
                    </div>

                    {{-- Only meaningful once the phone has turned out to belong
                         to somebody who has already bought here. Setting a
                         password on that row hands over their order history,
                         and a phone number is not a secret — so it asks for a
                         receipt, which a stranger does not have. This becomes a
                         code by SMS the day the shop has an SMS provider. --}}
                    <div class="vp-signin-claim">
                        <div class="vp-inp">
                            <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                            <input id="up-order" name="order_number" value="{{ old('order_number') }}" maxlength="24" placeholder="شماره سفارش (اگر قبلاً خرید کرده‌ای)">
                            <label class="visually-hidden" for="up-order">شماره سفارش</label>
                        </div>
                        <small>اگر قبلاً از ما خرید کرده‌ای، شماره یکی از سفارش‌هایت را وارد کن تا حساب به اسم خودت ساخته شود.</small>
                    </div>

                    <button type="submit" class="vp-enter-go">ثبت‌نام</button>
                </form>
            </div>

            <p class="vp-signin-alt">
                حساب نمی‌خواهی؟
                <a href="{{ storefront_route('orders.track') }}">سفارشت را با شماره‌اش پیگیری کن</a>
            </p>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    <script>
        // The eye on the password fields. An enhancement and nothing more: with
        // this script absent the field stays a password field and the form
        // still submits, which is why it is not what draws the button.
        (function () {
            document.addEventListener('click', function (e) {
                var eye = e.target.closest && e.target.closest('[data-vp-eye]');
                if (!eye) return;
                var field = document.getElementById(eye.getAttribute('data-vp-eye'));
                if (!field) return;
                var shown = field.type === 'text';
                field.type = shown ? 'password' : 'text';
                eye.setAttribute('aria-label', shown ? 'نمایش رمز' : 'پنهان کردن رمز');
                eye.querySelector('i').className = shown ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
            });
        }());
    </script>
@endpush
