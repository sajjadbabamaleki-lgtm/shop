@extends('layouts.admin')

@section('title', 'محصولات')

{{--
    The catalogue — §5's product list.

    Not a branch's: a product, its sizes and its photographs are the same in
    every shop, and it is the price and the stock that belong to a branch. So
    this screen is the platform's and the two beside it — «موجودی» and
    «قیمت‌ها» — are this branch's, which is the difference worth noticing
    before editing anything here.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">محصول، سایز و عکس — مشترک بین همه شعبه‌ها</p>

    <div class="vp-adm-head-side">
        <form class="vp-adm-filters" method="get" action="{{ route('admin.catalogue') }}" role="search">
            <label class="visually-hidden" for="vp-cat-q">جست‌وجو</label>
            <input id="vp-cat-q" class="vp-adm-search" type="search" name="q" value="{{ $q }}" placeholder="نام محصول">
            <button type="submit" class="vp-adm-apply">جست‌وجو</button>
            @if ($q !== '')
                <a class="vp-adm-clear" href="{{ route('admin.catalogue') }}">پاک کردن</a>
            @endif
        </form>

        <a class="vp-adm-apply" href="{{ route('admin.product.create') }}">محصول تازه</a>
    </div>
</div>

<section class="vp-adm-card">
    @if ($products->isEmpty())
        {{-- Two different sentences, because they are two different situations:
             a catalogue with nothing in it, and a search that matched nothing. --}}
        <p class="vp-adm-empty">
            @if ($q === '')
                هنوز محصولی ثبت نشده. با «محصول تازه» اولی را بساز.
            @else
                محصولی با این نام پیدا نشد.
            @endif
        </p>
    @else
        <table class="vp-admin-table">
            <thead><tr><th>محصول</th><th>برند</th><th>سایزها</th><th>وضعیت</th><th>انتشار</th><th></th></tr></thead>
            <tbody>
            @foreach ($products as $product)
                <tr>
                    <td>
                        <a href="{{ route('admin.product.edit', $product) }}">{{ $product->title }}</a>
                        <span class="vp-adm-sub">{{ $product->slug }}</span>
                    </td>
                    <td>{{ $product->brand?->name ?? '—' }}</td>
                    <td>{{ fa_number($product->variants_count) }}</td>
                    <td>
                        <span class="vp-adm-badge is-{{ $product->status === 'active' ? 'delivered' : 'cancelled' }}">
                            {{ $product->status === 'active' ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </td>
                    <td>{{ $product->published_at ? fa_date($product->published_at) : 'منتشر نشده' }}</td>
                    <td><a class="vp-adm-mini is-quiet" href="{{ route('admin.product.edit', $product) }}">ویرایش</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $products->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
