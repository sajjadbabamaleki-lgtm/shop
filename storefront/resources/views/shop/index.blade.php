@extends('layouts.storefront')

{{--
    The listing — the shop, a category and a search result, which are one page
    with a different opening filter.

    The white pane, its 20px margins, its 50px radius and its ring are
    .vp-best-panel's, quoted rather than reinvented: the page already has a
    material for "a big card holding a section", and a second one that was
    nearly the same would read as a mistake.

    The rail is a plain GET form. No JavaScript decides what is shown — the URL
    does, so a filtered listing can be linked to, bookmarked, and gone back to.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    {{-- The count lives in the bar above the grid and nowhere
                         else: it was printed twice, three centimetres apart. --}}
                    <h1 class="vp-shop-title">{{ $heading }}</h1>
                </div>

                <form class="vp-shop-search" action="{{ storefront_route('search') }}" method="get" role="search">
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="دنبال چی می‌گردی؟" aria-label="جست‌وجو در محصولات">
                    <button type="submit" aria-label="جست‌وجو"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                </form>
            </div>

            <div class="vp-shop-body">

                @include('shop.filters')

                <div class="vp-shop-main">

                    <div class="vp-shop-bar">
                        {{-- The count sits with the control that changes the order,
                             because "۵ کالا" and "ترتیب" are one thought: what is
                             in this list, and in what order. --}}
                        <span class="vp-shop-bar-count">{{ fa_number($products->total()) }} کالا</span>

                        <form class="vp-shop-sort" method="get" action="{{ storefront_route('shop') }}">
                            {{-- Sorting must not throw the other filters away, so they ride along hidden. --}}
                            @foreach (['q' => $filters['q'], 'brand' => $filters['brand'], 'size' => $filters['size'], 'color' => $filters['color']] as $name => $value)
                                @if ($value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                            @endforeach
                            @if ($filters['category'])<input type="hidden" name="category" value="{{ $filters['category']->slug }}">@endif
                            @if ($filters['sale'])<input type="hidden" name="sale" value="1">@endif

                            <label for="vp-sort">ترتیب</label>
                            <select id="vp-sort" name="sort" onchange="this.form.submit()">
                                @foreach ($sorts as $key => $label)
                                    <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <noscript><button type="submit">اعمال</button></noscript>
                        </form>
                    </div>

                    @if ($products->isEmpty())
                        <div class="vp-empty">
                            <span class="vp-empty-mark" aria-hidden="true">
                                <svg viewBox="0 0 48 48"><circle cx="21" cy="21" r="13" fill="none" stroke="currentColor" stroke-width="3"></circle><path d="M31 31 L42 42" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
                            </span>
                            <p class="vp-empty-say">چیزی با این مشخصات پیدا نشد.</p>
                            <a class="vp-empty-out" href="{{ storefront_route('shop') }}">دیدن همه محصولات</a>
                        </div>
                    @else
                        <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xxl-4 vp-shop-grid">
                            @foreach ($products as $product)
                                <div class="col">@include('shop.card')</div>
                            @endforeach
                        </div>

                        <div class="vp-shop-pages">{{ $products->links('pagination.vikyplus') }}</div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</section>
@endsection
