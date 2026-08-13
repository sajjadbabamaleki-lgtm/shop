{{--
    The category tiles, and the five trust badges under them.

    The tiles are the catalogue's, right to left in the order the categories
    carry. The badges are copy — nothing behind them to read — so they stay
    written out.

    Hand-owned: theme/make-blade.js no longer regenerates this file.
--}}
<section class="feature-area2 positive-relative overflow-hidden">
        <div class="container th-container">
            <div class="row vp-category-row">
                @foreach ($categories as $category)
                <div class="col">
                    <a class="vp-category" href="{{ storefront_route('category', $category) }}">
                        <img src="{{ asset($category->image_path) }}" alt="" loading="lazy">
                        <span class="vp-category-label">{{ $category->name }}</span>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
        <div class="vp-trust-row-wrap">
            <div class="row gy-4 row-cols-2 row-cols-lg-3 row-cols-xl-5 vp-trust-row">
                <div class="col">
                    <div class="feature-card style2">
                        <div class="box-icon">
                            <img src="{{ asset('assets/img/icon/feature_card_1-gold.svg') }}" alt="">
                        </div>
                        <div class="box-content">
                            <h3 class="box-title">ارسال سریع</h3>
                            <p class="box-text">ارسال به سراسر کشور</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="feature-card style2">
                        <div class="box-icon">
                            <img src="{{ asset('assets/img/icon/feature_card_2-gold.svg') }}" alt="">
                        </div>
                        <div class="box-content">
                            <h3 class="box-title">ضمانت بازگشت کالا</h3>
                            <p class="box-text">بازگشت و تعویض آسان</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="feature-card style2">
                        <div class="box-icon">
                            <img src="{{ asset('assets/img/icon/secure-gold.svg') }}" alt="">
                        </div>
                        <div class="box-content">
                            <h3 class="box-title">پرداخت امن</h3>
                            <p class="box-text">پرداخت آنلاین مطمئن</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="feature-card style2">
                        <div class="box-icon">
                            <img src="{{ asset('assets/img/icon/check2-gold.svg') }}" alt="">
                        </div>
                        <div class="box-content">
                            <h3 class="box-title">تضمین اصالت</h3>
                            <p class="box-text">گارانتی اصل بودن کالا</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="feature-card style2">
                        <div class="box-icon">
                            <img src="{{ asset('assets/img/icon/feature_card_4-gold.svg') }}" alt="">
                        </div>
                        <div class="box-content">
                            <h3 class="box-title">پشتیبانی آنلاین</h3>
                            <p class="box-text">پاسخگویی ۲۴ ساعته</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="feature-card style2">
                        <div class="box-icon">
                            <i class="fa-solid fa-boxes-stacked vp-trust-glyph" aria-hidden="true"></i>
                        </div>
                        <div class="box-content">
                            <h3 class="box-title">خرید تکی و عمده</h3>
                            <p class="box-text">امکان سفارش عمده</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
