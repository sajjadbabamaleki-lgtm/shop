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

            {{-- Nothing above the photograph on a phone — «بالاش ننویس هیچی».

                 The back chevron and the favourite went first, then the title
                 with them; the site's own header band is taken off this page
                 below 992 in the stylesheet, for «عکس اصلی هم نباید هدر داشته
                 باشه». The page opens on the shoe.

                 **The basket and the menu were in that band.** They are on
                 every other page and in the drawer; here the way on is the
                 «افزودن به سبد» bar at the foot. Said out loud because it is
                 the cost of the ask rather than an oversight. --}}

            <nav class="vp-pdp-crumbs d-none d-lg-flex" aria-label="مسیر">
                <a href="{{ storefront_route('home') }}">خانه</a>
                <span aria-hidden="true">/</span>
                <a href="{{ storefront_route('shop') }}">محصولات</a>
                @if ($product->categories->isNotEmpty())
                    <span aria-hidden="true">/</span>
                    <a href="{{ storefront_route('category', $product->categories->first()) }}">{{ $product->categories->first()->name }}</a>
                @endif
            </nav>

            <div class="vp-pdp-body">

                @php
                    // The colourway strip. Real media if there is more than one
                    // photograph; otherwise the one we have, repeated, because the
                    // client asked for the row to exist while the photographs are
                    // still coming — see `placeholders.colorway_shots`, and set it
                    // to 0 to switch this off.
                    $stand = (int) config('storefront.placeholders.colorway_shots');
                    $shots = $gallery->count() > 1
                        ? $gallery
                        : collect(array_fill(0, max(0, $stand), $product->primaryMedia()))->filter();
                @endphp

                <div class="vp-pdp-gallery">
                    <div class="vp-pdp-shot">
                        {{-- The reference's watermark: the brand's name, very
                             faint, behind the shoe. `brands.name_latin` is a
                             real column with a real value in it — this is the
                             one part of that screen's furniture the catalogue
                             can already fill. Before the <img>, so the
                             photograph paints over it without either needing a
                             z-index. --}}
                        @if ($product->brand?->name_latin)
                            <span class="vp-pdp-mark" aria-hidden="true">{{ $product->brand->name_latin }}</span>
                        @endif

                        @if ($product->primaryMedia())
                            <img src="{{ asset($product->primaryMedia()->path) }}" alt="{{ $product->title }}">
                        @endif

                    </div>

                    {{-- The two top corners: the favourite on the left, the
                         rating on the right. Phone only — the desktop page has
                         neither, and its rules turn both off.

                         Outside the shot rather than inside it. They were its
                         children until the photograph went to 80% and centred,
                         which took them in with it; they belong to the screen's
                         corners — «از راست ۱۲ پیکسل و از چپ هم ۱۲ پیکسل» — so
                         they hang off the block that spans the full line.

                         The heart is still outline rather than the reference's
                         filled one: there is no wishlist table behind it, and a
                         filled heart on every product is a state that is not
                         true. --}}
                    <button type="button" class="vp-pdp-fav" aria-label="افزودن {{ $product->title }} به علاقه‌مندی‌ها">
                        <i class="fa-regular fa-heart" aria-hidden="true"></i>
                    </button>

                    @if (config('storefront.placeholders.rating'))
                        <span class="vp-pdp-rate">
                            {{ fa_number(config('storefront.placeholders.rating')) }}
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                        </span>
                    @endif

                    @if ($shots->count() > 1)
                        {{-- Dots for the strip under it, the reference's own. They
                             say how many there are and which one is showing; they
                             are not controls, because there is nothing to switch
                             to while the row is standing in for itself. --}}
                        <div class="vp-pdp-dots" aria-hidden="true">
                            @foreach ($shots as $shot)
                                <span @class(['vp-pdp-dot', 'is-on' => $loop->first])></span>
                            @endforeach
                        </div>

                        @include('shop.gallery', ['shots' => $shots])
                    @endif
                </div>

                <div class="vp-pdp-info">
                    {{-- Name on one side, price on the other, the brand under the
                         name — the reference's arrangement. The price block moves
                         up here from below on a phone; above 992 it stays where it
                         was, under the title, where there is room for it. --}}
                    <div class="vp-pdp-head">
                        <div class="vp-pdp-naming">
                            <h1 class="vp-pdp-title">{{ $product->title }}</h1>
                            @if ($product->brand)
                                <a class="vp-pdp-brand" href="{{ storefront_route('shop', ['brand' => $product->brand->slug]) }}">{{ $product->brand->name }}</a>
                            @endif
                        </div>

                        <div class="vp-pdp-price">
                            <strong>{{ toman($offer->price) }} <span>تومان</span></strong>
                            @if ($offer->discountPercent())
                                <del>{{ toman($offer->compare_at_price) }}</del>
                                <span class="vp-pdp-cut">٪{{ fa_number($offer->discountPercent()) }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- The price leads, then what it was, then the cut. On an RTL
                         page the first child is the rightmost, so this is the order
                         the eye takes them in. --}}
                    @php
                        // How many different sellers can supply this shoe, across
                        // every size. The headline price is the cheapest of them,
                        // and saying so is the difference between a price and a
                        // number that looks arbitrary next to the rows below.
                        $sellerCount = $sellers->flatten(1)
                            ->map(fn (array $seller) => $seller['vendor']?->id ?? 0)
                            ->unique()
                            ->count();
                    @endphp

                    @if ($sellerCount > 1)
                        <p class="vp-pdp-from">ارزان‌ترین قیمت از میان {{ fa_number($sellerCount) }} فروشنده</p>
                    @endif

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

                    {{-- Sizes as chips, and the basket button at the foot of the
                         screen — the arrangement the client sent.

                         A basket line is a size *from a seller*, so the chip has to
                         carry both. Where the branch is the only seller of a size —
                         which is every size in the catalogue today — the chip is the
                         size and nothing else, exactly as the reference draws it.
                         Where a size has more than one seller, the chips cannot say
                         which, so those sizes keep their seller rows below and the
                         rows keep their own buttons.

                         One form, radios, one submit. No script: the chip is a
                         `<label>` over a radio, and the bar at the foot posts
                         whichever one is checked. --}}
                    @php
                        // A chip can only stand in for a seller row when the seller
                        // is the branch itself. One vendor selling a size is still
                        // one seller, but a chip that said only «۳۹» would take that
                        // vendor's name off the page — which is the whole point of a
                        // marketplace listing, and what `MarketplaceTest` caught.
                        $alone = fn ($v) => $sellers[$v->id]->count() === 1
                            && $sellers[$v->id]->first()['vendor'] === null;

                        $simple = $sizes->filter($alone);
                        $contested = $sizes->reject($alone);
                    @endphp

                    @if ($sizes->isEmpty())
                        <p class="vp-pdp-out">فعلاً موجود نیست.</p>
                    @endif

                    @if ($simple->isNotEmpty())
                        <form class="vp-pick" method="post" action="{{ storefront_route('cart.add') }}">
                            @csrf

                            <div class="vp-pick-head">
                                <h2 class="vp-pdp-choice-title">انتخاب سایز</h2>
                                <span class="vp-pick-note">{{ fa_number($simple->count()) }} سایز موجود</span>
                            </div>

                            @include('shop.sizes')

                            {{-- Inside the form, between the sizes and the buy
                                 row, because that is the order asked for and
                                 the buy row cannot leave the form it submits.
                                 It contributes no field. --}}
                            @include('shop.colors')

                            {{-- The bar the reference puts at the foot of the phone.
                                 It is part of this form, so the button adds whichever
                                 chip is checked. Above 992 it sits in the flow. --}}
                            {{-- On the phone this is the last row of the card —
                                 «باید اد تو کارت معادل فارسیش رو یه دکمه بیاد
                                 راست / انتخاب تعداد هم چپ همون دکمه / همه اینا
                                 باید رو کارت باشن». Above 992 it is what it has
                                 always been: the price and the button, in the
                                 flow under the sizes.

                                 The stepper is the template's own control, not
                                 a new one: `.quantity-plus` / `.quantity-minus`
                                 with a sibling `.qty-input` is what
                                 `assets/js/main.js` already binds on every
                                 page, and `CartController@add` already reads
                                 `quantity`. Nothing new had to run for it.

                                 It is phone-only; on the desktop the field is
                                 not drawn and the controller's own default of
                                 1 is what a submit means there, exactly as
                                 before. --}}
                            <div class="vp-pick-bar">
                                {{-- The number and its unit in a box of their
                                     own: the label stacks above them on the
                                     phone, and without the wrapper «تومان»
                                     becomes a third line of that column. --}}
                                <span class="vp-pick-price">
                                    <span class="vp-pick-label">قیمت</span>
                                    <span class="vp-pick-sum">{{ toman($offer->price) }} <em>تومان</em></span>
                                </span>

                                <div class="vp-qty">
                                    <button type="button" class="quantity-minus qty-btn" aria-label="یکی کمتر">
                                        <i class="fa-solid fa-minus" aria-hidden="true"></i>
                                    </button>
                                    <input class="qty-input" type="number" name="quantity" value="1" min="1" inputmode="numeric" aria-label="تعداد">
                                    <button type="button" class="quantity-plus qty-btn" aria-label="یکی بیشتر">
                                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                    </button>
                                </div>

                                <button type="submit" class="vp-pick-go">افزودن به سبد</button>
                            </div>
                        </form>
                    @endif

                    {{-- Every size, even when this shop cannot sell one of them
                         today: a shoe whose sizes are all gone still shows the
                         row, greyed, above the line that says so. Without this
                         the page would answer «همه سایزها باید باشن» only while
                         there was something to buy. --}}
                    @if ($simple->isEmpty() && $shopSizes->isNotEmpty())
                        <div class="vp-pick vp-pick-empty">
                            <div class="vp-pick-head">
                                <h2 class="vp-pdp-choice-title">انتخاب سایز</h2>
                            </div>

                            @include('shop.sizes')

                            @include('shop.colors')
                        </div>
                    @endif

                    @if ($contested->isNotEmpty())
                        <h2 class="vp-pdp-choice-title vp-pdp-sizes-title">سایزهایی با چند فروشنده</h2>
                    @endif

                    @foreach ($contested as $variant)
                        {{-- The id the size row's chip links down to. --}}
                        <div class="vp-sellers" id="size-{{ $variant->id }}">
                            <h3 class="vp-sellers-title"><span>سایز</span> {{ fa_number((int) $variant->size_value) }}</h3>

                            @foreach ($sellers[$variant->id] as $seller)
                                <div @class([
                                    'vp-seller',
                                    'is-ours' => $seller['vendor'] === null,
                                    'is-best' => $loop->first && $sellers[$variant->id]->count() > 1,
                                ])>
                                    <span class="vp-seller-who">
                                        <span class="vp-seller-name">
                                            {{ $seller['vendor']?->name ?? 'ویکی پلاس' }}
                                            @if ($loop->first && $sellers[$variant->id]->count() > 1)
                                                <em class="vp-seller-tag">ارزان‌ترین</em>
                                            @endif
                                        </span>
                                        {{-- Two or fewer left is worth saying differently: it is the
                                             difference between "in stock" and "decide now". --}}
                                        <span @class(['vp-seller-stock', 'is-low' => $seller['available'] <= 2])>
                                            {{ fa_number($seller['available']) }} عدد موجود
                                        </span>
                                    </span>
                                    <span class="vp-seller-buy">
                                        <span class="vp-seller-price">{{ toman($seller['offer']->price) }} <em>تومان</em></span>
                                        <form method="post" action="{{ storefront_route('cart.add') }}">
                                            @csrf
                                            <input type="hidden" name="variant" value="{{ $variant->id }}">
                                            @if ($seller['vendor'])<input type="hidden" name="vendor" value="{{ $seller['vendor']->id }}">@endif
                                            <button type="submit" class="vp-seller-add">افزودن به سبد</button>
                                        </form>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    {{-- The shoe's own description when it has one, and
                         `placeholders.description` when it has not — «زیرش
                         توضیحات کفش». The five demo products have none, and an
                         empty gap under the name is not what the reference
                         draws; the stand-in says nothing about the shoe, so
                         filling it in the panel is the only thing that makes
                         this paragraph specific. --}}
                    @php $blurb = $product->description ?: config('storefront.placeholders.description'); @endphp

                    @if ($blurb)
                        <div @class(['vp-pdp-desc', 'is-standin' => ! $product->description])><p>{{ $blurb }}</p></div>
                    @endif

                    {{-- Only when there is a fact to list. An empty <dl> is
                         invisible on a page that ends in white space and a hole
                         at the foot of a card, which is what this is now. --}}
                    @if ($product->material || $product->use_case || $product->care_instructions)
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
                    @endif
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="vp-shop-panel vp-pdp-related">
                <h2 class="vp-shop-title">نمونه‌های مشابه</h2>
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
