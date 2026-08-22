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

    There is no «جدید» chip any more — it was asked for and then asked away
    again, with `Product::isNew()`, which it was the only caller of. The corner
    is «ناموجود»'s alone now.
--}}
@php
    $offer = $product->offerHere();
    $cut = $offer?->discountPercent();
    $shot = $product->imagePath();
    $sold = (int) ($product->units_sold ?? 0);

    /*
     * The listing shows what the shop sells, including what it has run out of
     * — «نمیشه کفشی که موجودیش ۰ هست بیاد تو لیست فقط موجودی بزنه ۰؟». The card
     * has to say which, or an empty shelf looks like a shoe you can buy right
     * up until the product page refuses.
     */
    $out = $product->sellableStock() === 0;
@endphp
<article class="vp-card">
    {{-- The photograph and the favourite share a box of their own, and that is
         not tidiness. The heart sits on the shot's foot corner but cannot live
         inside it — a <button> inside an <a> is invalid, and a favourite is not
         a navigation — so it needs a positioned parent that ends where the shot
         ends. Against the card it would measure from the card's foot, which is
         below the name and the price. --}}
    <div class="vp-card-top">
        {{-- `is-supplied` cancels the phone's 15px nudge. That nudge is a
             correction for *this shop's* photographs — 1400×990 cut-outs with
             an empty band above and below the shoe — and the rule's own note
             said in as many words that «a photograph squarer than 4:3 would
             eat that margin». A supplier's photograph is whatever the seller
             uploaded, and the first one imported came out with its bottom
             sliced off inside the tile's `overflow: hidden`. --}}
        <a class="vp-card-shot{{ $product->source ? ' is-supplied' : '' }}" href="{{ storefront_route('product', $product) }}">
            <img src="{{ asset($shot) }}" alt="{{ $product->title }}" loading="lazy">
            {{-- «کلا کلمه جدید پاک بشه با دکمش». There was a «جدید» chip in
                 this corner and it is gone, badge and word together; what is
                 left is the one thing the corner has to be able to say. --}}
            @if ($out)
                <span class="vp-card-out">ناموجود</span>
            @endif
        </a>
        <button type="button" class="vp-card-fav" aria-label="افزودن {{ $product->title }} به علاقه‌مندی‌ها">
            <i class="fa-regular fa-heart" aria-hidden="true"></i>
        </button>
    </div>

    {{-- Six words at most; the whole name is on the product's own page. A
         name already that short is printed unchanged, which is every one of
         the shop's own. --}}
    <a class="vp-card-name" href="{{ storefront_route('product', $product) }}">{{ $product->cardName() }}</a>

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

    {{-- The basket, at the foot of every card.

         `addableVariant()` is what it adds, and its own docblock has said «the
         size a card's basket button adds» since before there was a card with a
         button on it — the default size where this branch can supply it, and
         the first one it can otherwise. That second half is the whole reason
         this is not `defaultVariant`: a listing shows a shoe while *any* size
         is sellable, so the default one is often the one that has just run out,
         and a button that added it would put a line in the basket that the
         checkout then refuses. The stories band on the home page adds the same
         way.

         Null means no size is sellable, which is the «ناموجود» the badge on the
         photograph is already saying. It gets a dead pill rather than nothing,
         so a sold-out card is the same shape as the others and the grid does
         not go ragged. --}}
    @php
        $addable = $product->addableVariant();
    @endphp

    @if ($addable)
        <form class="vp-card-buy" method="post" action="{{ storefront_route('cart.add') }}">
            @csrf
            <input type="hidden" name="variant" value="{{ $addable->id }}">
            <button type="submit" class="vp-card-add">
                اضافه کردن به سبد خرید
                {{-- `shopping-bag-plus` from **Tabler Icons**, MIT, unchanged
                     from the set. Picked off a sheet of eleven baskets and
                     trolleys with a plus — «یک عالیه» — the way every other
                     icon in this repository has been chosen. Its notice is
                     `download-version/assets/img/icon/LICENSE-tabler.txt`,
                     beside Fluent's and Phosphor's.

                     **Inline, not a file.** The category icons are `<img>`
                     with the gold baked in, and an `<img>` cannot inherit
                     `currentColor`; this one sits inside a button and has to
                     take the button's ink, which is the whole reason it is
                     markup here.

                     After the words, not before them — «آیکون باید جلوی جمله
                     باشه نه پشتش». On a right-to-left row that puts it on the
                     left, at the end the sentence is facing. --}}
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <g stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                        <path d="M12.5 21H8.574a3 3 0 0 1-2.965-2.544l-1.255-8.152A2 2 0 0 1 6.331 8H17.67a2 2 0 0 1 1.977 2.304l-.263 1.708M16 19h6m-3-3v6"></path>
                        <path d="M9 11V6a3 3 0 0 1 6 0v5"></path>
                    </g>
                </svg>
            </button>
        </form>
    @else
        <span class="vp-card-buy vp-card-add is-off" aria-disabled="true">ناموجود</span>
    @endif
</article>
