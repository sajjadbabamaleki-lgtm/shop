@extends('layouts.admin')

@section('title', 'سفارش‌ها')

{{--
    §4 and §24: a data grid with search, filters, sorting, selection, bulk
    actions, an adjustable page size and export.

    On a phone the rows become cards — the shell's script labels each cell from
    its column heading — which is §20's «Do not rely on horizontal scrolling…
    Transform records into cards». The tick box travels with the card, so a
    bulk action is available on a telephone too rather than being a desktop
    privilege.

    Everything that navigates carries the current filter. A screen whose sort
    link throws away the search is one somebody has to set up twice.
--}}

@php
    $carry = array_filter([
        'q' => $filters['q'],
        'status' => $filters['status'],
        'payment' => $filters['payment'],
        'per' => $filters['per'] === 20 ? null : $filters['per'],
    ] + ($range?->carry() ?? []), fn ($v) => $v !== null && $v !== '');

    // A sort link keeps the filter and flips only its own direction.
    $sortLink = function (string $column) use ($carry, $filters) {
        $dir = $filters['sort'] === $column && $filters['dir'] === 'desc' ? 'asc' : 'desc';

        return route('admin.orders', $carry + ['sort' => $column, 'dir' => $dir]);
    };

    $arrow = fn (string $column) => $filters['sort'] === $column
        ? ($filters['dir'] === 'asc' ? ' ↑' : ' ↓')
        : '';
@endphp

@section('content')

{{-- --- search and filters, §24 ------------------------------------------ --}}
<form class="vp-adm-filters" method="get" action="{{ route('admin.orders') }}">
    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
    <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

    <label class="visually-hidden" for="vp-q">جستجو</label>
    <input id="vp-q" class="vp-adm-search" type="search" name="q" value="{{ $filters['q'] }}"
           placeholder="شماره سفارش، نام، تلفن یا کد رهگیری">

    <label class="visually-hidden" for="vp-status">وضعیت</label>
    <select id="vp-status" name="status">
        <option value="">همه وضعیت‌ها</option>
        @foreach ($statuses as $key => $label)
            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
        @endforeach
    </select>

    <label class="visually-hidden" for="vp-payment">پرداخت</label>
    <select id="vp-payment" name="payment">
        <option value="">پرداخت: همه</option>
        <option value="paid" @selected($filters['payment'] === 'paid')>پرداخت‌شده</option>
        <option value="unpaid" @selected($filters['payment'] === 'unpaid')>پرداخت‌نشده</option>
        <option value="refunded" @selected($filters['payment'] === 'refunded')>برگشت‌خورده</option>
    </select>

    <label class="visually-hidden" for="vp-range">بازه</label>
    <select id="vp-range" name="range">
        <option value="">هر زمان</option>
        @foreach (\App\Support\Admin\DateRange::PRESETS as $key => $days)
            <option value="{{ $key }}" @selected($range?->preset === $key)>{{ \App\Support\Admin\DateRange::LABELS[$key] }}</option>
        @endforeach
    </select>

    <label class="visually-hidden" for="vp-per">تعداد در صفحه</label>
    <select id="vp-per" name="per">
        @foreach ($sizes as $size)
            <option value="{{ $size }}" @selected($filters['per'] === $size)>{{ fa_number($size) }} ردیف</option>
        @endforeach
    </select>

    <button type="submit" class="vp-adm-apply">اعمال</button>

    @if ($carry !== [])
        <a class="vp-adm-clear" href="{{ route('admin.orders') }}">پاک کردن</a>
    @endif

    <a class="vp-adm-clear" href="{{ route('admin.orders.export', $carry + ['sort' => $filters['sort'], 'dir' => $filters['dir']]) }}">
        خروجی CSV
    </a>
</form>

<section class="vp-adm-card">
    @if ($orders->isEmpty())
        {{-- §31 wants the empty state and the no-results state designed, and
             they are not the same sentence: one shop has no orders, the other
             has none matching what you typed. --}}
        <p class="vp-adm-empty">
            @if ($carry === [])
                هنوز سفارشی ثبت نشده.
            @else
                هیچ سفارشی با این فیلترها پیدا نشد.
            @endif
        </p>
    @else
        <form method="post" action="{{ route('admin.orders.bulk') }}">
            @csrf

            {{-- The bulk bar. It is in the document always rather than being
                 revealed by script, so it works with JavaScript off — the
                 select-all box is the only part that needs any. --}}
            <div class="vp-adm-bulk">
                {{-- Hidden until the script un-hides it: without JavaScript
                     a «select all» box that ticks nothing is a broken control,
                     while the row boxes below still work perfectly. --}}
                <label class="vp-adm-bulk-all" hidden>
                    <input type="checkbox" data-adm-all>
                    <span>انتخاب همه این صفحه</span>
                </label>

                <label class="visually-hidden" for="vp-bulk-status">وضعیت تازه</label>
                <select id="vp-bulk-status" name="status">
                    @foreach ($bulkTargets as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="vp-adm-apply">اعمال روی انتخاب‌شده‌ها</button>
            </div>

            <table class="vp-admin-table">
                <thead>
                    <tr>
                        <th></th>
                        <th><a href="{{ $sortLink('number') }}">شماره{{ $arrow('number') }}</a></th>
                        <th><a href="{{ $sortLink('placed_at') }}">تاریخ{{ $arrow('placed_at') }}</a></th>
                        <th>مشتری</th>
                        <th>وضعیت</th>
                        <th>پرداخت</th>
                        <th><a href="{{ $sortLink('grand_total') }}">مبلغ{{ $arrow('grand_total') }}</a></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>
                            <label class="visually-hidden" for="vp-o-{{ $order->id }}">انتخاب سفارش {{ $order->number }}</label>
                            <input id="vp-o-{{ $order->id }}" type="checkbox" name="orders[]" value="{{ $order->id }}" data-adm-row>
                        </td>
                        <td><a href="{{ route('admin.order', $order) }}">{{ $order->number }}</a></td>
                        <td>{{ $order->placed_at ? fa_date($order->placed_at) : 'ثبت نشده' }}</td>
                        <td>{{ $order->contact_name }}<br><small><bdi dir="ltr">{{ $order->contact_phone }}</bdi></small></td>
                        <td><span class="vp-adm-badge is-{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                        <td>
                            <span class="vp-adm-badge is-{{ $order->paymentTone() }}">
                                {{ $order->paymentLabel() }}
                            </span>
                        </td>
                        <td>{{ toman($order->grand_total) }}</td>
                        <td><a href="{{ route('admin.order', $order) }}">دیدن</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </form>

        <div class="vp-adm-pager">{{ $orders->links() }}</div>
    @endif
</section>
@endsection
