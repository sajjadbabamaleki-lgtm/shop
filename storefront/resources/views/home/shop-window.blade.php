{{--
    «فروشگاه» — the shop, in miniature, above the stepped sale.

    «الان تنها راه دسترسی به فروشگاه در هوم زدن یه دکمه یا آیکونه / میتونی یه
    مینی فروشگاه تو خود صفحه هوم بزنی؟ بالای بخش حراج پله ای». Three designs
    were drawn and the second was chosen, with eight cards rather than four —
    «تو نسخه موبایل بجای ۲ ردیف ۴ ردیف بشه تو دستاپ هم بجای یک ردیف ۲ ردیف».
    Two across and four down on a phone, four across and two down above 992.

    **Everything in it is the shop's own part, not a copy.** The search field
    and the category strip are `shop/index.blade.php`'s, class for class, and
    a card is `shop/card.blade.php` itself. The wrapper carries
    `vp-listing-panel` for the same reason the listing does: the desktop shop
    is generated from the phone's rules under that class, so these parts look
    on a desktop exactly as they do in the shop.

    The «فیلتر» button is a link to the listing with its popup open
    (`?filter=open`), not a second filter rail: the rail's facets are several
    queries, and on the live machine each costs about ten milliseconds of a
    page every visitor loads.

    Not in the static preview, for the same reason the reviews and the
    articles are not: the cards are the live catalogue, and a preview of it
    would be invented. `check-parity.js` hides it before it shoots.

    The cards are the listing's first page in its own order, **less any shoe
    with no photograph** — the payment-test items were the newest products and
    led the band as two grey boxes. Drawn only when there is something left.
--}}
@if ($shopWindow->isNotEmpty())
<section class="vp-homeshop-area" id="vp-homeshop">
    <div class="container th-container">
        <div class="vp-listing-panel vp-homeshop">
            <div class="vp-homeshop-head">
                <div>
                    <h2 class="vp-homeshop-title">فروشگاه</h2>
                    <p class="vp-homeshop-sub">جست‌وجو کن، دسته را بزن، یا از همین‌جا بخر</p>
                </div>
                <a class="vp-homeshop-all" href="{{ storefront_route('shop') }}">همه <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
            </div>

            <div class="vp-shop-top">
                <form class="vp-shop-find" action="{{ storefront_route('search') }}" method="get" role="search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" name="q" placeholder="چی می‌خوای؟" aria-label="جست‌وجو در محصولات">
                </form>
                <a class="vp-shop-filter-btn" href="{{ storefront_route('shop') }}?filter=open"><i class="fa-solid fa-sliders" aria-hidden="true"></i>فیلتر</a>
            </div>

            <nav class="vp-shop-strip" aria-label="دسته‌بندی‌ها">
                @foreach ($categories as $cat)
                    <a class="vp-shop-cat" href="{{ storefront_route('category', $cat) }}"@if ($cat->coming_soon) data-vp-soon="{{ $cat->name }}"@endif>
                        <img src="{{ asset(config('storefront.category_icons.'.$cat->slug, config('storefront.category_icons.default'))) }}" alt="" aria-hidden="true">
                        <span>{{ $cat->name }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="vp-homeshop-grid">
                @foreach ($shopWindow as $product)
                    <div>@include('shop.card')</div>
                @endforeach
            </div>

            <a class="vp-homeshop-go" href="{{ storefront_route('shop') }}">دیدن همه {{ fa_number($shopCount) }} کالا <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        </div>
    </div>
</section>
@endif
