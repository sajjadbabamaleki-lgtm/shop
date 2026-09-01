{{--
    «مقالات» — the last band before the FAQ.

    «تو صفحه هوم باید مقالات آخرین بخش قبل از سوالات متداول باشه به این شکل که
    یه عکس بعلاوه موارد زیرش و معمولا ۳ تا مقاله همیشه تو هوم باشه», built to
    the client's reference: the photograph, then the date, the title, and
    «ادامه مطلب ←».

    **The reference carries a byline — «By Michel Smith».** This shop has no
    author field: an article is the shop's, written in `/admin/articles`, and
    inventing a writer's name for it would be putting somebody's name on words
    they did not write. The date stands on its own.

    **Three, and none when there are none.** The band is absent until the shop
    has published something — no placeholder cards, no lorem. See
    `HomeController`.

    Hand-owned: `theme/make-blade.js` does not generate this file.
--}}
@if ($articles->isNotEmpty())
<section class="vp-home-arts space" aria-labelledby="vp-home-arts-title">
    <div class="container th-container">
        <div class="vp-home-arts-head">
            <h2 class="vp-home-arts-title" id="vp-home-arts-title">مقالات</h2>
            <a class="vp-home-arts-all" href="{{ storefront_route('articles') }}">همهٔ مقاله‌ها</a>
        </div>

        <div class="vp-home-arts-row">
            @foreach ($articles as $article)
                <article class="vp-home-art">
                    <a class="vp-home-art-shot" href="{{ storefront_route('article', $article) }}"
                       aria-label="{{ $article->title }}">
                        @if ($article->image)
                            <img src="{{ asset($article->image) }}" alt="" loading="lazy" decoding="async">
                        @endif
                    </a>

                    <p class="vp-home-art-when">{{ fa_date($article->published_at) }}</p>

                    <h3 class="vp-home-art-title">
                        <a href="{{ storefront_route('article', $article) }}">{{ $article->title }}</a>
                    </h3>

                    <a class="vp-home-art-more" href="{{ storefront_route('article', $article) }}">
                        ادامه مطلب
                        {{-- The arrow points the way the page reads, so in RTL
                             it points left. `aria-hidden` because «ادامه مطلب»
                             already says it. --}}
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
