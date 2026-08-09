@extends('layouts.store')
@section('title', $product->title.' · '.$tenant->storeName())

@section('content')
    <div class="grid grid--2" style="margin-top:14px">
        <div class="pane">
            @php $image = $product->media->firstWhere('is_primary', true) ?? $product->media->first(); @endphp
            @if($image)
                <img class="thumb" style="aspect-ratio:1" src="{{ asset($image->path) }}" alt="{{ $image->alt ?? $product->title }}">
            @endif
        </div>

        <div class="pane">
            <h1>{{ $product->title }}</h1>
            @if($product->brand)<p class="faint">{{ $product->brand->name }}</p>@endif
            @if($product->description)<p class="muted">{{ $product->description }}</p>@endif

            <h3 style="margin-top:18px">رنگ و سایز موجود</h3>
            <div class="scroller">
                <table>
                    <thead>
                    <tr><th>رنگ</th><th>سایز</th><th>قیمت</th><th>موجودی</th></tr>
                    </thead>
                    <tbody>
                    @foreach($offers as $offer)
                        <tr>
                            <td>{{ $offer['variant']->display_color }}</td>
                            <td class="num">{{ $offer['variant']->size_value }}</td>
                            <td class="price">
                                @toman($offer['price'])
                                @if($offer['compare_at'] && $offer['compare_at'] > $offer['price'])
                                    <s>@rial($offer['compare_at'])</s>
                                @endif
                            </td>
                            <td>
                                @if($offer['available'] > 0)
                                    <span class="tag tag--good">{{ $offer['available'] }} عدد</span>
                                @else
                                    <span class="tag">ناموجود</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Checkout is the next phase; the seller's own contact details
                 are here so the shop is usable in the meantime. --}}
            <a class="btn btn--gold" style="margin-top:16px" href="{{ route('store.contact') }}">سفارش و هماهنگی</a>
        </div>
    </div>
@endsection
