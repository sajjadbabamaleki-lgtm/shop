@extends('layouts.storefront')

@section('title', 'درباره ویکی پلاس')

{{--
    «درباره ما».

    Every sentence here is either something the client has told us or something
    this application demonstrably does. Nothing is invented — no number of
    customers, no pairs sold — because a made-up figure on an about page is the
    kind of thing that gets quoted back at the shop.

    **The three paragraphs under «گروه ویکی» are the client's own words**, sent
    verbatim, and they are the first facts anybody has given us about the
    business behind the shop: the group has traded since 1382, it makes,
    imports and wholesales, and ویکی پلاس is its online arm rather than a
    separate company. The page used to say outright that there was no founding
    year to print. Leave the wording alone — it is copy the client wrote about
    their own company, not description of software, and rewriting it for tone
    would be rewriting their claim about themselves.

    The strap line, the address and the telephone are the footer's own — the
    ones off the screenshot the client sent. They come from
    `storefront.contact` here so that the two cannot drift apart.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">درباره ویکی پلاس</h1>

            <p class="vp-doc-lead">
                ویکی پلاس فروشگاه آنلاین گروه ویکی است: کیف و کفش زنانه
                (کتانی، مجلسی، بوت، صندل و کیف) با تضمین کیفیت، ارسال سریع و
                امکان خرید تکی و عمده.
            </p>

            <h2>گروه ویکی</h2>
            <p>
                گروه ویکی از سال ۱۳۸۲ با هدف تولید، واردات و پخش عمده‌ی کیف و
                کفش زنانه فعالیت خود را آغاز کرد. از ابتدا، هدف ما ارائه‌ی
                بهترین و باکیفیت‌ترین محصولات بود، چرا که باور داریم بانوی
                ایرانی شایسته‌ی بالاترین سطح از سبک و کیفیت است.
            </p>
            <p>
                در طول این سال‌ها، ما همواره بر شیک‌پوش بودن، به‌روز بودن
                مدل‌ها و ارتقای کیفیت، کمیت و دوام محصولات تأکید داشته‌ایم.
                اکنون، با افتخار، ویکی پلاس را معرفی می‌کنیم؛ بخشی از این
                مجموعه‌ی بزرگ که در کنار فعالیت‌های تولید و پخش عمده، به‌صورت
                تخصصی در فضای آنلاین به فروش محصولات گروه ویکی می‌پردازد.
            </p>
            <p>
                در ویکی پلاس، تمامی محصولات با دقت و ظرافت و بر اساس نیاز
                بانوان ایرانی تهیه شده و این اطمینان را به شما می‌دهیم که خرید
                از ویکی پلاس یک تجربه‌ی بی‌نظیر و لذت‌بخش از خرید آنلاین کفش و
                کیف خواهد بود.
            </p>

            <h2>ما کجاییم</h2>
            <p>
                نشانی ویکی پلاس {{ config('storefront.contact.address') }} است.
            </p>
            <p>
                <strong>مراجعهٔ حضوری تنها برای خرید عمده انجام می‌شود.</strong>
                برای خرید تکی امکان مراجعهٔ حضوری وجود ندارد و سفارش‌ها از همین
                سایت ثبت و به سراسر ایران ارسال می‌شود. برای خرید عمده،
                <a href="{{ storefront_route('wholesale') }}">صفحهٔ خرید عمده</a>
                راه‌های هماهنگی را دارد.
            </p>

            <h2>چطور می‌فروشیم</h2>
            <p>
                قیمت هر کالا روی صفحه‌اش نوشته است و همان است که در سبد خرید و
                در فاکتور می‌آید. «حراج پله‌ای» هم یک تخفیف واقعی روی همان قیمت
                است، نه عددی که فقط کنار کالا نوشته شود: درصدی که روی کارت
                می‌بینید، همان درصدی است که از قیمت کم می‌شود.
            </p>
            <p>
                موجودی هر سایز به‌صورت جداگانه شمرده می‌شود. وقتی روی کالایی
                نوشته شده «فقط ۱ عدد باقی مانده»، دقیقاً یک عدد در انبار است؛ و
                هر کالایی که موجودی‌اش به پایان برسد از فهرست فروش خارج می‌شود،
                تا هیچ سفارشی برای کالایی که موجود نیست ثبت نشود.
            </p>

            <h2>فروشندگان دیگر</h2>
            <p>
                کنار کالاهای خودمان، فروشندگان دیگری هم می‌توانند در ویکی پلاس
                بفروشند. هر فروشنده با نام خودش کنار کالا نوشته می‌شود، پس همیشه
                معلوم است کالا را از چه کسی می‌خرید. اگر خودتان تولیدکننده یا
                فروشنده‌اید،
                <a href="{{ storefront_route('vendors.apply') }}">درخواست همکاری</a>
                بدهید.
            </p>

            <h2>تماس</h2>
            <p>
                تلفن فروشگاه
                <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
                است و
                <a href="{{ storefront_route('contact') }}">صفحه تماس با ما</a>
                همه راه‌های دیگر را دارد.
            </p>

            <div class="vp-doc-links">
                <a class="vp-doc-link" href="{{ storefront_route('shop') }}">دیدن محصولات</a>
                <a class="vp-doc-link is-quiet" href="{{ storefront_route('size-guide') }}">راهنمای سایز</a>
            </div>
        </div>
    </div>
</section>
@endsection
