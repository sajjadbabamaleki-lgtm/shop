{{--
    A product, as a card.

    The stepped sale's own card, unchanged: same square photograph, same glass
    strip, same burst in the corner, same basket. The listing is not a new
    design — it is the object the home page already uses to say "here is a
    thing you can go and look at", laid out in a grid.

    Two differences, both because this card can be any product rather than one
    that is definitely on offer: the struck-through price only appears when a
    promotion is actually running, and so does the burst.
--}}
@php
    $offer = $product->offerHere();
    $cut = $offer?->discountPercent();
    $shot = $product->primaryMedia();
@endphp
<div class="vp-deal">
    <a class="vp-deal-shot" href="{{ storefront_route('product', $product) }}">
        @if ($shot)<img src="{{ asset($shot->path) }}" alt="{{ $product->title }}" loading="lazy">@endif
        @if ($cut)@include('partials.deal-burst', ['key' => 'p'.$product->id, 'percent' => $cut])@endif
        <span class="vp-deal-label">
            <span class="vp-deal-lines">
                <span class="vp-deal-name">{{ $product->title }}</span>
                <span class="vp-deal-price">@if ($cut)<del>{{ toman($offer->compare_at_price) }}</del>@endif<strong>{{ toman($offer->price) }} <span>تومان</span></strong></span>
            </span>
        </span>
    </a>
    <button type="button" class="vp-deal-cart" aria-label="افزودن {{ $product->title }} به سبد خرید"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></button>
</div>
