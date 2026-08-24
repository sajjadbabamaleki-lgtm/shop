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
            {{-- A card per method rather than a table.
                 This screen's column is about 300px wide inside the panel's
                 grid, and a five-column table was already reading at three
                 words a line in it; a sixth column and a form under each row
                 pushed the toggle off the side entirely — measured, the last
                 two columns were outside the card. A method has four facts and
                 three of them are editable, so each one gets its own block: it
                 fits the narrow column, it fits a telephone, and the form sits
                 next to the numbers it changes. --}}
            <ul class="vp-ship-admin">
                @foreach ($methods as $method)
                    <li class="vp-ship-admin-item">
                        <div class="vp-ship-admin-head">
                            <b>{{ $method->name }}</b>
                            <span class="vp-adm-badge is-{{ $method->is_active ? 'delivered' : 'cancelled' }}">
                                {{ $method->is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                            <form method="post" action="{{ route('admin.fulfilment.methods.toggle', $method) }}">
                                @csrf
                                <button type="submit" class="vp-adm-clear">{{ $method->is_active ? 'خاموش' : 'روشن' }}</button>
                            </form>
                        </div>

                        <p class="vp-ship-admin-facts">
                            {{ $method->carrier ?: 'بدون شرکت حمل' }} ·
                            {{ fa_number($method->transit_min_days) }} تا {{ fa_number($method->transit_max_days) }} روز کاری ·
                            {{ $method->chargeLabel() }}
                        </p>

                        <form class="vp-ship-edit" method="post" action="{{ route('admin.fulfilment.methods.update', $method) }}">
                            @csrf
                            <label for="fm-charge-{{ $method->id }}">هزینه ارسال</label>
                            <select id="fm-charge-{{ $method->id }}" name="charge">
                                <option value="prepaid" @selected(! $method->isCollect())>مبلغ ثابت</option>
                                <option value="collect" @selected($method->isCollect())>پس‌کرایه</option>
                            </select>

                            <label for="fm-price-{{ $method->id }}">مبلغ (تومان)</label>
                            <input id="fm-price-{{ $method->id }}" type="text" inputmode="numeric" name="price"
                                   value="{{ $method->isCollect() ? '' : intdiv($method->price, 10) }}" placeholder="۰">

                            <label for="fm-emin-{{ $method->id }}">ترانزیت (روز کاری)</label>
                            <span class="vp-ship-days">
                                <input id="fm-emin-{{ $method->id }}" type="number" name="transit_min_days"
                                       min="0" max="60" value="{{ $method->transit_min_days }}" required>
                                <span>تا</span>
                                <input type="number" name="transit_max_days" aria-label="بیشترین روز کاری ترانزیت"
                                       min="0" max="60" value="{{ $method->transit_max_days }}" required>
                            </span>

                            <button type="submit" class="vp-adm-clear">ذخیره</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <p class="vp-adm-note">تغییر مبلغ روی سفارش‌های ثبت‌شده اثر ندارد؛ هر سفارش هزینه‌ای را که مشتری پذیرفته با خودش نگه می‌دارد.</p>
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

            <label for="fm-charge">هزینه ارسال</label>
            <select id="fm-charge" name="charge">
                <option value="prepaid">مبلغ ثابت (در سفارش حساب می‌شود)</option>
                <option value="collect">پس‌کرایه (هنگام تحویل)</option>
            </select>

            <label for="fm-price">مبلغ (تومان)</label>
            <input id="fm-price" type="text" inputmode="numeric" name="price" placeholder="۰">

            <button type="submit" class="vp-adm-apply">افزودن</button>
        </form>
    </section>
</div>
@endsection
