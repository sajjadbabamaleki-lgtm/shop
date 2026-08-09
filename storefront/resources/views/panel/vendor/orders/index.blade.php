@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'سفارش‌ها')
@section('heading', 'سفارش‌ها')

@section('content')
    <p class="faint">
        واحد کار اینجا «قلم سفارش» است، نه کل سفارش: یک سبد ممکن است کالای چند فروشنده را با هم
        داشته باشد و هر فروشنده فقط قلم خودش را می‌بیند و می‌فرستد.
    </p>

    <div class="row pane pane--tight">
        <a class="btn btn--small" href="{{ route('panel.vendor.orders.index', $vendor) }}">همه</a>
        @foreach(['pending' => 'جدید', 'accepted' => 'پذیرفته', 'packed' => 'بسته‌بندی', 'shipped' => 'ارسال‌شده', 'delivered' => 'تحویل‌شده'] as $key => $label)
            <a class="btn btn--small" href="{{ route('panel.vendor.orders.index', [$vendor, 'status' => $key]) }}">
                {{ $label }} <span class="num">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <div class="pane scroller" style="margin-top:18px">
        <table>
            <thead><tr><th>سفارش</th><th>کالا</th><th>تعداد</th><th>مبلغ</th><th>سهم شما</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td class="num">{{ $item->order->number }}<span class="faint" style="display:block">{{ $item->order->paid_at?->format('Y/m/d') }}</span></td>
                    <td>{{ $item->title }}<span class="faint" style="display:block">{{ $item->display_color }} / {{ $item->size_value }}</span></td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">@rial($item->line_total)</td>
                    <td class="num">@rial($item->seller_payable)<span class="faint" style="display:block">کمیسیون {{ $item->commission_rate_bps / 100 }}٪</span></td>
                    <td><span class="tag">{{ __('fulfillment.'.$item->fulfillment_status) }}</span></td>
                    <td><a class="btn btn--quiet btn--small" href="{{ route('panel.vendor.orders.show', [$vendor, $item]) }}">جزئیات</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">سفارشی در این وضعیت نیست.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $items->links() }}
@endsection
