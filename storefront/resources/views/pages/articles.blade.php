@extends('layouts.storefront')

@section('title', 'مقالات')

{{--
    «مقالات» — the list.

    «هیچ جایی برای مقالات در سایت نداریم», and «متن ساده با عکس و تیتر». So a
    card is a photograph, a title and a line, and nothing else: no author, no
    category chip, no reading time. Every one of those would be a field
    somebody has to fill in before an article can go up.

    The card is `.vp-doc`'s material rather than a product card's — an article
    is something to read, not something with a price — and the grid is the
    listing's own so the two pages have one idea of what a row of cards is.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">
            <h1 class="vp-shop-title">مقالات</h1>

            @if ($tag)
                {{-- Arrived from a chip under an article. The way back out is
                     the point: a filtered list with no way to clear it is a
                     dead end somebody reaches by clicking. --}}
                <p class="vp-doc-lead">
                    مقاله‌های برچسب «{{ $tag }}» —
                    <a href="{{ storefront_route('articles') }}">دیدن همهٔ مقاله‌ها</a>
                </p>
            @else
                <p class="vp-doc-lead">
                    هرچه دربارهٔ کفش و کیف نوشته‌ایم اینجاست؛ از انتخاب سایز و جنس
                    چرم تا نگهداری از کفشی که دوستش دارید.
                </p>
            @endif
        </div>

        @if ($articles->isEmpty())
            <div class="vp-shop-panel vp-doc">
                <p>{{ $tag ? 'مقاله‌ای با این برچسب نیست.' : 'هنوز مقاله‌ای منتشر نشده است.' }}</p>
            </div>
        @else
            {{-- The grid sits in `.vp-doc`'s own 820px reading column, because
                 the panel above it does — a full-width row under a narrowed
                 intro reads as two pages that got stapled together, which is
                 what it looked like when this was measured at 1200. Two
                 columns rather than three for the same reason: 820 into three
                 is a 260px card, and an article's title is a sentence. --}}
            <div class="vp-art-list">
            <div class="row gy-4 row-cols-1 row-cols-md-2 vp-art-grid">
                @foreach ($articles as $article)
                    <div class="col">
                        <a class="vp-art-card" href="{{ storefront_route('article', $article) }}">
                            @if ($article->image)
                                <span class="vp-art-shot">
                                    <img src="{{ asset($article->image) }}" alt="{{ $article->title }}"
                                         loading="lazy" decoding="async">
                                </span>
                            @endif
                            <span class="vp-art-body">
                                <span class="vp-art-title">{{ $article->title }}</span>
                                <span class="vp-art-sum">{{ $article->summary() }}</span>
                                <span class="vp-art-when">{{ fa_date($article->published_at) }}</span>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="vp-art-pager">{{ $articles->links('pagination.vikyplus') }}</div>
            </div>
        @endif
    </div>
</section>
@endsection
