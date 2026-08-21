@extends('layouts.admin')

@section('title', 'گزارش‌ها')

{{--
    One shop's numbers, counted from paid orders only. A placed order is a
    hope; counting it as revenue is how a shop believes it had a good month.

    The bars are drawn with a custom property rather than with a charting
    library: nothing here needs interaction, and a chart that arrives as 300KB
    of JavaScript to draw thirty rectangles is not a trade worth making. They
    are the dashboard's own bars — same class, same material — because two
    charts in one panel drawn two different ways is the thing §1 is about.
--}}

@php
    $best_day = (int) $daily->max('revenue');
    // The divisor, not the figure: a day with nothing sold would otherwise
    // divide by zero, and 1 Rial is not a number to print at anybody.
    $peak = max(1, $best_day);
@endphp

@section('content')

<div class="vp-adm-kpis">
    <div class="vp-adm-kpi">
        <span class="vp-adm-kpi-name">سفارش پرداخت‌شده</span>
        <span class="vp-adm-kpi-num">{{ fa_number($headline['orders']) }}</span>
    </div>
    <div class="vp-adm-kpi">
        <span class="vp-adm-kpi-name">فروش</span>
        <span class="vp-adm-kpi-num">{{ toman($headline['revenue']) }}<small>تومان</small></span>
    </div>
    <div class="vp-adm-kpi">
        <span class="vp-adm-kpi-name">میانگین هر سفارش</span>
        <span class="vp-adm-kpi-num">{{ toman($headline['average']) }}<small>تومان</small></span>
    </div>
    <div class="vp-adm-kpi">
        <span class="vp-adm-kpi-name">تخفیف داده‌شده</span>
        <span class="vp-adm-kpi-num">{{ toman($headline['discounts']) }}<small>تومان</small></span>
    </div>
</div>

<div class="vp-adm-grid">

    <section class="vp-adm-card vp-adm-span-3">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">۳۰ روز گذشته</h2>
            <span class="vp-adm-card-more">بیشترین روز: {{ toman($best_day) }} تومان</span>
        </div>

        <div class="vp-adm-bars" role="img"
             aria-label="فروش روزانه در ۳۰ روز گذشته، بیشترین {{ toman($best_day) }} تومان">
            @foreach ($daily as $day)
                <span class="vp-adm-bar" style="--h: {{ max(2, (int) round($day['revenue'] / $peak * 100)) }}%"
                      title="{{ fa_date($day['date']) }} — {{ toman($day['revenue']) }} تومان، {{ fa_number($day['orders']) }} سفارش"></span>
            @endforeach
        </div>
    </section>

    <section class="vp-adm-card vp-adm-span-2">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">پرفروش‌ترین‌ها</h2>
        </div>

        @if ($best->isEmpty())
            <p class="vp-adm-empty">هنوز فروشی ثبت نشده.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>کالا</th><th>تعداد</th><th>فروش (تومان)</th></tr></thead>
                <tbody>
                @foreach ($best as $row)
                    <tr>
                        <td>{{ $row->product_title }}</td>
                        <td>{{ fa_number((int) $row->units) }}</td>
                        <td>{{ toman((int) $row->revenue) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">انبار</h2>
        </div>

        <ul class="vp-adm-list">
            <li><span>کل موجودی</span><b>{{ fa_number($stock['units']) }} عدد</b></li>
            <li><span>رزروشده برای سفارش‌ها</span><b>{{ fa_number($stock['reserved']) }} عدد</b></li>
            <li><span>ردیف‌های دارای موجودی</span><b>{{ fa_number($stock['lines']) }}</b></li>
            <li><span>سفارش لغوشده</span><b>{{ fa_number($headline['cancelled']) }}</b></li>
        </ul>
    </section>
</div>
@endsection
