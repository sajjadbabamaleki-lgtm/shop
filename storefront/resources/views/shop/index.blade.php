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
        {{-- `vp-listing-panel` is the listing's own hook, and it exists so a
             phone rule can take this page's frame off without taking it off
             the basket, the product page, the checkout and the account, which
             are all built on the same `.vp-shop-panel`. --}}
        <div class="vp-shop-panel vp-listing-panel">

            {{-- The phone's own top bar: back, search, filter.

                 Built from the client's reference screenshot. It is one row on
                 a phone and hidden above 992, where the page keeps the heading
                 and the sidebar it already had — this is a phone layout laid
                 over a desktop one, not a replacement for it.

                 The filter is a <details>, so the panel opens with no
                 JavaScript at all and the page keeps its promise that the URL
                 decides what is shown. --}}
            <div class="vp-shop-top">
                {{-- A fixed destination, not `url()->previous()`.

                     That was the first cut and it is a real fault, not a style
                     preference: it puts the referrer into the page's HTML, so
                     the same URL renders differently for two visitors and
                     nothing downstream can cache it. `CataloguePagesTest`
                     caught it by comparing two requests for the same listing.

                     One step out, always: a category or a search result goes
                     up to the whole shop, and the shop itself goes home. --}}
                <a class="vp-shop-back" href="{{ ($filters['category'] || $filters['q']) ? storefront_route('shop') : storefront_route('home') }}" aria-label="بازگشت"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>

                <form class="vp-shop-find" action="{{ storefront_route('search') }}" method="get" role="search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    {{-- Shorter than the desktop's, which is a different input in a
                         different box: at 390 the field is 215 wide and the long
                         one was cut mid-word. --}}
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="چی می‌خوای؟" aria-label="جست‌وجو در محصولات">
                </form>

                {{-- A <details>, so the panel opens with no JavaScript and the
                     page keeps its promise that the URL decides what is shown.
                     The rail itself is unchanged — it is the same include the
                     desktop renders in its sidebar, just folded away here. --}}
                <details class="vp-shop-filter">
                    <summary class="vp-shop-filter-btn"><i class="fa-solid fa-sliders" aria-hidden="true"></i>فیلتر</summary>
                    <div class="vp-shop-filter-panel">@include('shop.filters')</div>
                </details>
            </div>

            {{-- «اون قسمت پاپلر و لاتستو اینا هم باید باشه». Links, not a
                 select: a sort is a place you can be, so it belongs in the URL
                 and in the back button. Each one carries the other filters
                 with it or changing the order would throw the filters away. --}}
            @php
                $carry = array_filter([
                    'q' => $filters['q'],
                    'brand' => $filters['brand'],
                    'size' => $filters['size'],
                    'color' => $filters['color'],
                    'category' => $filters['category']?->slug,
                    'sale' => $filters['sale'] ? 1 : null,
                ]);
                $priceOn = in_array($filters['sort'], \App\Http\Controllers\ShopController::PRICE_TABS, true);
                // The price tab toggles rather than picking: tapping it once
                // sorts up, tapping it again sorts down, which is what the
                // reference's caret means.
                $priceNext = $filters['sort'] === 'cheapest' ? 'dearest' : 'cheapest';
            @endphp
            <nav class="vp-shop-tabs" aria-label="ترتیب">
                @foreach (\App\Http\Controllers\ShopController::TABS as $key)
                    <a class="vp-shop-tab{{ $filters['sort'] === $key ? ' is-on' : '' }}"
                       href="{{ storefront_route('shop') }}?{{ http_build_query($carry + ['sort' => $key]) }}">{{ $sorts[$key] }}</a>
                @endforeach
                <a class="vp-shop-tab vp-shop-tab-price{{ $priceOn ? ' is-on' : '' }}"
                   href="{{ storefront_route('shop') }}?{{ http_build_query($carry + ['sort' => $priceNext]) }}">قیمت<i class="fa-solid fa-chevron-{{ $filters['sort'] === 'dearest' ? 'up' : 'down' }}" aria-hidden="true"></i></a>
            </nav>

            {{-- The client's own categories, not the reference's watches and
                 beauty. Same list and same order as the phone drawer and the
                 home page's tiles, with the icons config/storefront.php already
                 carries for them. --}}
            <nav class="vp-shop-strip" aria-label="دسته‌بندی‌ها">
                @foreach ($strip as $cat)
                    <a class="vp-shop-cat{{ $filters['category']?->is($cat) ? ' is-on' : '' }}" href="{{ storefront_route('category', $cat) }}">
                        <img src="{{ asset(config('storefront.category_icons.'.$cat->slug, config('storefront.category_icons.default'))) }}" alt="" aria-hidden="true">
                        <span>{{ $cat->name }}</span>
                    </a>
                @endforeach
            </nav>

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

                {{-- The desktop's sidebar. On a phone the same include is
                     inside the top bar's <details> instead, and this copy is
                     hidden — one rail, rendered twice, only ever shown once.
                     Rendering it twice rather than moving it keeps the desktop
                     layout exactly as it was; the phone's copy is inside a
                     closed <details>, so it costs markup and no paint. --}}
                <div class="vp-shop-rail-desktop">@include('shop.filters')</div>

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
