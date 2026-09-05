{{--
    «پرفروش‌ترین‌ها» — six tiles, each a shoe with its name and price.

    The photograph is the product's own now — «از همون عکس های قسمت حراج پله ای
    استفاده کن» — so the shot, the name and the price on a tile all belong to
    the same shoe, and the tile links to that shoe rather than to a category.
    The category is still what decides how many tiles there are and in what
    order; it just no longer supplies the picture.

    Still a placeholder in one respect, and admitted as one: these are not
    actually the best sellers. Nothing counts orders yet, so the six are the
    shoes somebody chose in `/admin/front-page`, or the band's own default
    behind them.

    **Everything a tile says about a shoe is read off that shoe.** The name is
    `bandName()`, the same title the listing prints with the kind off the front;
    the price and the cut are one `offerHere()` lookup, the same one
    `shop/card.blade.php` makes. So the tile, the listing and the product's own
    page cannot disagree — «اسم کفشها و قیمتشون هم باید از فروشگاه خونده بشه».

    The brand filters below do not filter — the six tiles are categories, not
    products tagged by brand — and the colour swatches on each tile do not
    switch a variant. Both are the template's own controls, kept because the
    row is going to need them once it lists real best sellers.

    Hand-owned: theme/make-blade.js no longer regenerates this file.
--}}
<section class="space overflow-hidden overflow-hidden vp-best-section">
        <div class="vp-best-panel">
            {{-- The way out sits opposite the title rather than at the end of the
                 filter row — «مشاهده همه محصولات اینجا باید حذف بشه بیاد روبروی عنوان
                 سمت چپ». Kept in step with theme/make-rtl-page.js's BEST_HEAD by hand,
                 the way every hand-owned band is; check-parity.js is what notices. --}}
            <div class="vp-best-head">
                <h2 class="vp-best-title">پرفروش‌ترین‌ها</h2>
                <a class="vp-best-all" href="{{ page_url('shop.html') }}">مشاهده همه محصولات</a>
            </div>
            <div class="vp-best-filters">
                <button type="button" class="vp-best-filter active">همه</button>
                <button type="button" class="vp-best-filter">نایک</button>
                <button type="button" class="vp-best-filter">جردن</button>
                <button type="button" class="vp-best-filter">نیوبالانس</button>
                <button type="button" class="vp-best-filter">گلدن گوس</button>
            </div>
            <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xl-6 vp-best-row">
                @foreach ($bestSellers as $tile)
                <div class="col">
                    <div class="vp-best">
                        {{-- The tile's picture, which is not always the product's.

                             «اینام برای بخش پر فروشها» — cut-outs, because a tile
                             wants one and the catalogue's photographs carry the
                             studio's ground.

                             **By slug, not by position.** The name and the price
                             are printed directly under this picture, so a picture
                             chosen by position puts a Nike over Golden Goose's name
                             at Golden Goose's price — measured, four of six tiles
                             mislabelled, when it was built that way. A slug with no
                             entry falls back to the product's own. --}}
                        @php($shot = config('storefront.placeholders.best_sellers.photos')[$tile['product']->slug] ?? $tile['product']->primaryMedia()?->path)
                        {{-- The offer this branch has for the shoe: the price
                             the tile prints, and the cut the burst prints. One
                             lookup, so the two can never disagree. --}}
                        @php($offer = $tile['product']->offerHere())
                        @php($cut = $offer?->discountPercent())
                        <a class="vp-best-shot" href="{{ storefront_route('product', $tile['product']) }}">
                            @if ($shot)<img src="{{ asset($shot) }}"{!! photo_srcset($shot) !!} alt="{{ $tile['product']->title }}" loading="lazy">@endif
                            {{-- **The shoe's own cut, or no burst at all.**

                                 This was a flat ٪۲۵ on every other tile — «بعضی از
                                 همون کارتها» — because the band was built from a
                                 collection that could not say which shoe was
                                 discounted, so "some" had to be a rule. It can say
                                 now, and a gold ٪۲۵ beside an undiscounted price is
                                 a number the shop does not honour: «اسم کفشها و
                                 قیمتشون هم باید از فروشگاه خونده بشه». It reads
                                 `discountPercent()`, which is what the listing's
                                 card prints, so the same shoe shows the same cut
                                 here, in the shop and on its own page.

                                 The sale cards' own burst, not a variant of it —
                                 «اون ستاره تخفیف فقط در هیرو باید سفید بشه» settled
                                 that this one stays gold. The $key is prefixed so
                                 its gradient id cannot collide with the five in the
                                 sale above. Kept in step with
                                 theme/make-rtl-page.js, or check-parity.js fails. --}}
                            @if ($cut)@include('partials.deal-burst', ['key' => 'b'.$loop->index, 'percent' => $cut])@endif
                            <div class="vp-best-colors" aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span>
                            </div>
                        </a>
                        {{-- «گوشه چپ کارت پر فروش ترینها باید یه مربع اندازه ی سبد
                             خرید تو هدر بیاد و روش یه قلب بزاری». Outside the <a>,
                             because a button inside a link is invalid and a
                             favourite is not a navigation — the same reason the
                             sale card's basket sits outside its own link.

                             Outline heart: nothing is favourited, and there is no
                             wishlist behind it yet. --}}
                        <button type="button" class="vp-best-fav" aria-label="افزودن {{ $tile['product']->title }} به علاقه‌مندی‌ها"><i class="fa-regular fa-heart" aria-hidden="true"></i></button>
                        <div class="vp-best-info">
                            <div class="vp-best-label">
                                <span class="vp-best-lines">
                                    <span class="vp-best-name">{{ $tile['product']->bandName() }}</span>
                                    {{-- **The price the shop charges, not the one before
                                         the sale.** «قیمتو ایناش هم باید برابر نمونش در
                                         فروشگاه باشه» — this band printed
                                         `compare_at_price`, so the same shoe read one
                                         number here and a lower one on its own page and
                                         in the listing, which is the number a customer
                                         actually pays. `offerHere()->price` is what
                                         `shop/card.blade.php` prints, and what this
                                         does. --}}
                                    <span class="vp-best-cta"><strong>{{ toman($offer->price) }} <span>تومان</span></strong></span>
                                </span>
                            </div>
                            {{-- Two marks, one shown at a time. «ما یدونه آیکون
                                 سبد خرید که روش بعلاوه داره تو صفحه فروشگاه
                                 داریم چرا آیکون جدیدی میاری؟» — the phone's
                                 square carries the shop page's own
                                 `shopping-bag-plus`, the Tabler icon the client
                                 picked off a sheet of eleven («یک عالیه»), byte
                                 for byte the same markup as `shop/card.blade.php`.
                                 Its notice is in
                                 `download-version/assets/img/icon/LICENSE-tabler.txt`.

                                 The plain bag stays for the desktop circle,
                                 which is not being changed — «به هیچ عنوان به
                                 نسخه دستاپ دست نزنی». One `display` rule each
                                 side of 992 picks which. --}}
                            <a class="vp-best-browse" href="{{ storefront_route('product', $tile['product']) }}" aria-label="افزودن {{ $tile['product']->title }} به سبد خرید"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>@include('partials.bag-plus', ['class' => 'vp-best-add'])</a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
