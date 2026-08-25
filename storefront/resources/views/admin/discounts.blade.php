@extends('layouts.admin')

@section('title', 'کدهای تخفیف')

{{--
    Codes. A branch makes its own and sees the platform's without being able to
    switch one off — a franchise running a local campaign should not need to
    ask, and should not be able to stop a national one.

    A used code is never deleted, only switched off: it is part of an order's
    history, and the redemptions hanging off it are what a campaign is judged
    by afterwards.
--}}

@section('content')
<div class="vp-adm-stack">

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">کدهای ساخته‌شده</h2>
        </div>

        @if ($codes->isEmpty())
            <p class="vp-adm-empty">هنوز کدی ساخته نشده. فرم پایین اولی را می‌سازد.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>کد</th><th>چقدر</th><th>دامنه</th><th>شرط</th><th>بازه</th><th>استفاده</th><th>هزینه (تومان)</th><th>وضعیت</th><th></th></tr></thead>
                <tbody>
                @foreach ($codes as $code)
                    <tr>
                        <td>
                            <strong>{{ $code->code }}</strong>
                            @if ($code->description)<span class="vp-adm-sub">{{ $code->description }}</span>@endif
                        </td>
                        <td>{{ $code->describe() }}</td>
                        <td>{{ $code->branch_id === null ? 'همه شعبه‌ها' : ($code->branch_id === $branchId ? 'این شعبه' : 'شعبه دیگر') }}</td>
                        <td>{{ $code->min_subtotal > 0 ? 'از '.toman($code->min_subtotal) : 'ندارد' }}</td>
                        <td>
                            {{ $code->starts_at ? fa_date($code->starts_at) : 'ندارد' }}
                            تا
                            {{ $code->ends_at ? fa_date($code->ends_at) : 'ندارد' }}
                        </td>
                        <td>{{ fa_number($code->redemptions_count) }}@if ($code->usage_limit) / {{ fa_number($code->usage_limit) }}@endif</td>
                        <td>{{ toman((int) ($code->redemptions_sum_amount ?? 0)) }}</td>
                        <td>
                            @if (! $code->is_active)
                                <span class="vp-adm-badge is-cancelled">خاموش</span>
                            @elseif ($code->isLive())
                                <span class="vp-adm-badge is-delivered">فعال</span>
                            @else
                                {{-- On and out of its dates is not the same thing as off,
                                     and reading them as one is how somebody switches a
                                     working code off looking for why it does nothing. --}}
                                <span class="vp-adm-badge is-placed">خارج از بازه</span>
                            @endif
                        </td>
                        <td>
                            @if ($code->branch_id === $branchId || $code->branch_id === null)
                                <form method="post" action="{{ route('admin.discounts.toggle', $code) }}">
                                    @csrf
                                    <button type="submit" class="vp-adm-mini is-quiet">{{ $code->is_active ? 'خاموش کن' : 'روشن کن' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="vp-adm-pager">{{ $codes->links('pagination.vikyplus') }}</div>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">کد تازه</h2>
        </div>

        <form class="vp-adm-form" method="post" action="{{ route('admin.discounts.store') }}">
            @csrf

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="d-code">کد</label>
                    <input id="d-code" name="code" value="{{ old('code') }}" required maxlength="32" placeholder="NOWRUZ1405">
                </div>
                <div class="vp-adm-form">
                    <label for="d-desc">توضیح (برای خودت)</label>
                    <input id="d-desc" name="description" value="{{ old('description') }}" maxlength="120">
                </div>
            </div>

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="d-type">نوع</label>
                    <select id="d-type" name="type">
                        <option value="percent">درصدی</option>
                        <option value="fixed">مبلغ ثابت (تومان)</option>
                    </select>
                </div>
                <div class="vp-adm-form">
                    <label for="d-value">مقدار</label>
                    <input id="d-value" name="value" inputmode="decimal" value="{{ old('value') }}" required>
                </div>
            </div>

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="d-min">حداقل خرید (تومان)</label>
                    <input id="d-min" name="min_subtotal" inputmode="numeric" value="{{ old('min_subtotal') }}">
                </div>
                <div class="vp-adm-form">
                    <label for="d-max">سقف تخفیف (تومان)</label>
                    <input id="d-max" name="max_discount" inputmode="numeric" value="{{ old('max_discount') }}">
                </div>
            </div>

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="d-start">از تاریخ</label>
                    <input id="d-start" type="date" name="starts_at" value="{{ old('starts_at') }}">
                </div>
                <div class="vp-adm-form">
                    <label for="d-end">تا تاریخ</label>
                    <input id="d-end" type="date" name="ends_at" value="{{ old('ends_at') }}">
                </div>
            </div>

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="d-limit">سقف تعداد استفاده</label>
                    <input id="d-limit" type="number" min="1" name="usage_limit" value="{{ old('usage_limit') }}">
                </div>
                <div class="vp-adm-form">
                    <label for="d-per">سقف برای هر مشتری</label>
                    <input id="d-per" type="number" min="1" name="usage_limit_per_customer" value="{{ old('usage_limit_per_customer') }}">
                </div>
            </div>

            @if (auth()->user()->hasPermissionTo('branch.manage'))
                <label class="vp-adm-check">
                    <input type="checkbox" name="platform_wide" value="1">
                    <span>برای همه شعبه‌ها</span>
                </label>
            @endif

            <button type="submit" class="vp-adm-apply">ساختن کد</button>
        </form>
    </section>
</div>
@endsection
