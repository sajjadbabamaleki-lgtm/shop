@extends('layouts.error')

@section('title', 'مشکلی پیش آمد')

{{--
    Ours, not theirs — the page says so, and gives the telephone, because
    somebody in the middle of placing an order needs a way to finish it rather
    than an explanation.

    Nothing on this page may touch the database. Whatever caused the 500 is
    quite likely to be the database, and a shell that queries one would turn
    this page into a second exception with no page left to render it.
--}}

@section('code', '۵۰۰')
@section('heading', 'مشکلی از سمت ما پیش آمد')
@section('say')
    خطا از سایت است، نه از کار شما. چند دقیقه دیگر دوباره امتحان کنید — و اگر
    وسط ثبت سفارش بودید، با
    <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
    تماس بگیرید تا همان‌جا سفارش را ثبت کنیم.
@endsection
