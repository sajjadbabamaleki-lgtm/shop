@extends('admin.print.sheet')

@section('title', 'فاکتور '.$order->number)

{{-- A5, black and white, «قاب» at 40% black — see print-sheet.css under
     «the invoice». --}}
@section('sheet_class', 'is-invoice')

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
<article class="vp-inv">
    {{-- The masthead: the shop's mark and name on the ink band, the sheet's
         own name and number opposite. --}}
    <header class="vp-inv-band">
        <div class="vp-inv-brand">
            <img class="vp-inv-logo" src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="">
            <div>
                <p class="vp-inv-shop">{{ $sender['name'] }}</p>
                <p class="vp-inv-strap">فروشگاه کیف و کفش زنانه</p>
            </div>
        </div>
        <div class="vp-inv-title">
            <h1>فاکتور فروش</h1>
            <p><bdi dir="ltr">{{ $order->number }}</bdi></p>
        </div>
    </header>
    <div class="vp-inv-rule" aria-hidden="true"></div>

    <div class="vp-inv-chips">
        <div><span>تاریخ ثبت</span><b>{{ $order->placed_at ? fa_date($order->placed_at, true) : '—' }}</b></div>
        @if ($order->paid_at)
            <div><span>تاریخ پرداخت</span><b>{{ fa_date($order->paid_at, true) }}</b></div>
        @endif
        <div><span>وضعیت</span><b>{{ $order->statusLabel() }}</b></div>
        <div><span>نوع خرید</span><b class="vp-inv-kind">{{ $instalment ? 'اقساطی' : 'نقدی' }}</b></div>
    </div>

    <div class="vp-inv-parties">
        <section class="vp-inv-party">
            <h3>فروشنده</h3>
            <p class="vp-inv-who">{{ $sender['name'] }}</p>
            <p>{{ $sender['address'] }}</p>
            <p>تلفن: <bdi dir="ltr">{{ $sender['phone'] }}</bdi></p>
        </section>
        <section class="vp-inv-party">
            <h3>خریدار</h3>
            <p class="vp-inv-who">{{ $order->contact_name }}</p>
            <p>{{ collect([$order->province, $order->city, $order->address])->filter()->implode('، ') }}</p>
            <p>
                تلفن: <bdi dir="ltr">{{ $order->contact_phone }}</bdi>
                @if ($order->postal_code)
                    <span class="vp-inv-dot">·</span> کد پستی: <bdi dir="ltr">{{ $order->postal_code }}</bdi>
                @endif
            </p>
        </section>
    </div>

    <div class="vp-inv-scroll">
    <table class="vp-inv-lines">
        <thead>
            <tr><th class="vp-inv-n">#</th><th>کالا</th><th>سایز</th><th>تعداد</th><th class="vp-inv-num">قیمت واحد (تومان)</th><th class="vp-inv-num">جمع (تومان)</th></tr>
        </thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td class="vp-inv-n">{{ fa_number($loop->iteration) }}</td>
                <td>
                    <div class="vp-inv-item">
                        @if ($photo = $item->photoPath())
                            <img class="vp-inv-shot" src="{{ asset($photo) }}" alt="">
                        @endif
                        <div>
                            <p class="vp-inv-item-name">{{ $item->product_title }}</p>
                            <p class="vp-inv-item-sub"><bdi dir="ltr">{{ $item->sku }}</bdi></p>
                            @if ($item->compare_at_price && $item->compare_at_price > $item->unit_price)
                                <p class="vp-inv-item-sub">قیمت پیش از حراج: {{ toman($item->compare_at_price) }} تومان</p>
                            @endif
                        </div>
                    </div>
                </td>
                <td>{{ $item->size_value }}</td>
                <td>
                    {{ fa_number($item->quantity) }}
                    @if ($item->returned_quantity > 0)
                        <p class="vp-inv-item-sub">{{ fa_number($item->returned_quantity) }} مرجوع شد</p>
                    @endif
                </td>
                <td class="vp-inv-num">{{ toman($item->unit_price) }}</td>
                <td class="vp-inv-num"><b>{{ toman($item->line_total) }}</b></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>

    <div class="vp-inv-money">
        {{-- What happened to the money, beside what was owed. --}}
        <section class="vp-inv-card vp-inv-pay">
            <h3>پرداخت</h3>
            <dl class="vp-inv-facts">
                <div><dt>وضعیت پرداخت</dt><dd>{{ $order->paymentLabel() }}</dd></div>
                @if ($receipt)
                    <div><dt>روش</dt><dd>{{ $receipt->gatewayLabel() }}</dd></div>
                    <div><dt>مبلغ پرداخت‌شده</dt><dd>{{ toman($receipt->amount) }} تومان</dd></div>
                    @if ($receipt->ref_id)
                        <div><dt>شماره پیگیری</dt><dd><bdi dir="ltr">{{ $receipt->ref_id }}</bdi></dd></div>
                    @endif
                    @if ($receipt->authority)
                        <div><dt>شمارهٔ تراکنش</dt><dd><bdi dir="ltr">{{ $receipt->authority }}</bdi></dd></div>
                    @endif
                    @if ($receipt->card_pan)
                        <div><dt>کارت</dt><dd><bdi dir="ltr">{{ $receipt->card_pan }}</bdi></dd></div>
                    @endif
                    @if ($receipt->paid_at)
                        <div><dt>زمان پرداخت</dt><dd>{{ fa_date($receipt->paid_at, true) }}</dd></div>
                    @endif
                @elseif ($order->payment_method)
                    <div><dt>روش</dt><dd>{{ $order->methodLabel() }}</dd></div>
                @endif
            </dl>
        </section>

        {{-- The column under the sums carries the shop's stamp — «مهر
             فروشگاه … بیاری زیر اون قسمتی که سمت چپ خالی مونده» — and
             stretches with the payment card beside it, so the two end on
             the same line. --}}
        <div class="vp-inv-side">
            <section class="vp-inv-card vp-inv-sums">
                <div class="vp-inv-sum"><span>جمع کالاها</span><span>{{ toman($order->subtotal) }} تومان</span></div>
                @if ($order->discount_total > 0)
                    <div class="vp-inv-sum"><span>تخفیف{{ $code ? ' (کد '.$code.')' : '' }}</span><span>− {{ toman($order->discount_total) }} تومان</span></div>
                @endif
                <div class="vp-inv-sum"><span>ارسال{{ $order->shippingMethod ? ' — '.$order->shippingMethod->name : '' }}</span><span>{{ $order->shippingLabel() }}</span></div>
                <div class="vp-inv-total"><span>مبلغ کل</span><span>{{ toman($order->grand_total) }} <small>تومان</small></span></div>
            </section>

            <section class="vp-inv-card vp-inv-seal">
                <h3>مهر فروشگاه</h3>
                @include('admin.print.stamp')
            </section>
        </div>
    </div>

    @if ($instalment)
        <section class="vp-inv-card vp-inv-instal">
            <h3>اقساط</h3>

            @if ($plan === null)
                <p class="vp-inv-warn">
                    برنامهٔ اقساط این خرید هنوز در پنل ثبت نشده. پیش‌پرداخت و تاریخ سررسید هر قسط را از صفحهٔ
                    سفارش («برنامهٔ اقساط») وارد کن تا اینجا چاپ شود.
                </p>
            @else
                <dl class="vp-inv-facts is-four">
                    <div><dt>پیش‌پرداخت</dt><dd>{{ toman($paidUpFront) }} تومان</dd></div>
                    <div><dt>تعداد اقساط</dt><dd>{{ fa_number(count($plan['instalments'])) }}</dd></div>
                    <div><dt>جمع اقساط</dt><dd>{{ toman($scheduled) }} تومان</dd></div>
                    <div><dt>پیش‌پرداخت + اقساط</dt><dd>{{ toman($paidUpFront + $scheduled) }} تومان</dd></div>
                </dl>

                @if ($plan['instalments'] !== [])
                    <table class="vp-inv-lines vp-inv-plan">
                        <thead><tr><th class="vp-inv-n">قسط</th><th>تاریخ سررسید</th><th class="vp-inv-num">مبلغ (تومان)</th></tr></thead>
                        <tbody>
                        @foreach ($plan['instalments'] as $row)
                            <tr>
                                <td class="vp-inv-n">{{ fa_number($loop->iteration) }}</td>
                                <td>{{ fa_date(\Carbon\CarbonImmutable::parse($row['due'])) }}</td>
                                <td class="vp-inv-num">{{ toman($row['amount']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </section>
    @endif

    @if ($order->note)
        <section class="vp-inv-card">
            <h3>یادداشت مشتری</h3>
            <p>{{ $order->note }}</p>
        </section>
    @endif

    <footer class="vp-inv-foot">
        <p class="vp-inv-thanks">از خرید شما سپاسگزاریم</p>
        <p>vikyplus.ir <span class="vp-inv-dot">·</span> <bdi dir="ltr">{{ $sender['phone'] }}</bdi></p>
    </footer>
</article>
@endsection
