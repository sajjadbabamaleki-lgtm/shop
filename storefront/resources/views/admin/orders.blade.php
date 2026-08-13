@extends('layouts.admin')

@section('content')
<section class="vp-shop-panel">
    <div class="vp-shop-head">
        <h1 class="vp-shop-title">سفارش‌ها</h1>
        <nav class="vp-admin-tabs">
            <a href="{{ route('admin.orders') }}" @class(['is-on' => ! $status])>همه</a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('admin.orders', ['status' => $key]) }}" @class(['is-on' => $status === $key])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($orders->isEmpty())
        <p class="vp-shop-empty">سفارشی با این وضعیت نیست.</p>
    @else
        <table class="vp-admin-table">
            <thead><tr><th>شماره</th><th>تاریخ</th><th>مشتری</th><th>وضعیت</th><th>پرداخت</th><th>مبلغ</th></tr></thead>
            <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td><a href="{{ route('admin.order', $order) }}">{{ $order->number }}</a></td>
                    <td>{{ fa_date($order->placed_at) }}</td>
                    <td>{{ $order->contact_name }}<br><span class="vp-cart-meta">{{ $order->contact_phone }}</span></td>
                    <td>{{ $order->statusLabel() }}</td>
                    <td>{{ $order->payment_status === 'paid' ? 'پرداخت شد' : 'پرداخت نشده' }}</td>
                    <td>{{ toman($order->grand_total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $orders->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
