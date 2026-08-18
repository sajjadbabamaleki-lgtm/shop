@extends('layouts.admin')

@section('title', 'پردازش و ارسال')

{{--
    §10's «Order Processing & Shipping Settings».

    The one thing this screen has to say out loud, and does: changing these
    does not move a promise already made. §10 forbids silently rewriting
    historical promises, so every order keeps the calendar it was confirmed
    under — and a shop owner who believes otherwise will declare a holiday and
    expect fifty customers to be told.
--}}

@php
    $weekdays = [6 => 'شنبه', 7 => 'یک‌شنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنج‌شنبه', 5 => 'جمعه'];
@endphp

@section('content')
<div class="vp-adm-grid">

    <section class="vp-adm-card vp-adm-span-2">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">زمان آماده‌سازی و تقویم کاری</h2>
        </div>

        <p class="vp-adm-empty">
            مهلت ارسال و بازه تحویلِ هر سفارش از روی همین‌ها حساب می‌شود.
            <b>تغییر این تنظیمات، سفارش‌هایی را که قبلاً تأیید شده‌اند جابه‌جا نمی‌کند</b> —
            هر سفارش تقویمی را که با آن قول داده شده نگه می‌دارد.
        </p>

        <form class="vp-adm-form" method="post" action="{{ route('admin.fulfilment.update') }}">
            @csrf

            <label for="fp-prep">آماده‌سازی (روز کاری)</label>
            <input id="fp-prep" type="number" name="preparation_days" min="0" max="60"
                   value="{{ old('preparation_days', $settings['preparation_days']) }}" required>

            <span class="vp-adm-form-label">روزهای کاری</span>
            <div class="vp-adm-weekdays">
                @foreach ($weekdays as $iso => $name)
                    <label>
                        <input type="checkbox" name="working_days[]" value="{{ $iso }}"
                               @checked(in_array($iso, old('working_days', $settings['working_days']), false))>
                        <span>{{ $name }}</span>
                    </label>
                @endforeach
            </div>

            <label for="fp-holidays">تعطیلات — هر تاریخ در یک خط</label>
            <textarea id="fp-holidays" name="holidays" rows="4" dir="ltr"
                      placeholder="2026-09-01">{{ old('holidays', implode("\n", $settings['holidays'])) }}</textarea>

            <label for="fp-cutoff">ساعت پایان پذیرش همان‌روز (اختیاری)</label>
            <input id="fp-cutoff" type="time" name="cutoff" value="{{ old('cutoff', $settings['cutoff']) }}">

            <label for="fp-maxdelay">سقف تأخیر مجاز (روز کاری)</label>
            <input id="fp-maxdelay" type="number" name="max_delay_days" min="0" max="60"
                   value="{{ old('max_delay_days', $settings['max_delay_days']) }}" required>

            <label class="vp-adm-check">
                <input type="checkbox" name="delays_enabled" value="1"
                       @checked(old('delays_enabled', $settings['delays_enabled']))>
                <span>ثبت تأخیر برای این شعبه فعال باشد</span>
            </label>

            <button type="submit" class="vp-adm-apply">ذخیره</button>
        </form>
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">روش‌های ارسال</h2>
        </div>

        @if ($methods->isEmpty())
            <p class="vp-adm-empty">هنوز روش ارسالی تعریف نشده. بدون آن، بازه تحویل با تخمین پیش‌فرض ۲ تا ۴ روز کاری حساب می‌شود.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>نام</th><th>شرکت</th><th>ترانزیت</th><th>وضعیت</th><th></th></tr></thead>
                <tbody>
                @foreach ($methods as $method)
                    <tr>
                        <td>{{ $method->name }}</td>
                        <td>{{ $method->carrier ?: '—' }}</td>
                        <td>{{ fa_number($method->transit_min_days) }} تا {{ fa_number($method->transit_max_days) }} روز کاری</td>
                        <td>
                            <span class="vp-adm-badge is-{{ $method->is_active ? 'delivered' : 'cancelled' }}">
                                {{ $method->is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td>
                            <form method="post" action="{{ route('admin.fulfilment.methods.toggle', $method) }}">
                                @csrf
                                <button type="submit" class="vp-adm-clear">{{ $method->is_active ? 'خاموش' : 'روشن' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <form class="vp-adm-form" method="post" action="{{ route('admin.fulfilment.methods.store') }}">
            @csrf
            <label for="fm-name">روش تازه</label>
            <input id="fm-name" type="text" name="name" maxlength="80" placeholder="مثلاً پست پیشتاز" required>

            <label for="fm-carrier">شرکت حمل</label>
            <input id="fm-carrier" type="text" name="carrier" maxlength="80" placeholder="مثلاً تیپاکس">

            <label for="fm-min">کمترین روز کاری ترانزیت</label>
            <input id="fm-min" type="number" name="transit_min_days" min="0" max="60" value="2" required>

            <label for="fm-max">بیشترین روز کاری ترانزیت</label>
            <input id="fm-max" type="number" name="transit_max_days" min="0" max="60" value="4" required>

            <button type="submit" class="vp-adm-apply">افزودن</button>
        </form>
    </section>
</div>
@endsection
