@extends('layouts.panel')
@include('panel.admin.menu')
@section('title', 'فروشندگان')
@section('heading', 'فروشندگان')

@section('content')
    <div class="row pane pane--tight">
        <a class="btn btn--small" href="{{ route('panel.platform.vendors.index') }}">همه</a>
        @foreach(['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'suspended' => 'تعلیق', 'rejected' => 'رد‌شده'] as $key => $label)
            <a class="btn btn--small" href="{{ route('panel.platform.vendors.index', ['status' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="pane scroller" style="margin-top:18px">
        <table>
            <thead><tr><th>فروشگاه</th><th>محصول</th><th>قلم فروش</th><th>کمیسیون</th><th>وضعیت</th><th>اقدام</th></tr></thead>
            <tbody>
            @forelse($vendors as $vendor)
                <tr>
                    <td>
                        {{ $vendor->name }}
                        <span class="faint" style="display:block" dir="ltr">{{ $vendor->slug }}</span>
                    </td>
                    <td class="num">{{ $vendor->offers_count }}</td>
                    <td class="num">{{ $vendor->order_items_count }}</td>
                    <td>
                        <form method="post" action="{{ route('panel.platform.vendors.commission', $vendor) }}" class="row">
                            @csrf @method('put')
                            <input type="number" step="0.01" name="commission_percent"
                                   value="{{ $vendor->commission_rate_bps !== null ? $vendor->commission_rate_bps / 100 : '' }}"
                                   placeholder="{{ $defaultPercent }}" style="max-width:90px">
                            <button class="btn btn--small">٪</button>
                        </form>
                    </td>
                    <td><span @class(['tag', 'tag--good' => $vendor->status === 'approved', 'tag--warn' => $vendor->status === 'pending', 'tag--bad' => in_array($vendor->status, ['rejected', 'suspended'])])>
                        {{ ['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'suspended' => 'تعلیق', 'rejected' => 'رد‌شده'][$vendor->status] }}
                    </span></td>
                    <td class="row">
                        @if($vendor->status !== 'approved')
                            <form method="post" action="{{ route('panel.platform.vendors.approve', $vendor) }}">@csrf @method('put')
                                <button class="btn btn--small btn--gold">تأیید</button></form>
                        @endif
                        @if($vendor->status === 'approved')
                            <form method="post" action="{{ route('panel.platform.vendors.suspend', $vendor) }}" class="row">@csrf @method('put')
                                <input name="reason" placeholder="دلیل" style="max-width:150px" required>
                                <button class="btn btn--small">تعلیق</button></form>
                        @endif
                        @if($vendor->status === 'pending')
                            <form method="post" action="{{ route('panel.platform.vendors.reject', $vendor) }}" class="row">@csrf @method('put')
                                <input name="reason" placeholder="دلیل" style="max-width:150px" required>
                                <button class="btn btn--small">رد</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">فروشنده‌ای نیست.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $vendors->links() }}
@endsection
