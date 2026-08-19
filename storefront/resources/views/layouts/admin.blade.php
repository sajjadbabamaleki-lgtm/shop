{{--
    The panel's shell — Phase 1 of the specification's own priority list:
    «1. Desktop App Shell 2. Mobile App Shell».

    It is deliberately not the shop's chrome any more. The specification opens
    with «The admin experience must be designed independently from the customer
    storefront» and closes with «Do not copy the customer storefront UI», and
    the frames that came of borrowing it are what got this rewritten. The
    materials now live in `public/assets/css/admin.css`, which nothing but this
    file loads.

    **Desktop** (§17): a sidebar down the reading edge, 248px, collapsing to
    72px, with a topbar carrying the screen's name, the branch and the way out.
    **Phone** (§19): no sidebar at all — a bottom bar of «خانه / سفارش‌ها /
    افزودن / محصولات / بیشتر», with «بیشتر» and «افزودن» opening a bottom
    sheet, and the full navigation available as a drawer.

    Every destination comes from `App\Support\Admin\Navigation`, once, so the
    sidebar, the bottom bar and the sheet cannot disagree about what exists or
    about who may see it.

    The topbar carries §14's search and §17's notifications, both of which now
    have something behind them: the box posts to `/admin/search`, which really
    searches orders, products, customers and codes, and the bell counts live
    state rather than a notifications table this application does not have —
    see `App\Support\Admin\Attention` for what that costs and what it buys.

    `partials.head` is still included: it carries the fonts, the design gate and
    the meta the panel needs. tweaks.css comes with it and still styles the
    panel's screens — its tables, buttons and cards. Only the shell moved.
--}}
<!doctype html>
<html class="no-js" lang="fa" dir="rtl">

<head>
@include('partials.head')
{{-- Fingerprinted the same way tweaks.css is, and for the same reason: this
     file is the panel's whole appearance, so a cached copy of yesterday's is
     the one thing that must not happen. --}}
@php
    $adminCss = public_path('assets/css/admin.css');
    $adminCssV = file_exists($adminCss) ? '?v='.substr(md5_file($adminCss), 0, 8) : '';
@endphp
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css').$adminCssV }}">
</head>

@php
    // The marketplace screens have no branch — a vendor sells across the whole
    // platform — so the shell has to hold that without falling over.
    $branch = $branch ?? null;
    $nav = app(\App\Support\Admin\Navigation::class);
    $sections = $nav->forUser(auth()->user(), $branch);
    $flat = $nav->flatFor(auth()->user(), $branch);
    $quickAdd = $nav->quickAdd(auth()->user(), $branch);
    $bottom = collect(\App\Support\Admin\Navigation::BOTTOM)
        ->map(fn (string $route) => collect($flat)->firstWhere('route', $route))
        ->filter()
        ->values();
    $branches = auth()->user()->administrableBranches();

    // The topbar's heading. A screen may name itself with @section('title'),
    // and where none does the navigation already knows what this screen is
    // called — taking it from there means the sidebar and the heading cannot
    // say two different things, and no screen had to be edited to get one.
    $here = collect($flat)->first(fn (array $item): bool => $nav->isOn($item['match']));

    // §17. Computed here rather than in every controller, because the bell is
    // part of the shell and a screen that forgot to provide it would simply
    // show nothing — the silent failure this panel keeps being bitten by.
    $waiting = app(\App\Support\Admin\Attention::class)->for(auth()->user(), $branch);
    $waitingCount = array_sum(array_column($waiting, 'count'));
@endphp

