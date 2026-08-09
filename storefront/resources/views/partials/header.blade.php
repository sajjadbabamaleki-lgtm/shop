{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}
<header class="th-header header-layout1 header-layout6">
        <div class="header-top">
            <div class="container th-container">
                <div class="row justify-content-center justify-content-lg-between align-items-center gy-2">
                    <div class="col-lg-4">
                        <div class="header-links">
                            <ul>
                                <li><img src="{{ asset('assets/img/icon/phone8-gold.svg') }}" alt=""><a href="tel:+00123456789">+00 123 456
                                        789</a></li>
                                <li><i class="fa-sharp fa-solid fa-envelope"></i><a href="mailto:helloerna@mail.com">helloerna@mail.com</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="header-right">
                            <div class="header-links">
                                <ul>
                                    <li><a href="{{ page_url('order-tracking.html') }}">پیگیری سفارش </a></li>
                                    <li><a href="{{ page_url('my-account.html') }}" class="d-none d-xl-block"><img src="{{ asset('assets/img/icon/user4.svg') }}" alt="">ورود / ثبت‌نام</a></li>
                                </ul>
                            </div>
                            <div class="currency-menu">
                                <select class="form-select nice-select">
                                    <option selected="">English</option>
                                    <option>Spanish</option>
                                    <option>Hindi</option>
                                </select>
                            </div>
                            <div class="currency-menu">
                                <select class="form-select nice-select">
                                    <option selected="">USD</option>
                                    <option>Euro</option>
                                    <option>GBP</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                                    <li class="menu-item-has-children">
                                        <a href="{{ page_url('electronics-shop.html') }}">خانه</a>
                                        <ul class="mega-menu mega-menu-content">
                                            <li>
                                                <div class="container">
                                                    <div class="row gy-4">
                                                        <div class="col-lg-4">
                                                            <div class="mega-menu-box">
                                                                <div class="mega-menu-img">
                                                                    <img src="{{ asset('assets/img/pages/electronics-shop.jpg') }}" alt="خانه One">
                                                                    <div class="btn-wrap">
                                                                        <a target="_blank" href="{{ page_url('index.html') }}" class="th-btn">View Demo</a>
                                                                    </div>
                                                                </div>
                                                                <h3 class="mega-menu-title"><a href="{{ page_url('index.html') }}"><span>01.</span>Electronics فروشگاه</a></h3>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <div class="mega-menu-box">
                                                                <div class="mega-menu-img">
                                                                    <img src="{{ asset('assets/img/pages/fashion-shop.jpg') }}" alt="خانه Two">
                                                                    <div class="btn-wrap">
                                                                        <a target="_blank" href="{{ page_url('fashion-shop.html') }}" class="th-btn ">View Demo</a>
                                                                    </div>
                                                                </div>
                                                                <h3 class="mega-menu-title"><a href="{{ page_url('fashion-shop.html') }}"><span>02.</span>fashion-shop</a></h3>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <div class="mega-menu-box">
                                                                <div class="mega-menu-img">
                                                                    <img src="{{ asset('assets/img/pages/grocery-shop.jpg') }}" alt="خانه Three">
                                                                    <div class="btn-wrap">
                                                                        <a target="_blank" href="{{ page_url('grocery-shop.html') }}" class="th-btn ">View Demo</a>
                                                                    </div>
                                                                </div>
                                                                <h3 class="mega-menu-title"><a href="{{ page_url('grocery-shop.html') }}"><span>03.</span>خانه
                                                                        Grocery-shop</a>
                                                                </h3>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <div class="mega-menu-box">
                                                                <div class="mega-menu-img">
                                                                    <img src="{{ asset('assets/img/pages/coffee-shop.jpg') }}" alt="خانه Four">
                                                                    <div class="btn-wrap">
                                                                        <a target="_blank" href="{{ page_url('coffee-shop.html') }}" class="th-btn ">View Demo</a>
                                                                    </div>
                                                                </div>
                                                                <h3 class="mega-menu-title"><a href="{{ page_url('coffee-shop.html') }}"><span>04.</span>خانه
                                                                        Coffee فروشگاه</a>
                                                                </h3>
                                                            </div>
                                                        </div>

                                                        <div class="col-lg-4">
                                                            <div class="mega-menu-box">
                                                                <div class="mega-menu-img">
                                                                    <img src="{{ asset('assets/img/pages/furniture-shop.jpg') }}" alt="خانه ten">
                                                                    <div class="btn-wrap">
                                                                        <a target="_blank" href="{{ page_url('furniture-shop.html') }}" class="th-btn ">View Demo</a>
                                                                    </div>
                                                                </div>
                                                                <h3 class="mega-menu-title"><a href="{{ page_url('furniture-shop.html') }}"><span>5.</span>خانه
                                                                        Furniture فروشگاه
                                                                    </a>
                                                                </h3>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <div class="mega-menu-box">
                                                                <div class="mega-menu-img">
                                                                    <img src="{{ asset('assets/img/pages/shoe-shop.jpg') }}" alt="خانه ten">
                                                                    <div class="btn-wrap">
                                                                        <a target="_blank" href="{{ page_url('shoe-shop.html') }}" class="th-btn ">View Demo</a>
                                                                    </div>
                                                                </div>
                                                                <h3 class="mega-menu-title"><a href="{{ page_url('shoe-shop.html') }}"><span>6.</span>خانه shoe-shop
                                                                    </a>
                                                                </h3>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>

                                    </li>
                                    <li class="menu-item-has-children">
                                        <a href="#">فروشگاه</a>
                                        <ul class="sub-menu">
                                            <li>
                                                <a href="#">فروشگاه Layout</a>
                                                <ul class="sub-menu">
                                                    <li><a href="{{ page_url('shop-grid.html') }}">فروشگاه</a></li>
                                                    <li><a href="{{ page_url('shop-grid-left-sidebar.html') }}">فروشگاه با سایدبار راست</a></li>
                                                    <li><a href="{{ page_url('shop-grid-right-sidebar.html') }}">فروشگاه با سایدبار چپ</a></li>
                                                    <li><a href="{{ page_url('shop-list.html') }}">لیست محصولات</a></li>
                                                    <li><a href="{{ page_url('shop.html') }}">فروشگاه Full Width</a></li>
                                                </ul>
                                            </li>
                                            <li><a href="{{ page_url('shop-details.html') }}">جزئیات محصول</a></li>
                                            <li><a href="{{ page_url('cart.html') }}">سبد خرید Page</a></li>
                                            <li><a href="{{ page_url('checkout.html') }}">تسویه حساب</a></li>
                                            <li><a href="{{ page_url('wishlist.html') }}">علاقه‌مندی‌ها</a></li>
                                            <li><a href="{{ page_url('my-account.html') }}">حساب کاربری</a></li>
                                            <li><a href="{{ page_url('search-product.html') }}">Search Result for Product</a></li>
                                        </ul>
                                    </li>
                                    <li class="menu-item-has-children">
                                        <a href="#">درباره ما</a>
                                        <ul class="sub-menu">
                                            <li><a href="{{ page_url('about.html') }}">About Style 1</a></li>
                                            <li><a href="{{ page_url('about-2.html') }}">About Style 2</a></li>
                                            <li><a href="{{ page_url('about-3.html') }}">About Style 3</a></li>
                                        </ul>
                                    </li>
                                    <li class="menu-item-has-children">
                                        <a href="#">صفحات</a>
                                        <ul class="sub-menu">
                                            <li><a href="{{ page_url('order-tracking.html') }}">پیگیری سفارش</a></li>
                                            <li><a href="{{ page_url('faq.html') }}">Faq Page</a></li>
                                            <li><a href="{{ page_url('error.html') }}">Error Page</a></li>
                                        </ul>
                                    </li>
                                    <li class="menu-item-has-children">
                                        <a href="#">وبلاگ</a>
                                        <ul class="sub-menu">
                                            <li>
                                                <a href="#">وبلاگ Layout</a>
                                                <ul class="sub-menu">
                                                    <li><a href="{{ page_url('blog-grid.html') }}">وبلاگ Grid</a></li>
                                                    <li><a href="{{ page_url('blog-grid-left-sidebar.html') }}">وبلاگ Grid With Left Sidebar</a></li>
                                                    <li><a href="{{ page_url('blog-grid-right-sidebar.html') }}">وبلاگ Grid With Right Sidebar</a></li>
                                                    <li><a href="{{ page_url('blog-list.html') }}">وبلاگ List</a></li>
                                                </ul>
                                            </li>
                                            <li>
                                                <a href="{{ page_url('blog.html') }}">وبلاگ Sidebar</a>
                                                <ul class="sub-menu">
                                                    <li><a href="{{ page_url('blog-left-sidebar.html') }}">وبلاگ Left Sidebar</a></li>
                                                    <li><a href="{{ page_url('blog-right-sidebar.html') }}">وبلاگ Right Sidebar</a></li>
                                                    <li><a href="{{ page_url('blog.html') }}">وبلاگ No Sidebar</a></li>
                                                </ul>
                                            </li>
                                            <li>
                                                <a href="{{ page_url('blog.html') }}">وبلاگ Details Sidebar</a>
                                                <ul class="sub-menu">
                                                    <li><a href="{{ page_url('blog-details-left-sidebar.html') }}">وبلاگ Left Sidebar</a></li>
                                                    <li><a href="{{ page_url('blog-details-right-sidebar.html') }}">وبلاگ Right Sidebar</a></li>
                                                    <li><a href="{{ page_url('blog-details.html') }}">وبلاگ Details Without Sidebar</a></li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                    <li class="menu-item-has-children">
                                        <a href="#">تماس با ما</a>
                                        <ul class="sub-menu">
                                            <li><a href="{{ page_url('contact.html') }}">Contact Style 1</a></li>
                                            <li><a href="{{ page_url('contact-2.html') }}">Contact Style 2</a></li>
                                            <li><a href="{{ page_url('contact-3.html') }}">Contact Style 3</a></li>
                                        </ul>
                                    </li>
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
                                            <div class="search-suggestions">
                                                <div class="box-suggestions">
                                                    <div class="product-grid style9 style-flex">
                                                        <div class="box-img">
                                                            <img src="{{ asset('assets/img/product/product_12_1.png') }}" alt="">
                                                        </div>
                                                        <div class="product-grid-content">
                                                            <h3 class="box-title"><a href="{{ page_url('shop-details.html') }}">Nike Renew
                                                                    Serenity Run</a></h3>
                                                            <span class="box-price"><del>$25.00</del> $20.00
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="product-grid style9 style-flex">
                                                        <div class="box-img">
                                                            <img src="{{ asset('assets/img/product/product_12_2.png') }}" alt="">
                                                        </div>
                                                        <div class="product-grid-content">
                                                            <h3 class="box-title"><a href="{{ page_url('shop-details.html') }}">Nike Renew
                                                                    Color Shoes</a></h3>
                                                            <span class="box-price"><del>$29.00</del> $25.00
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="product-grid style9 style-flex">
                                                        <div class="box-img">
                                                            <img src="{{ asset('assets/img/product/product_12_3.png') }}" alt="">
                                                        </div>
                                                        <div class="product-grid-content">
                                                            <h3 class="box-title"><a href="{{ page_url('shop-details.html') }}">Adidas Plastic
                                                                    Rebar Shoes</a></h3>
                                                            <span class="box-price"><del>$39.00</del> $35.00
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="product-grid style9 style-flex">
                                                        <div class="box-img">
                                                            <img src="{{ asset('assets/img/product/product_12_4.png') }}" alt="">
                                                        </div>
                                                        <div class="product-grid-content">
                                                            <h3 class="box-title"><a href="{{ page_url('shop-details.html') }}">Nike Flex Run
                                                                    2021</a></h3>
                                                            <span class="box-price"><del>$39.00</del> $35.00
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="product-grid style9 style-flex">
                                                        <div class="box-img">
                                                            <img src="{{ asset('assets/img/product/product_12_5.png') }}" alt="">
                                                        </div>
                                                        <div class="product-grid-content">
                                                            <h3 class="box-title"><a href="{{ page_url('shop-details.html') }}">Nike Air Max
                                                                    97</a></h3>
                                                            <span class="box-price"><del>$39.00</del> $35.00
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div><!-- /.box-suggestions -->
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <button type="button" class="icon-btn sideMenuToggler" aria-label="سبد خرید"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><span class="badge">5</span></button>
                                <button type="button" class="th-menu-toggle d-block d-lg-none" aria-label="باز کردن منو"><i class="far fa-bars" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
