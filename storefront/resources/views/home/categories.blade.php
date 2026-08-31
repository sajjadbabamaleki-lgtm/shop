{{--
    The category tiles, and the five trust badges under them.

    The tiles are the catalogue's, right to left in the order the categories
    carry. The badges are copy — nothing behind them to read — so they stay
    written out.

    **A section that is not open yet looks exactly like one that is.** These
    tiles were given a gold «به‌زودی» band along the foot and the photograph
    behind it came down in colour; the answer was «چرا رو کارتشون تو صفحه هوم
    زدی؟؟؟» and then «آیکونشون غیره فعال نشه، فقط هرجا کلیک میشه پاپاپ کامینگ
    سون بیاد». So the only thing `coming_soon` adds to this markup is
    `data-vp-soon`, which is invisible: the script at the foot of the page
    catches the click and says it in a card instead. Nothing here is greyed,
    badged or disabled, and it must stay that way.

    The `href` is left pointing at the section's own page, which says the same
    sentence in full — that is what a middle-click and a visitor with no
    JavaScript get.

    Hand-owned: theme/make-blade.js no longer regenerates this file. The static
    preview builds the same markup in theme/make-rtl-page.js — check-parity.js
    compares the two and expects zero.
--}}
<section class="feature-area2 positive-relative overflow-hidden">
        <div class="container th-container">
            <div class="row vp-category-row">
                @foreach ($categories as $category)
                <div class="col">
                    <a class="vp-category" href="{{ storefront_route('category', $category) }}"@if ($category->coming_soon) data-vp-soon="{{ $category->name }}"@endif>
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
