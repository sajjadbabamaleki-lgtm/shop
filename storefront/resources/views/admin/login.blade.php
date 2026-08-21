<!doctype html>
<html class="no-js" lang="fa" dir="rtl">

{{--
    The staff door.

    It is the one screen of the panel with no shell — no sidebar, no bottom
    bar, nothing to navigate to until somebody is signed in — so it loads
    admin.css itself rather than through `layouts.admin`. Fingerprinted the
    same way, for the same reason.

    This is `web`, never the bare `auth`: a shopper's session must not be able
    to satisfy it. The two sign-ins are two guards and this is the staff one.
--}}

<head>
@include('partials.head')
@php
    $adminCss = public_path('assets/css/admin.css');
    $adminCssV = file_exists($adminCss) ? '?v='.substr(md5_file($adminCss), 0, 8) : '';
@endphp
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css').$adminCssV }}">
</head>

<body class="vp-adm vp-adm-door">

<main class="vp-adm-gate">
    <img class="vp-adm-gate-mark" src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="ویکی پلاس" width="44" height="44">

    <h1 class="vp-adm-gate-title">ورود کارکنان</h1>
    <p class="vp-adm-empty">این صفحه برای مدیران و کارکنان شعبه است، نه برای خرید.</p>

    @foreach ($errors->all() as $error)
        <p class="vp-adm-note is-bad">{{ $error }}</p>
    @endforeach

    <form class="vp-adm-form" method="post" action="{{ route('admin.login.store') }}">
        @csrf

        <label for="lg-email">ایمیل</label>
        <input id="lg-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" dir="ltr">

        <label for="lg-pass">رمز</label>
        <input id="lg-pass" type="password" name="password" required autocomplete="current-password" dir="ltr">

        <label class="vp-adm-check">
            <input type="checkbox" name="remember" value="1">
            <span>مرا به خاطر بسپار</span>
        </label>

        <button type="submit" class="vp-adm-apply">ورود</button>
    </form>
</main>

</body>

</html>
