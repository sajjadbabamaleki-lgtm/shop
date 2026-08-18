@extends('layouts.storefront')

@section('title', 'حساب من')

{{--
    A shopper's own account.

    What an account is *for* here is the orders, so that is what the page is.
    The one other thing on it is the wishlist, which is a table now — it was
    «a feature nobody has asked for» in this comment until «اضافه کردن به لیست
    علاقمندی» arrived with the story buttons. An address book is still a table
    that does not exist, and still not linked to from here.

    The orders are this branch's, because `Order` is branch-scoped. A customer
    standing in the Shiraz shop sees what they bought in Shiraz, which is also
    the only list that branch's staff could help them with.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head vp-acct-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">{{ $customer->name ?: 'حساب من' }}</h1>
                    <p class="vp-shop-count">{{ $customer->phone }}</p>
                </div>
                <div class="vp-account-acts">
                    {{-- The unread count is on the link rather than only inside:
                         a reply the shop sent is a thing worth noticing from the
                         page somebody lands on, not one to find by opening. --}}
                    @php
                        $unreadThreads = \App\Models\Conversation::where('customer_id', $customer->id)
                            ->get()
                            ->sum(fn ($c) => $c->unreadFor(\App\Models\Conversation::CUSTOMER, $customer->id));
                    @endphp
                    <a class="vp-cart-keep" href="{{ storefront_route('account.messages') }}">
                        پیام‌های من@if ($unreadThreads > 0) ({{ fa_number($unreadThreads) }})@endif
                    </a>
                    <a class="vp-cart-keep" href="{{ storefront_route('account.wishlist') }}">لیست علاقمندی</a>
                    <form method="post" action="{{ storefront_route('account.logout') }}">
                        @csrf
                        <button type="submit" class="vp-cart-keep">خروج</button>
                    </form>
                </div>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            {{-- The password, changeable from inside.

                 It has to live somewhere: a shopper sets one on the screen that
                 verified their number and would otherwise never be able to
                 change it. Folded into a `<details>` because it is not what the
                 page is for — the orders are.

                 The old password is asked for even though this session is
                 already signed in. A session left open on a shared telephone is
                 the ordinary way an account is taken, and a password change is
                 what makes that permanent. Somebody who has genuinely forgotten
                 it signs out and comes back with a code, which is the stronger
                 proof anyway. --}}
            <details class="vp-acct-pass">
                <summary>تغییر رمز عبور</summary>

                <form method="post" action="{{ storefront_route('account.password.change') }}">
                    @csrf

                    <div class="vp-inp">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="in-current" name="current" type="password" maxlength="72"
                               autocomplete="current-password" placeholder="رمز فعلی">
                        <label class="visually-hidden" for="in-current">رمز فعلی</label>
                    </div>

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

                    <button type="submit" class="vp-enter-go">ذخیره</button>
                </form>
            </details>

            <h2 class="vp-filter-title">سفارش‌های من</h2>

            @if ($orders->isEmpty())
                <p class="vp-note">هنوز سفارشی ثبت نکرده‌ای.</p>
                <a class="vp-cart-go vp-account-shop" href="{{ storefront_route('shop') }}">رفتن به فروشگاه</a>
            @else
                <ul class="vp-account-orders">
                    @foreach ($orders as $order)
                        <li>
                            <a href="{{ storefront_route('order', $order) }}">
                                <span class="vp-account-no">{{ $order->number }}</span>
                                <span class="vp-account-when">{{ fa_date($order->placed_at) }}</span>
                                <span class="vp-account-what">{{ fa_number($order->items_count) }} قلم</span>
                                <span class="vp-account-sum">{{ toman($order->grand_total) }} تومان</span>
                                <span class="vp-account-state">{{ $order->statusLabel() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="vp-shop-pages">{{ $orders->links('pagination.vikyplus') }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
