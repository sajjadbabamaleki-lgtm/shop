@extends('layouts.admin')

{{--
    Requested, approved, paid. Three steps because the person who approves a
    payment and the person who makes it are not always the same.
--}}

@section('content')
<section class="vp-shop-panel">
    <h1 class="vp-shop-title">تسویه‌ها</h1>

    @if ($settlements->isEmpty())
        <p class="vp-shop-empty">درخواستی ثبت نشده.</p>
    @else
        <table class="vp-admin-table is-wide">
            <thead><tr><th>فروشنده</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th><th>شبا</th><th></th></tr></thead>
            <tbody>
            @foreach ($settlements as $settlement)
                <tr>
                    <td>{{ $settlement->vendor?->name }}</td>
                    <td>{{ fa_date($settlement->requested_at) }}</td>
                    <td>{{ toman($settlement->amount) }}</td>
                    <td>{{ $settlement->statusLabel() }}</td>
                    <td>{{ $settlement->vendor?->iban ?: '—' }}</td>
                    <td>
                        @if ($settlement->status === \App\Models\Settlement::REQUESTED)
                            <div class="vp-admin-inline">
                                <form method="post" action="{{ route('admin.settlement.action', $settlement) }}">
                                    @csrf<input type="hidden" name="action" value="approve">
                                    <button type="submit" class="vp-admin-save">تأیید</button>
                                </form>
                                <form method="post" action="{{ route('admin.settlement.action', $settlement) }}">
                                    @csrf<input type="hidden" name="action" value="reject">
                                    <button type="submit" class="vp-admin-save">رد</button>
                                </form>
                            </div>
                        @elseif ($settlement->status === \App\Models\Settlement::APPROVED)
                            <form class="vp-admin-inline" method="post" action="{{ route('admin.settlement.action', $settlement) }}">
                                @csrf
                                <input type="hidden" name="action" value="pay">
                                <input class="vp-admin-text" name="reference" placeholder="شماره پیگیری" required>
                                <button type="submit" class="vp-admin-save">ثبت پرداخت</button>
                            </form>
                        @else
                            {{ $settlement->payment_reference ?? '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $settlements->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
