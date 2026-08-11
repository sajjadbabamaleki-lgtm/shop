{{--
    The drawer that opens on a phone.

    Hand-owned. It began as a port of the static page like everything else in
    partials/, and stopped being one the moment its categories came from the
    catalogue: theme/make-blade.js leaves it alone now, and a markup change made
    in download-version has to be made here too. The static preview still holds
    the same drawer with the eight seeded categories typed into it, so
    check-parity.js compares like with like.

    Every destination here is a page that exists. `page_url()` sends the
    template's filenames somewhere real or to '#'; the three shortcuts and the
    categories need query strings and parameters that a filename cannot carry,
    so those are built from named routes directly.
--}}
<div class="th-menu-wrapper">
        <div class="th-menu-area">
            <div class="vp-drawer">
                <div class="vp-drawer-head">
                    <a href="{{ page_url('index.html') }}" class="vp-logo vp-logo-drawer">
                        <img src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="ویکی پلاس">
                        <span class="vp-logo-text">
                            <b>ویکی پلاس</b>
                            <small>فروشگاه کیف و کفش زنانه</small>
                        </span>
                    </a>
                    <button type="button" class="th-menu-toggle" aria-label="بستن منو"><i class="fal fa-times" aria-hidden="true"></i></button>
                </div>
                <div class="vp-drawer-body">
                    <p class="vp-drawer-label">دسترسی سریع</p>
                    <div class="vp-drawer-quick">
                        {{-- The sale is the lit one, and it is the listing's own
                             `sale` filter rather than a page of its own — one
                             grid, opened on a different question. --}}
                        <a class="vp-quick is-lit" href="{{ storefront_route('shop') }}?sale=1">
                            <span class="vp-quick-mark"><i class="fa-solid fa-tag" aria-hidden="true"></i></span>
                            <span class="vp-quick-name">تخفیف‌دارها</span>
                        </a>
                        <a class="vp-quick" href="{{ storefront_route('shop') }}?sort=newest">
                            <span class="vp-quick-mark"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
                            <span class="vp-quick-name">جدیدترین‌ها</span>
                        </a>
                        <a class="vp-quick" href="{{ storefront_route('shop') }}?sort=bestselling">
                            <span class="vp-quick-mark"><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i></span>
                            <span class="vp-quick-name">پرفروش‌ترین‌ها</span>
                        </a>
                    </div>
                    <div class="vp-drawer-heading">
                        <p class="vp-drawer-label">فروشگاه</p>
                        <a class="vp-drawer-all" href="{{ storefront_route('shop') }}">همه محصولات</a>
                    </div>
                    {{-- The shop's sections, from the composer in
                         AppServiceProvider — the same query the row of tiles
                         under the hero uses, so the drawer and the front page
                         cannot describe two different shops. --}}
                    <ul class="vp-drawer-cats">
                        @foreach ($drawerCategories as $category)
                        <li>
                            <a href="{{ storefront_route('category', $category) }}">
                                <img class="vp-cat-icon" src="{{ asset(config('storefront.category_icons.'.$category->slug, config('storefront.category_icons.default'))) }}" alt="" loading="lazy">
                                <span class="vp-cat-name">{{ $category->name }}</span>
                                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    {{-- Two chips rather than two more full-width rows: they
                         are not sections of the shop and reading as though they
                         were made the drawer a page and a half long. --}}
                    <ul class="vp-drawer-links">
                        <li>
                            <a href="{{ page_url('order-tracking.html') }}">
                                <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                                <span>پیگیری سفارش</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ page_url('vendor-register.html') }}">
                                <i class="fa-solid fa-store" aria-hidden="true"></i>
                                <span>فروشنده شوید</span>
                            </a>
                        </li>
                    </ul>
                </div>
                {{-- «ورود / ثبت‌نام» while nobody is signed in, and the account
                     once somebody is — the same button, saying the thing that
                     is true. A signed-in customer being offered «ثبت‌نام» is
                     how a menu tells somebody it has not noticed them. --}}
                <div class="vp-drawer-foot">
                    @auth('customer')
                        <a class="vp-drawer-cta" href="{{ storefront_route('account') }}">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <span>حساب من</span>
                        </a>
                    @else
                        <a class="vp-drawer-cta" href="{{ storefront_route('account.enter') }}">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <span>ورود / ثبت‌نام</span>
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
