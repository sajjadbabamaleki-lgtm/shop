@extends('layouts.storefront')

@section('title', $tag ? 'مقالات — '.$tag : 'مقالات')

{{--
    «مقالات» — the list.

    **Not in a card, and not against the screen edge.** The heading and its line
    sit on the page like the heading of any other band on this site — «اون متن
    بالایی چرا باید تو کادر باشه؟» — and the whole list is inset on a phone,
    where the container gives nothing of its own.

    The card is the front page's `.vp-home-art`, reused rather than a second
    design: an article should look the same wherever it is offered, and this
    page, the home band and the shelf under an article are three places that
    offer the same thing.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-arts">
            <div class="vp-arts-head">
                <h1 class="vp-arts-title">مقالات</h1>

                @if ($tag)
                    {{-- Arrived from a chip under an article. The way back out
                         is the point: a filtered list with no way to clear it
                         is a dead end somebody reaches by clicking. --}}
                    <p class="vp-arts-lead">
                        مقاله‌های برچسب «{{ $tag }}» —
                        <a href="{{ storefront_route('articles') }}">دیدن همهٔ مقاله‌ها</a>
                    </p>
                @else
                    <p class="vp-arts-lead">
                        هرچه دربارهٔ کفش و کیف نوشته‌ایم اینجاست؛ از انتخاب سایز و جنس
                        چرم تا نگهداری از کفشی که دوستش دارید.
                    </p>
                @endif
            </div>

            @if ($articles->isEmpty())
                <p class="vp-arts-none">
                    {{ $tag ? 'مقاله‌ای با این برچسب نیست.' : 'هنوز مقاله‌ای منتشر نشده است.' }}
                </p>
            @else
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

                            <h2 class="vp-home-art-title">
                                <a href="{{ storefront_route('article', $article) }}">{{ $article->title }}</a>
                            </h2>

                            <p class="vp-arts-sum">{{ $article->summary() }}</p>

                            <a class="vp-home-art-more" href="{{ storefront_route('article', $article) }}">
                                ادامه مطلب
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                            </a>
                        </article>
                    @endforeach
                </div>

                <div class="vp-art-pager-row">{{ $articles->links('pagination.vikyplus') }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
