@extends('layouts.storefront')

{{--
    One shoe.

    The same white pane as the listing, holding a photograph on one side and
    everything you need to decide on the other. The price, the sizes and the
    stock line are all this branch's — a shoe reached at /shiraz is Shiraz's
    price and Shiraz's shelf, and the page says which shop it is quoting.

    The basket button does nothing yet. There is no cart until the next phase,
    and a button that silently fails is worse than one that says so, so it is
    disabled and labelled rather than dressed up as working.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-pdp">

            <nav class="vp-pdp-crumbs" aria-label="مسیر">
                <a href="{{ storefront_route('home') }}">خانه</a>
                <span aria-hidden="true">/</span>
                <a href="{{ storefront_route('shop') }}">محصولات</a>
                @if ($product->categories->isNotEmpty())
                    <span aria-hidden="true">/</span>
                    <a href="{{ storefront_route('category', $product->categories->first()) }}">{{ $product->categories->first()->name }}</a>
                @endif
            </nav>

            <div class="vp-pdp-body">

                <div class="vp-pdp-gallery">
                    <div class="vp-pdp-shot">
                        @if ($product->primaryMedia())
                            <img src="{{ asset($product->primaryMedia()->path) }}" alt="{{ $product->title }}">
                        @endif
                    </div>
                    @if ($gallery->count() > 1)
                        <div class="vp-pdp-thumbs">
                            @foreach ($gallery as $shot)
                                <span class="vp-pdp-thumb"><img src="{{ asset($shot->path) }}" alt="" loading="lazy"></span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="vp-pdp-info">
                    @if ($product->brand)
                        <a class="vp-pdp-brand" href="{{ storefront_route('shop', ['brand' => $product->brand->slug]) }}">{{ $product->brand->name }}</a>
                    @endif

                    <h1 class="vp-pdp-title">{{ $product->title }}</h1>

                    {{-- The price leads, then what it was, then the cut. On an RTL
                         page the first child is the rightmost, so this is the order
                         the eye takes them in. --}}
                    <div class="vp-pdp-price">
                        <strong>{{ toman($offer->price) }} <span>تومان</span></strong>
                        @if ($offer->discountPercent())
                            <del>{{ toman($offer->compare_at_price) }}</del>
                            <span class="vp-pdp-cut">٪{{ fa_number($offer->discountPercent()) }}</span>
                        @endif
                    </div>

                    @if ($colorways->count() > 1)
                        <div class="vp-pdp-choice">
                            <h2 class="vp-pdp-choice-title">رنگ</h2>
                            <div class="vp-pdp-options">
                                @foreach ($colorways as $colorway)
                                    <span class="vp-pdp-option{{ $colorway['sellable'] ? '' : ' is-out' }}">{{ $colorway['display_color'] }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- One block per size, each listing everybody who can supply it.
                         A basket line is a size *from a seller*, so choosing both has
                         to be the same action as adding it — which is why every row
                         is its own little form rather than a radio somewhere else. --}}
                    <div class="vp-pdp-choice">
                        <h2 class="vp-pdp-choice-title">سایز و فروشنده</h2>

                        @if ($sizes->isEmpty())
                            <p class="vp-pdp-out">فعلاً موجود نیست.</p>
                        @endif
                    </div>

                    @foreach ($sizes as $variant)
                        <div class="vp-sellers">
                            <h3 class="vp-sellers-title">سایز {{ fa_number((int) $variant->size_value) }}</h3>

                            @foreach ($sellers[$variant->id] as $seller)
                                <div @class(['vp-seller', 'is-ours' => $seller['vendor'] === null])>
                                    <span class="vp-seller-who">
                                        <span class="vp-seller-name">{{ $seller['vendor']?->name ?? 'ویکی پلاس' }}</span>
                                        <span class="vp-seller-stock">{{ fa_number($seller['available']) }} عدد موجود</span>
                                    </span>
                                    <span class="vp-seller-buy">
                                        <span class="vp-seller-price">{{ toman($seller['offer']->price) }} تومان</span>
                                        <form method="post" action="{{ storefront_route('cart.add') }}">
                                            @csrf
                                            <input type="hidden" name="variant" value="{{ $variant->id }}">
                                            @if ($seller['vendor'])<input type="hidden" name="vendor" value="{{ $seller['vendor']->id }}">@endif
                                            <button type="submit" class="vp-seller-add">افزودن</button>
                                        </form>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    @if ($product->description)
                        <div class="vp-pdp-desc"><p>{{ $product->description }}</p></div>
                    @endif

                    <dl class="vp-pdp-facts">
                        @if ($product->material)
                            <div><dt>جنس</dt><dd>{{ $product->material }}</dd></div>
                        @endif
                        @if ($product->use_case)
                            <div><dt>مناسب برای</dt><dd>{{ $product->use_case }}</dd></div>
                        @endif
                        @if ($product->care_instructions)
                            <div><dt>نگهداری</dt><dd>{{ $product->care_instructions }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="vp-shop-panel vp-pdp-related">
                <h2 class="vp-shop-title">شبیه به این</h2>
                <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xxl-4 vp-shop-grid">
                    @foreach ($related as $other)
                        {{-- Named, not reused: looping "as $product" would overwrite
                             the page's own product for everything after it. --}}
                        <div class="col">@include('shop.card', ['product' => $other])</div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
