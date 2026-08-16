{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}

    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>@yield('title', config('app.name'))</title>
    <meta name="author" content="ویکی پلاس">
    <meta name="description" content="ویکی پلاس، فروشگاه اینترنتی کیف و کفش زنانه: کتانی، مجلسی، بوت، صندل و کیف، با ارسال به سراسر ایران.">
    <meta name="keywords" content="کفش زنانه, کیف زنانه, کتانی زنانه, کفش مجلسی, بوت زنانه, ویکی پلاس">
    <meta name="robots" content="INDEX,FOLLOW">

    <!-- Mobile Specific Metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">

    <!-- Favicons - Place favicon.ico in the root directory -->
    <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('assets/img/favicons/apple-icon-57x57.png') }}">
    <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('assets/img/favicons/apple-icon-60x60.png') }}">
    <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('assets/img/favicons/apple-icon-72x72.png') }}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('assets/img/favicons/apple-icon-76x76.png') }}">
    <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('assets/img/favicons/apple-icon-114x114.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('assets/img/favicons/apple-icon-120x120.png') }}">
    <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('assets/img/favicons/apple-icon-144x144.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('assets/img/favicons/apple-icon-152x152.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/img/favicons/apple-icon-180x180.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/img/favicons/android-icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/img/favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('assets/img/favicons/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/img/favicons/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('assets/img/favicons/manifest.json') }}">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="{{ asset('assets/img/favicons/ms-icon-144x144.png') }}">
    <meta name="theme-color" content="#ffffff">

    
    
    

    <!-- Bootstrap -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.rtl.min.css') }}">
    <!-- Fontawesome Icon -->
    <link rel="stylesheet" href="{{ asset('assets/css/fontawesome.min.css') }}">
    <!-- Magnific Popup -->
    <link rel="stylesheet" href="{{ asset('assets/css/magnific-popup.rtl.min.css') }}">
    <!-- Swiper Js -->
    <link rel="stylesheet" href="{{ asset('assets/css/swiper-bundle.rtl.min.css') }}">
    <!-- Theme Custom CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.rtl.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fonts-fa.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/rtl-fixes.css') }}">
    @php($tweaks = public_path('assets/css/tweaks.css'))
    <link rel="stylesheet" href="{{ asset('assets/css/tweaks.css') }}{{ file_exists($tweaks) ? '?v='.substr(md5_file($tweaks), 0, 8) : '' }}">

    <!-- The design gate: no template paints without tweaks.css. See its last
         rule, theme/make-rtl-page.js, and «قالب قبلی» in CLAUDE.md. -->
    <script>
        (function () {
            var root = document.documentElement;

            // A browser with no custom properties would fail this for ever,
            // and it has bigger problems with this page than the gate.
            if (!window.getComputedStyle || !window.CSS || !CSS.supports || !CSS.supports('--vp-design', 'ok')) return;

            var key = "vp-design-retry";
            var mark = function (v) { try { v === null ? sessionStorage.removeItem(key) : sessionStorage.setItem(key, v); } catch (e) {} };
            var marked = function () { try { return sessionStorage.getItem(key) === "1"; } catch (e) { return false; } };

            if (getComputedStyle(root).getPropertyValue('--vp-design').trim() === 'ok') { mark(null); return; }

            // Nothing is painted yet. Keep it that way.
            root.style.visibility = 'hidden';

            if (!marked()) {
                mark("1");
                setTimeout(function () { location.reload(); }, 1200);
                return;
            }

            mark(null);

            document.addEventListener('DOMContentLoaded', function () {
                var note = document.createElement('div');
                note.dir = "rtl";
                note.setAttribute('style', 'visibility:visible;position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:24px;background:#fff;color:#101111;font:400 16px/1.9 Tahoma,Arial,sans-serif;text-align:center');
                note.innerHTML =
                    '<strong style="font-size:22px;letter-spacing:.2px">ویکی پلاس</strong>'
                    + '<span>سایت در حال به‌روزرسانی است.</span>'
                    + '<span style="font-size:14px;color:#6b6b6b">چند لحظه دیگر دوباره تلاش کنید.</span>'
                    + '<button type="button" style="margin-top:6px;padding:10px 26px;border:0;border-radius:999px;background:#101111;color:#fff;font:inherit;cursor:pointer">تلاش دوباره</button>';
                note.querySelector('button').addEventListener('click', function () { location.reload(); });
                document.body.appendChild(note);
            });
        })();
    </script>
