@extends('layouts.storefront')

@section('title', 'پشتیبانی ویکی پلاس')

{{--
    «پشتیبانی» — the page the front page's own promise had no destination for.

    «یه قسمتی به عنوان پشتیبانی ۲۴ ساعته تو سایت داریم ولی هیچ جای دیگه راجع به
    پشتیبانی اصلاً … کسی موردی داره و سوالی داره کجا باید این سوال رو مطرح بکنه
    یا مشکلی داره کجا باید این مشکل رو عنوان بکنه نیست». The trust row has
    promised 24-hour support since the template was dressed, and there was
    nowhere on the site to say anything to anybody: `/contact` printed a
    telephone number and said outright that it had no form, because when it was
    written there was no table to put a message in.

    There is one now. This is `Enquiry::SUPPORT`, the same inbox the wholesale
    and franchise forms write to, read in `/admin/enquiries` — **which is the
    other half of the feature, not an extra**: there is still no mail provider
    in this application, so the row *is* the delivery. Nothing here promises a
    reply by any channel except a telephone call back.

    The form is the shared partial the other two enquiry pages use, so all
    three ask their five questions the same way and cannot drift apart about
    which of them are required.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">پشتیبانی</h1>

            <p class="vp-doc-lead">
                سؤالی دارید یا مشکلی پیش آمده؟ همین‌جا بنویسید؛ همکاران ما با
                شما تماس می‌گیرند.
            </p>

            <h2>پیش از نوشتن، شاید جوابتان اینجا باشد</h2>
            <ul class="vp-doc-list">
                <li>
                    پاسخ پرسش‌های پرتکرار — هزینهٔ ارسال، زمان رسیدن، تعویض
                    سایز و پرداخت — در
                    <a href="{{ storefront_route('faq') }}">سؤال‌های متداول</a>
                    است.
                </li>
                <li>
                    برای دیدن وضعیت سفارشی که ثبت کرده‌اید، از
                    <a href="{{ storefront_route('orders.track') }}">پیگیری سفارش</a>
                    استفاده کنید.
                </li>
                <li>
                    شرایط ارسال، مرجوعی و لغو سفارش در
                    <a href="{{ storefront_route('terms') }}">قوانین و مقررات</a>
                    نوشته شده است.
                </li>
            </ul>

            <h2>طرح سؤال یا اعلام مشکل</h2>

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            @include('pages.enquiry-form', [
                'action' => storefront_route('support.send'),
                'organisationLabel' => 'شماره سفارش، اگر دربارهٔ سفارشی است (اختیاری)',
                'messageLabel' => 'سؤال یا مشکل شما',
                'submit' => 'ارسال',
            ])

            <p class="vp-doc-note">
                اگر عجله دارید، تلفن فروشگاه
                <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
                است و
                <a href="{{ storefront_route('contact') }}">صفحهٔ تماس با ما</a>
                همهٔ راه‌های دیگر را دارد.
            </p>

        </div>
    </div>
</section>
@endsection
