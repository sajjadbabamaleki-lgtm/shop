@extends('layouts.storefront')

@section('title', 'ورود / ثبت‌نام')

{{--
    «ورود / ثبت‌نام» — the number first, then either a password or a code.

    **Two ways in, both asked for**: «ورود با رمز باشه، ورود با کد یکبار مصرف هم
    یه آپشن باشه چون اصلا ممکنه اون شماره اون لحظه در دسترس شخص نباشه که کد
    بیاد». A code is only a way in while the telephone is in the room; a shop
    with no second way locks out everybody whose phone is flat.

    Three steps, one screen, and which one shows is decided on the server rather
    than by a script — `$step` is 'phone', then 'password' or 'code'. The whole
    thing works with JavaScript off. The two scripts are the countdown on the
    resend button and the eye on the password field, and without either the form
    still submits: the server refuses an early resend with the seconds left in
    the message, and a password field that cannot be unmasked is only a password
    field.

    There is no tab strip and no second form. Registration is the code step for
    a number the shop has not met — same screen, one more field.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-track vp-enter-card">

            <div class="vp-enter-head">
                <span class="vp-enter-mark" aria-hidden="true">
                    <img src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="">
                </span>

                @if ($step === 'phone')
                    <h1 class="vp-enter-title">ورود به ویکی پلاس</h1>
                    <p class="vp-enter-say">شماره موبایلت را بنویس تا ادامه بدهیم.</p>
                @elseif ($step === 'password')
                    <h1 class="vp-enter-title">رمزت را بنویس</h1>
                    <p class="vp-enter-say">
                        ورود با شماره <span class="vp-enter-phone">{{ $phone }}</span>
                    </p>
                @else
                    <h1 class="vp-enter-title">کد را بنویس</h1>
                    <p class="vp-enter-say">
                        کد پنج‌رقمی به <span class="vp-enter-phone">{{ $phone }}</span> فرستاده شد.
                    </p>
                @endif
            </div>

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            @if ($step === 'phone')

                <form class="vp-enter-form" method="post" action="{{ storefront_route('account.start') }}">
                    @csrf

                    <div class="vp-inp">
                        <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                        <input id="in-phone" name="phone" value="{{ old('phone') }}" required maxlength="20"
                               inputmode="tel" autocomplete="tel" autofocus placeholder="شماره موبایل">
                        <label class="visually-hidden" for="in-phone">شماره موبایل</label>
                    </div>

                    <button type="submit" class="vp-enter-go">ادامه</button>
                </form>

            @elseif ($step === 'password')

                <form class="vp-enter-form" method="post" action="{{ storefront_route('account.password') }}">
                    @csrf

                    <div class="vp-inp">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="in-password" name="password" type="password" required maxlength="72"
                               autocomplete="current-password" autofocus placeholder="رمز عبور">
                        <label class="visually-hidden" for="in-password">رمز عبور</label>
                        <button type="button" class="vp-inp-eye" data-vp-eye="in-password"
                                aria-label="نمایش رمز" hidden>
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <button type="submit" class="vp-enter-go">ورود</button>
                </form>

                <div class="vp-enter-again">
                    {{-- The way back in when the telephone is not in the room —
                         which is the reason the password step exists at all, so
                         the two sit on the same screen rather than one behind
                         the other. --}}
                    <form method="post" action="{{ storefront_route('account.code') }}">
                        @csrf
                        <button type="submit" class="vp-enter-resend">ورود با کد یکبار مصرف</button>
                    </form>

                    <form method="post" action="{{ storefront_route('account.restart') }}">
                        @csrf
                        <button type="submit" class="vp-enter-change">شماره را عوض کن</button>
                    </form>
                </div>

            @else

                <form class="vp-enter-form" method="post" action="{{ storefront_route('account.verify') }}">
                    @csrf

                    {{-- `inputmode="numeric"` and `autocomplete="one-time-code"`:
                         between them a phone offers the code from the SMS above
                         the keyboard, which is the difference between one tap
                         and switching apps to read five digits. --}}
                    <div class="vp-inp vp-inp-code">
                        <input id="in-code" name="code" required maxlength="12" inputmode="numeric"
                               autocomplete="one-time-code" autofocus placeholder="- - - - -">
                        <label class="visually-hidden" for="in-code">کد</label>
                    </div>

                    @if ($needsName)
                        {{-- Only for a number that has never bought here. A
                             customer checkout made already has a name on it,
                             and asking again would be the shop forgetting
                             somebody it has already met. --}}
                        <div class="vp-inp">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <input id="in-name" name="name" value="{{ old('name') }}" required maxlength="80"
                                   autocomplete="name" placeholder="نام و نام خانوادگی">
                            <label class="visually-hidden" for="in-name">نام</label>
                        </div>
                    @endif

                    @if ($needsPassword)
                        {{-- This is the registration. A number with no password
                             sets one here, on the one screen that has just
                             proved the number belongs to whoever is typing —
                             which is the only moment it is safe to set one. --}}
                        <div class="vp-inp">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            <input id="in-new-password" name="password" type="password" required maxlength="72"
                                   autocomplete="new-password" placeholder="یک رمز برای دفعه‌های بعد">
                            <label class="visually-hidden" for="in-new-password">رمز عبور</label>
                            <button type="button" class="vp-inp-eye" data-vp-eye="in-new-password"
                                    aria-label="نمایش رمز" hidden>
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endif

                    <button type="submit" class="vp-enter-go">ورود</button>
                </form>

                <div class="vp-enter-again">
                    <form method="post" action="{{ storefront_route('account.code') }}">
                        @csrf
                        <button type="submit" class="vp-enter-resend" data-vp-resend="{{ $resendIn }}"
                                @disabled($resendIn > 0)>
                            @if ($resendIn > 0)
                                ارسال دوباره تا {{ fa_number($resendIn) }} ثانیه دیگر
                            @else
                                کد را دوباره بفرست
                            @endif
                        </button>
                    </form>

                    <form method="post" action="{{ storefront_route('account.restart') }}">
                        @csrf
                        <button type="submit" class="vp-enter-change">شماره را عوض کن</button>
                    </form>
                </div>

            @endif

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
        (function () {
            // The countdown on the resend button. An enhancement: without it the
            // button is live from the start and the server refuses an early
            // resend with the seconds left in the message, which is the same
            // answer said a moment later.
            var button = document.querySelector('[data-vp-resend]');
            var left = button ? (parseInt(button.getAttribute('data-vp-resend'), 10) || 0) : 0;

            if (button && left > 0) {
                var fa = function (n) {
                    return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
                };

                var tick = setInterval(function () {
                    left -= 1;
                    if (left <= 0) {
                        clearInterval(tick);
                        button.disabled = false;
                        button.textContent = 'کد را دوباره بفرست';
                        return;
                    }
                    button.textContent = 'ارسال دوباره تا ' + fa(left) + ' ثانیه دیگر';
                }, 1000);
            }

            // The eye. Hidden in the markup and shown here, so a page with no
            // script never draws a button that would do nothing.
            Array.prototype.forEach.call(document.querySelectorAll('[data-vp-eye]'), function (eye) {
                var field = document.getElementById(eye.getAttribute('data-vp-eye'));
                if (!field) return;

                eye.hidden = false;
                eye.addEventListener('click', function () {
                    var shown = field.type === 'text';
                    field.type = shown ? 'password' : 'text';
                    eye.setAttribute('aria-label', shown ? 'نمایش رمز' : 'پنهان کردن رمز');
                    eye.firstElementChild.className =
                        'fa-solid ' + (shown ? 'fa-eye' : 'fa-eye-slash');
                });
            });
        }());
    </script>
@endpush
