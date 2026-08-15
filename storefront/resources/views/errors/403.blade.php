@extends('layouts.error')

@section('title', 'دسترسی ندارید')

{{--
    Mostly reached from an order page: `OrderController` refuses an order that
    this session has not proved the phone number for. So the way out offered
    here is the tracking form, which is exactly the proof it wants.
--}}

@section('code', '۴۰۳')
@section('heading', 'اجازه دیدن این صفحه را ندارید')
@section('say')
    اگر دنبال یک سفارش هستید، از صفحه پیگیری سفارش با شماره سفارش و شماره
    موبایلی که سفارش با آن ثبت شده وارد شوید.
@endsection

@section('extra')
    <a class="vp-doc-link is-quiet" href="{{ route('orders.track') }}">پیگیری سفارش</a>
@endsection
