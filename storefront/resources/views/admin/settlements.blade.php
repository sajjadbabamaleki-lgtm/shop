@extends('layouts.admin')

@section('title', 'تسویه‌ها')

{{--
    Requested, approved, paid. Three steps because the person who approves a
    payment and the person who makes it are not always the same.
--}}

@section('content')
<section class="vp-adm-card">
    @if ($settlements->isEmpty())
        <p class="vp-adm-empty">درخواست تسویه‌ای ثبت نشده.</p>
    @else
        <table class="vp-admin-table">
            <thead><tr><th>فروشنده</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th><th>شبا</th><th></th></tr></thead>
            <tbody>
            @foreach ($settlements as $settlement)
                <tr>
                    <td>{{ $settlement->vendor?->name }}</td>
                    <td>{{ fa_date($settlement->requested_at) }}</td>
                    <td>{{ toman($settlement->amount) }}</td>
                    <td>
                        <span class="vp-adm-badge is-{{ $settlement->status === \App\Models\Settlement::REQUESTED ? 'placed' : ($settlement->status === \App\Models\Settlement::APPROVED ? 'paid' : 'delivered') }}">
                            {{ $settlement->statusLabel() }}
                        </span>
                    </td>
                    <td><bdi dir="ltr">{{ $settlement->vendor?->iban ?: 'ندارد' }}</bdi></td>
                    <td>
                        @if ($settlement->status === \App\Models\Settlement::REQUESTED)
                            <div class="vp-adm-inline">
                                <form method="post" action="{{ route('admin.settlement.action', $settlement) }}">
                                    @csrf<input type="hidden" name="action" value="approve">
                                    <button type="submit" class="vp-adm-mini">تأیید</button>
                                </form>
                                <form method="post" action="{{ route('admin.settlement.action', $settlement) }}">
                                    @csrf<input type="hidden" name="action" value="reject">
                                    <button type="submit" class="vp-adm-mini is-bad">رد</button>
                                </form>
                            </div>
                        @elseif ($settlement->status === \App\Models\Settlement::APPROVED)
                            <form class="vp-adm-inline" method="post" action="{{ route('admin.settlement.action', $settlement) }}">
                                @csrf
                                <input type="hidden" name="action" value="pay">
                                <label class="visually-hidden" for="vp-ref-{{ $settlement->id }}">شماره پیگیری</label>
                                <input id="vp-ref-{{ $settlement->id }}" class="vp-adm-cell is-text" name="reference" placeholder="شماره پیگیری" required>
                                <button type="submit" class="vp-adm-mini">ثبت پرداخت</button>
                            </form>
                        @else
                            <bdi dir="ltr">{{ $settlement->payment_reference ?? 'ندارد' }}</bdi>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $settlements->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
