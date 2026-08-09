@extends('layouts.store')
@section('title', 'محصولات · '.$tenant->storeName())

@section('content')
    <form method="get" action="{{ route('store.catalog') }}" class="pane pane--tight row" style="margin-top:14px">
        <input type="search" name="q" value="{{ $search }}" placeholder="جست‌وجو در محصولات این فروشگاه">
        <button class="btn btn--gold">بگرد</button>
    </form>

    @include('store.partials.product-grid', ['products' => $products, 'prices' => $prices])

    <div class="pagination">{{ $products->links() }}</div>
@endsection
