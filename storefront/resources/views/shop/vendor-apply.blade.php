@extends('layouts.storefront')

{{--
    «فروشنده شوید».

    The only public form on this site that makes an account. It creates the
    company as pending — nothing it lists reaches the shop until somebody
    approves it — and says so plainly rather than implying the shop is open the
    moment the form is submitted.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-track vp-apply">

            @if (session('applied'))
                <h1 class="vp-shop-title">درخواست «{{ session('applied') }}» ثبت شد</h1>
                <p class="vp-note is-good">
                    حسابت ساخته شد و در انتظار بررسی است. با همان ایمیل و رمز
                    می‌توانی وارد شوی و کالاهایت را ثبت کنی؛ تا وقتی تأیید نشده،
                    روی سایت نمایش داده نمی‌شوند.
                </p>
                <a class="vp-filter-apply vp-cart-go" href="{{ route('admin.login') }}">ورود به حساب</a>
            @else
                <h1 class="vp-shop-title">فروشنده ویکی پلاس شوید</h1>
                <p class="vp-shop-count">
                    کالاهایت را کنار کالاهای ما بفروش. ثبت‌نام رایگان است و کارمزد فقط
                    روی فروش گرفته می‌شود.
                </p>

                @foreach ($errors->all() as $error)
                    <p class="vp-note is-bad">{{ $error }}</p>
                @endforeach

                <form class="vp-checkout" method="post" action="{{ storefront_route('vendors.apply.store') }}">
                    @csrf

                    <div class="vp-field is-wide">
                        <label for="va-name">نام فروشگاه</label>
                        <input id="va-name" name="name" value="{{ old('name') }}" required maxlength="120">
                    </div>

                    <div class="vp-field-row">
                        <div class="vp-field">
                            <label for="va-legal">نام حقوقی (اختیاری)</label>
                            <input id="va-legal" name="legal_name" value="{{ old('legal_name') }}" maxlength="160">
                        </div>
                        <div class="vp-field">
                            <label for="va-national">شناسه ملی (اختیاری)</label>
                            <input id="va-national" name="national_id" value="{{ old('national_id') }}" maxlength="32" inputmode="numeric">
                        </div>
                    </div>

                    <div class="vp-field-row">
                        <div class="vp-field">
                            <label for="va-contact">نام شما</label>
                            <input id="va-contact" name="contact" value="{{ old('contact') }}" required maxlength="120">
                        </div>
                        <div class="vp-field">
                            <label for="va-phone">شماره تماس</label>
                            <input id="va-phone" name="phone" value="{{ old('phone') }}" required maxlength="32" inputmode="tel">
                        </div>
                    </div>

                    <div class="vp-field is-wide">
                        <label for="va-address">نشانی (اختیاری)</label>
                        <textarea id="va-address" name="address" rows="2" maxlength="500">{{ old('address') }}</textarea>
                    </div>

                    <div class="vp-field is-wide">
                        <label for="va-email">ایمیل — با همین وارد می‌شوی</label>
                        <input id="va-email" type="email" name="email" value="{{ old('email') }}" required maxlength="160" autocomplete="username">
                    </div>

                    <div class="vp-field-row">
                        <div class="vp-field">
                            <label for="va-pass">رمز</label>
                            <input id="va-pass" type="password" name="password" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="vp-field">
                            <label for="va-pass2">تکرار رمز</label>
                            <input id="va-pass2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="vp-filter-apply vp-cart-go">ارسال درخواست</button>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection
