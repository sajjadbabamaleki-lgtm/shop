@extends('layouts.storefront')

@section('title', 'سوالات متداول')

{{--
    «سوالات متداول».

    The eight questions themselves live in `partials/faq-items.blade.php`,
    because the client asked for them at the foot of the home page too and two
    copies of eight answers is one copy that goes stale. That partial is where
    the note about reading every answer off the application belongs, and it is
    there. This page is the lead paragraph, the heading, and the fact that its
    first box opens.

    The exchange answer is the honest one: there is no returns flow in this
    application, so it says to telephone rather than describing a button that
    does not exist. `storefront.content.exchange_days` is the window, and it is
    a placeholder waiting on the client.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">سوالات متداول</h1>

            @include('partials.faq-lead')

            <div class="vp-faq">
                @include('partials.faq-items', ['openFirst' => true])
            </div>
        </div>
    </div>
</section>
@endsection
