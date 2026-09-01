@extends('layouts.storefront')

@section('title', $article->title)

{{--
    One article, to the client's reference: the photograph across the top of the
    reading column, the date under it, the title large and bold, then the text.
    Under that, the pager, the share row, and three more to read.

    **What the reference has and this does not, and why.**

    - *A byline.* There is no author field: an article is the shop's, written in
      `/admin/articles`. Inventing a writer's name would put somebody's name on
      words they did not write, so the date stands alone.
    - *A pull-quote and an inline gallery.* Both need an editor that stores
      structure, and what was asked for is «متن ساده با عکس و تیتر». The body is
      plain text, printed escaped, with the writer's own line breaks kept.
    - *Tags.* No tag field, and a row of chips that filter nothing is a control
      that does nothing.
    - *Comments under the article.* The shop's comments are a buyer's word about
      a shoe they paid for — that is what makes them worth reading. An article
      has no purchase behind it, so the same gate cannot apply, and an open box
      on a public page is a different feature with a different queue.

    Everything above is a field away; none of it is invented today.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <article class="vp-art-doc">
            @if ($article->image)
                <img class="vp-art-doc-shot" src="{{ asset($article->image) }}"
                     alt="{{ $article->title }}" decoding="async">
            @endif

            <p class="vp-art-doc-when">{{ fa_date($article->published_at) }}</p>

            <h1 class="vp-art-doc-title">{{ $article->title }}</h1>

            @if ($article->excerpt)
                <p class="vp-art-doc-lead">{{ $article->excerpt }}</p>
            @endif

            <p class="vp-art-text">{{ $article->body }}</p>

            {{-- Sharing, and only where a link can carry the whole thing: the
                 two the shop's own footer already lists. No counter, no
                 third-party script — every one of those is a request to
                 somebody else's server on a page this shop serves. --}}
            <div class="vp-art-share">
                <span class="vp-art-share-label">هم‌رسانی</span>
                {{-- The footer's own marks, not glyphs: telegram has no glyph
                     in this shop's icon subset, and the footer settled on
                     images for all five after a glyph's baseline was measured
                     landing a pixel high on one engine and not another. Reusing
                     the files means nothing to re-subset. --}}
                <a class="vp-art-share-go"
                   href="https://wa.me/?text={{ rawurlencode($article->title.' — '.storefront_route('article', $article)) }}"
                   target="_blank" rel="noopener" aria-label="هم‌رسانی در واتساپ">
                    <img src="{{ asset('assets/img/social/whatsapp.svg') }}" alt="" width="20" height="20">
                </a>
                <a class="vp-art-share-go"
                   href="https://t.me/share/url?url={{ rawurlencode(storefront_route('article', $article)) }}&text={{ rawurlencode($article->title) }}"
                   target="_blank" rel="noopener" aria-label="هم‌رسانی در تلگرام">
                    <img src="{{ asset('assets/img/social/telegram.svg') }}" alt="" width="22" height="22">
                </a>
            </div>
        </article>

        @if ($newer || $older)
            {{-- The list is newest first, so «قبلی» is the newer article and
                 «بعدی» the older. The arrows point the way the page reads. --}}
            <nav class="vp-art-pager" aria-label="مقالهٔ قبلی و بعدی">
                @if ($newer)
                    <a class="vp-art-pager-go" href="{{ storefront_route('article', $newer) }}">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <span>
                            <b>قبلی</b>
                            <em>{{ $newer->title }}</em>
                        </span>
                    </a>
                @else
                    <span></span>
                @endif

                @if ($older)
                    <a class="vp-art-pager-go is-next" href="{{ storefront_route('article', $older) }}">
                        <span>
                            <b>بعدی</b>
                            <em>{{ $older->title }}</em>
                        </span>
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    </a>
                @endif
            </nav>
        @endif

        @if ($more->isNotEmpty())
            <div class="vp-art-more">
                <h2 class="vp-home-arts-title">خواندنی‌های دیگر</h2>

                {{-- The home page's own cards, so an article looks the same
                     wherever it is offered. --}}
                <div class="vp-home-arts-row">
                    @foreach ($more as $other)
                        <article class="vp-home-art">
                            <a class="vp-home-art-shot" href="{{ storefront_route('article', $other) }}"
                               aria-label="{{ $other->title }}">
                                @if ($other->image)
                                    <img src="{{ asset($other->image) }}" alt="" loading="lazy" decoding="async">
                                @endif
                            </a>
                            <p class="vp-home-art-when">{{ fa_date($other->published_at) }}</p>
                            <h3 class="vp-home-art-title">
                                <a href="{{ storefront_route('article', $other) }}">{{ $other->title }}</a>
                            </h3>
                            <a class="vp-home-art-more" href="{{ storefront_route('article', $other) }}">
                                ادامه مطلب
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                            </a>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
