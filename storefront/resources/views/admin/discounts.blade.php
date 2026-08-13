@extends('layouts.admin')

{{--
    Codes. A branch makes its own and sees the platform's without being able to
    switch one off — a franchise running a local campaign should not need to
    ask, and should not be able to stop a national one.

    A used code is never deleted, only switched off: it is part of an order's
    history, and the redemptions hanging off it are what a campaign is judged
    by afterwards.
--}}

@section('content')
<section class="vp-shop-panel">
    <h1 class="vp-shop-title">کدهای تخفیف</h1>

    @if ($codes->isEmpty())
        <p class="vp-shop-empty">کدی ساخته نشده.</p>
    @else
        <table class="vp-admin-table is-wide">
            <thead><tr><th>کد</th><th>چقدر</th><th>دامنه</th><th>شرط</th><th>بازه</th><th>استفاده</th><th>هزینه (تومان)</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            @foreach ($codes as $code)
                <tr>
                    <td><strong>{{ $code->code }}</strong>@if ($code->description)<br><span class="vp-cart-meta">{{ $code->description }}</span>@endif</td>
                    <td>{{ $code->describe() }}</td>
                    <td>{{ $code->branch_id === null ? 'همه شعبه‌ها' : ($code->branch_id === $branchId ? 'این شعبه' : 'شعبه دیگر') }}</td>
                    <td>{{ $code->min_subtotal > 0 ? 'از '.toman($code->min_subtotal) : '—' }}</td>
                    <td>
                        {{ $code->starts_at ? fa_date($code->starts_at) : '—' }}
                        تا
                        {{ $code->ends_at ? fa_date($code->ends_at) : '—' }}
                    </td>
                    <td>{{ fa_number($code->redemptions_count) }}@if ($code->usage_limit) / {{ fa_number($code->usage_limit) }}@endif</td>
                    <td>{{ toman((int) ($code->redemptions_sum_amount ?? 0)) }}</td>
                    <td>{{ $code->is_active ? ($code->isLive() ? 'فعال' : 'خارج از بازه') : 'خاموش' }}</td>
                    <td>
                        @if ($code->branch_id === $branchId || $code->branch_id === null)
                            <form method="post" action="{{ route('admin.discounts.toggle', $code) }}">
                                @csrf
                                <button type="submit" class="vp-admin-save">{{ $code->is_active ? 'خاموش' : 'روشن' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $codes->links('pagination.vikyplus') }}</div>
    @endif
</section>

<section class="vp-shop-panel vp-track">
    <h2 class="vp-shop-title">کد تازه</h2>

    <form class="vp-checkout" method="post" action="{{ route('admin.discounts.store') }}">
        @csrf
        <div class="vp-field">
            <label for="d-code">کد</label>
            <input id="d-code" name="code" value="{{ old('code') }}" required maxlength="32" placeholder="NOWRUZ1405">
        </div>
        <div class="vp-field">
            <label for="d-desc">توضیح (برای خودت)</label>
            <input id="d-desc" name="description" value="{{ old('description') }}" maxlength="120">
        </div>
        <div class="vp-field-row">
            <div class="vp-field">
                <label for="d-type">نوع</label>
                <select id="d-type" name="type" class="vp-admin-pick">
                    <option value="percent">درصدی</option>
                    <option value="fixed">مبلغ ثابت (تومان)</option>
                </select>
            </div>
            <div class="vp-field">
                <label for="d-value">مقدار</label>
                <input id="d-value" name="value" inputmode="decimal" value="{{ old('value') }}" required>
            </div>
        </div>
        <div class="vp-field-row">
            <div class="vp-field">
                <label for="d-min">حداقل خرید (تومان)</label>
                <input id="d-min" name="min_subtotal" inputmode="numeric" value="{{ old('min_subtotal') }}">
            </div>
            <div class="vp-field">
                <label for="d-max">سقف تخفیف (تومان)</label>
                <input id="d-max" name="max_discount" inputmode="numeric" value="{{ old('max_discount') }}">
            </div>
        </div>
        <div class="vp-field-row">
            <div class="vp-field">
                <label for="d-start">از تاریخ</label>
                <input id="d-start" type="date" name="starts_at" value="{{ old('starts_at') }}">
            </div>
            <div class="vp-field">
                <label for="d-end">تا تاریخ</label>
                <input id="d-end" type="date" name="ends_at" value="{{ old('ends_at') }}">
            </div>
        </div>
        <div class="vp-field-row">
            <div class="vp-field">
                <label for="d-limit">سقف تعداد استفاده</label>
                <input id="d-limit" type="number" min="1" name="usage_limit" value="{{ old('usage_limit') }}">
            </div>
            <div class="vp-field">
                <label for="d-per">سقف برای هر مشتری</label>
                <input id="d-per" type="number" min="1" name="usage_limit_per_customer" value="{{ old('usage_limit_per_customer') }}">
            </div>
        </div>

        @if (auth()->user()->hasPermissionTo('branch.manage'))
            <label class="vp-admin-remember"><input type="checkbox" name="platform_wide" value="1"> برای همه شعبه‌ها</label>
        @endif

        <button type="submit" class="vp-filter-apply vp-cart-go">ساختن کد</button>
    </form>
</section>
@endsection
