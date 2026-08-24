@extends('layouts.storefront')

@section('title', 'حساب من')

{{--
    A shopper's own account — «حساب کاربری».

    **What was here before was a heading, three gold words in a row, a fold and
    a grey box**, and the client said so: «این چه حساب کاربری داغونیه برای کاربر
    زدی خیلی داغونه». It was not a screen anybody had designed; it was the
    orders list with everything else the account had grown pushed into the
    margin beside the name.

    So it is three panels now, in the order somebody reads them:

      1. who you are, what you have, and where else to go,
      2. the orders — which is still what the account is *for*,
      3. the settings, which are housekeeping and go last.

    Everything on it is the shop's own material: `.vp-shop-panel` is the home
    page's `.vp-best-panel`, the doors are the same tinted glass as the header
    island, and the empty state is `.vp-empty`, which the wishlist already had.
    Nothing here is a new look — it is this page finally wearing the one the
    rest of the shop wears.

    The orders are this branch's, because `Order` is branch-scoped. A customer
    standing in the Shiraz shop sees what they bought in Shiraz, which is also
    the only list that branch's staff could help them with.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">

        {{-- 1. Who you are ------------------------------------------------ --}}

        <div class="vp-shop-panel vp-acct-card">

            <div class="vp-acct-top">
                {{-- The first letter of the name, in the shop's gold. A mark
                     rather than a photograph: there is nowhere to upload one
                     and a grey silhouette says less than the letter does. --}}
                <span class="vp-acct-face" aria-hidden="true">
                    @if (trim((string) $customer->name) !== '')
                        {{ mb_substr(trim($customer->name), 0, 1) }}
                    @else
                        <i class="fa-solid fa-user"></i>
                    @endif
                </span>

                <div class="vp-acct-who">
                    <h1 class="vp-acct-name">{{ $customer->name ?: 'حساب من' }}</h1>
                    {{-- `bdi`, because a phone number in a right-to-left line
                         is a left-to-right run and the leading zero ends up on
                         the wrong end without it. --}}
                    <p class="vp-acct-phone"><bdi>{{ $customer->phone }}</bdi></p>
                </div>

                <form method="post" action="{{ storefront_route('account.logout') }}">
                    @csrf
                    <button type="submit" class="vp-acct-out">
                        <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                        <span>خروج</span>
                    </button>
                </form>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            {{-- The four numbers a shopper came to check. «در جریان» is the one
                 that matters most and the one nothing on this page used to
                 say: an order still moving, as against one that is finished. --}}
            <ul class="vp-acct-figs">
                <li>
                    <b>{{ fa_number($ordersTotal) }}</b>
                    <span>سفارش</span>
                </li>
                <li>
                    <b>{{ fa_number($ordersOpen) }}</b>
                    <span>در جریان</span>
                </li>
                <li>
                    <b>{{ fa_number($wishlistCount) }}</b>
                    <span>علاقه‌مندی</span>
                </li>
                <li>
                    <b>{{ fa_number($unreadMessages) }}</b>
                    <span>پیام تازه</span>
                </li>
            </ul>

            {{-- Where else the account goes. These were three gold words in a
                 row beside the name, at the size of body text, on a page whose
                 every other control is a button — «پیام‌های من» in particular
                 is where a shop answers somebody, and it read as a footnote. --}}
            <nav class="vp-acct-doors">
                <a class="vp-acct-door" href="{{ storefront_route('account.messages') }}">
                    <span class="vp-acct-door-mark" aria-hidden="true"><i class="fa-solid fa-comment-dots"></i></span>
                    <span class="vp-acct-door-what">
                        <b>پیام‌های من</b>
                        <small>گفت‌وگو با فروشگاه</small>
                    </span>
                    @if ($unreadMessages > 0)
                        <em class="vp-acct-door-new">{{ fa_number($unreadMessages) }}</em>
                    @endif
                </a>

                <a class="vp-acct-door" href="{{ storefront_route('account.wishlist') }}">
                    <span class="vp-acct-door-mark" aria-hidden="true"><i class="fa-solid fa-heart"></i></span>
                    <span class="vp-acct-door-what">
                        <b>لیست علاقمندی</b>
                        <small>{{ $wishlistCount > 0 ? fa_number($wishlistCount).' کالای ذخیره‌شده' : 'هرچه پسندیدی، اینجا می‌ماند' }}</small>
                    </span>
                </a>

                <a class="vp-acct-door" href="{{ storefront_route('orders.track') }}">
                    <span class="vp-acct-door-mark" aria-hidden="true"><i class="fa-solid fa-truck"></i></span>
                    <span class="vp-acct-door-what">
                        <b>پیگیری سفارش</b>
                        <small>با شماره سفارش</small>
                    </span>
                </a>

                <a class="vp-acct-door" href="{{ storefront_route('shop') }}">
                    <span class="vp-acct-door-mark" aria-hidden="true"><i class="fa-solid fa-bag-shopping"></i></span>
                    <span class="vp-acct-door-what">
                        <b>ادامه خرید</b>
                        <small>دیدن همه محصولات</small>
                    </span>
                </a>
            </nav>
        </div>

        {{-- 2. The orders -------------------------------------------------- --}}

        <div class="vp-shop-panel">

            <div class="vp-acct-sect">
                <h2 class="vp-acct-sect-title">سفارش‌های من</h2>
                @if ($ordersTotal > 0)
                    <span class="vp-acct-sect-count">{{ fa_number($ordersTotal) }} سفارش</span>
                @endif
            </div>

            @if ($orders->isEmpty())
                <div class="vp-empty">
                    <span class="vp-empty-mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48"><path d="M24 4 42 13v22L24 44 6 35V13ZM6 13l18 9 18-9M24 22v22" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"></path></svg>
                    </span>
                    <p class="vp-empty-say">هنوز سفارشی ثبت نکرده‌ای.</p>
                    <a class="vp-empty-out" href="{{ storefront_route('shop') }}">رفتن به فروشگاه</a>
                </div>
            @else
                <ul class="vp-acct-orders">
                    @foreach ($orders as $order)
                        <li>
                            {{-- The status chip carries the panel's own five
                                 tones — `is-placed` through `is-cancelled` —
                                 so the same order is the same colour to the
                                 shopper and to the shop. --}}
                            <a class="vp-acct-order" href="{{ storefront_route('order', $order) }}">
                                <span class="vp-acct-order-no"><bdi>{{ $order->number }}</bdi></span>
                                <span class="vp-acct-order-state is-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                                <span class="vp-acct-order-meta">
                                    {{ fa_date($order->placed_at) }} · {{ fa_number($order->items_count) }} قلم
                                </span>
                                <span class="vp-acct-order-sum">{{ toman($order->grand_total) }} <small>تومان</small></span>
                                <i class="fa-solid fa-chevron-left vp-acct-order-go" aria-hidden="true"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="vp-shop-pages">{{ $orders->links('pagination.vikyplus') }}</div>
            @endif
        </div>

        {{-- 3. The settings ------------------------------------------------ --}}

        <div class="vp-shop-panel">

            <div class="vp-acct-sect">
                <h2 class="vp-acct-sect-title">تنظیمات حساب</h2>
            </div>

            <div class="vp-acct-set">

                {{-- The name. It had no way of being changed at all: it is set on
                     the code step, once, and a shopper who typed it in a hurry — or
                     whose row came from a checkout somebody else filled in — was
                     stuck with it. The number underneath it is not editable and
                     must not become so; `updateProfile` says why. --}}
                <form class="vp-acct-form" method="post" action="{{ storefront_route('account.profile') }}">
                    @csrf

                    <label class="vp-acct-label" for="in-name">نام و نام خانوادگی</label>

                    <div class="vp-inp">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        <input id="in-name" name="name" value="{{ old('name', $customer->name) }}"
                               required maxlength="80" autocomplete="name" placeholder="نام و نام خانوادگی">
                    </div>

                    @error('name')
                        <p class="vp-acct-err">{{ $message }}</p>
                    @enderror

                    <p class="vp-acct-fixed">
                        شماره موبایل <bdi>{{ $customer->phone }}</bdi> — با همین شماره وارد می‌شوی و عوض نمی‌شود.
                    </p>

                    <button type="submit" class="vp-acct-save">ذخیره</button>
                </form>

                {{-- «تغییر رمز عبور», folded away — housekeeping, and the panel it
                     sits in is the housekeeping panel now, so the fold is about
                     length rather than about hiding it.

                     The old password is asked for even though this session is
                     already signed in. A session left open on a shared telephone
                     is the ordinary way an account is taken, and a password
                     change is what makes that permanent. Somebody who has
                     genuinely forgotten it signs out and comes back with a code,
                     which is the stronger proof anyway.

                     Open when it is the thing that failed: the form used to
                     redirect back to a page that showed the error nowhere at
                     all, so a shopper who mistyped their current password saw
                     the fold shut and nothing else happen. --}}
                <details class="vp-acct-pass" @if ($errors->has('current') || $errors->has('password')) open @endif>
                    <summary>تغییر رمز عبور</summary>

                    <form method="post" action="{{ storefront_route('account.password.change') }}">
                        @csrf

                        <div class="vp-inp">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            <input id="in-current" name="current" type="password" maxlength="72"
                                   autocomplete="current-password" placeholder="رمز فعلی">
                            <label class="visually-hidden" for="in-current">رمز فعلی</label>
                        </div>

                        @error('current')
                            <p class="vp-acct-err">{{ $message }}</p>
                        @enderror

                        <div class="vp-inp">
                            <i class="fa-solid fa-key" aria-hidden="true"></i>
                            <input id="in-new" name="password" type="password" required maxlength="72"
                                   autocomplete="new-password" placeholder="رمز تازه">
                            <label class="visually-hidden" for="in-new">رمز تازه</label>
                        </div>

                        <div class="vp-inp">
                            <i class="fa-solid fa-key" aria-hidden="true"></i>
                            <input id="in-new2" name="password_confirmation" type="password" required maxlength="72"
                                   autocomplete="new-password" placeholder="رمز تازه، دوباره">
                            <label class="visually-hidden" for="in-new2">تکرار رمز تازه</label>
                        </div>

                        @error('password')
                            <p class="vp-acct-err">{{ $message }}</p>
                        @enderror

                        <button type="submit" class="vp-acct-save">ذخیره رمز</button>
                    </form>
                </details>

            </div>
        </div>
    </div>
</section>
@endsection
