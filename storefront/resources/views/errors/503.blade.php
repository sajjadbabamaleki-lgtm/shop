@extends('layouts.error')

@section('title', 'به‌زودی برمی‌گردیم')

{{--
    Maintenance mode — `php artisan down` renders this. Same rule as the 500:
    the application is deliberately not serving, so nothing here may need it.
--}}

@section('code', '۵۰۳')
@section('heading', 'به‌زودی برمی‌گردیم')
@section('say')
    سایت برای چند دقیقه در حال به‌روزرسانی است. اگر عجله دارید با
    <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
    تماس بگیرید؛ فروشگاه باز است.
@endsection
