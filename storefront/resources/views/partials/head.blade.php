{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}

    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>@yield('title', config('app.name'))</title>
    <meta name="author" content="Erna">
    <meta name="description" content="Erna - Multi-Purpose Modern & Minimal WooCommerce Template">
    <meta name="keywords" content="Erna - Multi-Purpose Modern & Minimal WooCommerce Template">
    <meta name="robots" content="INDEX,FOLLOW">

    <!-- Mobile Specific Metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

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
    {{-- Fingerprinted, and this is not housekeeping.

         tweaks.css is the one stylesheet on this site that changes, and it was
         served at a plain URL — so a visitor who had been here before got the
         new HTML (a response, never cached) against their browser's old CSS.
         That is not a subtle failure: the client opened a rebuilt product card
         and saw the new markup with none of its rules on it — an unstyled
         button below the photograph where a white circle should have been on
         it — and reported the build as broken. It was not broken; it was half
         of it.

         The hash is of the file's contents, so the URL changes when the file
         does and not otherwise. `file_exists` because a missing stylesheet
         should render a page without styles, not a 500. --}}
    @php($tweaks = public_path('assets/css/tweaks.css'))
    <link rel="stylesheet" href="{{ asset('assets/css/tweaks.css') }}{{ file_exists($tweaks) ? '?v='.substr(md5_file($tweaks), 0, 8) : '' }}">
