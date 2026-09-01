{{--
    «نظر مشتریان» — the band before the brand strip.

    «نظرات باید قبل از برندها باشه و بصورت اسلایدی از چپ برن راست», built to the
    client's reference: a quote mark and five stars across the top of the card,
    the sentence, then the writer at the foot.

    **The reference has a photograph and a job title under the name.** This shop
    has neither. A `customers` row is a telephone number and, if somebody filled
    it in, a name — so the round mark is the initial, and the line under the
    name is the shoe they actually bought, which is a fact the shop holds and a
    more useful one on a storefront than «CEO Of Company».

    **Nothing here is invented, and the band is absent when there is nothing.**
    Every card is an approved comment from somebody with a paid order for that
    shoe. A front page carrying testimonials nobody wrote is the one lie a shop
    must not tell, so with no reviews there is no band — not a placeholder, not
    a sample. See `HomeController::reviews()`.

    Hand-owned: `theme/make-blade.js` does not generate this file.
--}}
@if ($reviews->isNotEmpty())
<section class="vp-home-reviews space" aria-labelledby="vp-home-reviews-title">
    <div class="container th-container">
        <div class="vp-home-reviews-head">
            <h2 class="vp-home-reviews-title" id="vp-home-reviews-title">نظر مشتریان</h2>
        </div>

        {{-- A scroll-snap strip and not a slider library.
             «بصورت اسلایدی از چپ برن راست» — and in an RTL page that is what a
             horizontal scroller already does: the phone's own gesture turns it
             with nothing loaded and nothing to go wrong, which is the same
             choice the product gallery made. It also keeps the band
             deterministic for `check-parity.js`, where every Swiper on this
             page has to be pinned by hand before a shot can be trusted. --}}
        <div class="vp-home-reviews-row">
            @foreach ($reviews as $review)
                <article class="vp-review">
                    <div class="vp-review-top">
                        <span class="vp-review-quote" aria-hidden="true">”</span>
                        <span class="vp-stars" aria-hidden="true">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="fa-solid fa-star{{ $i <= $review->rating ? '' : ' is-off' }}"></i>
                            @endfor
                        </span>
                        <span class="vp-sr">{{ fa_number($review->rating) }} از ۵</span>
                    </div>

                    <p class="vp-review-said">{{ $review->body }}</p>

                    <div class="vp-review-who">
                        <span class="vp-review-mark" aria-hidden="true">{{ $review->authorInitial() }}</span>
                        <span class="vp-review-name">
                            {{-- A masked number is neutral characters end to end,
                                 so an RTL line reorders its runs. A name needs no
                                 such thing and must not get one. --}}
                            @if ($review->authorIsNumber())
                                <bdi dir="ltr">{{ $review->authorName() }}</bdi>
                            @else
                                {{ $review->authorName() }}
                            @endif
                            {{-- The shoe, not a job title: it is a fact the shop
                                 holds, and it is what a shopper reading this
                                 wants to know. It links, because somebody who
                                 believes the review wants the shoe. --}}
                            <a class="vp-review-bought" href="{{ storefront_route('product', $review->product) }}">
                                خریدار {{ $review->product->cardName() }}
                            </a>
                        </span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
