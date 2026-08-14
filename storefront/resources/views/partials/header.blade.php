{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}
<header class="th-header header-layout1 header-layout6">
        <div class="sticky-wrapper">
            <!-- Main Menu Area -->
            <div class="menu-area">
                <div class="container th-container">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-auto">
                            <div class="header-logo">
                                <a href="{{ page_url('index.html') }}" class="vp-logo">
                                    <img src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="ویکی پلاس">
                                    <span class="vp-logo-text">
                                        <b>ویکی پلاس</b>
                                        <small>فروشگاه کیف و کفش زنانه</small>
                                    </span>
                                </a>
                            </div>
                        </div>
                        <div class="col-auto me-xl-auto">
                            <nav class="main-menu d-none d-lg-inline-block">
                                <ul>
                                    <li><a href="{{ page_url('index.html') }}">خانه</a></li>
                                    <li><a href="{{ page_url('shop.html') }}">فروشگاه</a></li>
                                    <li><a href="{{ page_url('order-tracking.html') }}">پیگیری سفارش</a></li>
                                    <li><a href="{{ page_url('vendor-register.html') }}">فروشنده شوید</a></li>
                                </ul>
                            </nav>
                        </div>
                        <div class="col-auto">
                            <div class="header-button">
                                <div class="top-search">
                                    <form class="header-search">
                                        <div class="box-search ">
                                            <input type="text" placeholder="جستجوی محصول یا برند...">
                                            <button type="submit" class="th-btn" aria-label="جستجو"><svg class="vp-search-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="8.5" cy="8.5" r="6" stroke="currentColor" stroke-width="2"/><line x1="12.9" y1="12.9" x2="15.3" y2="15.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
                                            
                                        </div>
                                    </form>
                                </div>
                                <a href="{{ page_url('search-product.html') }}" class="icon-btn vp-search-btn d-lg-none" aria-label="جستجو"><svg class="vp-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="8.5" cy="8.5" r="6" stroke="currentColor" stroke-width="2"/><line x1="12.9" y1="12.9" x2="15.3" y2="15.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></a><a href="{{ page_url('my-account.html') }}" class="icon-btn vp-account-btn" aria-label="{{ auth('customer')->check() ? 'حساب من' : 'ورود / ثبت‌نام' }}"><i class="fa-solid fa-user" aria-hidden="true"></i></a><button type="button" class="icon-btn sideMenuToggler" aria-label="سبد خرید"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><span class="badge">{{ fa_number($basketCount ?? 0) }}</span></button>
                                <button type="button" class="th-menu-toggle d-block d-lg-none" aria-label="باز کردن منو"><i class="far fa-bars" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
