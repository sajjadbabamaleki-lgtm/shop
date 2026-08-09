@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'پروفایل و مینی‌سایت')
@section('heading', 'پروفایل و مینی‌سایت')

@section('content')
    <section class="pane">
        <h2>نشانی مینی‌سایت شما</h2>
        <p class="muted">
            این نشانی را می‌توانید مستقیم به مشتری بدهید. صفحه‌ای که باز می‌شود فقط فروشگاه شماست:
            هیچ لینکی به ویکی‌پلاس ندارد و مشتری از آن به سایت مادر نمی‌رسد.
        </p>
        <p><a class="btn btn--gold" href="{{ $storeUrl }}" target="_blank" rel="noopener" dir="ltr">{{ $storeUrl }}</a></p>
        <p class="faint">کمیسیون فعلی شما: <b>{{ $commissionPercent }}٪</b> از هر فروش. تغییر آن با پلتفرم است.</p>
    </section>

    <form method="post" action="{{ route('panel.vendor.profile.update', $vendor) }}" class="pane" style="margin-top:18px">
        @csrf @method('put')

        <h2>پروفایل حرفه‌ای</h2>
        <div class="field"><label for="name">نام فروشگاه</label>
            <input id="name" name="name" value="{{ old('name', $vendor->name) }}" required></div>
        <div class="field"><label for="tagline">شعار / یک خط معرفی</label>
            <input id="tagline" name="tagline" value="{{ old('tagline', $vendor->storefrontSettings()['tagline'] ?? '') }}"></div>
        <div class="field"><label for="about">درباره فروشگاه</label>
            <textarea id="about" name="about">{{ old('about', $vendor->about) }}</textarea></div>

        <div class="row">
            <div class="field" style="flex:1"><label for="phone">تلفن</label>
                <input id="phone" name="phone" value="{{ old('phone', $vendor->phone) }}" dir="ltr"></div>
            <div class="field" style="flex:1"><label for="email">ایمیل</label>
                <input id="email" type="email" name="email" value="{{ old('email', $vendor->email) }}" dir="ltr"></div>
        </div>
        <div class="row">
            <div class="field" style="flex:1"><label for="province">استان</label>
                <input id="province" name="province" value="{{ old('province', $vendor->province) }}"></div>
            <div class="field" style="flex:1"><label for="city">شهر</label>
                <input id="city" name="city" value="{{ old('city', $vendor->city) }}"></div>
        </div>
        <div class="field"><label for="address">نشانی</label>
            <textarea id="address" name="address">{{ old('address', $vendor->address) }}</textarea></div>

        <div class="row">
            <div class="field" style="flex:1"><label for="instagram">اینستاگرام</label>
                <input id="instagram" name="instagram" value="{{ old('instagram', $vendor->instagram) }}" dir="ltr"></div>
            <div class="field" style="flex:1"><label for="telegram">تلگرام</label>
                <input id="telegram" name="telegram" value="{{ old('telegram', $vendor->telegram) }}" dir="ltr"></div>
            <div class="field" style="flex:1"><label for="website">وب‌سایت</label>
                <input id="website" type="url" name="website" value="{{ old('website', $vendor->website) }}" dir="ltr"></div>
        </div>

        <h2 style="margin-top:18px">ظاهر مینی‌سایت</h2>
        <div class="field" style="max-width:200px"><label for="accent">رنگ شاخص</label>
            <input id="accent" name="accent" value="{{ old('accent', $vendor->storefrontSettings()['accent'] ?? '#C0972F') }}" dir="ltr"></div>

        <h2 style="margin-top:18px">اطلاعات تسویه</h2>
        <div class="row">
            <div class="field" style="flex:1"><label for="bank_iban">شماره شبا</label>
                <input id="bank_iban" name="bank_iban" value="{{ old('bank_iban', $vendor->bank_iban) }}" dir="ltr"></div>
            <div class="field" style="flex:1"><label for="bank_account_holder">نام صاحب حساب</label>
                <input id="bank_account_holder" name="bank_account_holder" value="{{ old('bank_account_holder', $vendor->bank_account_holder) }}"></div>
        </div>

        <button class="btn btn--gold">ذخیره</button>
    </form>
@endsection
