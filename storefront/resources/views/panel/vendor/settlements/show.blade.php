@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'ریز تسویه')
@section('heading', 'تسویه '.$settlement->reference)

@section('content')
    <div class="grid grid--4">
        <div class="pane pane--tight stat"><b>@rial($settlement->gross_sales)</b><span>فروش ناخالص</span></div>
        <div class="pane pane--tight stat"><b>@rial($settlement->commission_total)</b><span>کمیسیون</span></div>
        <div class="pane pane--tight stat"><b>@rial($settlement->refund_total)</b><span>بازگشت وجه</span></div>
        <div class="pane pane--tight stat"><b>@rial($settlement->net_payable)</b><span>خالص پرداختی</span></div>
    </div>

    <section class="pane" style="margin-top:18px">
        <h2>اقلام این دوره</h2>
        <div class="scroller">
            <table>
                <thead><tr><th>سفارش</th><th>کالا</th><th>تعداد</th><th>مبلغ</th><th>کمیسیون</th><th>سهم شما</th></tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td class="num">{{ $item->order->number }}</td>
                        <td>{{ $item->title }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">@rial($item->line_total)</td>
                        <td class="num">@rial($item->commission_amount)</td>
                        <td class="num">@rial($item->seller_payable)</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="pane" style="margin-top:18px">
        <h2>سطرهای دفتر مالی</h2>
        <div class="scroller">
            <table>
                <thead><tr><th>تاریخ</th><th>شرح</th><th>مبلغ</th></tr></thead>
                <tbody>
                @foreach($entries as $entry)
                    <tr>
                        <td class="faint">{{ $entry->created_at->format('Y/m/d H:i') }}</td>
                        <td>{{ $entry->memo }}</td>
                        <td class="num">{{ \App\Support\Money::signed($entry->amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
