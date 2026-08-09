{{--
    The parent store's layout.

    Deliberately plain: the real storefront is still the ThemeForest page in
    download-version/ and has not been ported into Blade (see HANDOFF.md).
    Dressing these pages up in a second, different design would create exactly
    the drift that port is meant to avoid, so they use the settled tokens and
    nothing more.
--}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @include('partials.tokens')
</head>
<body>
<div class="wrap">
    <header class="row row--between" style="padding:20px 0">
        <a href="{{ route('home') }}"><b>{{ config('app.name') }}</b></a>
        <nav class="row" style="gap:16px;font-size:.9rem">
            <a href="{{ route('vendors.index') }}">فروشندگان</a>
            <a href="{{ route('vendors.register') }}">فروش در ویکی‌پلاس</a>
            @auth
                <a href="{{ route('panel.home') }}">پنل من</a>
                <form method="post" action="{{ route('logout') }}">@csrf
                    <button class="btn btn--quiet btn--small">خروج</button>
                </form>
            @else
                <a href="{{ route('login') }}">ورود</a>
            @endauth
        </nav>
    </header>

    @if(session('status'))
        <p class="notice">{{ session('status') }}</p>
    @endif

    <main>@yield('content')</main>

    <footer class="faint" style="margin:48px 0 32px">{{ config('app.name') }}</footer>
</div>
</body>
</html>
