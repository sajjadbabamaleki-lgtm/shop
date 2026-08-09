@extends('layouts.panel')
@include('panel.franchise.menu')
@section('title', 'نمای کلی')
@section('heading', 'نمای کلی شعبه')
@section('actions')
    <a class="btn btn--small" href="{{ $storeUrl }}" target="_blank" rel="noopener">دیدن مینی‌سایت شعبه</a>
@endsection

@section('content')
    <div class="grid grid--4">
        <div class="pane pane--tight stat"><b>@rial($today['gross'])</b><span>فروش امروز (تومان)</span></div>
        <div class="pane pane--tight stat"><b>@rial($month['gross'])</b><span>فروش ۳۰ روز</span></div>
        <div class="pane pane--tight stat"><b class="num">{{ $openFulfillment }}</b><span>قلم در انتظار ارسال</span></div>
        <div class="pane pane--tight stat"><b class="num">{{ $reorder->count() }}</b><span>کالای زیر نقطه سفارش</span></div>
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
            <h2>قواعد مرکزی این شعبه</h2>
            <table>
                <tr><td>حالت قیمت‌گذاری</td><td>{{ ['none' => 'بدون اختیار', 'limited' => 'محدود', 'free' => 'آزاد'][$franchise->price_override_mode] }}</td></tr>
                <tr><td>حداکثر تخفیف مجاز</td><td class="num">{{ $franchise->max_discount_bps / 100 }}٪</td></tr>
                <tr><td>حداکثر افزایش مجاز</td><td class="num">{{ $franchise->max_markup_bps / 100 }}٪</td></tr>
                <tr><td>تأمین موجودی</td><td>{{ ['local' => 'فقط انبار شعبه', 'central' => 'فقط انبار مرکزی', 'hybrid' => 'شعبه، سپس انبار مرکزی'][$franchise->inventory_mode] }}</td></tr>
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
            <h2>نیازمند سفارش مجدد</h2>
            @forelse($reorder as $row)
                <div class="row row--between">
                    <span>{{ $row->variant->product->title ?? $row->variant->sku }} — {{ $row->variant->size_value }}</span>
                    <span class="tag tag--warn num">{{ $row->sellable_stock }} / {{ $row->reorder_point }}</span>
                </div>
            @empty
                <p class="muted">چیزی زیر نقطه سفارش نیست.</p>
            @endforelse
        </section>
    </div>
@endsection
