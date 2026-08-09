@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'مالی و تسویه')
@section('heading', 'مالی و تسویه')

@section('content')
    <div class="grid grid--3">
        <div class="pane pane--tight stat"><b>@rial($payable)</b><span>مانده قابل تسویه (تومان)</span></div>
        <div class="pane pane--tight stat"><b class="num">{{ $settlements->total() }}</b><span>دوره تسویه</span></div>
        <div class="pane pane--tight stat"><b class="num">{{ config('marketplace.settlement.hold_days_after_delivery') }}</b><span>روز نگهداشت پس از تحویل</span></div>
    </div>

    <section class="pane" style="margin-top:18px">
        <h2>دوره‌های تسویه</h2>
        <div class="scroller">
            <table>
                <thead><tr><th>شناسه</th><th>دوره</th><th>فروش</th><th>کمیسیون</th><th>خالص</th><th>وضعیت</th><th></th></tr></thead>
                <tbody>
                @forelse($settlements as $settlement)
                    <tr>
                        <td dir="ltr" class="num">{{ $settlement->reference }}</td>
                        <td class="faint">{{ $settlement->period_start->format('Y/m/d') }} – {{ $settlement->period_end->format('Y/m/d') }}</td>
                        <td class="num">@rial($settlement->gross_sales)</td>
                        <td class="num">@rial($settlement->commission_total)</td>
                        <td class="num"><b>@rial($settlement->net_payable)</b></td>
                        <td><span @class(['tag', 'tag--good' => $settlement->status === 'paid'])>
                            {{ ['draft' => 'پیش‌نویس', 'approved' => 'تأییدشده', 'paid' => 'پرداخت‌شده', 'failed' => 'ناموفق'][$settlement->status] }}
                        </span></td>
                        <td><a class="btn btn--quiet btn--small" href="{{ route('panel.vendor.settlements.show', [$vendor, $settlement]) }}">ریز</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">هنوز دوره‌ای تسویه نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $settlements->links() }}
    </section>

    <section class="pane" style="margin-top:18px">
        <h2>صورت‌حساب جاری</h2>
        <p class="faint">
            مانده شما جمع همین سطرهاست، نه عددی که جایی ذخیره شده باشد. هر ریالی که در بالا
            می‌بینید یک سطر اینجا دارد.
        </p>
        <div class="scroller">
            <table>
                <thead><tr><th>تاریخ</th><th>شرح</th><th>سفارش</th><th>مبلغ</th></tr></thead>
                <tbody>
                @forelse($statement as $entry)
                    <tr>
                        <td class="faint">{{ $entry->created_at->format('Y/m/d') }}</td>
                        <td>{{ $entry->memo }}</td>
                        <td class="faint">{{ $entry->orderItem?->title }}</td>
                        <td class="num" @class(['tag--bad' => $entry->amount < 0])>{{ \App\Support\Money::signed($entry->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">سطری ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
