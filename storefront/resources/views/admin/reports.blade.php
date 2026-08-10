@extends('layouts.admin')

{{--
    One shop's numbers, counted from paid orders only. A placed order is a
    hope; counting it as revenue is how a shop believes it had a good month.

    The bars are drawn with width percentages rather than with a charting
    library: nothing here needs interaction, and a chart that arrives as 300KB
    of JavaScript to draw thirty rectangles is not a trade worth making.
--}}

@section('content')
<h1 class="vp-shop-title vp-admin-title">گزارش {{ $branch->name }}</h1>

<div class="vp-admin-figures">
    <div class="vp-admin-figure"><span class="vp-admin-figure-num">{{ fa_number($headline['orders']) }}</span><span class="vp-admin-figure-name">سفارش پرداخت‌شده</span></div>
    <div class="vp-admin-figure"><span class="vp-admin-figure-num">{{ toman($headline['revenue']) }}</span><span class="vp-admin-figure-name">فروش (تومان)</span></div>
    <div class="vp-admin-figure"><span class="vp-admin-figure-num">{{ toman($headline['average']) }}</span><span class="vp-admin-figure-name">میانگین هر سفارش</span></div>
    <div class="vp-admin-figure"><span class="vp-admin-figure-num">{{ toman($headline['discounts']) }}</span><span class="vp-admin-figure-name">تخفیف داده‌شده</span></div>
</div>

<section class="vp-shop-panel">
    <h2 class="vp-shop-title">۳۰ روز گذشته</h2>

    @php
        $best_day = (int) $daily->max('revenue');
        // The divisor, not the figure: a day with nothing sold would otherwise
        // divide by zero, and 1 Rial is not a number to print at anybody.
        $peak = max(1, $best_day);
    @endphp

    <div class="vp-bars">
        @foreach ($daily as $day)
            <div class="vp-bar" title="{{ fa_date($day['date']) }} — {{ toman($day['revenue']) }}">
                <span class="vp-bar-fill" style="height: {{ max(2, (int) round($day['revenue'] / $peak * 100)) }}%"></span>
            </div>
        @endforeach
    </div>

    <p class="vp-shop-count">بیشترین روز: {{ toman($best_day) }} تومان</p>
</section>

<div class="vp-admin-cols">
    <section class="vp-shop-panel">
        <h2 class="vp-shop-title">پرفروش‌ترین‌ها</h2>

        @if ($best->isEmpty())
            <p class="vp-shop-empty">هنوز فروشی ثبت نشده.</p>
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

    <section class="vp-shop-panel">
        <h2 class="vp-shop-title">انبار</h2>
        <div class="vp-cart-row"><span>کل موجودی</span><span>{{ fa_number($stock['units']) }} عدد</span></div>
        <div class="vp-cart-row"><span>رزروشده برای سفارش‌ها</span><span>{{ fa_number($stock['reserved']) }} عدد</span></div>
        <div class="vp-cart-row"><span>ردیف‌های دارای موجودی</span><span>{{ fa_number($stock['lines']) }}</span></div>
        <div class="vp-cart-row"><span>سفارش لغوشده</span><span>{{ fa_number($headline['cancelled']) }}</span></div>
    </section>
</div>
@endsection
