@extends('layouts.panel')
@include('panel.vendor.menu')
@section('title', 'محصولات من')
@section('heading', 'محصولات من')
@section('actions')
    <a class="btn btn--gold btn--small" href="{{ route('panel.vendor.offers.create', $vendor) }}">افزودن محصول</a>
@endsection

@section('content')
    <form method="get" class="pane pane--tight row">
        <input type="search" name="q" value="{{ $search }}" placeholder="نام محصول">
        <select name="status" style="max-width:160px">
            <option value="">همه وضعیت‌ها</option>
            @foreach(['active' => 'فعال', 'draft' => 'پیش‌نویس', 'paused' => 'متوقف'] as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn--small">فیلتر</button>
    </form>

    <div class="pane scroller" style="margin-top:18px">
        <table>
            <thead>
            <tr><th>محصول</th><th>رنگ / سایز</th><th>قیمت</th><th>موجودی</th><th>وضعیت</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($offers as $offer)
                <tr>
                    <td>
                        {{ $offer->product->title }}
                        @if($offer->product->brand)<span class="faint">{{ $offer->product->brand->name }}</span>@endif
                    </td>
                    <td class="faint">{{ $offer->variant->display_color }} / {{ $offer->variant->size_value }}</td>
                    <td class="num">@rial($offer->price)</td>
                    <td class="num">
                        {{ $offer->sellable_stock }}
                        @if($offer->stock_reserved > 0)<span class="faint">({{ $offer->stock_reserved }} رزرو)</span>@endif
                    </td>
                    <td>
                        <span @class(['tag', 'tag--good' => $offer->status === 'active'])>
                            {{ ['active' => 'فعال', 'draft' => 'پیش‌نویس', 'paused' => 'متوقف'][$offer->status] }}
                        </span>
                    </td>
                    <td><a class="btn btn--quiet btn--small" href="{{ route('panel.vendor.offers.edit', [$vendor, $offer]) }}">ویرایش</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">هنوز محصولی اضافه نکرده‌اید.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $offers->links() }}
@endsection
