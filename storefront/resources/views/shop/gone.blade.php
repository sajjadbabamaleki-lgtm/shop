@extends('layouts.storefront')

{{--
    A shoe the shop has stopped selling, at the address it used to have.

    Every class on this page is already somewhere else in the templates —
    `.vp-shop-panel` from the listing, `.vp-empty` from the basket and the
    no-results state, `.vp-pdp-related` and the grid from the product page.
    That is deliberate and not laziness: a new class name here would mean
    re-cutting the three bought stylesheets before this page could be styled at
    all (`theme/make-css-subset.js`, and `CssSubsetTest` is what says so), and
    this page has nothing to say that the shop has not already said somewhere.

    The sentence names the shoe. Somebody arriving from ترب or from a
    bookmark clicked a specific thing, and «این محصول موجود نیست» without the
    name reads as a broken site rather than as an answer.
--}}

@section('title', $product->title.' — '.config('app.name'))

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <nav class="vp-pdp-crumbs d-none d-lg-flex" aria-label="مسیر">
                <a href="{{ storefront_route('home') }}">خانه</a>
                <span aria-hidden="true">/</span>
                <a href="{{ storefront_route('shop') }}">محصولات</a>
                @if ($product->categories->isNotEmpty())
                    <span aria-hidden="true">/</span>
                    <a href="{{ storefront_route('category', $product->categories->first()) }}">{{ $product->categories->first()->name }}</a>
                @endif
            </nav>

            <div class="vp-empty">
                <span class="vp-empty-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="17" fill="none" stroke="currentColor" stroke-width="3"></circle><path d="M14 14 L34 34" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
                </span>
                <p class="vp-empty-say">«{{ $product->title }}» دیگر در فروشگاه عرضه نمی‌شود.</p>
                <a class="vp-empty-out" href="{{ $out }}">دیدن بقیه محصولات</a>
            </div>
        </div>

        @if ($instead->isNotEmpty())
            <div class="vp-shop-panel vp-pdp-related">
                <h2 class="vp-shop-title">به جای آن، این‌ها هست</h2>
                <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xxl-4 vp-shop-grid">
                    @foreach ($instead as $other)
                        {{-- Named, not reused: looping «as $product» would overwrite
                             the shoe this page is about. --}}
                        <div class="col">@include('shop.card', ['product' => $other])</div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
