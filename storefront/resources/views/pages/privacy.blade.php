@extends('layouts.storefront')

@section('title', 'حریم خصوصی')

{{--
    «حریم خصوصی».

    ⚠️ A draft, like the terms next to it — accurate about the software, not
    reviewed by a lawyer.

    What makes it worth publishing as it stands is that it is *checkable*: the
    list of what we hold is the `customers` and `orders` columns, the sentence
    about passwords is true because the column is hashed and nothing reads it
    back, the sentence about staff actions being recorded is true because
    `audits` is a real table with real rows in it, and the cookie paragraph
    describes the session cookie and the basket token that `CartManager` keeps
    in it — which is genuinely all this site sets.

    If a tracking script or an analytics tag is ever added, this page is where
    that has to be said, and the day it is added is the day this comment is
    read again.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc vp-legal">

            <h1 class="vp-shop-title">حریم خصوصی</h1>

            <p class="vp-doc-lead">
                این صفحه می‌گوید چه اطلاعاتی از شما نگه می‌داریم، چرا، و با چه
                کسی به اشتراک می‌گذاریم. کوتاه است، چون چیز زیادی نگه
                نمی‌داریم.
            </p>

            <h2>چه چیزی نگه می‌داریم</h2>
            <ul class="vp-doc-list">
                <li>
                    برای ثبت سفارش: نام، شماره موبایل، نشانی، کد پستی و
                    توضیحی که برای پیک می‌نویسید.
                </li>
                <li>
                    سابقه سفارش‌هایتان: چه چیزی، چه تعداد، به چه قیمتی و در
                    چه تاریخی. این همان چیزی است که فاکتور و پیگیری سفارش را
                    ممکن می‌کند.
                </li>
                <li>
                    اگر حساب کاربری بسازید: رمزتان به‌صورت رمزنگاری‌شده ذخیره
                    می‌شود. رمز شما در دسترس ما هم نیست و قابل بازگرداندن
                    نیست.
                </li>
            </ul>
            <p>
                شمارهٔ کارت و رمز شما به این سایت وارد نمی‌شود: پرداخت روی
                صفحهٔ خود درگاه بانکی انجام می‌شود. چیزی که از یک پرداخت نزد
                ما می‌ماند، شمارهٔ پیگیری آن و چهار رقم آخر کارت است که خود
                درگاه برمی‌گرداند؛ به همین دلیل که اگر پرداختی گم شد، بشود
                پیگیری‌اش کرد.
            </p>

            <h2>کوکی‌ها</h2>
            <p>
                این سایت یک کوکی می‌گذارد تا بداند سبد خرید و ورود شما مال
                کدام مرورگر است. بدون آن، سبد خرید بین دو صفحه فراموش می‌شود.
                کوکی تبلیغاتی و ردیاب روی این سایت نداریم.
            </p>

            <h2>با چه کسی به اشتراک می‌گذاریم</h2>
            <ul class="vp-doc-list">
                <li>
                    با شرکت پست یا پیک، به اندازه‌ای که برای رساندن بسته لازم
                    است: نام، نشانی و شماره تماس.
                </li>
                <li>
                    اگر کالایی را از فروشنده دیگری در ویکی پلاس خریده باشید،
                    اطلاعات لازم برای ارسال همان کالا به آن فروشنده داده
                    می‌شود.
                </li>
            </ul>
            <p>
                اطلاعات شما را به کسی نمی‌فروشیم و برای تبلیغات در اختیار
                دیگران نمی‌گذاریم.
            </p>

            <h2>دسترسی کارکنان</h2>
            <p>
                کارکنان فروشگاه فقط به اندازه کاری که انجام می‌دهند به سفارش‌ها
                دسترسی دارند، و تغییری که در پنل روی یک سفارش یا موجودی داده
                می‌شود ثبت می‌شود: چه کسی، چه زمانی و چه چیزی را عوض کرده.
            </p>

            <h2>حق شما</h2>
            <p>
                می‌توانید بخواهید اطلاعاتتان را اصلاح کنیم یا حسابتان را
                ببندیم؛ کافی است
                <a href="{{ storefront_route('contact') }}">تماس بگیرید</a>.
                سوابق مالی سفارش‌های انجام‌شده را به عنوان سند فروش نگه
                می‌داریم، حتی اگر حساب بسته شود.
            </p>

            <p class="vp-legal-date">
                آخرین بروزرسانی: {{ fa_date(\Illuminate\Support\Carbon::parse(config('storefront.content.legal_updated'))) }}
            </p>
        </div>
    </div>
</section>
@endsection
