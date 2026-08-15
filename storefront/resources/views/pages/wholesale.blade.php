@extends('layouts.storefront')

@section('title', 'فروش عمده')

{{--
    «فروش عمده».

    The front page's trust row has said «خرید تکی و عمده / امکان سفارش عمده»
    since the template was dressed, and the footer's strap line says it on
    every page. There was no page behind either — no price, no minimum, no
    form. The shop was making an offer everywhere and had no way of hearing
    the answer.

    **No price list, and that is not an omission to fix in the view.** There is
    no wholesale tier in the catalogue — no column, no second offer, nothing —
    so any number printed here would be one nobody decided. What the page can
    honestly do is take the enquiry and say a person will telephone, which is
    what the shop does anyway.

    The form writes an `enquiries` row and that row *is* the delivery: there is
    no mail provider. /admin/enquiries is where it is read.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">فروش عمده</h1>

            @if (session('sent'))
                <p class="vp-note is-good">
                    درخواستت ثبت شد. همکاران ما با همان شماره‌ای که نوشتی تماس
                    می‌گیرند.
                </p>
            @endif

            <p class="vp-doc-lead">
                اگر بوتیک، فروشگاه یا فروش اینترنتی دارید، می‌توانید کیف و کفش
                را از ویکی پلاس عمده بخرید. بگویید چه می‌خواهید و چند تا؛ ما
                تماس می‌گیریم و قیمت و زمان تحویل را می‌گوییم.
            </p>

            <h2>چطور کار می‌کند</h2>
            <ol class="vp-doc-steps">
                <li>فرم پایین را پر کنید و بنویسید چه مدل‌هایی و چه تعدادی می‌خواهید.</li>
                <li>همکاران ما تماس می‌گیرند و قیمت عمده و موجودی سایزها را می‌گویند.</li>
                <li>سفارش را هماهنگ می‌کنیم و برای شما ارسال می‌شود.</li>
            </ol>

            <p class="vp-doc-note">
                قیمت عمده روی سایت نوشته نمی‌شود، چون به مدل، تعداد و موجودی
                همان زمان بستگی دارد. عددی که در تماس گفته می‌شود همان عددی است
                که فاکتور می‌شود.
            </p>

            <h2>درخواست قیمت عمده</h2>

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            @include('pages.enquiry-form', [
                'action' => storefront_route('wholesale.send'),
                'organisationLabel' => 'نام فروشگاه یا کسب‌وکار (اختیاری)',
                'messageLabel' => 'چه مدل‌هایی و چه تعدادی می‌خواهید؟',
                'submit' => 'ارسال درخواست',
            ])

            <p>
                عجله دارید؟ همین را در واتساپ
                <a href="{{ config('storefront.contact.whatsapp_href') }}" target="_blank" rel="noopener">{{ config('storefront.contact.whatsapp') }}</a>
                بفرستید یا با
                <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
                تماس بگیرید.
            </p>

            <div class="vp-doc-links">
                <a class="vp-doc-link" href="{{ storefront_route('shop') }}">دیدن محصولات</a>
                <a class="vp-doc-link is-quiet" href="{{ storefront_route('franchise') }}">اخذ نمایندگی</a>
            </div>
        </div>
    </div>
</section>
@endsection
