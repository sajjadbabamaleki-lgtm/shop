@extends('layouts.panel')
@include('panel.admin.menu')
@section('title', 'مدیریت پلتفرم')
@section('heading', 'نمای کلی پلتفرم')

@section('content')
    <div class="grid grid--4">
        <div class="pane pane--tight stat"><b>@rial($gmv)</b><span>گردش کل فروش (تومان)</span></div>
        <div class="pane pane--tight stat"><b>@rial($commission)</b><span>کمیسیون کل</span></div>
        <div class="pane pane--tight stat"><b>@rial($commissionThisMonth)</b><span>کمیسیون این ماه</span></div>
        <div class="pane pane--tight stat"><b class="num">{{ $vendors['approved'] ?? 0 }}</b><span>فروشنده فعال</span></div>
    </div>

    <div class="grid grid--2" style="margin-top:18px">
        <section class="pane">
            <h2>در انتظار تأیید</h2>
            @forelse($pendingVendors as $vendor)
                <div class="row row--between">
                    <span>{{ $vendor->name }}<span class="faint" style="display:block">{{ $vendor->created_at->format('Y/m/d') }}</span></span>
                    <a class="btn btn--small" href="{{ route('panel.platform.vendors.index', ['status' => 'pending']) }}">بررسی</a>
                </div>
            @empty
                <p class="muted">درخواستی در صف نیست.</p>
            @endforelse
        </section>

        <section class="pane">
            <h2>شبکه</h2>
            <table>
                @foreach(['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'suspended' => 'تعلیق', 'rejected' => 'رد‌شده'] as $key => $label)
                    <tr><td>فروشنده — {{ $label }}</td><td class="num">{{ $vendors[$key] ?? 0 }}</td></tr>
                @endforeach
                @foreach(['pending' => 'در انتظار', 'active' => 'فعال', 'suspended' => 'تعلیق', 'closed' => 'بسته'] as $key => $label)
                    <tr><td>نمایندگی — {{ $label }}</td><td class="num">{{ $franchises[$key] ?? 0 }}</td></tr>
                @endforeach
            </table>
        </section>
    </div>

    <section class="pane" style="margin-top:18px">
        <h2>پرفروش‌ترین فروشندگان</h2>
        <div class="scroller">
            <table>
                <thead><tr><th>فروشنده</th><th>فروش</th><th>کمیسیون</th></tr></thead>
                <tbody>
                @forelse($topVendors as $row)
                    <tr><td>{{ $row->name }}</td><td class="num">@rial($row->revenue)</td><td class="num">@rial($row->commission)</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">فروشی ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
