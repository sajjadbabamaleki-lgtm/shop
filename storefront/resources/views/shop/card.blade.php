{{--
    A product, as a card, to the client's reference screenshot.

    Built for the phone: a square photograph on a grey tile with a badge at one
    corner and a favourite at the other, then the name, the price, the cut
    beside the price it was, and the sales line.

    «جایی که محصول قرار میگیره باید مربع باشه» — the tile is 1:1 and the
    photograph is fitted inside it, not filled. A cut-out cropped to a square
    loses the toe or the heel, which on a shoe is the whole silhouette; that
    rule is older than this card and applies here for the same reason.

    Two of the reference's lines are not here, and both are absences of data
    rather than of effort:

    - **No rating.** There is no review table. A star with a number beside a
      real price is a claim, and this repo has taken an invented one out once
      already.
    - **The sales line only appears when something has sold.** `units_sold`
      counts paid order items, and on the demo catalogue that is nought for
      every product — printing «۰ فروش» on all of them says less than saying
      nothing. It appears on its own once orders exist.

    The «جدید» badge is real too: `isNew()` is `published_at` inside a window,
    so a product stops being new by itself.
--}}
@php
    $offer = $product->offerHere();
    $cut = $offer?->discountPercent();
    $shot = $product->primaryMedia();
    $sold = (int) ($product->units_sold ?? 0);
@endphp
<article class="vp-card">
    {{-- The photograph and the favourite share a box of their own, and that is
         not tidiness. The heart sits on the shot's foot corner but cannot live
         inside it — a <button> inside an <a> is invalid, and a favourite is not
         a navigation — so it needs a positioned parent that ends where the shot
         ends. Against the card it would measure from the card's foot, which is
         below the name and the price. --}}
    <div class="vp-card-top">
        <a class="vp-card-shot" href="{{ storefront_route('product', $product) }}">
            @if ($shot)<img src="{{ asset($shot->path) }}" alt="{{ $product->title }}" loading="lazy">@endif
            @if ($product->isNew())<span class="vp-card-new">جدید</span>@endif
        </a>
        <button type="button" class="vp-card-fav" aria-label="افزودن {{ $product->title }} به علاقه‌مندی‌ها">
            <i class="fa-regular fa-heart" aria-hidden="true"></i>
        </button>
    </div>

    {{-- «تخفیف پله ای و برند بیان تو اون ردیف اول قبل قیمت» — the first line
         under the photograph, above the name and so above the price.

         The sale marker is not a second badge for the same fact as the chip
         further down. The chip says how much is off; this says *which* offer
         it is, and on this site that matters: a stepped sale's price falls
         again next week, and a shopper who knows that reads the same number
         differently. It shows when the product carries a live promotion, which
         is what being in the stepped sale is — see CLAUDE.md: the sale is a
         price on an offer, not a flag on a product. --}}
    <span class="vp-card-meta">
        @if ($product->brand)<span class="vp-card-brand">{{ $product->brand->name }}</span>@endif
        @if ($cut)<span class="vp-card-ladder">حراج پله‌ای</span>@endif
    </span>

    <a class="vp-card-name" href="{{ storefront_route('product', $product) }}">{{ $product->title }}</a>

    <strong class="vp-card-price">{{ toman($offer->price) }} <span>تومان</span></strong>

    @if ($cut)
        <span class="vp-card-was">
            <span class="vp-card-cut">٪{{ fa_number($cut) }}</span>
            <del>{{ toman($offer->compare_at_price) }}</del>
        </span>
    @endif

    @if ($sold > 0)
        <span class="vp-card-sold">{{ fa_number($sold) }} فروش</span>
    @endif
</article>
