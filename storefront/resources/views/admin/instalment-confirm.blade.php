@extends('layouts.admin')

@section('title', ($kind === 'cancel' ? 'لغو کامل' : 'تأیید مرجوعی').' — سفارش '.$order->number)

{{--
    Step two of the two SnappPay ask for: «حتما هنگام بروزرسانی تأیید دو
    مرحله‌ای در پنل ادمین داشته باشید».

    Nothing has been sent when this is drawn. The first post computed what the
    order would be worth and came here; only the button at the foot reaches
    their API, and after it there is no way back — which is why this screen's
    whole job is to print the numbers that are about to become true, rather
    than to ask «مطمئنی؟» a second time.

    Every class on it is one the panel already has. A new one would be a new
    word in CssSubsetTest's vocabulary and a rule that does not exist in
    tweaks.css, and this screen is not worth either.
--}}

@php
    $returning = $order->items->filter(fn ($item) => ($lines[$item->id] ?? 0) > 0);
@endphp

@section('content')

<div class="vp-adm-grid">
    <section class="vp-adm-card vp-adm-span-2">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">
                {{ $kind === 'cancel' ? 'لغو کامل سفارش نزد اسنپ‌پی' : 'تأیید مرجوعی اقساطی' }}
            </h2>
            <span class="vp-adm-card-more">
                سفارش {{ $order->number }} · اسنپ‌پی · <bdi dir="ltr">{{ $payment->authority }}</bdi>
            </span>
        </div>

        <p class="vp-adm-empty">
            هنوز چیزی ارسال نشده و هیچ‌چیز تغییر نکرده است. با زدن دکمهٔ پایین،
            @if ($kind === 'cancel')
                کل این سفارش نزد اسنپ‌پی لغو می‌شود، همهٔ اقساط مشتری برداشته می‌شود
                و موجودی به انبار برمی‌گردد.
            @else
                اقلام زیر به انبار برمی‌گردند و همان لحظه به اسنپ‌پی اعلام می‌شود.
            @endif
            <b>این کار برگشت‌پذیر نیست.</b>
        </p>

        @if ($kind === 'return')
            <table class="vp-admin-table">
                <thead><tr><th>کالا</th><th>سایز</th><th>برگشتی</th><th>بعد از این می‌ماند</th></tr></thead>
                <tbody>
                @foreach ($returning as $item)
                    <tr>
                        <td>{{ $item->product_title }}</td>
                        <td>{{ $item->size_value }}</td>
                        <td>{{ fa_number((int) $lines[$item->id]) }}</td>
                        <td>{{ fa_number($item->remaining() - (int) $lines[$item->id]) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        {{-- The money, both sides of the change. The figures come from
             `AfterReturns`, which is also what builds the basket SnappPay is
             sent — so what is printed here is what goes, rather than a second
             arithmetic that could disagree with it. --}}
        <table class="vp-admin-table">
            <thead><tr><th>&nbsp;</th><th>الان</th><th>بعد از این</th></tr></thead>
            <tbody>
                <tr>
                    <td>جمع کالاها</td>
                    <td>{{ toman($before->subtotal) }} تومان</td>
                    <td>{{ $after ? toman($after->subtotal).' تومان' : '۰ تومان' }}</td>
                </tr>
                <tr>
                    <td>تخفیف</td>
                    <td>{{ toman($before->discount) }} تومان</td>
                    <td>{{ $after ? toman($after->discount).' تومان' : '۰ تومان' }}</td>
                </tr>
                <tr>
                    <td>هزینهٔ ارسال</td>
                    <td>{{ toman($before->shipping) }} تومان</td>
                    {{-- Delivery is never returned on an update — the parcel was
                         carried — but a cancelled order is the whole purchase
                         reversed, delivery with it. --}}
                    <td>{{ $after ? toman($after->shipping).' تومان' : '۰ تومان' }}</td>
                </tr>
                <tr>
                    <td><b>مبلغی که مشتری قسط می‌دهد</b></td>
                    <td><b>{{ toman($before->payable) }} تومان</b></td>
                    <td><b>{{ $after ? toman($after->payable).' تومان' : '۰ تومان' }}</b></td>
                </tr>
            </tbody>
        </table>

        <p class="vp-adm-empty">علت ثبت‌شده: {{ $reason }}</p>

        <form class="vp-adm-form" method="post"
              action="{{ $kind === 'cancel'
                    ? route('admin.order.instalments.cancel', $order)
                    : route('admin.order.return', $order) }}">
            @csrf
            <input type="hidden" name="confirmed" value="1">
            <input type="hidden" name="reason" value="{{ $reason }}">
            @foreach ($lines as $itemId => $units)
                <input type="hidden" name="lines[{{ $itemId }}]" value="{{ (int) $units }}">
            @endforeach

            <button type="submit" class="vp-adm-danger">
                {{ $kind === 'cancel' ? 'بله، نزد اسنپ‌پی لغو کن' : 'بله، به اسنپ‌پی اعلام کن' }}
            </button>
        </form>

        <a class="vp-adm-mini is-quiet" href="{{ route('admin.order', $order) }}">انصراف، برگرد به سفارش</a>
    </section>
</div>

@endsection
