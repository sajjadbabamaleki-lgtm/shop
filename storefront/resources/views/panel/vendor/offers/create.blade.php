@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'افزودن محصول')
@section('heading', 'افزودن محصول')

@section('content')
    <div class="pane">
        <h2>۱. محصول را از کاتالوگ انتخاب کنید</h2>
        <p class="muted">
            کاتالوگ ویکی‌پلاس مشترک است: هویت محصول، برند، رنگ و سایز یکی است و شما قیمت و موجودی
            خودتان را روی آن ثبت می‌کنید. اگر کالایی در کاتالوگ نیست، از پشتیبانی درخواست افزودن بدهید.
        </p>

        <form method="get" class="row">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="نام محصول">
            <button class="btn btn--small">بگرد</button>
        </form>

        <div class="row" style="margin-top:12px">
            @foreach($products as $candidate)
                <a class="btn btn--small" href="{{ route('panel.vendor.offers.create', [$vendor, 'product' => $candidate->slug]) }}">
                    {{ $candidate->title }}
                </a>
            @endforeach
        </div>
    </div>

    @if($product)
        <form method="post" action="{{ route('panel.vendor.offers.store', $vendor) }}" class="pane" style="margin-top:18px">
            @csrf
            <h2>۲. {{ $product->title }}</h2>

            <div class="field">
                <label for="variant_id">رنگ و سایز</label>
                <select id="variant_id" name="variant_id" required>
                    @foreach($product->variants as $variant)
                        <option value="{{ $variant->id }}" @disabled(in_array($variant->id, $taken, true))>
                            {{ $variant->display_color }} — {{ $variant->size_value }}
                            @if(in_array($variant->id, $taken, true)) (قبلاً ثبت شده) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="row">
                <div class="field" style="flex:1"><label for="price">قیمت شما (ریال)</label>
                    <input id="price" type="number" name="price" value="{{ old('price') }}" required min="1000"></div>
                <div class="field" style="flex:1"><label for="compare_at_price">قیمت پیش از تخفیف (اختیاری)</label>
                    <input id="compare_at_price" type="number" name="compare_at_price" value="{{ old('compare_at_price') }}"></div>
            </div>

            <div class="row">
                <div class="field" style="flex:1"><label for="stock_on_hand">موجودی</label>
                    <input id="stock_on_hand" type="number" name="stock_on_hand" value="{{ old('stock_on_hand', 0) }}" required min="0"></div>
                <div class="field" style="flex:1"><label for="handling_days">زمان آماده‌سازی (روز)</label>
                    <input id="handling_days" type="number" name="handling_days" value="{{ old('handling_days', 1) }}" required min="0"></div>
                <div class="field" style="flex:1"><label for="vendor_sku">کد انبار خودتان</label>
                    <input id="vendor_sku" name="vendor_sku" value="{{ old('vendor_sku') }}" dir="ltr"></div>
            </div>

            <div class="field">
                <label for="status">وضعیت</label>
                <select id="status" name="status">
                    <option value="active">فعال — در فروشگاه نمایش داده شود</option>
                    <option value="draft">پیش‌نویس</option>
                </select>
            </div>

            <button class="btn btn--gold">ثبت</button>
        </form>
    @endif
@endsection
