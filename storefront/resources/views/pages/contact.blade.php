@extends('layouts.storefront')

@section('title', 'تماس با ویکی پلاس')

{{--
    «تماس با ما».

    **No form.** There is no mail provider configured in this application and
    no table to put a message in, so a contact form here would take what
    somebody typed and drop it — which is worse than not offering one, and is
    exactly the failure the footer's twenty-one dead links already were. When a
    mail provider arrives, the form goes in this panel and this comment goes.

    Every channel listed is one that actually answers: the telephone and the
    address are the client's own, and the WhatsApp number is the one behind the
    floating button that is on every page of this site.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">تماس با ما</h1>

            <p class="vp-doc-lead">
                برای سفارش، پیگیری، تعویض سایز یا هر سؤالی درباره کالاها، از هر
                کدام از این راه‌ها که راحت‌تر است با ما حرف بزنید.
            </p>

            <ul class="vp-contact-list">
                <li>
                    <span class="vp-contact-mark" aria-hidden="true"><i class="fa-solid fa-phone"></i></span>
                    <span class="vp-contact-what">
                        <b>تلفن فروشگاه</b>
                        <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
                        <small>{{ config('storefront.contact.hours') }}</small>
                    </span>
                </li>
                <li>
                    <span class="vp-contact-mark is-whatsapp" aria-hidden="true"><i class="fa-brands fa-whatsapp"></i></span>
                    <span class="vp-contact-what">
                        <b>واتساپ</b>
                        <a href="{{ config('storefront.contact.whatsapp_href') }}" target="_blank" rel="noopener">{{ config('storefront.contact.whatsapp') }}</a>
                        <small>عکس کالا و سؤال سایز را همین‌جا بفرستید.</small>
                    </span>
                </li>
                <li>
                    <span class="vp-contact-mark" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                    <span class="vp-contact-what">
                        <b>نشانی فروشگاه</b>
                        <span>{{ config('storefront.contact.address') }}</span>
                    </span>
                </li>
            </ul>

            <h2>پیگیری سفارشی که ثبت کرده‌اید</h2>
            <p>
                برای دیدن وضعیت یک سفارش لازم نیست تماس بگیرید:
                <a href="{{ storefront_route('orders.track') }}">صفحه پیگیری سفارش</a>
                با شماره سفارش و شماره موبایلی که سفارش با آن ثبت شده، وضعیت را
                نشان می‌دهد.
            </p>

            <h2>سؤال‌های پرتکرار</h2>
            <p>
                جواب بیشتر سؤال‌ها (هزینه ارسال، روش پرداخت، تعویض سایز) در
                <a href="{{ storefront_route('faq') }}">صفحه سوالات متداول</a>
                نوشته شده است.
            </p>

            <h2>همکاری در فروش</h2>
            <p>
                اگر می‌خواهید کالاهایتان را در ویکی پلاس بفروشید، از
                <a href="{{ storefront_route('vendors.apply') }}">فرم فروشنده شوید</a>
                درخواست بدهید. ثبت‌نام رایگان است و کارمزد فقط روی فروش گرفته
                می‌شود.
            </p>
        </div>
    </div>
</section>
@endsection
