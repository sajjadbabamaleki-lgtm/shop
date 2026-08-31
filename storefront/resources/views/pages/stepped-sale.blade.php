@extends('layouts.storefront')

@section('title', 'حراج پله‌ای ویکی پلاس')

{{--
    «حراج پله‌ای» — the page that explains it.

    **Every word of the copy here is the client's**, sent verbatim in their
    requirements: «یه بخش مجزای توضیحات برای حراج پله‌ای طراحی کنید که این
    توضیحات داخلش نوشته شده باشه». Same rule as «درباره ما» — this is the shop
    describing its own way of selling, not description of software, so tidying
    its tone would be rewriting their claim. Leave the wording alone.

    **Why it needed a page of its own.** The stepped sale is the largest idea
    in this shop and it was explained nowhere: a shopper met a percentage on a
    card and had to guess what «پله» meant. The client also wants the
    distinction with plain discounts made explicit — «توجه داشته باشید که
    تخفیف‌دارها با حراج پله‌ای فرق می‌کنه» — which is the last section.

    The table image (#18) is deliberately not here; images are out of scope for
    this round.

    No new class in the whole file. `.vp-doc`, `.vp-doc-lead`, `.vp-doc-list`,
    `.vp-doc-steps` and `.vp-doc-note` are what the other six content pages are
    built from, and a new name would mean a new vocabulary, a re-cut of the
    stylesheets, and something to collide with in the 15,000 lines of
    `tweaks.css`.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">حراج پله‌ای ویکی پلاس</h1>

            <p class="vp-doc-lead">
                خرید هوشمندانه، قیمت منصفانه.
            </p>

            <p>
                در ویکی پلاس ما یک مدل فروش متفاوت و هوشمندانه برای فروش کفش و
                کیف زنانه طراحی کرده‌ایم به نام حراج پله‌ای؛ روشی شفاف و
                زمان‌بندی‌شده که به شما این امکان را می‌دهد تا کالاهای منتخب را
                با تخفیف‌های مرحله‌ای و واقعی خریداری کنید.
            </p>

            <h2>حراج پله‌ای مخصوص چه کالاهایی است؟</h2>
            <ul class="vp-doc-list">
                <li>کالاهایی که موجودی محدود دارند (مثلاً سایز یا رنگ خاص).</li>
                <li>یا در فروش عادی به‌طور کامل به فروش نرسیده‌اند.</li>
            </ul>
            <p>
                این کالاها وارد یک فرآیند مشخص می‌شوند که اگر در یک مرحله
                فروخته نشوند، به مرحله بعد منتقل شده و با تخفیف بیشتر عرضه
                می‌شوند.
            </p>

            <h2>حراج پله‌ای چگونه کار می‌کند؟</h2>
            <ol class="vp-doc-steps">
                <li>هر کالا از پله اول با تخفیف مشخص شروع می‌شود.</li>
                <li>اگر در آن هفته فروش نرود، به پله بعدی (هفته بعد) منتقل می‌شود.</li>
                <li>با هر پله، درصد تخفیف افزایش پیدا می‌کند.</li>
                <li>در هر مرحله فقط موجودی باقی‌مانده وارد پله بعد می‌شود.</li>
            </ol>

            <p class="vp-doc-tip">
                <strong>مثال:</strong>
                اگر از یک مدل فقط چند سایز محدود باقی بماند، همان سایزها وارد
                مرحله بعدی حراج می‌شوند، نه کل محصول.
            </p>

            <h2>قوانین مهم حراج پله‌ای</h2>
            <ul class="vp-doc-list">
                <li>کالاهای حراج پله‌ای موجودی محدود دارند.</li>
                <li>امکان اتمام کالا در هر مرحله وجود دارد.</li>
                <li>انتقال به پله بعدی فقط در صورت باقی‌ماندن کالا انجام می‌شود.</li>
                <li>تخفیف‌ها به‌صورت خودکار و زمان‌بندی‌شده اعمال می‌شوند.</li>
                <li>اولویت خرید با کاربرانی است که زودتر تصمیم می‌گیرند.</li>
            </ul>

            <h2>چرا حراج پله‌ای</h2>
            <ul class="vp-doc-list">
                <li>تخفیف واقعی و شفاف.</li>
                <li>فرصت خرید هوشمندانه.</li>
                <li>مدیریت حرفه‌ای کالاهای محدود.</li>
                <li>تجربه‌ای متفاوت و بی‌سابقه در فروش آنلاین ایران.</li>
            </ul>

            {{--
                «تخفیف‌دارها با حراج پله‌ای فرق می‌کنه» — asked for as a
                warning, because the hero's own link used to land on the
                discounted-items listing and the two were being read as one
                thing.
            --}}
            <h2>تفاوت حراج پله‌ای با کالاهای تخفیف‌دار</h2>
            <p>
                هر کالایی که روی آن تخفیف خورده باشد، لزوماً در حراج پله‌ای
                نیست. <strong>کالای تخفیف‌دار</strong> یک قیمت کاهش‌یافته دارد
                که تا اطلاع بعدی همان است، اما <strong>کالای حراج پله‌ای</strong>
                در یک فرآیند زمان‌بندی‌شده قرار دارد و درصد تخفیفش هفته به هفته
                و تا زمانی که موجودی باقی بماند، بالا می‌رود.
            </p>

            <p class="vp-doc-note">
                کالاهای حراج پله‌ای را در
                <a href="{{ storefront_route('shop') }}">فهرست فروشگاه</a>
                می‌بینید؛ درصدی که روی هر کارت نوشته شده، همان درصدی است که از
                قیمت کم می‌شود.
            </p>

        </div>
    </div>
</section>
@endsection
