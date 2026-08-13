@extends('layouts.admin')

@section('content')
<section class="vp-shop-panel">
    <div class="vp-shop-head">
        <div class="vp-shop-heading">
            <h1 class="vp-shop-title">کاتالوگ</h1>
            <p class="vp-shop-count">محصول، سایز و عکس — مشترک بین همه شعبه‌ها</p>
        </div>
        <div class="vp-admin-inline">
            <form class="vp-shop-search" method="get" action="{{ route('admin.catalogue') }}" role="search">
                <input type="search" name="q" value="{{ $q }}" placeholder="نام محصول" aria-label="جست‌وجو">
                <button type="submit" aria-label="جست‌وجو"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
            </form>
            <a class="vp-admin-save" href="{{ route('admin.product.create') }}">محصول تازه</a>
        </div>
    </div>

    @if ($products->isEmpty())
        <div class="vp-empty">
            <span class="vp-empty-mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M24 6 L42 16 v16 L24 42 L6 32 V16 z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path></svg></span>
            <p class="vp-empty-say">هنوز محصولی ثبت نشده.</p>
            <a class="vp-empty-out" href="{{ route('admin.product.create') }}">اولین محصول را بساز</a>
        </div>
    @else
        <table class="vp-admin-table is-wide">
            <thead><tr><th>محصول</th><th>برند</th><th>سایزها</th><th>وضعیت</th><th>انتشار</th><th></th></tr></thead>
            <tbody>
            @foreach ($products as $product)
                <tr>
                    <td>{{ $product->title }}<br><span class="vp-cart-meta">{{ $product->slug }}</span></td>
                    <td>{{ $product->brand?->name ?? '—' }}</td>
                    <td>{{ fa_number($product->variants_count) }}</td>
                    <td>{{ $product->status === 'active' ? 'فعال' : 'غیرفعال' }}</td>
                    <td>{{ $product->published_at ? fa_date($product->published_at) : 'منتشر نشده' }}</td>
                    <td><a class="vp-admin-save" href="{{ route('admin.product.edit', $product) }}">ویرایش</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $products->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
