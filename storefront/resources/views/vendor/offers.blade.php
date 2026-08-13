@extends('layouts.vendor')

{{--
    A vendor lists against the shop's own catalogue: they pick a size that
    already exists and put a price on it. That is what lets one shoe have three
    sellers instead of becoming three shoes.

    A price change goes back for approval; a stock change does not. Running out
    is a fact, not a proposal.
--}}

@section('content')
<section class="vp-shop-panel">
    <h1 class="vp-shop-title">افزودن کالا</h1>

    <form class="vp-vendor-add" method="post" action="{{ route('vendor.offers.store') }}">
        @csrf
        <div class="vp-field">
            <label for="v-variant">کالا</label>
            <select id="v-variant" name="variant" class="vp-admin-pick" required>
                <option value="">انتخاب کن…</option>
                @foreach ($available as $variant)
                    <option value="{{ $variant->id }}">{{ $variant->product?->title }} — سایز {{ fa_number((int) $variant->size_value) }} ({{ $variant->sku }})</option>
                @endforeach
            </select>
        </div>
        <div class="vp-field">
            <label for="v-price">قیمت (تومان)</label>
            <input id="v-price" name="price" inputmode="numeric" required>
        </div>
        <div class="vp-field">
            <label for="v-stock">موجودی</label>
            <input id="v-stock" name="stock_on_hand" type="number" min="0" value="1" required>
        </div>
        <div class="vp-field">
            <label for="v-sku">کد خودت</label>
            <input id="v-sku" name="vendor_sku" maxlength="64">
        </div>
        <button type="submit" class="vp-admin-save">ثبت و ارسال برای تأیید</button>
    </form>
</section>

<section class="vp-shop-panel">
    <h2 class="vp-shop-title">کالاهای من</h2>

    @if ($offers->isEmpty())
        <p class="vp-shop-empty">هنوز چیزی ثبت نکردی.</p>
    @else
        <table class="vp-admin-table is-wide">
            <thead><tr><th>کالا</th><th>سایز</th><th>قیمت (تومان)</th><th>موجودی</th><th>رزرو</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            @foreach ($offers as $offer)
                <tr>
                    <td>{{ $offer->variant?->product?->title }}</td>
                    <td>{{ fa_number((int) $offer->variant?->size_value) }}</td>
                    <form method="post" action="{{ route('vendor.offers.update') }}">
                        @csrf
                        <input type="hidden" name="offer" value="{{ $offer->id }}">
                        <td><input class="vp-admin-num is-wide" name="price" inputmode="numeric" value="{{ intdiv($offer->price, 10) }}"></td>
                        <td><input class="vp-admin-num" type="number" name="stock_on_hand" min="{{ $offer->stock_reserved }}" value="{{ $offer->stock_on_hand }}"></td>
                        <td>{{ fa_number($offer->stock_reserved) }}</td>
                        <td>
                            {{ $offer->statusLabel() }}
                            @if ($offer->status_note)<br><span class="vp-cart-meta">{{ $offer->status_note }}</span>@endif
                        </td>
                        <td><button type="submit" class="vp-admin-save">ثبت</button></td>
                    </form>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $offers->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
