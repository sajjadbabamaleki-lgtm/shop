@extends('layouts.vendor')

{{--
    The balance is the sum of the rows below it, computed now. There is no
    stored total anywhere that could disagree with its own history.
--}}

@section('content')
<div class="vp-admin-figures">
    <div class="vp-admin-figure">
        <span class="vp-admin-figure-num">{{ toman($balance) }}</span>
        <span class="vp-admin-figure-name">مانده حساب (تومان)</span>
    </div>
    <div class="vp-admin-figure">
        <span class="vp-admin-figure-num">{{ toman(max(0, $available)) }}</span>
        <span class="vp-admin-figure-name">قابل درخواست (تومان)</span>
    </div>
</div>

<section class="vp-shop-panel">
    <div class="vp-shop-head">
        <h1 class="vp-shop-title">تسویه‌ها</h1>
        @if ($available > 0)
            <form method="post" action="{{ route('vendor.account.settle') }}">
                @csrf
                <button type="submit" class="vp-admin-save">درخواست تسویه</button>
            </form>
        @endif
    </div>

    @if ($settlements->isEmpty())
        <p class="vp-shop-empty">درخواستی ثبت نشده.</p>
    @else
        <table class="vp-admin-table">
            <thead><tr><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th><th>پیگیری</th></tr></thead>
            <tbody>
            @foreach ($settlements as $settlement)
                <tr>
                    <td>{{ fa_date($settlement->requested_at) }}</td>
                    <td>{{ toman($settlement->amount) }}</td>
                    <td>{{ $settlement->statusLabel() }}</td>
                    <td>{{ $settlement->payment_reference ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>

<section class="vp-shop-panel">
    <h2 class="vp-shop-title">گردش حساب</h2>

    @if ($entries->isEmpty())
        <p class="vp-shop-empty">هنوز گردشی نیست.</p>
    @else
        <table class="vp-admin-table">
            <thead><tr><th>تاریخ</th><th>بابت</th><th>شرح</th><th>مبلغ (تومان)</th></tr></thead>
            <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ fa_date($entry->created_at) }}</td>
                    <td>{{ $entry->typeLabel() }}</td>
                    <td>{{ $entry->note }}</td>
                    <td @class(['vp-ledger-out' => $entry->amount < 0])>{{ $entry->amount < 0 ? '−' : '' }}{{ toman(abs($entry->amount)) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $entries->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
