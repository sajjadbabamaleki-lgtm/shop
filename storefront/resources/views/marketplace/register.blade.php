@extends('layouts.site')
@section('title', 'فروش در ویکی‌پلاس')

@section('content')
    <div class="grid grid--2" style="margin-top:24px">
        <section class="pane">
            <h1>فروشگاه خودتان را در ویکی‌پلاس بسازید</h1>
            <p class="muted">
                محصولاتتان را می‌فروشید، پنل فروش اختصاصی خودتان را دارید و یک مینی‌وب‌سایت مستقل
                می‌گیرید که می‌توانید نشانی‌اش را مستقیم به مشتری بدهید.
            </p>
            <ul class="muted">
                <li>پنل فروش با گزارش فروش، سفارش‌ها، موجودی و تسویه‌حساب</li>
                <li>مینی‌وب‌سایت مستقل با نشانی اختصاصی</li>
                <li>کمیسیون پلتفرم: <b>{{ $commissionPercent }}٪</b> از هر فروش</li>
                <li>پس از ثبت‌نام، فروشگاه شما پیش از انتشار بررسی و تأیید می‌شود</li>
            </ul>
        </section>

        <form method="post" action="{{ route('vendors.register') }}" class="pane">
            @csrf
            <h2>ثبت‌نام</h2>

            @if($errors->any())
                <div class="notice notice--bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            <div class="field"><label for="name">نام و نام خانوادگی</label>
                <input id="name" name="name" value="{{ old('name') }}" required></div>
            <div class="field"><label for="shop_name">نام فروشگاه</label>
                <input id="shop_name" name="shop_name" value="{{ old('shop_name') }}" required></div>
            <div class="field"><label for="slug">نشانی فروشگاه (اختیاری، لاتین)</label>
                <input id="slug" name="slug" value="{{ old('slug') }}" placeholder="kafsh-ali" dir="ltr">
                <span class="faint">اگر خالی بگذارید از نام فروشگاه ساخته می‌شود.</span></div>
            <div class="field"><label for="email">ایمیل</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required></div>
            <div class="field"><label for="phone">تلفن</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" required dir="ltr"></div>
            <div class="row">
                <div class="field" style="flex:1"><label for="province">استان</label>
                    <input id="province" name="province" value="{{ old('province') }}"></div>
                <div class="field" style="flex:1"><label for="city">شهر</label>
                    <input id="city" name="city" value="{{ old('city') }}"></div>
            </div>
            <div class="field"><label for="password">گذرواژه</label>
                <input id="password" type="password" name="password" required></div>
            <div class="field"><label for="password_confirmation">تکرار گذرواژه</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required></div>
            <div class="field"><label for="about">درباره فروشگاه</label>
                <textarea id="about" name="about">{{ old('about') }}</textarea></div>

            <button class="btn btn--gold">ساخت فروشگاه</button>
        </form>
    </div>
@endsection
