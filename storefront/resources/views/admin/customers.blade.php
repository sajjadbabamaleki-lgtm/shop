@extends('layouts.admin')

@section('title', 'مشتریان')

{{--
    «مشتریان» — everybody who has ordered, by name, telephone and birth date.

    «که اگر یک زمانی خاستیم پیامک تبلیغاتی براشون بفرستیم یک جا داشته
    باشیمشون» — three columns and nothing else, and «خروجی CSV» is the same
    three for an SMS panel to import. A birth date is there only for somebody
    who gave one when they made an account; checkout does not ask.
--}}

@section('content')
<form class="vp-adm-filters" method="get" action="{{ route('admin.customers') }}">
    <label class="visually-hidden" for="vp-q">جستجو</label>
    <input id="vp-q" class="vp-adm-search" type="search" name="q" value="{{ $q }}"
           placeholder="نام یا شماره موبایل">

    <button type="submit" class="vp-adm-apply">جستجو</button>

    @if ($q !== '')
        <a class="vp-adm-clear" href="{{ route('admin.customers') }}">پاک کردن</a>
    @endif

    <a class="vp-adm-clear" href="{{ route('admin.customers.export', $q !== '' ? ['q' => $q] : []) }}">
        خروجی CSV
    </a>
</form>

<section class="vp-adm-card">
    <div class="vp-adm-card-head">
        <h2 class="vp-adm-card-title">مشتریانی که سفارش داده‌اند <span class="vp-adm-sub">{{ fa_number($total) }} نفر</span></h2>
    </div>

    @if ($customers->isEmpty())
        <p class="vp-adm-empty">{{ $q !== '' ? 'کسی با این نام یا شماره پیدا نشد.' : 'هنوز کسی سفارش نداده.' }}</p>
    @else
        <table class="vp-admin-table">
            <thead><tr><th>نام</th><th>شماره موبایل</th><th>تاریخ تولد</th></tr></thead>
            <tbody>
            @foreach ($customers as $customer)
                <tr>
                    <td>{{ $customer->name ?: '—' }}</td>
                    <td><bdi dir="ltr">{{ $customer->phone }}</bdi></td>
                    <td>{{ $customer->birth_date ? fa_date($customer->birth_date) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $customers->links() }}</div>
    @endif
</section>
@endsection
