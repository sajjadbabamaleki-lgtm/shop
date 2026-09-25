@extends('admin.print.sheet')

@section('title', 'فاکتور '.$order->number)

{{--
    The invoice the shop prints for itself — «ک کالا چی بوده، مبلغ چقد بوده،
    قسطی یا نقدی بوده، اگ قسطیه تاریخ سر رسید قسطاش چ زمانیه و پیش پرداخت چقد
    داده … خلاصه هر اطلاعات مالی ک برای خرید هست».

    Every figure is read off the order as it was written when it was placed —
    `unit_price`, `line_total`, `discount_total`, `shipping_total`,
    `grand_total` — never recomputed from today's prices, which is the same
    rule the order screen keeps. The instalment plan is printed exactly as it
    was typed on the order screen; nothing here invents a date or a figure the
    lender did not give, and a plan nobody has written yet is said to be
    missing rather than left blank.
--}}

@php
    $paidUpFront = $plan['down_payment'] ?? 0;
    $scheduled = collect($plan['instalments'] ?? [])->sum('amount');
@endphp

@section('content')
<header class="vp-inv-head">
    <div>
        <h1>فاکتور فروش</h1>
        <p class="vp-soft">{{ $sender['name'] }}</p>
    </div>

    <div class="vp-inv-meta">
        <span class="vp-soft">شماره سفارش</span><b><bdi dir="ltr">{{ $order->number }}</bdi></b>
        <span class="vp-soft">تاریخ ثبت</span><b>{{ $order->placed_at ? fa_date($order->placed_at, true) : '—' }}</b>
        @if ($order->paid_at)
            <span class="vp-soft">تاریخ پرداخت</span><b>{{ fa_date($order->paid_at, true) }}</b>
        @endif
        <span class="vp-soft">وضعیت</span><b>{{ $order->statusLabel() }}</b>
    </div>
</header>

<div class="vp-inv-parties">
    <div class="vp-inv-party">
        <h3>فروشنده</h3>
        <p><b>{{ $sender['name'] }}</b></p>
        <p>{{ $sender['address'] }}</p>
        <p>تلفن: <bdi dir="ltr">{{ $sender['phone'] }}</bdi></p>
    </div>
    <div class="vp-inv-party">
        <h3>خریدار</h3>
        <p><b>{{ $order->contact_name }}</b> — <bdi dir="ltr">{{ $order->contact_phone }}</bdi></p>
        <p>{{ collect([$order->province, $order->city, $order->address])->filter()->implode('، ') }}</p>
        @if ($order->postal_code)
            <p>کد پستی: <bdi dir="ltr">{{ $order->postal_code }}</bdi></p>
        @endif
    </div>
</div>

