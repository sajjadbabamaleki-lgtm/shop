@extends('layouts.panel')
@include('panel.franchise.menu')
@section('title', 'سفارش‌ها')
@section('heading', 'سفارش‌های شعبه')

@section('content')
    <div class="row pane pane--tight">
        <a class="btn btn--small" href="{{ route('panel.franchise.orders.index', $franchise) }}">همه</a>
        @foreach(['pending', 'accepted', 'packed', 'shipped', 'delivered'] as $key)
            <a class="btn btn--small" href="{{ route('panel.franchise.orders.index', [$franchise, 'status' => $key]) }}">{{ __('fulfillment.'.$key) }}</a>
        @endforeach
    </div>

    <div class="pane scroller" style="margin-top:18px">
        <table>
            <thead><tr><th>سفارش</th><th>کالا</th><th>تعداد</th><th>مبلغ</th><th>وضعیت</th><th>اقدام</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td class="num">{{ $item->order->number }}<span class="faint" style="display:block">{{ $item->order->paid_at?->format('Y/m/d') }}</span></td>
                    <td>{{ $item->title }}<span class="faint" style="display:block">{{ $item->display_color }} / {{ $item->size_value }}</span></td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">@rial($item->line_total)</td>
                    <td><span class="tag">{{ __('fulfillment.'.$item->fulfillment_status) }}</span></td>
                    <td>
                        @if($transitions[$item->fulfillment_status] ?? false)
                            <form method="post" action="{{ route('panel.franchise.orders.advance', [$franchise, $item]) }}" class="row">
                                @csrf @method('put')
                                <select name="fulfillment_status" style="max-width:160px">
                                    @foreach($transitions[$item->fulfillment_status] as $state)
                                        <option value="{{ $state }}">{{ __('fulfillment.'.$state) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn--small">ثبت</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">سفارشی نیست.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $items->links() }}
@endsection
