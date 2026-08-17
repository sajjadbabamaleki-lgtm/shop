{{--
    The panel's shell.

    It borrows the storefront's head — the same fonts, the same RTL build of the
    template's CSS, the same tweaks.css — because the client asked for a panel
    that looks like the shop rather than like an admin framework. What it does
    not borrow is the shop's chrome: no top bar, no mega menu, no footer. A
    person working here is not shopping.

    The branch being administered is named in the header at all times. In a
    system where the same screen shows Shiraz's numbers or Tehran's depending
    on who is signed in, "which shop am I looking at" is not a detail.
--}}
<!doctype html>
<html class="no-js" lang="fa" dir="rtl">

<head>
@include('partials.head')
</head>

@php
    // The marketplace screens have no branch — a vendor sells across the whole
    // platform — so the shell has to hold that without falling over.
    $branch = $branch ?? null;
@endphp
<body class="shoe-shop vp-admin-body">

<header class="vp-admin-bar">
    <div class="vp-admin-bar-in">
        <a class="vp-admin-mark" href="{{ route('admin.dashboard') }}">
            <strong>ویکی پلاس</strong>
            <span>پنل شعبه</span>
        </a>

        <nav class="vp-admin-nav">
            @if ($branch)
                <a href="{{ route('admin.dashboard') }}" @class(['is-on' => request()->routeIs('admin.dashboard')])>خانه</a>
                <a href="{{ route('admin.orders') }}" @class(['is-on' => request()->routeIs('admin.order*')])>سفارش‌ها</a>
                <a href="{{ route('admin.inventory') }}" @class(['is-on' => request()->routeIs('admin.inventory*')])>موجودی</a>
                @if (auth()->user()->hasPermissionToAt($branch, 'branch.pricing.manage'))
                    <a href="{{ route('admin.pricing') }}" @class(['is-on' => request()->routeIs('admin.pricing*')])>قیمت‌ها</a>
                    <a href="{{ route('admin.discounts') }}" @class(['is-on' => request()->routeIs('admin.discounts*')])>تخفیف‌ها</a>
                @endif
                @if (auth()->user()->hasPermissionToAt($branch, 'report.view'))
                    <a href="{{ route('admin.reports') }}" @class(['is-on' => request()->routeIs('admin.reports')])>گزارش</a>
                @endif
                @if (auth()->user()->hasPermissionToAt($branch, 'branch.staff.manage'))
                    <a href="{{ route('admin.staff') }}" @class(['is-on' => request()->routeIs('admin.staff*')])>کارکنان</a>
                @endif
            @endif
            {{-- The marketplace is not a branch's business, so these appear
                 only for the platform-wide permissions that own them. --}}
            @if (auth()->user()->hasPermissionTo('catalogue.manage'))
                <a href="{{ route('admin.catalogue') }}" @class(['is-on' => request()->routeIs('admin.catalogue') || request()->routeIs('admin.product.*')])>کاتالوگ</a>
            @endif
            @if (auth()->user()->hasPermissionTo('report.view'))
                <a href="{{ route('admin.reports.platform') }}" @class(['is-on' => request()->routeIs('admin.reports.platform')])>گزارش پلتفرم</a>
            @endif
            @if (auth()->user()->hasPermissionTo('vendor.view'))
                <a href="{{ route('admin.vendors') }}" @class(['is-on' => request()->routeIs('admin.vendor*')])>فروشندگان</a>
            @endif
            @if (auth()->user()->hasPermissionTo('marketplace.settlement.view'))
                <a href="{{ route('admin.settlements') }}" @class(['is-on' => request()->routeIs('admin.settlement*')])>تسویه‌ها</a>
            @endif
            @if (auth()->user()->hasPermissionTo('platform.enquiry.manage'))
                <a href="{{ route('admin.enquiries') }}" @class(['is-on' => request()->routeIs('admin.enquir*')])>درخواست‌ها</a>
            @endif
            @if (auth()->user()->hasPermissionTo('marketplace.commission.manage'))
                <a href="{{ route('admin.commissions') }}" @class(['is-on' => request()->routeIs('admin.commissions*')])>کارمزد</a>
            @endif
            @if ($branch && auth()->user()->hasPermissionToAt($branch, 'branch.settings.manage'))
                <a href="{{ route('admin.settings') }}" @class(['is-on' => request()->routeIs('admin.settings*')])>تنظیمات</a>
            @endif
        </nav>

        <div class="vp-admin-who">
            @php $branches = auth()->user()->administrableBranches(); @endphp

            @if ($branch && $branches->count() > 1)
                <form method="post" action="{{ route('admin.branch.switch') }}" class="vp-admin-switch">
                    @csrf
                    <label class="visually-hidden" for="vp-branch">شعبه</label>
                    <select id="vp-branch" name="branch" onchange="this.form.submit()">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit">برو</button></noscript>
                </form>
            @elseif ($branch)
                <span class="vp-admin-branch">{{ $branch->name }}</span>
            @endif

            <form method="post" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="vp-admin-out">خروج</button>
            </form>
        </div>
    </div>
