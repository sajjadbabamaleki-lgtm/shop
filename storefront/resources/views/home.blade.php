@extends('layouts.storefront')

{{--
    The home page, top to bottom. The sections are the ones that survived the
    cut described in HANDOFF.md — testimonials, feature products, blog and
    instagram came off the page and are not included here.

    Only the offer banner and the footer are still wholly the template's.
--}}

@section('content')
    @include('home.stories')
    @include('home.hero')
    @include('home.categories')
    @include('home.dice')
    @include('home.ladder')
    @include('home.best-sellers')
    @include('home.offer-banner')
    @include('home.daily-deal')
    @include('home.brands')
    @include('home.faq')
@endsection
