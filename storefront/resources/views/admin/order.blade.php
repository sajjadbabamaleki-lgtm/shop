@extends('layouts.admin')

{{--
    One order. The buttons offer only the moves that make sense from where it
    is now — the controller decides that, and this asks it rather than drawing
    every status and hoping.
--}}

@section('content')
<section class="vp-shop-panel">
    <div class="vp-shop-head">
        <div class="vp-shop-heading">
            <h1 class="vp-shop-title">سفارش {{ $order->number }}</h1>
            <p class="vp-shop-count">{{ $order->statusLabel() }} — {{ fa_date($order->placed_at, true) }}</p>
        </div>
        <a class="vp-cart-keep" href="{{ route('admin.orders') }}">بازگشت به فهرست</a>
    </div>

    <div class="vp-cart">
        <div class="vp-cart-lines">
            @foreach ($order->items as $item)
                <div class="vp-cart-line">
                    <div class="vp-cart-what">
                        <span class="vp-cart-name">{{ $item->product_title }}</span>
                        <span class="vp-cart-meta">سایز {{ fa_number((int) $item->size_value) }} — {{ $item->sku }}</span>
                    </div>
                    <div class="vp-cart-qty-static">{{ fa_number($item->quantity) }} عدد</div>
                    <div class="vp-cart-money"><strong>{{ toman($item->line_total) }}</strong></div>
                </div>
            @endforeach
        </div>

        <aside class="vp-cart-sum">
            <h2 class="vp-filter-title">جمع</h2>
            <div class="vp-cart-row"><span>کالاها</span><span>{{ toman($order->subtotal) }}</span></div>
            <div class="vp-cart-row"><span>ارسال</span><span>{{ toman($order->shipping_total) }}</span></div>
            <div class="vp-cart-row is-total"><span>مبلغ کل</span><span>{{ toman($order->grand_total) }}</span></div>

            <h2 class="vp-filter-title vp-order-to">تحویل به</h2>
            <p class="vp-order-address">
                {{ $order->contact_name }}<br>
                {{ $order->contact_phone }}<br>
                @if ($order->province || $order->city){{ $order->province }} {{ $order->city }}<br>@endif
                {{ $order->address }}
                @if ($order->postal_code)<br>کد پستی {{ $order->postal_code }}@endif
                @if ($order->note)<br><span class="vp-cart-meta">{{ $order->note }}</span>@endif
            </p>

            @php
                $moves = match ($order->status) {
                    \App\Models\Order::PLACED => ['paid' => 'ثبت پرداخت', 'cancelled' => 'لغو سفارش'],
                    \App\Models\Order::PAID => ['shipped' => 'ارسال شد', 'cancelled' => 'لغو سفارش'],
                    \App\Models\Order::SHIPPED => ['delivered' => 'تحویل شد'],
                    default => [],
                };
            @endphp

            @if ($moves && auth()->user()->hasPermissionToAt($branch, 'branch.orders.manage'))
                <div class="vp-admin-moves">
                    @foreach ($moves as $to => $label)
                        <form method="post" action="{{ route('admin.order.update', $order) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $to }}">
                            <button type="submit" @class(['vp-admin-move', 'is-off' => $to === 'cancelled'])>{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
</section>
@endsection
