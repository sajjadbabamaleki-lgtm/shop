{{--
    The shell every error page uses.

    **It is not `layouts/storefront`, and that is deliberate.** The storefront
    shell pulls in the header, the phone drawer and the mini basket, and all
    three have view composers behind them that ask the database questions — the
    mini basket's calls `CartManager::current()`, which *throws* when no branch
    is bound for the request. A 404 for an address that matched no route never
    ran `ResolveTenant`, so no branch is bound: rendering the ordinary shell on
    that page would turn a 404 into a 500. The 500 page would then be the same
    shell, and the error would be the one nobody could read.

    So this shell touches nothing. It reads the head partial — stylesheets and
    favicons, no data — and writes its own markup. It is the page that has to
    work when everything else does not.

    The links at the foot are plain `route()` rather than `storefront_route()`
    for the same reason: `storefront_route()` asks the tenant context which
    branch this is, and on these pages there may not be one. Somebody who was
    browsing a franchise lands back at the main store, which is a real cost and
    the right trade for a page that must never fail twice.
--}}
<!doctype html>
<html class="no-js" lang="fa" dir="rtl">

<head>
@include('partials.head')
</head>

<body class="shoe-shop vp-error-body">

<main class="vp-error">
    <div class="vp-error-card">
        <a class="vp-logo vp-error-logo" href="{{ route('home') }}">
            <img src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="ویکی پلاس">
            <span class="vp-logo-text">
                <b>ویکی پلاس</b>
                <small>فروشگاه کیف و کفش زنانه</small>
            </span>
        </a>

        <p class="vp-error-code" aria-hidden="true">@yield('code')</p>

        <h1 class="vp-error-title">@yield('heading')</h1>

        <p class="vp-error-say">@yield('say')</p>

        <div class="vp-error-ways">
            <a class="vp-doc-link" href="{{ route('home') }}">صفحه اصلی</a>
            <a class="vp-doc-link is-quiet" href="{{ route('shop') }}">فروشگاه</a>
            @hasSection('extra')
                @yield('extra')
            @endif
        </div>

        <p class="vp-error-help">
            کمک لازم دارید؟ <a href="{{ route('contact') }}">با ما تماس بگیرید</a>.
        </p>
    </div>
</main>

</body>

</html>
