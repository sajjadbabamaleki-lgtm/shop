@extends('layouts.admin')

@section('title', 'رمز کارکنان')

{{--
    Setting a member of staff's password.

    A card each rather than a table: this screen sits in the panel's ~300px
    column like «روش‌های ارسال» does, and a row with three password fields in
    it has nowhere to put them. See StaffPasswordController for who may open
    this at all — the answer is the two full-access titles and nobody else.
--}}

@section('content')
<div class="vp-adm-stack">

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">رمز حساب‌های کارکنان</h2>
        </div>

        <p class="vp-adm-note">
            فقط مالک شرکت این صفحه را می‌بیند. برای هر تغییر، رمز <b>خودت</b> پرسیده
            می‌شود — تا اگر کسی پشت سیستم بازِ تو نشست، نتواند رمز همه را عوض کند.
        </p>

        <ul class="vp-ship-admin">
            @foreach ($people as $person)
                <li class="vp-ship-admin-item">
                    <div class="vp-ship-admin-head">
                        <b>{{ $person->name }}</b>
                        @if ($person->is($me))
                            <span class="vp-adm-badge is-delivered">خودت</span>
                        @endif
                    </div>

                    <p class="vp-ship-admin-facts">
                        <bdi dir="ltr">{{ $person->email }}</bdi>
                        @foreach ($person->roles as $role)
                            · {{ $role->name }}
                        @endforeach
                    </p>

                    <form class="vp-ship-edit" method="post"
                          action="{{ route('admin.passwords.update', $person) }}" autocomplete="off">
                        @csrf

                        <label for="pw-new-{{ $person->id }}">رمز تازه</label>
                        <input id="pw-new-{{ $person->id }}" type="password" name="password"
                               autocomplete="new-password" required>

                        <label for="pw-again-{{ $person->id }}">تکرار</label>
                        <input id="pw-again-{{ $person->id }}" type="password" name="password_confirmation"
                               autocomplete="new-password" required>

                        <label for="pw-me-{{ $person->id }}">رمز خودت</label>
                        <input id="pw-me-{{ $person->id }}" type="password" name="confirm"
                               autocomplete="current-password" required>

                        <button type="submit" class="vp-adm-clear">ذخیره</button>
                    </form>
                </li>
            @endforeach
        </ul>

        <p class="vp-adm-note">
            با عوض شدن رمز، جلسه‌های «مرا به خاطر بسپار» آن حساب هم بسته می‌شود و
            باید دوباره وارد شود.
        </p>
    </section>
</div>
@endsection