<h2>کالاها</h2>
<div class="vp-inv-scroll">
<table>
    <thead>
        <tr><th></th><th>کالا</th><th>کد</th><th>سایز</th><th>تعداد</th><th class="vp-inv-num">قیمت واحد</th><th class="vp-inv-num">جمع</th></tr>
    </thead>
    <tbody>
    @foreach ($order->items as $item)
        <tr>
            <td>
                @if ($photo = $item->photoPath())
                    <img class="vp-inv-shot" src="{{ asset($photo) }}" alt="">
                @endif
            </td>
            <td>
                {{ $item->product_title }}
                @if ($item->compare_at_price && $item->compare_at_price > $item->unit_price)
                    <br><small>قیمت پیش از حراج: {{ toman($item->compare_at_price) }} تومان</small>
                @endif
            </td>
            <td><bdi dir="ltr">{{ $item->sku }}</bdi></td>
            <td>{{ $item->size_value }}</td>
            <td>
                {{ fa_number($item->quantity) }}
                @if ($item->returned_quantity > 0)
                    <br><small>{{ fa_number($item->returned_quantity) }} مرجوع شد</small>
                @endif
            </td>
            <td class="vp-inv-num">{{ toman($item->unit_price) }}</td>
            <td class="vp-inv-num">{{ toman($item->line_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

<table class="vp-inv-sums">
    <tr><td>جمع کالاها</td><td class="vp-inv-num">{{ toman($order->subtotal) }} تومان</td></tr>
    @if ($order->discount_total > 0)
        <tr>
            <td>تخفیف{{ $code ? ' (کد '.$code.')' : '' }}</td>
            <td class="vp-inv-num">− {{ toman($order->discount_total) }} تومان</td>
        </tr>
    @endif
    <tr>
        <td>ارسال{{ $order->shippingMethod ? ' — '.$order->shippingMethod->name : '' }}</td>
        <td class="vp-inv-num">{{ $order->shippingLabel() }}</td>
    </tr>
    <tr class="is-total"><td>مبلغ کل</td><td class="vp-inv-num">{{ toman($order->grand_total) }} تومان</td></tr>
</table>

<h2>پرداخت</h2>
<div class="vp-inv-facts">
    <div><span>نوع خرید</span><span class="vp-inv-kind">{{ $instalment ? 'اقساطی' : 'نقدی' }}</span></div>
    <div><span>وضعیت پرداخت</span><b>{{ $order->paymentLabel() }}</b></div>
    @if ($receipt)
        <div><span>روش</span><b>{{ $receipt->gatewayLabel() }}</b></div>
        <div><span>مبلغ پرداخت‌شده</span><b>{{ toman($receipt->amount) }} تومان</b></div>
        @if ($receipt->ref_id)
            <div><span>شماره پیگیری</span><b><bdi dir="ltr">{{ $receipt->ref_id }}</bdi></b></div>
        @endif
        @if ($receipt->authority)
            <div><span>شمارهٔ تراکنش</span><b><bdi dir="ltr">{{ $receipt->authority }}</bdi></b></div>
        @endif
        @if ($receipt->card_pan)
            <div><span>کارت</span><b><bdi dir="ltr">{{ $receipt->card_pan }}</bdi></b></div>
        @endif
        @if ($receipt->paid_at)
            <div><span>زمان پرداخت</span><b>{{ fa_date($receipt->paid_at, true) }}</b></div>
        @endif
    @elseif ($order->payment_method)
        <div><span>روش</span><b>{{ $order->methodLabel() }}</b></div>
    @endif
</div>

@if ($instalment)
    <h2>اقساط</h2>

    @if ($plan === null)
        <p class="vp-inv-warn">
            برنامهٔ اقساط این خرید هنوز در پنل ثبت نشده. پیش‌پرداخت و تاریخ سررسید هر قسط را از صفحهٔ
            سفارش («برنامهٔ اقساط») وارد کن تا اینجا چاپ شود.
        </p>
    @else
        <div class="vp-inv-facts">
            <div><span>پیش‌پرداخت</span><b>{{ toman($paidUpFront) }} تومان</b></div>
            <div><span>تعداد اقساط</span><b>{{ fa_number(count($plan['instalments'])) }}</b></div>
            <div><span>جمع اقساط</span><b>{{ toman($scheduled) }} تومان</b></div>
            <div><span>پیش‌پرداخت + اقساط</span><b>{{ toman($paidUpFront + $scheduled) }} تومان</b></div>
        </div>

        @if ($plan['instalments'] !== [])
            <table class="vp-inv-plan">
                <thead><tr><th>قسط</th><th>تاریخ سررسید</th><th class="vp-inv-num">مبلغ</th></tr></thead>
                <tbody>
                @foreach ($plan['instalments'] as $row)
                    <tr>
                        <td>{{ fa_number($loop->iteration) }}</td>
                        <td>{{ fa_date(\Carbon\CarbonImmutable::parse($row['due'])) }}</td>
                        <td class="vp-inv-num">{{ toman($row['amount']) }} تومان</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif
@endif

@if ($order->note)
    <h2>یادداشت مشتری</h2>
    <p>{{ $order->note }}</p>
@endif

<div class="vp-inv-sign">
    <div>مهر و امضای فروشنده</div>
    <div>امضای خریدار</div>
</div>
@endsection
