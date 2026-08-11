{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}
<div class="th-menu-wrapper">
        <div class="th-menu-area text-center">
            <button class="th-menu-toggle" aria-label="بستن منو"><i class="fal fa-times" aria-hidden="true"></i></button>
            <div class="mobile-logo">
                <a href="{{ page_url('index.html') }}" class="vp-logo vp-logo-stack">
                    <img src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="ویکی پلاس">
                    <span class="vp-logo-text">
                        <b>ویکی پلاس</b>
                        <small>فروشگاه کیف و کفش زنانه</small>
                    </span>
                </a>
            </div>
            <div class="th-mobile-menu">
                <ul>
                    <li class="menu-item-has-children">
                        <a href="{{ page_url('electronics-shop.html') }}">خانه</a>
                        <ul class="sub-menu">
                            <li><a href="{{ page_url('electronics-shop.html') }}">Electronics فروشگاه</a></li>
                            <li><a href="{{ page_url('fashion-shop.html') }}">fashion-shop</a></li>
                            <li><a href="{{ page_url('grocery-shop.html') }}">Grocery-shop</a></li>
                            <li><a href="{{ page_url('coffee-shop.html') }}">Coffee فروشگاه</a></li>
                            <li><a href="{{ page_url('furniture-shop.html') }}">Furniture فروشگاه</a></li>
                            <li><a href="{{ page_url('shoe-shop.html') }}">Shoe فروشگاه</a></li>
                        </ul>
                    </li>
                    <li class="menu-item-has-children">
                        <a href="#">فروشگاه</a>
                        <ul class="sub-menu">
                            <li><a href="{{ page_url('shop-grid.html') }}">فروشگاه</a></li>
                            <li><a href="{{ page_url('shop-grid-left-sidebar.html') }}">فروشگاه با سایدبار راست</a></li>
                            <li><a href="{{ page_url('shop-grid-right-sidebar.html') }}">فروشگاه با سایدبار چپ</a></li>
                            <li><a href="{{ page_url('shop-list.html') }}">لیست محصولات</a></li>
                            <li><a href="{{ page_url('shop.html') }}">فروشگاه Full Width</a></li>
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
                            <li><a href="{{ page_url('blog-grid.html') }}">وبلاگ Grid</a></li>
                            <li><a href="{{ page_url('blog-grid-left-sidebar.html') }}">وبلاگ Grid With Left Sidebar</a></li>
                            <li><a href="{{ page_url('blog-grid-right-sidebar.html') }}">وبلاگ Grid With Right Sidebar</a></li>
                            <li><a href="{{ page_url('blog-list.html') }}">وبلاگ List</a></li>
                            <li><a href="{{ page_url('blog-left-sidebar.html') }}">وبلاگ Left Sidebar</a></li>
                            <li><a href="{{ page_url('blog-right-sidebar.html') }}">وبلاگ Right Sidebar</a></li>
                            <li><a href="{{ page_url('blog.html') }}">وبلاگ No Sidebar</a></li>
                            <li><a href="{{ page_url('blog-details-left-sidebar.html') }}">وبلاگ Left Sidebar</a></li>
                            <li><a href="{{ page_url('blog-details-right-sidebar.html') }}">وبلاگ Right Sidebar</a></li>
                            <li><a href="{{ page_url('blog-details.html') }}">وبلاگ Details Without Sidebar</a></li>
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
            </div>
        </div>
    </div>
