@extends('layouts.storefront')

@section('title', $article->title)

{{--
    One article — «متن ساده با عکس و تیتر».

    **The body is printed escaped and is never rendered as markup.** That is
    what was asked for, and it is also the safe reading of it: an editor that
    stored HTML would put whatever was pasted into it on a public page. The
    paragraphs are the writer's own line breaks, held by `white-space:
    pre-line` on `.vp-art-text` — see `tweaks.css`.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <article class="vp-shop-panel vp-doc vp-art-doc">
            <h1 class="vp-shop-title">{{ $article->title }}</h1>

            <p class="vp-art-doc-when">{{ fa_date($article->published_at) }}</p>

            @if ($article->image)
                <img class="vp-art-doc-shot" src="{{ asset($article->image) }}"
                     alt="{{ $article->title }}" decoding="async">
            @endif

            @if ($article->excerpt)
                <p class="vp-doc-lead">{{ $article->excerpt }}</p>
            @endif

            <p class="vp-art-text">{{ $article->body }}</p>
        </article>

        @if ($more->isNotEmpty())
            <div class="vp-shop-panel vp-doc vp-art-more">
                <h2 class="vp-shop-title">خواندنی‌های دیگر</h2>
                <ul class="vp-art-more-list">
                    @foreach ($more as $other)
                        <li>
                            <a href="{{ storefront_route('article', $other) }}">{{ $other->title }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
@endsection
