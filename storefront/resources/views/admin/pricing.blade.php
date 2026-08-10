@extends('layouts.admin')

{{--
    What this branch charges. Typed and read in Toman; stored in Rial.

    «قیمت قبل از تخفیف» is the struck-through number on the card. Leaving it
    empty is how a sale ends.
--}}

@section('content')
<section class="vp-shop-panel">
    <div class="vp-shop-head">
        <div class="vp-shop-heading">
            <h1 class="vp-shop-title">قیمت‌ها</h1>
            <p class="vp-shop-count">{{ $branch->name }} — همه مبلغ‌ها به تومان</p>
        </div>
        <form class="vp-shop-search" method="get" action="{{ route('admin.pricing') }}" role="search">
            <input type="search" name="q" value="{{ $q }}" placeholder="نام کالا یا کد" aria-label="جست‌وجو">
            <button type="submit" aria-label="جست‌وجو"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
        </form>
    </div>

    @if ($offers->isEmpty())
        <p class="vp-shop-empty">چیزی پیدا نشد.</p>
    @else
        <table class="vp-admin-table is-wide">
            <thead>
                <tr><th>کالا</th><th>کد</th><th>سایز</th><th>قیمت فروش</th><th>قبل از تخفیف</th><th>وضعیت</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($offers as $offer)
                <tr>
                    <td>{{ $offer->variant?->product?->title }}</td>
                    <td>{{ $offer->variant?->sku }}</td>
                    <td>{{ fa_number((int) $offer->variant?->size_value) }}</td>
                    <form method="post" action="{{ route('admin.pricing.update', ['q' => $q]) }}">
                        @csrf
                        <input type="hidden" name="offer" value="{{ $offer->id }}">
                        <td><input class="vp-admin-num is-wide" type="text" inputmode="numeric" name="price" value="{{ intdiv($offer->price, 10) }}"></td>
                        <td><input class="vp-admin-num is-wide" type="text" inputmode="numeric" name="compare_at_price" value="{{ $offer->compare_at_price ? intdiv($offer->compare_at_price, 10) : '' }}"></td>
                        <td>
                            <select name="status" class="vp-admin-pick">
                                <option value="active" @selected($offer->status === 'active')>فروش</option>
                                <option value="inactive" @selected($offer->status === 'inactive')>متوقف</option>
                            </select>
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