@php
    // One sprite, drawn once, referenced by every link. Stroke icons at 18px
    // in the sidebar and 20px in the bottom bar, so they are geometry rather
    // than a font — an icon font is one more file that can fail to arrive.
    $icons = [
        'house' => '<path d="M3 9.5 10 4l7 5.5V16a1 1 0 0 1-1 1h-3v-4H7v4H4a1 1 0 0 1-1-1V9.5Z"/>',
        'receipt' => '<path d="M5 3h10v14l-2.5-1.5L10 17l-2.5-1.5L5 17V3Z"/><path d="M8 7h4M8 10.5h4"/>',
        'tag' => '<path d="M3 10.5V4h6.5L17 11.5 10.5 18 3 10.5Z"/><circle cx="6.75" cy="6.75" r="1"/>',
        'box' => '<path d="M10 3l7 3.5v7L10 17l-7-3.5v-7L10 3Z"/><path d="M3 6.5 10 10l7-3.5M10 10v7"/>',
        'stack' => '<path d="M10 3 3 6.5 10 10l7-3.5L10 3Z"/><path d="M3 10.5 10 14l7-3.5M3 14 10 17.5 17 14"/>',
        'price' => '<circle cx="10" cy="10" r="7"/><path d="M10 6v8M12 8.2c0-1-.9-1.7-2-1.7s-2 .7-2 1.6c0 2.2 4 1.2 4 3.4 0 .9-.9 1.6-2 1.6s-2-.7-2-1.7"/>',
        'store' => '<path d="M3.5 7.5 5 3.5h10l1.5 4"/><path d="M3.5 7.5a2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0"/><path d="M4.5 9.4V16a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1V9.4"/>',
        'wallet' => '<path d="M3 6.5A1.5 1.5 0 0 1 4.5 5H15a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6.5Z"/><circle cx="13.5" cy="10" r="1"/>',
        'percent' => '<path d="M5.5 14.5 14.5 5.5"/><circle cx="6.5" cy="6.5" r="1.8"/><circle cx="13.5" cy="13.5" r="1.8"/>',
        'chart' => '<path d="M3 17h14"/><path d="M6 17V9.5M10 17V4.5M14 17v-5"/>',
        'inbox' => '<path d="M3 11.5 5.2 4.6A1 1 0 0 1 6.15 4h7.7a1 1 0 0 1 .95.6L17 11.5V15a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3.5Z"/><path d="M3 11.5h4l1 2h4l1-2h4"/>',
        'users' => '<circle cx="8" cy="7.5" r="2.5"/><path d="M3.5 16c0-2.2 2-4 4.5-4s4.5 1.8 4.5 4"/><path d="M14 12.2c1.5.5 2.5 1.9 2.5 3.8"/><circle cx="14" cy="7.8" r="2"/>',
        'gear' => '<circle cx="10" cy="10" r="2.6"/><path d="M10 2.8v2M10 15.2v2M17.2 10h-2M4.8 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/>',
        'more' => '<circle cx="4.5" cy="10" r="1.4"/><circle cx="10" cy="10" r="1.4"/><circle cx="15.5" cy="10" r="1.4"/>',
        'plus' => '<path d="M10 4.5v11M4.5 10h11"/>',
        'menu' => '<path d="M3.5 5.5h13M3.5 10h13M3.5 14.5h13"/>',
        'close' => '<path d="M5.5 5.5l9 9M14.5 5.5l-9 9"/>',
        'out' => '<path d="M12.5 6V4.5a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V14"/><path d="M8.5 10h8M14 7.5 16.5 10 14 12.5"/>',
        'panel' => '<rect x="3" y="4" width="14" height="12" rx="2"/><path d="M12.5 4v12"/>',
        'search' => '<circle cx="9" cy="9" r="5.2"/><path d="M12.9 12.9 17 17"/>',
        'bell' => '<path d="M6 8.5a4 4 0 0 1 8 0c0 3.2 1.2 4.4 1.2 4.4H4.8S6 11.7 6 8.5Z"/><path d="M8.4 15.4a1.8 1.8 0 0 0 3.2 0"/>',
        'chat' => '<path d="M3.5 6a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H8l-4 3v-3a.5.5 0 0 1-.5-.5V6Z"/>',
    ];
    $icon = fn (string $name) => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$name] ?? $icons['box']).'</svg>';
@endphp

<body class="vp-adm" data-side="wide">

