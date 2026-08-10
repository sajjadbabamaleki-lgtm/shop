@extends('layouts.vendor')

{{--
    A vendor's own lines, across every storefront that sold one. A vendor sells
    to the platform rather than to a shop, so which branch took the order is a
    column here and not a filter on the page.
--}}

@section('content')
<section class="vp-shop-panel">
    <h1 class="vp-shop-title">سفارش‌ها</h1>

    @if ($items->isEmpty())
        <p class="vp-shop-empty">هنوز فروشی ثبت نشده.</p>
    @else
        <table class="vp-admin-table is-wide">
            <thead><tr><th>سفارش</th><th>تاریخ</th><th>فروشگاه</th><th>کالا</th><th>سایز</th><th>تعداد</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
            <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->order?->number }}</td>
                    <td>{{ $item->order?->placed_at ? fa_date($item->order->placed_at) : '—' }}</td>
                    <td>{{ $item->order?->branch?->name }}</td>
                    <td>{{ $item->product_title }}</td>
                    <td>{{ fa_number((int) $item->size_value) }}</td>
                    <td>{{ fa_number($item->quantity) }}</td>
                    <td>{{ toman($item->line_total) }}</td>
                    <td>{{ $statuses[$item->order?->status] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $items->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
