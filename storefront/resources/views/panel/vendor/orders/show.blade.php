@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'قلم سفارش')
@section('heading', 'سفارش '.$item->order->number)

@section('content')
    <div class="grid grid--2">
        <section class="pane">
            <h2>{{ $item->title }}</h2>
            <table>
                <tr><td>رنگ و سایز</td><td>{{ $item->display_color }} / {{ $item->size_value }}</td></tr>
                <tr><td>کد کالا</td><td dir="ltr" class="num">{{ $item->sku }}</td></tr>
                <tr><td>تعداد</td><td class="num">{{ $item->quantity }}</td></tr>
                <tr><td>مبلغ فروش</td><td class="num">@rial($item->line_total)</td></tr>
                <tr><td>کمیسیون پلتفرم ({{ $item->commission_rate_bps / 100 }}٪)</td><td class="num">@rial($item->commission_amount)</td></tr>
                <tr><td><b>سهم شما</b></td><td class="num"><b>@rial($item->seller_payable)</b></td></tr>
                @if($item->refunded_amount > 0)
                    <tr><td>بازگشت وجه</td><td class="num">@rial($item->refunded_amount)</td></tr>
                @endif
            </table>

            <h3 style="margin-top:16px">گیرنده</h3>
            <p class="muted">
                {{ $item->order->customer_name }}<br>
                <span dir="ltr" class="num">{{ $item->order->customer_phone }}</span><br>
                {{ collect((array) $item->order->shipping_address)->filter()->join('، ') }}
            </p>
        </section>

        <section class="pane">
            <h2>وضعیت ارسال</h2>
            <p><span class="tag">{{ __('fulfillment.'.$item->fulfillment_status) }}</span></p>

            @if($next)
                <form method="post" action="{{ route('panel.vendor.orders.advance', [$vendor, $item]) }}">
                    @csrf @method('put')
                    <div class="field">
                        <label for="fulfillment_status">مرحله بعد</label>
                        <select id="fulfillment_status" name="fulfillment_status">
                            @foreach($next as $state)
                                <option value="{{ $state }}">{{ __('fulfillment.'.$state) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="tracking_code">کد رهگیری پست</label>
                        <input id="tracking_code" name="tracking_code" value="{{ $item->tracking_code }}" dir="ltr">
                    </div>
                    <button class="btn btn--gold">ثبت</button>
                </form>
            @else
                <p class="muted">این قلم به پایان چرخه رسیده است.</p>
            @endif

            @if($item->delivered_at)
                <p class="faint">
                    تحویل: {{ $item->delivered_at->format('Y/m/d') }} — مبلغ این قلم
                    {{ config('marketplace.settlement.hold_days_after_delivery') }} روز پس از تحویل قابل تسویه می‌شود.
                </p>
            @endif
        </section>
    </div>
@endsection
