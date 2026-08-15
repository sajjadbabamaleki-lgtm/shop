@extends('layouts.storefront')

@section('title', 'اخذ نمایندگی ویکی پلاس')

{{--
    «اخذ نمایندگی».

    The branch network is the largest thing in this application — `branches`,
    a price and a stock figure per branch, a panel scoped to the signed-in
    person's branch, `php artisan branch:open` — and until this page there was
    no way for anybody outside to *ask* for one. Vendors have `/vendors/apply`;
    a prospective franchisee had the telephone number.

    **This page opens nothing.** A branch is created by `branch:open` after a
    contract exists between two companies, and no public form is going to
    shortcut that. What this does is file the enquiry and say honestly what
    happens next, which is a telephone call.

    What the page states about a branch is what the code actually does: its own
    prices, its own stock, its own panel, the central catalogue. That is
    `BranchOpener` and `ResolveTenant` described in Persian, not a sales
    promise.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">اخذ نمایندگی</h1>

            @if (session('sent'))
                <p class="vp-note is-good">
                    درخواستت ثبت شد. همکاران ما با همان شماره‌ای که نوشتی تماس
                    می‌گیرند.
                </p>
            @endif

            <p class="vp-doc-lead">
                ویکی پلاس در شهرهای دیگر نماینده می‌پذیرد. نماینده، فروشگاه
                خودش را دارد و زیر نام ویکی پلاس می‌فروشد — با کاتالوگ مشترک و
                قیمت و موجودی خودش.
            </p>

            <h2>نماینده چه چیزی می‌گیرد</h2>
            <ul class="vp-doc-list">
                <li>
                    <b>یک آدرس از سایت.</b> فروشگاه شما روی
                    vikyplus.ir با نشانی شهر خودتان باز می‌شود و مشتری همان‌جا
                    خرید می‌کند.
                </li>
                <li>
                    <b>قیمت و موجودی خودتان.</b> کاتالوگ مشترک است، اما قیمت هر
                    کالا و موجودی هر سایز مال شعبه شماست و کسی از بیرون آن را
                    عوض نمی‌کند.
                </li>
                <li>
                    <b>پنل خودتان.</b> سفارش‌ها، موجودی، قیمت‌ها، تخفیف‌ها و
                    گزارش فروش شعبه — فقط شعبه خودتان، نه شعبه دیگران.
                </li>
                <li>
                    <b>کارکنان خودتان.</b> برای هر نفر حساب جدا با دسترسی
                    محدود می‌سازید و هر تغییری که می‌دهند ثبت می‌شود.
                </li>
            </ul>

            <h2>مسیر کار</h2>
            <ol class="vp-doc-steps">
                <li>فرم پایین را پر کنید و بنویسید در کدام شهر و با چه سابقه‌ای.</li>
                <li>همکاران ما تماس می‌گیرند و شرایط و سرمایه اولیه را می‌گویند.</li>
                <li>پس از توافق و قرارداد، شعبه شما روی سایت باز می‌شود.</li>
            </ol>

            <p class="vp-doc-note">
                شرایط مالی و سرمایه اولیه در تماس گفته می‌شود، چون به شهر و
                اندازه فروشگاه بستگی دارد. این فرم فقط یک درخواست است و
                تعهدی برای هیچ‌کدام از دو طرف نمی‌سازد.
            </p>

            <h2>درخواست نمایندگی</h2>

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            @include('pages.enquiry-form', [
                'action' => storefront_route('franchise.send'),
                'organisationLabel' => 'نام فروشگاه فعلی (اختیاری)',
                'messageLabel' => 'در کدام شهر، و چه سابقه‌ای در این کار دارید؟',
                'submit' => 'ارسال درخواست',
            ])

            <div class="vp-doc-links">
                <a class="vp-doc-link" href="{{ storefront_route('contact') }}">تماس با ما</a>
                <a class="vp-doc-link is-quiet" href="{{ storefront_route('wholesale') }}">فروش عمده</a>
            </div>
        </div>
    </div>
</section>
@endsection
