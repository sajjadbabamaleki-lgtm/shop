{{--
    The vendor panel's shell. The branch panel's, with a different nav and the
    vendor's name where the branch's would be — the two are the same kind of
    screen for two different kinds of business, and looking alike is correct.

    The vendor's approval state is shown at all times. A vendor whose offers
    are not being displayed should not have to guess why.
--}}
<!doctype html>
<html class="no-js" lang="fa" dir="rtl">

<head>
@include('partials.head')
</head>

<body class="shoe-shop vp-admin-body">

<header class="vp-admin-bar">
    <div class="vp-admin-bar-in">
        <a class="vp-admin-mark" href="{{ route('vendor.offers') }}">
            <strong>{{ $vendor->name }}</strong>
            <span>پنل فروشنده</span>
        </a>

        <nav class="vp-admin-nav">
            <a href="{{ route('vendor.offers') }}" @class(['is-on' => request()->routeIs('vendor.offers*')])>کالاهای من</a>
            <a href="{{ route('vendor.orders') }}" @class(['is-on' => request()->routeIs('vendor.orders')])>سفارش‌ها</a>
            <a href="{{ route('vendor.account') }}" @class(['is-on' => request()->routeIs('vendor.account*')])>حساب مالی</a>
        </nav>

        <div class="vp-admin-who">
            <span @class(['vp-admin-branch', 'vp-vendor-pending' => ! $vendor->isApproved()])>{{ $vendor->statusLabel() }}</span>
            <form method="post" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="vp-admin-out">خروج</button>
            </form>
        </div>
    </div>
</header>

<main class="vp-admin-main">
    <div class="container th-container">
        @unless ($vendor->isApproved())
            <p class="vp-note is-bad">
                تا وقتی حساب فروشندگی تأیید نشده، کالاهایت در سایت نمایش داده نمی‌شوند.
                @if ($vendor->status_note) — {{ $vendor->status_note }} @endif
            </p>
        @endunless

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

</body>

</html>
