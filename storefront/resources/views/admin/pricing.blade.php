@extends('layouts.admin')

@section('title', 'قیمت‌ها')

{{--
    What this branch charges. Typed and read in Toman; stored in Rial.

    «قیمت قبل از تخفیف» is the struck-through number on the card. Leaving it
    empty is how a sale ends.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">{{ $branch->name }} — همه مبلغ‌ها به تومان</p>

    <div class="vp-adm-head-side">
        <form class="vp-adm-filters" method="get" action="{{ route('admin.pricing') }}" role="search">
            <label class="visually-hidden" for="vp-pri-q">جست‌وجو</label>
            <input id="vp-pri-q" class="vp-adm-search" type="search" name="q" value="{{ $q }}" placeholder="نام کالا یا کد">
            <button type="submit" class="vp-adm-apply">جست‌وجو</button>
            @if ($q !== '')
                <a class="vp-adm-clear" href="{{ route('admin.pricing') }}">پاک کردن</a>
            @endif
        </form>
    </div>
</div>

<section class="vp-adm-card">
    @if ($offers->isEmpty())
        <p class="vp-adm-empty">
            @if ($q === '')
                هنوز کالایی برای فروش در این شعبه باز نشده.
            @else
                چیزی با این نام یا کد پیدا نشد.
            @endif
        </p>
    @else
        <table class="vp-admin-table">
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
                        <td><input class="vp-adm-cell is-wide" type="text" inputmode="numeric" name="price" value="{{ intdiv($offer->price, 10) }}"></td>
                        <td><input class="vp-adm-cell is-wide" type="text" inputmode="numeric" name="compare_at_price" value="{{ $offer->compare_at_price ? intdiv($offer->compare_at_price, 10) : '' }}"></td>
                        <td>
                            <select name="status" class="vp-adm-cell">
                                <option value="active" @selected($offer->status === 'active')>فروش</option>
                                <option value="inactive" @selected($offer->status === 'inactive')>متوقف</option>
                            </select>
                        </td>
                        <td><button type="submit" class="vp-adm-mini">ثبت</button></td>
                    </form>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $offers->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
