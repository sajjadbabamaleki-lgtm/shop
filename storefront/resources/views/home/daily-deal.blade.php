{{--
    The daily-deal banner: one product, its category, and what is left of it.

    The stock line is the variant's real sellable stock, so «فقط ۱ عدد باقی
    مانده» is a count and not a claim — and the bar beside it is drawn near
    empty because one left is not a comfortable stock level.

    Like the best sellers, the price shown is the one before the sale.

    The countdown is the template's own widget; data-offer-date is the single
    input it reads.

    Hand-owned: theme/make-blade.js no longer regenerates this file.
--}}
@if ($dailyDeal)
<section class="space overflow-hidden overflow-hidden vp-daily-deal-section">
        <div class="container th-container vp-daily-deal-wrap">
            <div class="vp-daily-deal">
                <div class="vp-daily-deal-copy">
                    <span class="vp-daily-deal-badge">پیشنهاد امروز</span>
                    <h2 class="vp-daily-deal-title">قبل از<br>تمام شدن بخرش!</h2>
                    <p class="vp-daily-deal-sub">عجله کن؛ موجودی محدوده.</p>
                    <a href="{{ page_url('shop-details.html') }}" class="vp-daily-deal-cta">خرید کنید<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="vp-daily-deal-card">
                    <div class="vp-daily-deal-info">
                        <span class="vp-daily-deal-cat">{{ $dailyDeal['category']?->name }}</span>
                        <h3 class="vp-daily-deal-name">{{ $dailyDeal['product']->title }}</h3>
                        <strong class="vp-daily-deal-price">{{ toman($dailyDeal['product']->defaultVariant->compare_at_price) }} <span>تومان</span></strong>
                        <div class="vp-daily-deal-stock">
                            <span>فقط {{ fa_number($dailyDeal['product']->sellableStock()) }} عدد باقی مانده</span>
                            <span class="vp-daily-deal-bar"><span class="vp-daily-deal-bar-fill"></span></span>
                        </div>
                        <ul class="counter-list vp-daily-deal-timer" data-offer-date="{{ $dailyDeal['ends_at'] }}">
                            <li><div class="day count-number">00</div><span class="count-name">روز</span></li>
                            <li><div class="hour count-number">00</div><span class="count-name">ساعت</span></li>
                            <li><div class="minute count-number">00</div><span class="count-name">دقیقه</span></li>
                            <li><div class="seconds count-number">00</div><span class="count-name">ثانیه</span></li>
                        </ul>
                    </div>
                    <div class="vp-daily-deal-shot"><img src="{{ asset($dailyDeal['product']->primaryMedia()->path) }}" alt="" loading="lazy"></div>
                </div>
            </div>
        </div>
    </section>
@endif
