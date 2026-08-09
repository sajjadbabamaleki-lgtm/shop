@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'ویرایش محصول')
@section('heading', $offer->product->title)

@section('content')
    <div class="grid grid--2">
        <form method="post" action="{{ route('panel.vendor.offers.update', [$vendor, $offer]) }}" class="pane">
            @csrf @method('put')
            <h2>{{ $offer->variant->display_color }} — سایز {{ $offer->variant->size_value }}</h2>

            <div class="row">
                <div class="field" style="flex:1"><label for="price">قیمت (ریال)</label>
                    <input id="price" type="number" name="price" value="{{ old('price', $offer->price) }}" required min="1000"></div>
                <div class="field" style="flex:1"><label for="compare_at_price">قیمت پیش از تخفیف</label>
                    <input id="compare_at_price" type="number" name="compare_at_price" value="{{ old('compare_at_price', $offer->compare_at_price) }}"></div>
            </div>

            <div class="row">
                <div class="field" style="flex:1"><label for="stock_on_hand">موجودی انبار</label>
                    <input id="stock_on_hand" type="number" name="stock_on_hand" value="{{ old('stock_on_hand', $offer->stock_on_hand) }}" required min="0">
                    <span class="faint">{{ $offer->stock_reserved }} عدد رزرو سبد مشتری است و قابل فروش نیست.</span></div>
                <div class="field" style="flex:1"><label for="stock_note">توضیح تغییر موجودی</label>
                    <input id="stock_note" name="stock_note" placeholder="مثلاً: رسید انبار"></div>
            </div>

            <div class="row">
                <div class="field" style="flex:1"><label for="handling_days">زمان آماده‌سازی (روز)</label>
                    <input id="handling_days" type="number" name="handling_days" value="{{ old('handling_days', $offer->handling_days) }}" required min="0"></div>
                <div class="field" style="flex:1"><label for="vendor_sku">کد انبار</label>
                    <input id="vendor_sku" name="vendor_sku" value="{{ old('vendor_sku', $offer->vendor_sku) }}" dir="ltr"></div>
                <div class="field" style="flex:1"><label for="status">وضعیت</label>
                    <select id="status" name="status">
                        @foreach(['active' => 'فعال', 'paused' => 'متوقف', 'draft' => 'پیش‌نویس'] as $key => $label)
                            <option value="{{ $key }}" @selected($offer->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select></div>
            </div>

            <button class="btn btn--gold">ذخیره</button>
        </form>

        <section class="pane">
            <h2>دفتر موجودی</h2>
            <p class="faint">
                موجودی با «ثبت حرکت» تغییر می‌کند، نه با بازنویسی عدد. برای همین همیشه می‌توان
                پرسید این کالا کِی و چرا کم یا زیاد شده است.
            </p>
            <table>
                @forelse($movements as $movement)
                    <tr>
                        <td class="faint">{{ $movement->created_at->format('Y/m/d H:i') }}</td>
                        <td>{{ ['receipt' => 'رسید', 'sale' => 'فروش', 'adjustment' => 'اصلاح', 'return' => 'مرجوعی', 'reservation' => 'رزرو', 'release' => 'آزادسازی'][$movement->type] ?? $movement->type }}</td>
                        <td class="num">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                        <td class="faint">{{ $movement->note }}</td>
                    </tr>
                @empty
                    <tr><td class="muted">حرکتی ثبت نشده است.</td></tr>
                @endforelse
            </table>

            <form method="post" action="{{ route('panel.vendor.offers.destroy', [$vendor, $offer]) }}" style="margin-top:14px">
                @csrf @method('delete')
                <button class="btn btn--small">برداشتن این محصول از فروشگاه</button>
            </form>
        </section>
    </div>
@endsection
