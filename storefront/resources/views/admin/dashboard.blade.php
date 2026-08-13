@extends('layouts.admin')

{{--
    The first screen. Four figures and two short lists — what somebody opening
    the panel in the morning actually needs before they decide what to do.
--}}

@section('content')
<h1 class="vp-shop-title vp-admin-title">{{ $branch->name }}</h1>

<div class="vp-admin-figures">
    <div class="vp-admin-figure">
        <span class="vp-admin-figure-num">{{ fa_number($placed) }}</span>
        <span class="vp-admin-figure-name">سفارش در انتظار</span>
    </div>
    <div class="vp-admin-figure">
        <span class="vp-admin-figure-num">{{ fa_number($today) }}</span>
        <span class="vp-admin-figure-name">سفارش امروز</span>
    </div>
    <div class="vp-admin-figure">
        <span class="vp-admin-figure-num">{{ toman($unpaidValue) }}</span>
        <span class="vp-admin-figure-name">پرداخت‌نشده (تومان)</span>
    </div>
    <div class="vp-admin-figure">
        <span class="vp-admin-figure-num">{{ toman($soldThisMonth) }}</span>
        <span class="vp-admin-figure-name">فروش این ماه (تومان)</span>
    </div>
</div>

<div class="vp-admin-cols">
    <section class="vp-shop-panel">
        <h2 class="vp-shop-title">تازه‌ترین سفارش‌ها</h2>

        @if ($recent->isEmpty())
            <p class="vp-shop-empty">هنوز سفارشی ثبت نشده.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>شماره</th><th>تاریخ</th><th>وضعیت</th><th>مبلغ</th></tr></thead>
                <tbody>
                @foreach ($recent as $order)
                    <tr>
                        <td><a href="{{ route('admin.order', $order) }}">{{ $order->number }}</a></td>
                        <td>{{ fa_date($order->placed_at) }}</td>
                        <td>{{ $order->statusLabel() }}</td>
                        <td>{{ toman($order->grand_total) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="vp-shop-panel">
        <h2 class="vp-shop-title">رو به اتمام</h2>

        @if ($low->isEmpty())
            <p class="vp-shop-empty">چیزی زیر حد هشدار نیست.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>کالا</th><th>سایز</th><th>موجود</th></tr></thead>
                <tbody>
                @foreach ($low as $row)
                    <tr>
                        <td>{{ $row->variant?->product?->title }}</td>
                        <td>{{ fa_number((int) $row->variant?->size_value) }}</td>
                        <td>{{ fa_number($row->sellable_stock) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
