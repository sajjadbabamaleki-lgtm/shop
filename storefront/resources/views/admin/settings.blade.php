@extends('layouts.admin')

@section('title', 'تنظیمات شعبه')

{{--
    The branch's own details. The working calendar and the shipping methods are
    not here — they are «پردازش و ارسال», because a holiday is not an address
    and folding the two into one screen made the one people open weekly sit
    under the one they set once.
--}}

@section('content')
<section class="vp-adm-card">
    <div class="vp-adm-card-head">
        <h2 class="vp-adm-card-title">{{ $branch->name }}</h2>
    </div>

    <p class="vp-adm-empty">
        نشانی این شعبه در سایت: <bdi dir="ltr"><code>/{{ $branch->pathPrefix() ?: '' }}</code></bdi>
    </p>

    <form class="vp-adm-form" method="post" action="{{ route('admin.settings.update') }}">
        @csrf

        <label for="st-name">نام شعبه</label>
        <input id="st-name" name="name" value="{{ old('name', $branch->name) }}" required maxlength="120">

        <div class="vp-adm-form-row">
            <div class="vp-adm-form">
                <label for="st-province">استان</label>
                <input id="st-province" name="province" value="{{ old('province', $branch->province) }}" maxlength="60">
            </div>
            <div class="vp-adm-form">
                <label for="st-city">شهر</label>
                <input id="st-city" name="city" value="{{ old('city', $branch->city) }}" maxlength="60">
            </div>
        </div>

        <label for="st-address">نشانی</label>
        <textarea id="st-address" name="address" rows="3" maxlength="500">{{ old('address', $branch->address) }}</textarea>

        <div class="vp-adm-form-row">
            <div class="vp-adm-form">
                <label for="st-phone">تلفن</label>
                <input id="st-phone" name="phone" value="{{ old('phone', $branch->phone) }}" maxlength="32" dir="ltr">
            </div>
            <div class="vp-adm-form">
                <label for="st-email">ایمیل</label>
                <input id="st-email" type="email" name="email" value="{{ old('email', $branch->email) }}" maxlength="120" dir="ltr">
            </div>
        </div>

        <label for="st-presence">نوع شعبه</label>
        <select id="st-presence" name="presence">
            <option value="both" @selected($branch->presence === 'both')>حضوری و آنلاین</option>
            <option value="physical" @selected($branch->presence === 'physical')>فقط حضوری</option>
            <option value="online" @selected($branch->presence === 'online')>فقط آنلاین</option>
        </select>

        <button type="submit" class="vp-adm-apply">ثبت</button>
    </form>
</section>
@endsection
