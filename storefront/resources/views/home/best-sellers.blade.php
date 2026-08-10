{{--
    «پرفروش‌ترین‌ها» — six category photographs with a shoe's name and price on
    each strip.

    The pairing is a placeholder and is admitted as one: a category photograph
    is not a SKU, and the client asked for a name and a price on the strip
    anyway rather than leave it bare. Which product lands under which
    photograph carries no meaning. The price shown is the one before the sale,
    which is also why it does not agree with the same shoe's card in the
    stepped sale above.

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
                        <a class="vp-best-shot" href="{{ page_url('shop.html') }}">
                            <img src="{{ asset($tile['category']->image_path) }}" alt="" loading="lazy">
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
                            <a class="vp-best-browse" href="{{ page_url('shop.html') }}" aria-label="افزودن {{ $tile['product']->short_title }} به سبد خرید"><i class="fa-solid fa-plus" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
