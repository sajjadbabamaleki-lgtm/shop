@extends('layouts.storefront')

@section('title', 'سوالات متداول')

{{--
    «سوالات متداول».

    Every answer here is read off the application rather than written about it:
    the delivery charge and the free-delivery threshold are
    `storefront.checkout`, the same two numbers `CheckoutController` adds up
    with, so this page cannot quote a price the basket does not charge. The
    cancellation answer is `Order::isCancellable()` in words — placed orders
    only — and the registration answer is what `AccountController` actually
    asks for.

    The exchange answer is the honest one: there is no returns flow in this
    application, so the page says to telephone rather than describing a button
    that does not exist. `storefront.content.exchange_days` is the window, and
    it is a placeholder waiting on the client.

    `<details>` rather than the template's accordion: it needs no script, it
    opens with the keyboard, and a browser's find-in-page can see inside a
    closed one. The first is open because a page of closed boxes reads as
    empty.
--}}

@php
    $shippingFlat = (int) config('storefront.checkout.shipping_flat');
    $freeAbove = (int) config('storefront.checkout.free_shipping_above');
    $exchangeDays = (int) config('storefront.content.exchange_days');
@endphp

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">سوالات متداول</h1>

            <p class="vp-doc-lead">
                اگر جواب سؤالتان این‌جا نبود، با
                <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
                تماس بگیرید یا در
                <a href="{{ config('storefront.contact.whatsapp_href') }}" target="_blank" rel="noopener">واتساپ</a>
                بپرسید.
            </p>

            <div class="vp-faq">
                <details open>
                    <summary>هزینه ارسال چقدر است؟</summary>
                    <p>
                        هزینه ارسال {{ toman($shippingFlat) }} تومان است و برای
                        سفارش‌های بالای {{ toman($freeAbove) }} تومان رایگان
                        می‌شود. مبلغ دقیق، پیش از ثبت سفارش در صفحه پرداخت
                        نوشته می‌شود؛ چیزی بعداً به آن اضافه نمی‌شود.
                    </p>
                </details>

                <details>
                    <summary>چطور پرداخت کنم؟</summary>
                    <p>
                        فعلاً فقط پرداخت در محل، هنگام تحویل. درگاه بانکی هنوز
                        وصل نشده و تا وصل نشده، هیچ جای این سایت از شما شماره
                        کارت یا رمز نمی‌خواهد. اگر صفحه‌ای به نام ویکی پلاس از
                        شما اطلاعات بانکی خواست، مال ما نیست.
                    </p>
                </details>

                <details>
                    <summary>سفارشم را چطور پیگیری کنم؟</summary>
                    <p>
                        از
                        <a href="{{ storefront_route('orders.track') }}">صفحه پیگیری سفارش</a>،
                        با شماره سفارش (که با VP- شروع می‌شود) و شماره موبایلی
                        که سفارش با آن ثبت شده. اگر حساب کاربری دارید، همه
                        سفارش‌هایتان در
                        <a href="{{ storefront_route('account.enter') }}">حساب کاربری</a>
                        فهرست شده است.
                    </p>
                </details>

                <details>
                    <summary>می‌توانم سفارشم را لغو کنم؟</summary>
                    <p>
                        تا وقتی وضعیت سفارش «ثبت شد» است، بله — دکمه لغو در
                        همان صفحه سفارش هست و کالاها بلافاصله به فروشگاه
                        برمی‌گردند. بعد از این‌که سفارش ارسال شد دیگر از سایت
                        قابل لغو نیست و باید تماس بگیرید.
                    </p>
                </details>

                <details>
                    <summary>سایز کفش اشتباه بود. تعویض می‌کنید؟</summary>
                    <p>
                        تا {{ fa_number($exchangeDays) }} روز بعد از تحویل، برای
                        تعویض سایز با ما تماس بگیرید. تعویض فعلاً تلفنی و
                        دستی انجام می‌شود، نه از داخل سایت، پس لطفاً کالا را
                        نپوشیده و با جعبه نگه دارید تا هماهنگ شود.
                    </p>
                    <p>
                        بهتر از تعویض، این است که اول
                        <a href="{{ storefront_route('size-guide') }}">راهنمای سایز</a>
                        را ببینید یا طول پایتان را برای ما بفرستید.
                    </p>
                </details>

                <details>
                    <summary>کد تخفیف را کجا وارد کنم؟</summary>
                    <p>
                        در صفحه پرداخت، کنار جمع فاکتور. کد روی کالاهای خود
                        ویکی پلاس اعمال می‌شود و رقم آن، پیش از ثبت سفارش، در
                        همان جمع به شما نشان داده می‌شود.
                    </p>
                </details>

                <details>
                    <summary>حساب کاربری لازم است؟</summary>
                    <p>
                        نه. می‌توانید بدون ثبت‌نام سفارش دهید. اگر حساب بسازید،
                        سفارش‌هایتان یک‌جا جمع می‌شود.
                    </p>
                    <p>
                        نکته‌ای که ممکن است به آن بربخورید: اگر قبلاً با همین
                        شماره موبایل سفارش داده‌اید، هنگام ثبت‌نام شماره یکی از
                        سفارش‌های خودتان را می‌پرسیم. این تنها راهی است که
                        مطمئن شویم شماره مال خودتان است — پیامک تأیید هنوز
                        نداریم.
                    </p>
                </details>

                <details>
                    <summary>می‌خواهم کالاهایم را در ویکی پلاس بفروشم.</summary>
                    <p>
                        از
                        <a href="{{ storefront_route('vendors.apply') }}">فرم فروشنده شوید</a>
                        درخواست بدهید. ثبت‌نام رایگان است، کارمزد فقط روی فروش
                        گرفته می‌شود، و کالاهایتان پس از تأیید روی سایت
                        می‌آید.
                    </p>
                </details>
            </div>
        </div>
    </div>
</section>
@endsection