<div class="vp-adm-shell">

    {{-- The scrim closes whatever is open. On a desktop it is not rendered at
         all — there is nothing to close. --}}
    <div class="vp-adm-scrim" data-adm-close hidden></div>

    <aside class="vp-adm-side" id="vp-adm-side">
        {{-- The drawer's head. On a desktop this is just the mark: the shut
             button and the branch are `display: none` because a sidebar that
             is always there has nothing to close and the topbar already says
             the branch. Both are in the markup at every width rather than
             behind a Blade condition — the server has no idea how wide the
             window is, and a guess is how the phone drawer got missed before
             (see «the phone drawer is invisible to every check we have»). --}}
        <div class="vp-adm-mark">
            <a class="vp-adm-mark-link" href="{{ route('admin.dashboard') }}">
                <img src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="" width="28" height="28">
                <b>ویکی پلاس</b>
            </a>

            <button type="button" class="vp-adm-icon-btn vp-adm-shut" data-adm-close aria-label="بستن منو">
                {!! $icon('close') !!}
            </button>
        </div>

        <div class="vp-adm-side-branch">
            @include('admin.partials.branch', ['id' => 'vp-branch-side', 'label' => true])
        </div>

        <nav class="vp-adm-nav" aria-label="بخش‌های پنل">
            @foreach ($sections as $section)
                @if ($section['group'] !== '')
                    <p class="vp-adm-group">{{ $section['group'] }}</p>
                @endif
                @foreach ($section['items'] as $item)
                    <a class="vp-adm-link{{ $nav->isOn($item['match']) ? ' is-on' : '' }}"
                       href="{{ route($item['route']) }}"
                       @if ($nav->isOn($item['match'])) aria-current="page" @endif>
                        {!! $icon($item['icon']) !!}<span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="vp-adm-side-foot">
            <form method="post" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="vp-adm-out">{!! $icon('out') !!}<span>خروج</span></button>
            </form>
        </div>
    </aside>

    <div class="vp-adm-work">
        <header class="vp-adm-top">
            <button type="button" class="vp-adm-icon-btn vp-adm-drawer-btn" data-adm-drawer aria-controls="vp-adm-side" aria-expanded="false" aria-label="منوی پنل">
                {!! $icon('menu') !!}
            </button>

            <button type="button" class="vp-adm-icon-btn vp-adm-tight-btn" data-adm-tight aria-label="جمع کردن منو">
                {!! $icon('panel') !!}
            </button>

            <h1 class="vp-adm-top-title">@yield('title', $here['label'] ?? 'پنل مدیریت')</h1>

            <div class="vp-adm-spacer"></div>

            {{-- §14. A form, so it works with JavaScript off; the script only
                 adds Ctrl+K to it. On a phone the field itself is hidden by the
                 stylesheet and the icon becomes a link to the same screen —
                 there is no room for a search box beside a branch name at
                 390px, and a field squeezed to 80px is not a search box.

                 Only where a branch is bound. `/admin/search` sits inside the
                 branch group, so on a marketplace screen — where there is no
                 branch, deliberately — it would be a box in the chrome leading
                 to a 403. Offering a destination that exists but is closed is
                 the same failure as offering one that does not exist. --}}
            @if ($branch)
                <form class="vp-adm-find" method="get" action="{{ route('admin.search') }}" role="search">
                    <label class="visually-hidden" for="vp-adm-find">جست‌وجو در پنل</label>
                    {!! $icon('search') !!}
                    <input id="vp-adm-find" type="search" name="q" value="{{ request()->routeIs('admin.search') ? request()->query('q') : '' }}"
                           placeholder="جست‌وجو…  Ctrl+K" data-adm-find>
                </form>

                <a class="vp-adm-icon-btn vp-adm-find-small" href="{{ route('admin.search') }}" aria-label="جست‌وجو">
                    {!! $icon('search') !!}
                </a>
            @endif

            {{-- §17. `<details>` rather than a script: the panel is one page
                 load per action anyway, and a menu that needs JavaScript to
                 open is a menu that is sometimes not there. --}}
            <details class="vp-adm-bell">
                <summary class="vp-adm-icon-btn" aria-label="کارهای در انتظار ({{ fa_number($waitingCount) }})">
                    {!! $icon('bell') !!}
                    @if ($waitingCount > 0)
                        <span class="vp-adm-bell-count">{{ fa_number($waitingCount) }}</span>
                    @endif
                </summary>

                <div class="vp-adm-bell-panel">
                    @if ($waiting === [])
                        <p class="vp-adm-empty">چیزی در انتظار نیست.</p>
                    @else
                        <ul class="vp-adm-list">
                            @foreach ($waiting as $item)
                                <li>
                                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                                    <span class="vp-adm-badge is-{{ $item['tone'] === 'bad' ? 'cancelled' : ($item['tone'] === 'warn' ? 'placed' : 'paid') }}">
                                        {{ fa_number($item['count']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </details>

            <div class="vp-adm-who">
                @include('admin.partials.branch', ['id' => 'vp-branch'])
            </div>
        </header>

        <main class="vp-adm-page">
            @if (session('status'))
                <p class="vp-adm-note is-good">{{ session('status') }}</p>
            @endif

            @foreach ($errors->all() as $error)
                <p class="vp-adm-note is-bad">{{ $error }}</p>
            @endforeach

            @yield('content')
        </main>
    </div>

    {{-- §19's bottom navigation. Rendered always and shown by the stylesheet
         below 992 — a phone-only branch in Blade would mean the markup depends
         on a guess about the window, which the server does not have. --}}
    <nav class="vp-adm-bottom" aria-label="ناوبری اصلی">
        @foreach ($bottom as $i => $item)
            <a class="vp-adm-tab{{ $nav->isOn($item['match']) ? ' is-on' : '' }}"
               href="{{ route($item['route']) }}"
               @if ($nav->isOn($item['match'])) aria-current="page" @endif>
                {!! $icon($item['icon']) !!}<span>{{ $item['label'] }}</span>
            </a>

            @if ($i === 1 && $quickAdd !== [])
                <button type="button" class="vp-adm-add" data-adm-sheet="add" aria-label="افزودن">
                    {!! $icon('plus') !!}
                </button>
            @endif
        @endforeach

        <button type="button" class="vp-adm-tab" data-adm-sheet="more">
            {!! $icon('more') !!}<span>بیشتر</span>
        </button>
    </nav>

    <div class="vp-adm-sheet" id="vp-adm-sheet" role="dialog" aria-modal="true" aria-label="بخش‌های دیگر" hidden>
        @if ($quickAdd !== [])
            <p class="vp-adm-sheet-title" data-adm-sheet-part="add">افزودن</p>
            <ul class="vp-adm-sheet-list" data-adm-sheet-part="add">
                @foreach ($quickAdd as $add)
                    <li><a href="{{ route($add['route']) }}">{!! $icon('plus') !!}<span>{{ $add['label'] }}</span></a></li>
                @endforeach
            </ul>
        @endif

        <p class="vp-adm-sheet-title" data-adm-sheet-part="more">همه بخش‌ها</p>
        <ul class="vp-adm-sheet-list" data-adm-sheet-part="more">
            @foreach ($flat as $item)
                <li>
                    <a class="{{ $nav->isOn($item['match']) ? 'is-on' : '' }}" href="{{ route($item['route']) }}">
                        {!! $icon($item['icon']) !!}<span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>

@include('partials.scripts')

@include('partials.admin-scripts')

</body>

</html>
