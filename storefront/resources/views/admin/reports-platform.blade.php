@extends('layouts.admin')

{{--
    The one report allowed to look across every branch, which is why it needs
    platform authority to reach: comparing shops is exactly what one shop must
    not be able to do.
--}}

@section('content')
<h1 class="vp-shop-title vp-admin-title">گزارش پلتفرم</h1>

<div class="vp-admin-figures">
    <div class="vp-admin-figure"><span class="vp-admin-figure-num">{{ fa_number($headline['orders']) }}</span><span class="vp-admin-figure-name">سفارش پرداخت‌شده</span></div>
    <div class="vp-admin-figure"><span class="vp-admin-figure-num">{{ toman($headline['revenue']) }}</span><span class="vp-admin-figure-name">فروش کل (تومان)</span></div>
    <div class="vp-admin-figure"><span class="vp-admin-figure-num">{{ fa_number($headline['branches']) }}</span><span class="vp-admin-figure-name">شعبه</span></div>
    <div class="vp-admin-figure"><span class="vp-admin-figure-num">{{ fa_number($headline['vendors']) }}</span><span class="vp-admin-figure-name">فروشنده تأییدشده</span></div>
</div>

<section class="vp-shop-panel">
    <h2 class="vp-shop-title">۳۰ روز گذشته</h2>

    @php
        $best_day = (int) $daily->max('revenue');
        // The divisor, not the figure: a day with nothing sold would otherwise
        // divide by zero, and 1 Rial is not a number to print at anybody.
        $peak = max(1, $best_day);
    @endphp

    <div class="vp-chart">
        {{-- The ceiling, drawn once. Thirty bars with no line above them are
             thirty heights nobody can put a number to. --}}
        <span class="vp-chart-top">{{ toman($best_day) }}</span>

        <div class="vp-bars">
            @foreach ($daily as $day)
                <div class="vp-bar" title="{{ fa_date($day['date']) }} — {{ toman($day['revenue']) }} تومان، {{ fa_number($day['orders']) }} سفارش">
                    <span class="vp-bar-fill" style="height: {{ max(2, (int) round($day['revenue'] / $peak * 100)) }}%"></span>
                </div>
            @endforeach
        </div>

        {{-- A date under every fifth bar. Thirty labels would be unreadable and
             none at all makes the row a shape rather than a month. --}}
        <div class="vp-chart-days">
            @foreach ($daily as $day)
                <span class="vp-chart-day">{{ $loop->index % 5 === 0 ? fa_date($day['date']) : '' }}</span>
            @endforeach
        </div>
    </div>
</section>

<div class="vp-admin-cols">
    <section class="vp-shop-panel">
        <h2 class="vp-shop-title">به تفکیک شعبه</h2>
        <table class="vp-admin-table">
            <thead><tr><th>شعبه</th><th>سفارش</th><th>فروش (تومان)</th></tr></thead>
            <tbody>
            @foreach ($byBranch as $row)
                <tr>
                    <td>{{ $row['branch']->name }}</td>
                    <td>{{ fa_number($row['orders']) }}</td>
                    <td>{{ toman($row['revenue']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="vp-shop-panel">
        <h2 class="vp-shop-title">به تفکیک فروشنده</h2>

        @if ($byVendor->isEmpty())
            <p class="vp-shop-empty">فروشنده‌ای ثبت نشده.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>فروشنده</th><th>فروش</th><th>کارمزد ما</th><th>مانده</th></tr></thead>
                <tbody>
                @foreach ($byVendor as $row)
                    <tr>
                        <td>{{ $row['vendor']->name }}</td>
                        <td>{{ toman($row['sales']) }}</td>
                        <td>{{ toman($row['commission']) }}</td>
                        <td>{{ toman($row['balance']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <div class="vp-cart-row is-total"><span>بدهی کل به فروشندگان</span><span>{{ toman($owed) }} تومان</span></div>
        <div class="vp-cart-row"><span>در صف تسویه</span><span>{{ toman($awaiting) }} تومان</span></div>
    </section>
</div>
@endsection
