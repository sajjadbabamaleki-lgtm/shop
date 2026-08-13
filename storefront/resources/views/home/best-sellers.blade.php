{{--
    «پرفروش‌ترین‌ها» — six tiles, each a shoe with its name and price.

    The photograph is the product's own now — «از همون عکس های قسمت حراج پله ای
    استفاده کن» — so the shot, the name and the price on a tile all belong to
    the same shoe, and the tile links to that shoe rather than to a category.
    The category is still what decides how many tiles there are and in what
    order; it just no longer supplies the picture.

    Still a placeholder in one respect, and admitted as one: these are not
    actually the best sellers. Nothing counts orders yet, so the six are the
    priced products cycled over the category list. The price shown is the one
    before the sale, which is why it does not agree with the same shoe's card
    in the stepped sale above.

    The brand filters below do not filter — the six tiles are categories, not
    products tagged by brand — and the colour swatches on each tile do not
    switch a variant. Both are the template's own controls, kept because the
    row is going to need them once it lists real best sellers.

    Hand-owned: theme/make-blade.js no longer regenerates this file.
--}}
<section class="space overflow-hidden overflow-hidden vp-best-section">
        <div class="vp-best-panel">
            <div class="vp-best-head">
                <h2 class="vp-best-title">پرفروش‌ترین‌ها</h2>
                <div class="vp-best-filters">
                    <button type="button" class="vp-best-filter active">همه</button>
                    <button type="button" class="vp-best-filter">نایک</button>
                    <button type="button" class="vp-best-filter">جردن</button>
                    <button type="button" class="vp-best-filter">نیوبالانس</button>
                    <button type="button" class="vp-best-filter">گلدن گوس</button>
                    <a class="vp-best-all" href="{{ page_url('shop.html') }}">مشاهده همه محصولات</a>
                </div>
            </div>
            <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xl-6 vp-best-row">
                @foreach ($bestSellers as $tile)
                <div class="col">
                    <div class="vp-best">
                        @php($shot = $tile['product']->primaryMedia())
                        <a class="vp-best-shot" href="{{ storefront_route('product', $tile['product']) }}">
                            @if ($shot)<img src="{{ asset($shot->path) }}" alt="{{ $tile['product']->title }}" loading="lazy">@endif
                            <div class="vp-best-colors" aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span>
                            </div>
                        </a>
                        <div class="vp-best-info">
                            <div class="vp-best-label">
                                <span class="vp-best-lines">
                                    <span class="vp-best-name">{{ $tile['product']->short_title }}</span>
                                    <span class="vp-best-cta"><strong>{{ toman($tile['product']->offerHere()->compare_at_price) }} <span>تومان</span></strong></span>
                                </span>
                            </div>
                            <a class="vp-best-browse" href="{{ storefront_route('product', $tile['product']) }}" aria-label="افزودن {{ $tile['product']->short_title }} به سبد خرید"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
