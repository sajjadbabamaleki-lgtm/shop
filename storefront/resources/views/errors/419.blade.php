@extends('layouts.error')

@section('title', 'صفحه منقضی شد')

{{--
    A stale CSRF token — the tab was left open too long, then a form was
    submitted. It is not the visitor's fault and the page says so, because
    «۴۱۹» means nothing to anybody.
--}}

@section('code', '۴۱۹')
@section('heading', 'صفحه منقضی شد')
@section('say')
    این صفحه مدتی باز مانده بود. یک بار صفحه را تازه کنید و دوباره امتحان
    کنید؛ سبد خریدتان سر جایش است.
@endsection

@section('extra')
    <a class="vp-doc-link is-quiet" href="{{ route('cart') }}">سبد خرید</a>
@endsection
