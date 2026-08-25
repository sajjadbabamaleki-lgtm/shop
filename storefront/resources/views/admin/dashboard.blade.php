@extends('layouts.admin')

@section('title', 'خانه')

{{--
    §3 of the specification: six figures each against the previous equivalent
    period, a date control over them, and the modules below.

    **The order of the modules is the design.** §3 ends «Desktop should use a
    modular multi-column grid. Mobile should prioritize actionable cards before
    analytics», so the two things somebody can act on — orders waiting and
    shelves running out — are written first in the markup and the analytics
    follow. The desktop grid then places them side by side; the phone gets the
    document order, which is already the right one. Nothing reorders with CSS,
    because a `order:` that only exists at one width is a second opinion about
    what matters.

    Two of §3's figures are not shown as numbers and the reasons are on the
    screen rather than only in the controller: profit needs a cost the order
    line never snapshotted, and sales by seller needs a vendor the order line
    does not carry.
--}}

@php
    // A figure and what it was. `null` where there is nothing to compare
    // against — a period with no sales is not «down 100%», it is a period with
    // nothing in it, and printing a percentage there invents a trend.
    $change = function (int $now, int $then): ?array {
        if ($then === 0) {
            return null;
        }

        $delta = (int) round((($now - $then) / $then) * 100);

        return ['delta' => $delta, 'way' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat')];
    };

    // The model already names these. A second map here would be a second
    // source of truth for the same five words, and the two would drift the
    // first time somebody reworded one of them — which is exactly what had
    // happened: this file said «در انتظار» where the order page said «ثبت شد».
    $statusNames = \App\Models\Order::statusLabels();

    $peak = collect($chart)->max('total') ?: 1;
@endphp

@section('content')

{{-- §3's date control. Links rather than a form: a window is a place you can
     be, so it belongs in the URL and in the back button — the same reason the
     shop's own sort tabs are links. --}}
<div class="vp-adm-range" role="group" aria-label="بازه گزارش">
    @foreach (\App\Support\Admin\DateRange::PRESETS as $key => $days)
        <a class="vp-adm-range-tab{{ $range->preset === $key ? ' is-on' : '' }}"
           href="{{ route('admin.dashboard', ['range' => $key]) }}">{{ \App\Support\Admin\DateRange::LABELS[$key] }}</a>
    @endforeach

    <form class="vp-adm-range-custom" method="get" action="{{ route('admin.dashboard') }}">
        <input type="hidden" name="range" value="custom">
        <label class="visually-hidden" for="vp-from">از تاریخ</label>
        <input id="vp-from" type="date" name="from" value="{{ $range->from->toDateString() }}">
        <label class="visually-hidden" for="vp-to">تا تاریخ</label>
        <input id="vp-to" type="date" name="to" value="{{ $range->to->toDateString() }}">
        <button type="submit">اعمال</button>
    </form>
</div>

{{-- --- the six figures, §3 --------------------------------------------- --}}
<div class="vp-adm-kpis">
    @php
        $cards = [
            ['name' => 'فروش', 'value' => toman($figures['sales']), 'unit' => 'تومان', 'c' => $change($figures['sales'], $before['sales'])],
            ['name' => 'سفارش', 'value' => fa_number($figures['orders']), 'unit' => '', 'c' => $change($figures['orders'], $before['orders'])],
            ['name' => 'میانگین سبد', 'value' => toman($figures['aov']), 'unit' => 'تومان', 'c' => $change($figures['aov'], $before['aov'])],
            ['name' => 'مشتری تازه', 'value' => fa_number($figures['customers']), 'unit' => '', 'c' => $change($figures['customers'], $before['customers'])],
            ['name' => 'لغوشده', 'value' => fa_number($figures['cancelled']), 'unit' => '', 'c' => $change($figures['cancelled'], $before['cancelled'])],
        ];
    @endphp

    @foreach ($cards as $card)
        <div class="vp-adm-kpi">
            <span class="vp-adm-kpi-name">{{ $card['name'] }}</span>
            <span class="vp-adm-kpi-num">{{ $card['value'] }}@if ($card['unit'])<small>{{ $card['unit'] }}</small>@endif</span>
            @if ($card['c'])
                <span class="vp-adm-kpi-move is-{{ $card['c']['way'] }}">
                    {{ $card['c']['delta'] > 0 ? '+' : '' }}{{ fa_number($card['c']['delta']) }}٪
                    <small>نسبت به دوره قبل</small>
                </span>
            @else
                <span class="vp-adm-kpi-move is-none">دوره قبل چیزی نداشت</span>
            @endif
        </div>
    @endforeach

    {{-- §3 asks for estimated profit. What is honest is the margin over the
         lines that carry a cost, with the share of the sale it covers. --}}
    <div class="vp-adm-kpi">
        <span class="vp-adm-kpi-name">سود ناخالص</span>
        @if ($margin['covered'] > 0)
            <span class="vp-adm-kpi-num">{{ toman($margin['margin']) }}<small>تومان</small></span>
            <span class="vp-adm-kpi-move is-none">
                روی {{ fa_number((int) round($margin['covered'] / max($margin['total'], 1) * 100)) }}٪ فروش
                <small>بقیه بهای تمام‌شده ندارند</small>
            </span>
        @else
            <span class="vp-adm-kpi-num is-quiet">نامشخص</span>
            {{-- One line, like every other card's, and on its own: the
                 sentence that used to explain it — «تا وقتی در «قیمت‌ها» وارد
                 نشود…» — first made this tile taller than the five beside it,
                 then sat under the grid, and the client wanted it gone
                 outright. The tile says what is true; «قیمت‌ها» is one tap
                 away in the menu. --}}
            <span class="vp-adm-kpi-move is-none">بهای تمام‌شده ندارد</span>
        @endif
    </div>
</div>


{{-- --- what can be acted on, first (§19) -------------------------------- --}}
<div class="vp-adm-grid">

    <section class="vp-adm-card vp-adm-span-2">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">نیازمند اقدام</h2>
            <a class="vp-adm-card-more" href="{{ route('admin.orders') }}">همه سفارش‌ها</a>
        </div>

        @if ($needsAction->isEmpty())
            <p class="vp-adm-empty">سفارشی منتظر نیست.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>شماره</th><th>مشتری</th><th>وضعیت</th><th>مبلغ</th><th></th></tr></thead>
                <tbody>
                @foreach ($needsAction as $order)
                    <tr>
                        <td>{{ $order->number }}</td>
                        <td>{{ $order->contact_name }}</td>
                        <td><span class="vp-adm-badge is-{{ $order->status }}">{{ $statusNames[$order->status] ?? $order->status }}</span></td>
                        <td>{{ toman($order->grand_total) }}</td>
                        <td><a href="{{ route('admin.order', $order) }}">دیدن</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">رو به اتمام</h2>
            <a class="vp-adm-card-more" href="{{ route('admin.inventory') }}">موجودی</a>
        </div>

        @if ($low->isEmpty())
            <p class="vp-adm-empty">چیزی زیر حد هشدار نیست.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>کالا</th><th>سایز</th><th>مانده</th></tr></thead>
                <tbody>
                @foreach ($low as $row)
                    <tr>
                        <td>{{ $row->variant?->product?->title }}</td>
                        <td>{{ fa_number((int) $row->variant?->size_value) }}</td>
                        <td><span class="vp-adm-badge is-low">{{ fa_number($row->sellable_stock) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">پرداخت‌نشده</h2>
        </div>
        <p class="vp-adm-big">{{ toman($unpaidValue) }} <small>تومان</small></p>
        <p class="vp-adm-empty">مجموع سفارش‌های لغونشده‌ای که هنوز پرداخت نشده‌اند.</p>
    </section>

    {{-- --- and then the analytics --------------------------------------- --}}

    <section class="vp-adm-card vp-adm-span-3">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">فروش</h2>
            <span class="vp-adm-card-more">{{ $range->label() }}</span>
        </div>

        @if ($figures['sales'] === 0)
            <p class="vp-adm-empty">در این بازه فروشی ثبت نشده.</p>
        @else
            {{-- Bars, not a canvas: this is a count per day and the shape of it
                 is the whole message. A charting library would be another file
                 that can fail to arrive, for a picture that is fourteen divs. --}}
            <div class="vp-adm-bars" role="img"
                 aria-label="فروش روزانه در {{ $range->label() }}، بیشترین {{ toman($peak) }} تومان">
                @foreach ($chart as $point)
                    <span class="vp-adm-bar" style="--h: {{ max(2, (int) round($point['total'] / $peak * 100)) }}%"
                          title="{{ fa_date($point['day']) }}: {{ toman($point['total']) }} تومان"></span>
                @endforeach
            </div>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">سفارش‌ها به تفکیک وضعیت</h2>
        </div>
        <ul class="vp-adm-list">
            @foreach ($byStatus as $row)
                <li>
                    <span class="vp-adm-badge is-{{ $row['status'] }}">{{ $statusNames[$row['status']] ?? $row['status'] }}</span>
                    <b>{{ fa_number($row['count']) }}</b>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">پرفروش‌ترین‌ها</h2>
        </div>

        @if ($best === [])
            <p class="vp-adm-empty">در این بازه چیزی فروخته نشده.</p>
        @else
            <ul class="vp-adm-list">
                @foreach ($best as $row)
                    <li><span>{{ $row['title'] }}</span><b>{{ fa_number($row['quantity']) }}</b></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">تازه‌ترین سفارش‌ها</h2>
            <a class="vp-adm-card-more" href="{{ route('admin.orders') }}">همه</a>
        </div>

        @if ($recent->isEmpty())
            <p class="vp-adm-empty">هنوز سفارشی ثبت نشده.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>شماره</th><th>وضعیت</th><th>مبلغ</th></tr></thead>
                <tbody>
                @foreach ($recent as $order)
                    <tr>
                        <td><a href="{{ route('admin.order', $order) }}">{{ $order->number }}</a></td>
                        <td><span class="vp-adm-badge is-{{ $order->status }}">{{ $statusNames[$order->status] ?? $order->status }}</span></td>
                        <td>{{ toman($order->grand_total) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
