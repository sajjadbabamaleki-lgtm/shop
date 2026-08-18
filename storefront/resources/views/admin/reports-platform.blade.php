@extends('layouts.admin')

@section('title', 'گزارش پلتفرم')

{{--
    The one report allowed to look across every branch, which is why it needs
    platform authority to reach: comparing shops is exactly what one shop must
    not be able to do.
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
        <span class="vp-adm-kpi-name">فروش کل</span>
        <span class="vp-adm-kpi-num">{{ toman($headline['revenue']) }}<small>تومان</small></span>
    </div>
    <div class="vp-adm-kpi">
        <span class="vp-adm-kpi-name">شعبه</span>
        <span class="vp-adm-kpi-num">{{ fa_number($headline['branches']) }}</span>
    </div>
    <div class="vp-adm-kpi">
        <span class="vp-adm-kpi-name">فروشنده تأییدشده</span>
        <span class="vp-adm-kpi-num">{{ fa_number($headline['vendors']) }}</span>
    </div>
</div>

<div class="vp-adm-grid">

    <section class="vp-adm-card vp-adm-span-3">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">۳۰ روز گذشته</h2>
            <span class="vp-adm-card-more">بیشترین روز: {{ toman($best_day) }} تومان</span>
        </div>

        <div class="vp-adm-bars" role="img"
             aria-label="فروش روزانه پلتفرم در ۳۰ روز گذشته، بیشترین {{ toman($best_day) }} تومان">
            @foreach ($daily as $day)
                <span class="vp-adm-bar" style="--h: {{ max(2, (int) round($day['revenue'] / $peak * 100)) }}%"
                      title="{{ fa_date($day['date']) }} — {{ toman($day['revenue']) }} تومان، {{ fa_number($day['orders']) }} سفارش"></span>
            @endforeach
        </div>
    </section>

    <section class="vp-adm-card vp-adm-span-2">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">به تفکیک شعبه</h2>
        </div>

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

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">بدهی به فروشندگان</h2>
        </div>

        <ul class="vp-adm-list">
            <li><span>بدهی کل</span><b>{{ toman($owed) }} تومان</b></li>
            <li><span>در صف تسویه</span><b>{{ toman($awaiting) }} تومان</b></li>
        </ul>
    </section>

    <section class="vp-adm-card vp-adm-span-3">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">به تفکیک فروشنده</h2>
        </div>

        @if ($byVendor->isEmpty())
            <p class="vp-adm-empty">فروشنده‌ای ثبت نشده.</p>
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
    </section>
</div>
@endsection
