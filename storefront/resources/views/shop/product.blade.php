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
                    {{-- `is-supplied` for the same reason the card has it: the
                         framing below — 76% of the tile, fitted — is measured
                         for this shop's cut-outs, and a supplier's photograph
                         has its own margin already in it. --}}
                    <div class="vp-pdp-shot{{ $product->source ? ' is-supplied' : '' }}">
                        {{-- The brand's name used to sit behind the shoe here,
                             very faint, the way the reference screen has it.
                             «اون نوشته پشت کفش که نوشته گلدن گوس باید پاک بشه
                             بکگراند محصول و محیط باید سفید صددرصد باشه» — it is
                             gone, and with it the only thing on this half of
                             the screen that was not white. Every photograph in
                             the catalogue is a cut-out on transparency, so what
                             is left behind the shoe is the page itself, 255 at
                             every pixel. `brands.name_latin` is still a real
                             column and nothing else reads it. --}}
                        {{-- More than one photograph is a strip you can swipe,
                             and one is the picture it always was.

                             The strip is `scroll-snap`, so the phone's own
                             gesture turns the page with nothing loaded and
                             nothing to go wrong; the marks below it are
                             anchors, so they work the same way. The script
                             underneath only spares the page the jump an anchor
                             makes and lights the mark that is showing — take it
                             away and this is still a gallery. --}}
                        @if ($gallery->count() > 1)
                            <div class="vp-pdp-frames">
                                @foreach ($gallery as $i => $shot)
                                    <img id="vp-shot-{{ $product->id }}-{{ $i }}"
                                         src="{{ asset($shot->path) }}"{!! photo_srcset($shot->path) !!}
                                         alt="{{ $product->title }}"
                                         @if ($i) loading="lazy" @endif>
                                @endforeach
                            </div>
                        @elseif ($product->primaryMedia())
                            <img src="{{ asset($product->imagePath()) }}"{!! photo_srcset($product->imagePath()) !!} alt="{{ $product->title }}">
                        @endif

                        {{-- The name and the price, on the photograph — «اسم
                             کفش و قیمتو برام ببر رو یه بیضی شیشه ای مایل به
                             سفید بزار قسمت پایین کفش و از تو کارت حذفش کن».

                             Phone only: `.vp-pdp-head` above is `display: none`
                             there and draws normally at every other width, so
                             this plate is drawn only where that one is not.
                             Both are in the markup at every width — the h1 and
                             its price stay in the document for anything reading
                             it rather than looking at it — and exactly one of
                             them is ever visible.

                             Inside the shot rather than over the gallery, so
                             the frame's own `overflow: hidden` clips it to the
                             photograph's corner if it ever grows. --}}
                        <div class="vp-pdp-plate">
                            <span class="vp-pdp-plate-name">{{ $product->title }}</span>
                            <span class="vp-pdp-plate-price">{{ toman($offer->price) }} <span>تومان</span></span>
                        </div>
                    </div>

                    {{-- The two top corners: the close on the left, the
                         rating on the right. Phone only — the desktop page has
                         neither, and its rules turn both off.

                         **A close, not a favourite.** «فقط یک آیکون مربع ضبدر
                         بیار بجای مربع قلب» — and with the header off this page
                         again, it is also the only way off the screen, so it is
                         a link to somewhere rather than `history.back()`:
                         somebody who arrived from a search engine or a shared
                         link has no history to go back to, and it lands on the
                         shoe's own category instead.

                         Outside the shot rather than inside it. They were its
                         children until the photograph went to 80% and centred,
                         which took them in with it; they belong to the screen's
                         corners — «از راست ۱۲ پیکسل و از چپ هم ۱۲ پیکسل» — so
                         they hang off the block that spans the full line. --}}
                    @php
                        $back = $product->categories->isNotEmpty()
                            ? storefront_route('category', $product->categories->first())
                            : storefront_route('shop');
                    @endphp

                    <a class="vp-pdp-close" href="{{ $back }}" aria-label="بستن">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </a>

                    @if (config('storefront.placeholders.rating'))
                        <span class="vp-pdp-rate">
                            {{ fa_number(config('storefront.placeholders.rating')) }}
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                        </span>
                    @endif

                    @php
                        // A full block, not `@php(...)`: the one-line form does
                        // not survive the parentheses in this expression — it
                        // compiles to `<?php(` and takes the rest of the
                        // template with it, which is how $sellerCount below
                        // came to be undefined.
                        $live = $gallery->count() > 1;
                    @endphp

                    @if ($shots->count() > 1)
                        {{-- Dots for the strip under it, the reference's own.
                             They say how many there are and which one is
                             showing. They became controls the day a product
                             arrived with five real photographs; while the row
                             is standing in for a single one they are still
                             what they were, because there is nothing to switch
                             to. --}}
                        <div class="vp-pdp-dots" @unless ($live) aria-hidden="true" @endunless>
                            @foreach ($shots as $shot)
                                @if ($live)
                                    <a class="vp-pdp-dot{{ $loop->first ? ' is-on' : '' }}"
                                       data-vp-shot="{{ $loop->index }}"
                                       href="#vp-shot-{{ $product->id }}-{{ $loop->index }}"
                                       aria-label="عکس {{ fa_number($loop->iteration) }}"></a>
                                @else
                                    <span @class(['vp-pdp-dot', 'is-on' => $loop->first])></span>
                                @endif
                            @endforeach
                        </div>

                        @include('shop.gallery', ['shots' => $shots, 'live' => $live, 'product' => $product])
                    @endif
                </div>

                <div class="vp-pdp-info">
                    {{-- Name on one side, price on the other, the brand under the
                         name — the reference's arrangement. The price block moves
                         up here from below on a phone; above 992 it stays where it
                         was, under the title, where there is room for it. --}}
                    {{-- Desktop only, all three of these: the reference the
                         client sent for the desktop puts the maker over the
                         name and the rating under it, and the phone's own
                         arrangement — settled over a dozen rounds — is not
                         touched. The stylesheet hides them below 992. --}}
                    @if ($product->brand)
                        <a class="vp-pdp-maker" href="{{ storefront_route('shop', ['brand' => $product->brand->slug]) }}">
                            @if ($product->brand->logo_path)
                                <img src="{{ asset($product->brand->logo_path) }}" alt="" loading="lazy">
                            @endif
                            <span>{{ $product->brand->name }}</span>
                        </a>
                    @endif

                    <div class="vp-pdp-head">
                        <div class="vp-pdp-naming">
                            <h1 class="vp-pdp-title">{{ $product->title }}</h1>

                            {{-- The line under the name. On the phone it is
                                 the sale — «زیر اسم به جای اون گلدن گوس زرد
                                 بنویس ۳۰٪ تخفیف پله ای» — and the number in it
                                 is the offer's own cut, not the words'. A shoe
                                 with no cut keeps the brand there, which is
                                 what the desktop shows either way.

                                 It says «پله‌ای» only when the offer really is
                                 in the stepped sale, and a campaign is a thing
                                 with dates: an imported product carries the
                                 supplier's own before-price and no window, so
                                 the line would have announced it as part of a
                                 sale it has nothing to do with. The cut is the
                                 same number either way. --}}
                            @if ($offer->discountPercent())
                                <span class="vp-pdp-ladder">٪{{ fa_number($offer->discountPercent()) }} {{ $offer->promotion_starts_at || $offer->promotion_ends_at ? 'تخفیف پله‌ای' : 'تخفیف' }}</span>
                            @endif

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

                    {{-- The stars, and deliberately no count beside them.

                         The reference reads «25k+ Total Reviews». There is no
                         review table, so a count would be a fabricated number
                         wearing the clothes of a counted one — the thing this
                         repo's config comment on `placeholders.rating` exists
                         to forbid. The average is the same admitted stand-in
                         the phone already shows; set that config to null and
                         this row leaves the page with it. --}}
                    @if (config('storefront.placeholders.rating'))
                        <div class="vp-pdp-stars">
                            <span class="vp-pdp-stars-row" aria-hidden="true">
                                @for ($i = 0; $i < 5; $i++)<i class="fa-solid fa-star"></i>@endfor
                            </span>
                            <span class="vp-pdp-stars-say">امتیاز {{ fa_number(config('storefront.placeholders.rating')) }} از ۵</span>
                        </div>
                    @endif

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
                            {{-- For the heart below, which posts this same form
                                 to the wishlist. The basket ignores it. --}}
                            <input type="hidden" name="product" value="{{ $product->id }}">

                            <div class="vp-pick-head">
                                <h2 class="vp-pdp-choice-title">انتخاب سایز</h2>
                                <span class="vp-pick-note">{{ fa_number($simple->count()) }} سایز موجود</span>
                            </div>

                            {{-- The EU/US/CM switch was here. «اون ۳ حالت سایز
                                 حذف بشه و فقط شماره سایز بمونه مث نسخه موبایل»
                                 — the phone never had it, and one page showing
                                 the same shoe in two numbering systems was a
                                 choice nobody had asked to make. The chips are
                                 EU now, everywhere, and `/size-guide` is where
                                 the conversion table lives. --}}

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

                                {{-- Desktop only. «خرید فوری» is the same add —
                                     same stock check, same reservation — with
                                     `buy_now` telling the controller to land on
                                     the checkout instead of the basket, so there
                                     is no second path that could drift from the
                                     first. --}}
                                <button type="submit" name="buy_now" value="1" class="vp-pick-now">خرید فوری</button>

                                {{-- Same form, different destination. A form
                                     cannot nest inside another, and the heart
                                     belongs on this row — `formaction` is the
                                     one thing in HTML that gives a button its
                                     own target. The wishlist reads `product`
                                     and ignores the rest. --}}
                                <button type="submit" class="vp-pdp-like"
                                        formaction="{{ storefront_route('account.wishlist.add') }}"
                                        aria-label="افزودن به علاقه‌مندی‌ها">
                                    <i class="fa-regular fa-heart" aria-hidden="true"></i>
                                </button>
                            </div>

                            {{-- What the reference puts under the buttons. It used
                                 to promise free delivery above a threshold, off
                                 `storefront.checkout`. That threshold is not what
                                 the shopper is charged any more — delivery is the
                                 shipping method they pick at checkout, and two of
                                 the three are پس‌کرایه — so this says what is
                                 actually true instead of a promise the basket no
                                 longer honours. --}}
                            <p class="vp-pdp-ship">
                                <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                                ارسال با پست پیشتاز، تیپاکس یا پست معمولی؛ روش را هنگام ثبت سفارش انتخاب می‌کنید
                            </p>
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
                        <div @class(['vp-pdp-desc', 'is-standin' => ! $product->description])>
                            {{-- Desktop only: the reference heads this paragraph.
                                 The phone gets the same words in its own
                                 `.vp-pdp-about` panel further down the page and
                                 does not need a second heading. --}}
                            <h2 class="vp-pdp-desc-title">توضیحات محصول</h2>

                            {{-- **Four lines, then a way to see the rest.**
                                 «توضیحات محصول باید ۴ خطش مشخص باشه و یه شو مور
                                 بزاری براش» — the blurb ran to eleven lines and
                                 pushed the whole column past the photograph
                                 beside it, which is what «هم ارتفاع» is about.

                                 A checkbox and a label, not a script: the same
                                 way every other control on this page works, so
                                 it opens with the keyboard and cannot break.
                                 The input is before the text because CSS can
                                 only look forward from `:checked` to a sibling.
                                 It is `aria-hidden` with the label carrying the
                                 words, so a screen reader is told «متن کامل»
                                 rather than «checkbox, unchecked». --}}
                            <input class="vp-pdp-more-in" type="checkbox" id="vp-desc-{{ $product->id }}" aria-hidden="true" tabindex="-1">
                            <p class="vp-pdp-desc-text">{{ $blurb }}</p>
                            <label class="vp-pdp-more" for="vp-desc-{{ $product->id }}" role="button" tabindex="0">
                                <span class="vp-pdp-more-open">متن کامل</span>
                                <span class="vp-pdp-more-shut">بستن</span>
                            </label>
                        </div>
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

        {{-- «حالا توضیحات کفش بیاد اولین آیتم بعد از کارت بعد نمونه های مشابه /
             عنوان توضیحات کفش و یه متن ۴ خطی در مورد گلدن گوس زیرش».

             The first thing under the card and above the related shelf, on the
             phone. The desktop's description is where it has always been,
             inside the right-hand column, and this block is not drawn there.

             Three things are looked for, in this order: the shoe's own
             description, typed in the panel; the brand's paragraph from
             `placeholders.brand_blurbs`; and the generic line. The brand's is
             about the maker rather than about this pair — where it is from,
             what it is known for — because a paragraph about this shoe's
             leather or fit would be inventing its specification. --}}
        @php
            $about = $product->description
                ?: (config('storefront.placeholders.brand_blurbs.'.($product->brand?->slug ?? '—'))
                    ?: config('storefront.placeholders.description'));
        @endphp

        @if ($about)
            <div class="vp-shop-panel vp-pdp-about">
                <h2 class="vp-pdp-choice-title">توضیحات کفش</h2>
                <p>{{ $about }}</p>
            </div>
        @endif

        @if ($related->isNotEmpty())
            <div class="vp-shop-panel vp-pdp-related">
                <h2 class="vp-shop-title">کفش‌های مشابه در همین بودجه</h2>
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
