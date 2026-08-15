@extends('layouts.error')

@section('title', 'کمی صبر کنید')

{{--
    The throttles on the sign-in, the registration and the vendor application
    all land here. Somebody who mistyped a password three times is the usual
    visitor, not an attacker, so the page is an apology with a clock rather
    than an accusation.
--}}

@section('code', '۴۲۹')
@section('heading', 'کمی صبر کنید')
@section('say')
    در مدت کوتاهی درخواست زیادی از این مرورگر آمده است. چند دقیقه دیگر دوباره
    امتحان کنید.
@endsection
