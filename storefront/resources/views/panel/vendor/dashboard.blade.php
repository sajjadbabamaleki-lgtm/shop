@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'نمای کلی')
@section('heading', 'نمای کلی')
@section('actions')
    <a class="btn btn--small" href="{{ $storeUrl }}" target="_blank" rel="noopener">دیدن مینی‌سایت</a>
@endsection

@section('content')
    @unless($vendor->isStorefrontLive())
        <p class="notice">
            فروشگاه شما هنوز منتشر نشده است ({{ ['pending' => 'در انتظار تأیید', 'suspended' => 'تعلیق‌شده', 'rejected' => 'رد شده'][$vendor->status] ?? $vendor->status }}).
            محصولاتتان را می‌توانید همین حالا آماده کنید.
        </p>
    @endunless

    <div class="grid grid--4">
        <div class="pane pane--tight stat"><b>@rial($today['gross'])</b><span>فروش امروز (تومان)</span></div>
        <div class="pane pane--tight stat"><b>@rial($month['gross'])</b><span>فروش ۳۰ روز</span></div>
        <div class="pane pane--tight stat"><b>@rial($payable)</b><span>مانده قابل تسویه</span></div>
        <div class="pane pane--tight stat"><b class="num">{{ $openFulfillment }}</b><span>قلم در انتظار ارسال</span></div>
    </div>

    <div class="grid grid--2" style="margin-top:18px">
        <section class="pane">
            <h2>فروش ۳۰ روز گذشته</h2>
            <div class="spark">
                @php $peak = max(1, $daily->max()); @endphp
                @foreach($daily as $day => $total)
                    <i style="height:{{ max(2, (int) round($total / $peak * 100)) }}%" title="{{ $day }}"></i>
                @endforeach
            </div>
            <div class="row row--between faint"><span>{{ $daily->keys()->first() }}</span><span>{{ $daily->keys()->last() }}</span></div>
        </section>

        <section class="pane">
            <h2>سهم پلتفرم</h2>
            <p class="muted">
                نرخ کمیسیون شما <b>{{ $commissionPercent }}٪</b> است. این نرخ در لحظه فروش روی هر قلم
                ثبت می‌شود، بنابراین تغییر بعدی آن سفارش‌های گذشته را عوض نمی‌کند.
            </p>
            <table>
                <tr><td>فروش ناخالص (کل)</td><td class="num">@rial($allTime['gross'])</td></tr>
                <tr><td>کمیسیون پلتفرم</td><td class="num">@rial($allTime['commission'])</td></tr>
                <tr><td>سهم شما</td><td class="num"><b>@rial($allTime['payable'])</b></td></tr>
                <tr><td>بازگشت وجه</td><td class="num">@rial($allTime['refunded'])</td></tr>
            </table>
        </section>
    </div>

    <div class="grid grid--2" style="margin-top:18px">
        <section class="pane">
            <h2>پرفروش‌ترین‌ها</h2>
            @forelse($bestSellers as $row)
                <div class="row row--between"><span>{{ $row->title }}</span><span class="num">{{ $row->units }} عدد</span></div>
            @empty
                <p class="muted">هنوز فروشی ثبت نشده است.</p>
            @endforelse
        </section>

        <section class="pane">
            <h2>موجودی رو به اتمام</h2>
            @forelse($lowStock as $offer)
                <div class="row row--between">
                    <a href="{{ route('panel.vendor.offers.edit', [$vendor, $offer]) }}">
                        {{ $offer->variant->product->title ?? $offer->variant->sku }} — {{ $offer->variant->size_value }}
                    </a>
                    <span class="tag tag--warn num">{{ $offer->sellable_stock }}</span>
                </div>
            @empty
                <p class="muted">همه چیز موجود است.</p>
            @endforelse
        </section>
    </div>
@endsection
