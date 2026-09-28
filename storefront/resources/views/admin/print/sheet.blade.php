{{--
    The shell the panel's printed sheets share — the address label and the
    invoice.

    **Not the panel's shell and not the shop's.** Both carry navigation, a
    design gate and a stylesheet the size of a book, and none of that belongs
    on a sheet of paper. This is the site's own Persian face and one short
    stylesheet, `print-sheet.css` — kept out of the Blade on purpose, see its
    header.
--}}
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title')</title>
<link rel="stylesheet" href="{{ asset('assets/css/fonts-fa.css') }}">
@php($sheetCss = public_path('assets/css/print-sheet.css'))
<link rel="stylesheet" href="{{ asset('assets/css/print-sheet.css').(is_file($sheetCss) ? '?v='.substr(md5_file($sheetCss), 0, 10) : '') }}">

</head>
<body>
<main class="vp-sheet">
    <div class="vp-sheet-tools">
        <button type="button" onclick="window.print()">چاپ</button>
        <a href="{{ route('admin.order', $order) }}">بازگشت به سفارش</a>
    </div>

    @yield('content')
</main>
</body>
</html>
