{{--
    The panel shell, shared by the seller's panel, the branch's and the
    platform's.

    One layout for all three because the chrome is genuinely the same — a
    sidebar, a title, a flash message. What differs is the menu, and each
    section supplies its own.
--}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پنل') · {{ config('app.name') }}</title>
    @include('partials.tokens')
    <style>
        .shell { display: grid; grid-template-columns: 236px 1fr; gap: 22px; align-items: start; padding: 22px 0 60px; }
        @media (max-width: 860px) { .shell { grid-template-columns: 1fr; } }
        .side { position: sticky; top: 22px; }
        .side a { display: block; padding: 7px 12px; border-radius: 999px; font-size: .9rem; }
        .side a.on { background: #fff; box-shadow: inset 0 0 0 1.4px var(--hairline); font-weight: 600; }
        .side hr { border: 0; border-top: 1px solid var(--hairline); margin: 12px 0; }
    </style>
</head>
<body>
<div class="wrap shell">
    <aside class="side pane pane--tight">
        <div style="padding:4px 12px 10px">
            <b>@yield('store-name', 'پنل مدیریت')</b>
            <span class="faint" style="display:block">@yield('store-kind', '')</span>
        </div>
        @yield('menu')
        <hr>
        <a href="{{ route('panel.home') }}">تغییر فروشگاه</a>
        <form method="post" action="{{ route('logout') }}" style="padding:6px 12px">@csrf
            <button class="btn btn--quiet btn--small">خروج</button>
        </form>
    </aside>

    <main>
        <div class="row row--between" style="margin-bottom:18px">
            <h1>@yield('heading')</h1>
            <div>@yield('actions')</div>
        </div>

        @if(session('status'))
            <p class="notice">{{ session('status') }}</p>
        @endif

        @if($errors->any())
            <div class="notice notice--bad">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
