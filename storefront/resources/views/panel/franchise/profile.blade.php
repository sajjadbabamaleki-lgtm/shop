@extends('layouts.panel')
@include('panel.franchise.menu')
@section('title', 'اطلاعات شعبه')
@section('heading', 'اطلاعات شعبه')

@section('content')
    <section class="pane">
        <h2>نشانی مینی‌سایت شعبه</h2>
        <p><a class="btn btn--gold" href="{{ $storeUrl }}" target="_blank" rel="noopener" dir="ltr">{{ $storeUrl }}</a></p>
        <p class="faint">
            نام شعبه، کد آن، محدوده قیمت‌گذاری و شیوه تأمین موجودی توسط دفتر مرکزی تعیین می‌شود و
            از این صفحه قابل تغییر نیست.
        </p>
    </section>

    <form method="post" action="{{ route('panel.franchise.profile.update', $franchise) }}" class="pane" style="margin-top:18px">
        @csrf @method('put')

        <div class="row">
            <div class="field" style="flex:1"><label for="phone">تلفن شعبه</label>
                <input id="phone" name="phone" value="{{ old('phone', $franchise->phone) }}" dir="ltr"></div>
            <div class="field" style="flex:1"><label for="working_hours">ساعت کاری</label>
                <input id="working_hours" name="working_hours" value="{{ old('working_hours', $hours) }}"></div>
        </div>

        <div class="field"><label for="address">نشانی</label>
            <textarea id="address" name="address">{{ old('address', $franchise->address) }}</textarea></div>

        <div class="field"><label for="tagline">یک خط معرفی</label>
            <input id="tagline" name="tagline" value="{{ old('tagline', $franchise->storefrontSettings()['tagline'] ?? '') }}"></div>

        <div class="field" style="max-width:200px"><label for="accent">رنگ شاخص</label>
            <input id="accent" name="accent" value="{{ old('accent', $franchise->storefrontSettings()['accent'] ?? '#C0972F') }}" dir="ltr"></div>

        <button class="btn btn--gold">ذخیره</button>
    </form>
@endsection
