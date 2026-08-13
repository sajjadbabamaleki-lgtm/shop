@extends('layouts.admin')

@section('content')
<section class="vp-shop-panel vp-track">
    <h1 class="vp-shop-title">تنظیمات شعبه</h1>
    <p class="vp-shop-count">نشانی این شعبه در سایت: <code>/{{ $branch->pathPrefix() ?: '' }}</code></p>

    <form class="vp-checkout" method="post" action="{{ route('admin.settings.update') }}">
        @csrf

        <div class="vp-field">
            <label for="st-name">نام شعبه</label>
            <input id="st-name" name="name" value="{{ old('name', $branch->name) }}" required maxlength="120">
        </div>

        <div class="vp-field-row">
            <div class="vp-field">
                <label for="st-province">استان</label>
                <input id="st-province" name="province" value="{{ old('province', $branch->province) }}" maxlength="60">
            </div>
            <div class="vp-field">
                <label for="st-city">شهر</label>
                <input id="st-city" name="city" value="{{ old('city', $branch->city) }}" maxlength="60">
            </div>
        </div>

        <div class="vp-field">
            <label for="st-address">نشانی</label>
            <textarea id="st-address" name="address" rows="3" maxlength="500">{{ old('address', $branch->address) }}</textarea>
        </div>

        <div class="vp-field-row">
            <div class="vp-field">
                <label for="st-phone">تلفن</label>
                <input id="st-phone" name="phone" value="{{ old('phone', $branch->phone) }}" maxlength="32">
            </div>
            <div class="vp-field">
                <label for="st-email">ایمیل</label>
                <input id="st-email" type="email" name="email" value="{{ old('email', $branch->email) }}" maxlength="120">
            </div>
        </div>

        <div class="vp-field">
            <label for="st-presence">نوع شعبه</label>
            <select id="st-presence" name="presence" class="vp-admin-pick">
                <option value="both" @selected($branch->presence === 'both')>حضوری و آنلاین</option>
                <option value="physical" @selected($branch->presence === 'physical')>فقط حضوری</option>
                <option value="online" @selected($branch->presence === 'online')>فقط آنلاین</option>
            </select>
        </div>

        <button type="submit" class="vp-filter-apply vp-cart-go">ثبت</button>
    </form>
</section>
@endsection
