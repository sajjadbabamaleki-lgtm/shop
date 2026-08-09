{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}
<div class="magic-cursor relative z-10">
        <div class="cursor"></div>
        <div class="cursor-follower"></div>
    </div>

    <div class="cursor-follower"></div>

    <!-- slider drag cursor -->
    <div class="slider-drag-cursor"><img src="{{ asset('assets/img/icon/arrow.svg') }}" alt=""></div>

    <div class="preloader ">
        <button class="th-btn preloaderCls">Cancel Preloader </button>
        <div class="preloader-inner">
            <div class="loader">
                <img src="{{ asset('assets/img/theme-img/shopping-loader.gif') }}" alt="">
            </div>
        </div>
    </div>    <div id="QuickView" class="white-popup mfp-hide">
        <div class="container bg-white rounded-20">
            <div class="row gx-60">
                <div class="col-lg-6">
                    <div class="product-big-img">
                        <div class="img"><img src="{{ asset('assets/img/product/product_details_1_1.png') }}" alt="Product Image"></div>
                    </div>
                </div>
                <div class="col-lg-6 align-self-center">
                    <div class="product-about">
                        <p class="price">$120.85<del>$150.99</del></p>
                        <h2 class="product-title">Women’s fashion Bag</h2>
                        <div class="product-rating">
                            <div class="star-rating" role="img" aria-label="Rated 5.00 out of 5"><span style="width:100%">Rated <strong class="rating">5.00</strong> out of 5 based on <span class="rating">1</span> customer rating</span></div>
                            <a href="{{ page_url('shop-details.html') }}" class="woocommerce-review-link">(<span class="count">4</span> customer reviews)</a>
                        </div>
                        <p class="text">Fashion is heavily influenced by cultural movements, social changes, and historical events. For example, the 1960s saw the rise of the hippie movement, which brought bohemian styles to the forefront. Public figures and social media influencers have a significant impact on fashion trends. Collaborations between celebrities.</p>
                        <div class="mt-2 link-inherit">
                            <p>
                                <strong class="text-title me-3">Availability:</strong>
                                <span class="stock in-stock"><i class="far fa-check-square me-2 ms-1"></i>In Stock</span>
                            </p>
                        </div>
                        <div class="actions">
                            <div class="quantity">
                                <input type="number" class="qty-input" step="1" min="1" max="100" name="quantity" value="1" title="Qty">
                                <button class="quantity-plus qty-btn"><i class="far fa-chevron-up"></i></button>
                                <button class="quantity-minus qty-btn"><i class="far fa-chevron-down"></i></button>
                            </div>
                            <button class="th-btn">Add to سبد خرید</button>
                            <a href="{{ page_url('wishlist.html') }}" class="icon-btn"><i class="far fa-heart"></i></a>
                        </div>
                        <div class="product_meta">
                            <span class="sku_wrapper">SKU: <span class="sku">Fashion-1254</span></span>
                            <span class="posted_in">Category: <a href="{{ page_url('shop.html') }}">Bag, Fashion Hand Bag, Uncategorized.</a></span>
                            <span>Tags: <a href="{{ page_url('shop.html') }}">Fashion</a></span>
                        </div>
                    </div>
                </div>
            </div>
            <button title="Close (Esc)" type="button" class="mfp-close">×</button>
        </div>
    </div>    <div class="sidemenu-wrapper sidemenu-cart ">
        <div class="sidemenu-content">
            <button class="closeButton sideMenuCls"><i class="far fa-times"></i></button>
            <div class="widget woocommerce widget_shopping_cart">
                <h3 class="widget_title">فروشگاهping cart</h3>
                <div class="widget_shopping_cart_content">
                    <ul class="woocommerce-mini-cart cart_list product_list_widget ">
                        <li class="woocommerce-mini-cart-item mini_cart_item">
                            <a href="#" class="remove remove_from_cart_button"><i class="far fa-times"></i></a>
                            <a href="#"><img src="{{ asset('assets/img/product/product_12_1.png') }}" alt="سبد خرید Image">Nike Renew Serenity Run</a>
                            <span class="quantity">1 ×
                                <span class="woocommerce-Price-amount amount">
                                    <span class="woocommerce-Price-currencySymbol">$</span>39.00</span>
                            </span>
                        </li>
                        <li class="woocommerce-mini-cart-item mini_cart_item">
                            <a href="#" class="remove remove_from_cart_button"><i class="far fa-times"></i></a>
                            <a href="#"><img src="{{ asset('assets/img/product/product_12_2.png') }}" alt="سبد خرید Image">Nike Renew Color Shoes</a>
                            <span class="quantity">1 ×
                                <span class="woocommerce-Price-amount amount">
                                    <span class="woocommerce-Price-currencySymbol">$</span>38.00</span>
                            </span>
                        </li>
                        <li class="woocommerce-mini-cart-item mini_cart_item">
                            <a href="#" class="remove remove_from_cart_button"><i class="far fa-times"></i></a>
                            <a href="#"><img src="{{ asset('assets/img/product/product_12_3.png') }}" alt="سبد خرید Image">Adidas Plastic Rebar Shoes</a>
                            <span class="quantity">1 ×
                                <span class="woocommerce-Price-amount amount">
                                    <span class="woocommerce-Price-currencySymbol">$</span>37.00</span>
                            </span>
                        </li>
                        <li class="woocommerce-mini-cart-item mini_cart_item">
                            <a href="#" class="remove remove_from_cart_button"><i class="far fa-times"></i></a>
                            <a href="#"><img src="{{ asset('assets/img/product/product_12_4.png') }}" alt="سبد خرید Image">Nike Flex Run 2021</a>
                            <span class="quantity">1 ×
                                <span class="woocommerce-Price-amount amount">
                                    <span class="woocommerce-Price-currencySymbol">$</span>32.00</span>
                            </span>
                        </li>
                        <li class="woocommerce-mini-cart-item mini_cart_item">
                            <a href="#" class="remove remove_from_cart_button"><i class="far fa-times"></i></a>
                            <a href="#"><img src="{{ asset('assets/img/product/product_12_5.png') }}" alt="سبد خرید Image">Nike Air Max 97</a>
                            <span class="quantity">1 ×
                                <span class="woocommerce-Price-amount amount">
                                    <span class="woocommerce-Price-currencySymbol">$</span>29.00</span>
                            </span>
                        </li>
                    </ul>
                    <p class="woocommerce-mini-cart__total total">
                        <strong>Subtotal:</strong>
                        <span class="woocommerce-Price-amount amount">
                            <span class="woocommerce-Price-currencySymbol">$</span>137.00</span>
                    </p>
                    <p class="woocommerce-mini-cart__buttons buttons">
                        <a href="{{ page_url('cart.html') }}" class="th-btn wc-forward">View cart</a>
                        <a href="{{ page_url('checkout.html') }}" class="th-btn checkout wc-forward">تسویه حساب</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="popup-search-box d-none d-lg-block">
        <button class="searchClose"><i class="fal fa-times"></i></button>
        <form action="#">
            <input type="text" placeholder="What are you looking for?">
            <button type="submit"><i class="fal fa-search"></i></button>
        </form>
    </div>
