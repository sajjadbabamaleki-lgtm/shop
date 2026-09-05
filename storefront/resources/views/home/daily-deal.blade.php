{{--
    The daily-deal banner: one product, its category, and what is left of it.

    The stock line is what this branch actually has left, so «فقط ۱ عدد باقی
    مانده» is a count and not a claim — and the bar beside it is drawn near
    empty because one left is not a comfortable stock level.

    **The price is the one the shop charges.** It printed `compare_at_price`,
    the number before the cut — «این باید قیمتشو اسمشو از فروشگاه بخونه» — so
    this band, the shoe's own page and the listing disagreed about what a
    customer pays. `offerHere()->price` is what `shop/card.blade.php` prints
    and now what this does.

    The countdown is the template's own widget; data-offer-date is the single
    input it reads, and `HomeController::dailyDealEndsAt()` is where that date
    comes from and why it rolls.

    Hand-owned: theme/make-blade.js no longer regenerates this file.
--}}
@if ($dailyDeal)
<section class="space overflow-hidden overflow-hidden vp-daily-deal-section">
        <div class="container th-container vp-daily-deal-wrap">
            <div class="vp-daily-deal">
                <div class="vp-daily-deal-copy">
                    {{-- «اون قسمت پیشنهاد امروز هم بشه پیشنهاد ویژه». The
                         deadline underneath is still the end of today when
                         nothing else sets one, and the clock re-arms itself on
                         a fresh day — so what the band offers is still today's,
                         it is simply no longer named after the day. Kept in
                         step with theme/make-rtl-page.js, or parity fails. --}}
                    <span class="vp-daily-deal-badge">پیشنهاد ویژه</span>
                    <h2 class="vp-daily-deal-title">قبل از<br>تمام شدن بخرش!</h2>
                    <p class="vp-daily-deal-sub">عجله کن؛ موجودی محدوده.</p>
                </div>
                <div class="vp-daily-deal-card">
                    <div class="vp-daily-deal-info">
                        <span class="vp-daily-deal-cat">{{ $dailyDeal['category']?->name }}</span>
                        <h3 class="vp-daily-deal-name">{{ $dailyDeal['product']->title }}</h3>
                        {{-- One lookup for both numbers and the burst, so the
                             three cannot disagree about the same offer. --}}
                        @php($offer = $dailyDeal['product']->offerHere())
                        {{-- **The before-price only while there is really a cut.**
                             «قیمت اصلیش ۴ میلیون ۹۰۰ باشه که ۲۰ درصد تخفیف خورده»
                             — so the band shows both numbers now, where it showed
                             one. It is guarded on `hasActivePromotion()` and not on
                             `compare_at_price` alone: a campaign that ends leaves
                             that column behind, and a struck-through number over an
                             undiscounted price is the shop telling a lie nothing
                             would go red about. With no cut it prints exactly what
                             it printed before.

                             One line, deliberately: a newline inside the element
                             is a whitespace text node, and the two copies of this
                             page have to render to the same pixel. --}}
                        <strong class="vp-daily-deal-price">@if ($offer->hasActivePromotion())<del>{{ toman($offer->compare_at_price) }}</del>@endif{{ toman($offer->price) }} <span>تومان</span></strong>
                        <div class="vp-daily-deal-stock">
                            <span>فقط {{ fa_number($dailyDeal['product']->sellableStock()) }} عدد باقی مانده</span>
                            <span class="vp-daily-deal-bar"><span class="vp-daily-deal-bar-fill"></span></span>
                        </div>
                        <ul class="counter-list vp-daily-deal-timer" data-offer-date="{{ $dailyDeal['ends_at'] }}">
                            {{-- **Reversed, so the clock reads روز ساعت دقیقه ثانیه
                                 from the left.** The page is RTL, so the first
                                 child of the row lands on the right, and the
                                 natural order came out with the seconds leftmost
                                 — a timer running backwards against the way a
                                 clock is read.

                                 Order here is presentation only: the widget finds
                                 each box by its own class (`.day`, `.hour`,
                                 `.minute`, `.seconds`) inside the list, so moving
                                 them cannot mis-wire it. And it is done in the
                                 markup rather than with `direction: ltr` on the
                                 row, which would flip the Persian labels under
                                 the digits with it. --}}
                            <li><div class="seconds count-number">00</div><span class="count-name">ثانیه</span></li>
                            <li><div class="minute count-number">00</div><span class="count-name">دقیقه</span></li>
                            <li><div class="hour count-number">00</div><span class="count-name">ساعت</span></li>
                            <li><div class="day count-number">00</div><span class="count-name">روز</span></li>
                        </ul>
                    </div>
                    {{-- «ستاره تخفیف هم بیاد روش» — the sale cards' own burst,
                         on the photograph, drawn from `discountPercent()` so the
                         figure on it is the one the two prices beside it imply.
                         `$key` is 'd' because the page already draws this shape
                         five times for the stepped sale and six for the best
                         sellers, and a repeated gradient id would have them all
                         take the first one's fill.

                         Kept in step with theme/make-rtl-page.js, or parity fails. --}}
                    {{-- **The photograph and the button, in one column.**
                         «اون دکمه خرید کنید باید بشه اضافه کردن به سبد خرید به
                         همراه اون آیکون سبدی که بعلاوه کنارشه و بیاد زیر کفش
                         قرار بگیره» — so the call to action left the copy
                         column, where it sat above the card, and stands under
                         the shoe. This wrapper exists for that: the card is a
                         row of two, and a button under the photograph means the
                         photograph and the button are one of them.

                         The words are the listing's own — `.vp-card-add` says
                         «اضافه کردن به سبد خرید» — and so is the mark, after
                         them rather than before: «آیکون باید جلوی جمله باشه نه
                         پشتش», which on a right-to-left row is the left end. --}}
                    <div class="vp-daily-deal-figure">
                        {{-- **The cut-out, not the catalogue's own photograph.**
                             «آن رانینگ تو هیرو یعنی مشکی سفیده بیاد اینجا» — the
                             product's own shot is the studio's, on the studio's
                             ground; the hero draws this shoe as a cut-out and
                             the band beside it must draw the same one.

                             It reads the map the best sellers read, and that is
                             the point rather than an accident: one shoe has one
                             cut-out wherever the front page draws it, and a
                             second map keyed by the same slugs is how the two
                             drift apart with nothing to notice. The key is
                             still named for the band that first needed it. --}}
                        @php($shot = config('storefront.placeholders.best_sellers.photos')[$dailyDeal['product']->slug] ?? $dailyDeal['product']->imagePath())
                        <div class="vp-daily-deal-shot">@if ($offer->hasActivePromotion())@include('partials.deal-burst', ['key' => 'd', 'percent' => $offer->discountPercent()])@endif<img src="{{ asset($shot) }}"{!! photo_srcset($shot) !!} alt="" loading="lazy"></div>
                        <a href="{{ storefront_route('product', $dailyDeal['product']) }}" class="vp-daily-deal-cta">اضافه کردن به سبد خرید@include('partials.bag-plus', ['class' => 'vp-daily-deal-mark'])</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
