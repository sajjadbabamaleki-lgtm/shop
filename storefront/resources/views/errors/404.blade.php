@extends('layouts.error')

@section('title', 'صفحه پیدا نشد')

{{--
    The one a visitor actually meets: a mistyped address, a link from somewhere
    old, or a product that has left the catalogue. Before this file existed,
    that visitor got Laravel's own page — English, left-to-right, on a white
    screen with no way back to the shop.
--}}

@section('code', '۴۰۴')
@section('heading', 'این صفحه پیدا نشد')
@section('say')
    نشانی را اشتباه تایپ کرده‌اید، یا کالایی که دنبالش بودید دیگر در فروشگاه
    نیست. از این‌جا می‌توانید برگردید.
@endsection

@section('extra')
    <a class="vp-doc-link is-quiet" href="{{ route('orders.track') }}">پیگیری سفارش</a>
@endsection