</header>

<main class="vp-admin-main">
    <div class="container th-container">
        @if (session('status'))
            <p class="vp-note is-good">{{ session('status') }}</p>
        @endif

        @foreach ($errors->all() as $error)
            <p class="vp-note is-bad">{{ $error }}</p>
        @endforeach

        @yield('content')
    </div>
</main>

@include('partials.scripts')

{{--
    Two small things the panel needs on a phone, and nothing on a desktop.

    **The cells get their column's heading.** Below 992 tweaks.css turns every
    row into a card, and a card of bare values — «۳۷», «۴۰», «۲» — says
    nothing without them. Doing it here rather than writing `data-label` into
    seventeen tables by hand means the next table somebody adds is covered
    without them having to know: the label comes from the table's own
    `<thead>`, so it cannot drift out of step with the column it names either.

    It is deliberately not a fallback the page depends on. Without this script
    no table carries `data-vp-stack`, the stacking rules match nothing, and the
    panel scrolls its tables sideways exactly as it did before — the old
    behaviour, not a broken screen.

    **The strip scrolls to where you are.** The links are one scrolling line on
    a phone and «تنظیمات» is the fourteenth of them, so the screen you are on
    can start off past the edge. `inline: 'center'` rather than `'nearest'`
    because a link flush against the edge reads as the end of the strip.
--}}
<script>
    (function () {
        document.querySelectorAll('.vp-admin-table').forEach(function (table) {
            var headings = [].map.call(
                table.querySelectorAll('thead th'),
                function (th) { return th.textContent.trim(); }
            );

            if (!headings.length) return;

            [].forEach.call(table.querySelectorAll('tbody tr'), function (row) {
                var column = 0;

                // `row.cells`, not `row.children`. Several of these rows wrap
                // their controls in a <form>, and a <form> is not allowed as a
                // child of <tr> — the parser leaves it, and its hidden inputs,
                // sitting among the cells as siblings. Walking `children`
                // counted those three as columns and every label after them
                // came out shifted: «شمارش», «حد هشدار» and «توضیح» arrived
                // blank on the inventory screen. `cells` is the row's actual
                // cells and nothing else.
                [].forEach.call(row.cells, function (cell) {
                    var span = cell.colSpan || 1;

                    // A cell across the whole table is an empty state, not a
                    // value under a heading.
                    if (span > 1) {
                        cell.setAttribute('data-vp-full', '');
                    } else if (headings[column] !== undefined) {
                        cell.setAttribute('data-label', headings[column]);
                    }

                    column += span;
                });
            });

            table.setAttribute('data-vp-stack', '');
        });

        var here = document.querySelector('.vp-admin-nav a.is-on');

        if (here && here.scrollIntoView) {
            here.scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    }());
</script>

</body>

</html>
