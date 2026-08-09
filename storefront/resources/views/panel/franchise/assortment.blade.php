@extends('layouts.panel')
@include('panel.franchise.menu')
@section('title', 'سبد کالا و قیمت')
@section('heading', 'سبد کالا و قیمت')

@section('content')
    <p class="faint">
        قیمت خالی یعنی «قیمت مرکزی». اگر عددی بگذارید، باید در محدوده اختیارات این شعبه باشد:
        حداکثر {{ $franchise->max_discount_bps / 100 }}٪ تخفیف و {{ $franchise->max_markup_bps / 100 }}٪ افزایش.
    </p>

    <form method="get" class="pane pane--tight row">
        <input type="search" name="q" value="{{ $search }}" placeholder="جست‌وجو در سبد شعبه">
        <input type="search" name="add" value="{{ request('add') }}" placeholder="افزودن کالای جدید از کاتالوگ">
        <button class="btn btn--small">بگرد</button>
    </form>

    @if(request()->filled('add'))
        <section class="pane" style="margin-top:18px">
            <h2>افزودن از کاتالوگ</h2>
            @foreach($candidates as $product)
                <div class="row row--between" style="border-bottom:1px solid var(--hairline);padding:6px 0">
                    <span>{{ $product->title }}</span>
                    <form method="post" action="{{ route('panel.franchise.assortment.store', $franchise) }}" class="row">
                        @csrf
                        <select name="variant_id" style="max-width:220px">
                            @foreach($product->variants as $variant)
                                <option value="{{ $variant->id }}">{{ $variant->display_color }} — {{ $variant->size_value }} (@rial($variant->price))</option>
                            @endforeach
                        </select>
                        <input type="number" name="price_override" placeholder="قیمت شعبه (اختیاری)" style="max-width:180px">
                        <button class="btn btn--small btn--gold">افزودن</button>
                    </form>
                </div>
            @endforeach
        </section>
    @endif

    <div class="pane scroller" style="margin-top:18px">
        <table>
            <thead><tr><th>کالا</th><th>رنگ / سایز</th><th>قیمت مرکزی</th><th>قیمت شعبه</th><th>فعال</th><th></th></tr></thead>
            <tbody>
            @forelse($offers as $offer)
                <tr>
                    <form method="post" action="{{ route('panel.franchise.assortment.update', [$franchise, $offer]) }}">
                        @csrf @method('put')
                        <td>{{ $offer->product->title }}</td>
                        <td class="faint">{{ $offer->variant->display_color }} / {{ $offer->variant->size_value }}</td>
                        <td class="num">@rial($offer->variant->price)</td>
                        <td><input type="number" name="price_override" value="{{ $offer->price_override }}" placeholder="مرکزی" style="max-width:150px"></td>
                        <td>
                            <select name="is_active" style="max-width:100px">
                                <option value="1" @selected($offer->is_active)>بله</option>
                                <option value="0" @selected(! $offer->is_active)>خیر</option>
                            </select>
                        </td>
                        <td><button class="btn btn--small">ذخیره</button></td>
                    </form>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">سبد این شعبه خالی است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $offers->links() }}
@endsection
