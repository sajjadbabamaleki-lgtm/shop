@extends('layouts.storefront')

@section('title', 'قوانین و مقررات')

{{--
    «قوانین و مقررات».

    ⚠️ **A draft.** It is accurate about the software — every rule below is one
    this application actually applies, and the two that are not enforced in
    code (the exchange window, and delivery times) say so — but nobody
    qualified in Iranian consumer law has read it. It is here because a shop
    that takes money with no terms at all is worse off than one with a draft,
    and because «قوانین و مقررات» is a footer link that has gone nowhere since
    the template arrived. Have it reviewed before the shop grows.

    Where a clause describes behaviour, it describes the real behaviour:
    `PlaceOrder` really does re-check stock inside the transaction and refuse
    the order rather than overselling; `Order::isCancellable()` really does
    allow cancellation only while the order is «ثبت شد»; a marketplace line
    really is supplied by the vendor named on it.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc vp-legal">

            <h1 class="vp-shop-title">قوانین و مقررات</h1>

            <p class="vp-doc-lead">
                با ثبت سفارش در ویکی پلاس، این شرایط را می‌پذیرید. سعی کرده‌ایم
                کوتاه و بی‌ابهام بنویسیم؛ هر جا سؤالی داشتید
                <a href="{{ storefront_route('contact') }}">تماس بگیرید</a>.
            </p>

            <h2>۱. قیمت‌ها</h2>
            <p>
                همه قیمت‌ها به تومان است و همان چیزی است که در سبد خرید و
                فاکتور می‌آید. هزینه ارسال جداگانه و پیش از ثبت سفارش نشان داده
                می‌شود. قیمت‌ها ممکن است تغییر کند، اما قیمت سفارشی که ثبت شده
                همان است که در لحظه ثبت به شما نشان داده شده و بعداً تغییر
                نمی‌کند.
            </p>

            <h2>۲. ثبت سفارش و موجودی</h2>
            <p>
                سفارش وقتی قطعی است که موجودی همان لحظه کنار گذاشته شود. اگر
                آخرین موجودی یک سایز، بین دیدن صفحه و ثبت سفارش شما فروخته شود،
                سفارش ثبت نمی‌شود و همان‌جا به شما گفته می‌شود کدام کالا مانده
                است — به جای این‌که سفارش را بگیریم و بعد بگوییم نداریم.
            </p>

            <h2>۳. پرداخت</h2>
            <p>
                در حال حاضر تنها روش پرداخت، پرداخت در محل هنگام تحویل است.
                هیچ‌جای این سایت اطلاعات کارت بانکی از شما نمی‌خواهد. اگر بعداً
                درگاه بانکی اضافه شود، پیش از پرداخت به‌روشنی گفته می‌شود.
            </p>

            <h2>۴. ارسال</h2>
            <p>
                سفارش‌ها به سراسر ایران فرستاده می‌شود. زمان تحویل به مقصد و به
                شرکت پست یا پیک بستگی دارد و از کنترل ما بیرون است؛ زمان تقریبی
                را هنگام هماهنگی به شما می‌گوییم.
            </p>

            <h2>۵. لغو سفارش</h2>
            <p>
                تا وقتی وضعیت سفارش «ثبت شد» است، خودتان می‌توانید از صفحه
                سفارش آن را لغو کنید و کالاها بلافاصله به فروشگاه برمی‌گردد.
                پس از ارسال، لغو از سایت ممکن نیست و باید تماس بگیرید.
            </p>

            <h2>۶. تعویض کالا</h2>
            <p>
                تا {{ fa_number((int) config('storefront.content.exchange_days')) }}
                روز پس از تحویل، اگر کالا نپوشیده و همراه جعبه و لوازم آن باشد،
                تعویض سایز را هماهنگ می‌کنیم. این کار فعلاً تلفنی انجام می‌شود.
            </p>

            <h2>۷. کالاهای فروشندگان دیگر</h2>
            <p>
                کنار کالاهای ویکی پلاس، کالاهایی هم هست که فروشندگان دیگری
                عرضه می‌کنند؛ نام فروشنده روی همان کالا نوشته شده. تأمین و
                کیفیت آن کالا با فروشنده است، اما پیگیری سفارش و رسیدگی به
                شکایت از طریق ما انجام می‌شود.
            </p>

            <h2>۸. شعبه‌ها</h2>
            <p>
                هر شعبه ویکی پلاس موجودی و قیمت خودش را دارد. سفارشی که ثبت
                می‌کنید به همان شعبه‌ای تعلق دارد که در آن خرید کرده‌اید و
                همان شعبه آن را تحویل می‌دهد.
            </p>

            <h2>۹. حساب کاربری</h2>
            <p>
                نگهداری از رمز حساب با خودتان است. اگر فکر می‌کنید کسی به
                حسابتان دسترسی پیدا کرده، به ما خبر دهید.
            </p>

            <h2>۱۰. محتوای سایت</h2>
            <p>
                عکس‌ها، متن‌ها و نشان ویکی پلاس متعلق به این فروشگاه است و
                استفاده تجاری از آن‌ها بدون اجازه مجاز نیست.
            </p>

            <h2>۱۱. تغییر این قوانین</h2>
            <p>
                این متن ممکن است به‌روز شود. تاریخ آخرین تغییر پایین همین صفحه
                نوشته شده و سفارش‌های ثبت‌شده تابع متنی هستند که در زمان ثبت
                منتشر بوده است.
            </p>

            <p class="vp-legal-date">
                آخرین بروزرسانی: {{ fa_date(\Illuminate\Support\Carbon::parse(config('storefront.content.legal_updated'))) }}
            </p>
        </div>
    </div>
</section>
@endsection
