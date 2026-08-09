{{--
    The mini-store layout (architecture §4).

    This file shares nothing with the parent store's layout, and that is the
    whole design. There is no partial included from both, no header component
    with a "back to VikyPlus" link behind a flag, and no footer carrying the
    platform's name — because every one of those is a place the parent leaks
    back in, and the client's requirement was that a customer given this
    address finds no way through.

    The routing does the real enforcement (see TenantRouting): on a mini
    store's own hostname the parent's routes are not registered at all. This
    layout is the second line, and GuardMiniStoreIsolation is the tripwire that
    fails the test suite if either is ever undone.

    The only branding here is the seller's own.
--}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $tenant->storeName())</title>
    <meta name="description" content="{{ $tenant->storefrontSettings()['tagline'] ?? $tenant->storeName() }}">
    @include('partials.tokens')
    <style>
        :root { --accent: {{ $tenant->storefrontSettings()['accent'] ?? '#C0972F' }}; }
        .shop-head { padding: 22px 0 8px; }
        .shop-mark {
            width: 52px; height: 52px; border-radius: 16px; object-fit: cover;
            background: var(--glass); display: grid; place-items: center;
            font-weight: 700; color: var(--accent);
        }
        .shop-nav { display: flex; gap: 18px; font-size: .9rem; }
        .shop-nav a { padding-bottom: 3px; }
        .shop-nav a.on { box-shadow: 0 1.4px 0 var(--accent); }
        .shop-foot { margin: 48px 0 32px; color: var(--ink-soft); font-size: .84rem; }
        .price { font-weight: 700; font-variant-numeric: tabular-nums; }
        .price s { color: var(--ink-faint); font-weight: 400; margin-inline-start: 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <header class="shop-head row row--between">
        <a href="{{ route('store.home') }}" class="row" style="gap:12px">
            @if($tenant->logo_path)
                <img src="{{ asset($tenant->logo_path) }}" alt="{{ $tenant->storeName() }}" class="shop-mark">
            @else
                <span class="shop-mark">{{ mb_substr($tenant->storeName(), 0, 1) }}</span>
            @endif
            <span>
                <b>{{ $tenant->storeName() }}</b>
                @if($tenant->storefrontSettings()['tagline'] ?? null)
                    <span class="faint" style="display:block">{{ $tenant->storefrontSettings()['tagline'] }}</span>
                @endif
            </span>
        </a>

        <nav class="shop-nav">
            <a href="{{ route('store.home') }}" @class(['on' => request()->routeIs('store.home')])>خانه</a>
            <a href="{{ route('store.catalog') }}" @class(['on' => request()->routeIs('store.catalog')])>محصولات</a>
            <a href="{{ route('store.about') }}" @class(['on' => request()->routeIs('store.about')])>درباره ما</a>
            <a href="{{ route('store.contact') }}" @class(['on' => request()->routeIs('store.contact')])>تماس</a>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="shop-foot pane pane--tight">
        <div class="row row--between">
            <span>{{ $tenant->storeName() }}</span>
            <span>
                @if($tenant->phone) {{ $tenant->phone }} @endif
                @if($tenant->city) · {{ $tenant->city }} @endif
            </span>
        </div>
    </footer>
</div>
</body>
</html>
