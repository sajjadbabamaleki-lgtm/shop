@extends('layouts.storefront')

@section('title', 'راهنمای سایز کفش زنانه')

{{--
    «راهنمای سایز» — the page a shoe shop cannot do without, and the one the
    footer has been linking to since the template arrived.

    **The table is a general conversion chart, not a measurement of anything we
    sell.** It is the ordinary women's EU↔US↔UK correspondence with the foot
    length each EU size is cut for; it is not derived from our own lasts,
    because nobody has measured them. The page says so in as many words rather
    than letting a table imply a precision it does not have — a size guide that
    is quietly wrong is returned shoes, and returns are the one thing this
    application still has no flow for.

    Which sizes are actually on the shelf is a question for the catalogue, and
    the page sends people there rather than answering it here: stock changes by
    the hour and by the branch, and a number typed into a content page does
    not.
--}}

@php
    /*
     | EU size, foot length in centimetres, and the US and UK equivalents.
     |
     | The centimetre column is the length of the *foot* the size is cut for,
     | not the length of the shoe, which is what somebody measuring at home
     | has in front of them.
     */
    $chart = [
        ['eu' => 35, 'cm' => 22.5, 'us' => 5, 'uk' => 2.5],
        ['eu' => 36, 'cm' => 23, 'us' => 6, 'uk' => 3.5],
        ['eu' => 37, 'cm' => 23.5, 'us' => 6.5, 'uk' => 4],
        ['eu' => 38, 'cm' => 24.5, 'us' => 7.5, 'uk' => 5],
        ['eu' => 39, 'cm' => 25, 'us' => 8.5, 'uk' => 6],
        ['eu' => 40, 'cm' => 25.5, 'us' => 9, 'uk' => 6.5],
        ['eu' => 41, 'cm' => 26.5, 'us' => 10, 'uk' => 7.5],
    ];
@endphp

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel vp-doc">

            <h1 class="vp-shop-title">راهنمای سایز</h1>

            <p class="vp-doc-lead">
                سایزی که روی کفش‌های ما نوشته می‌شود، سایز اروپایی (EU) است.
                اگر سایزتان را می‌دانید همان را سفارش دهید؛ اگر مطمئن نیستید،
                پایین بخوانید که چطور در دو دقیقه اندازه بگیرید.
            </p>

            <h2>اول پایتان را اندازه بگیرید</h2>
            <ol class="vp-doc-steps">
                <li>کاغذی را روی زمین صاف بگذارید و پشت آن را به دیوار بچسبانید.</li>
                <li>پاشنه‌تان را به دیوار تکیه دهید و صاف روی کاغذ بایستید.</li>
                <li>جلوی بلندترین انگشت را روی کاغذ علامت بزنید.</li>
                <li>فاصله لبه کاغذ تا علامت را با خط‌کش اندازه بگیرید؛ این «طول پا» است.</li>
            </ol>

            <p class="vp-doc-tip">
                سه نکته که تفاوت را می‌سازند: هر دو پا را اندازه بگیرید و
                <b>بزرگ‌تر</b> را ملاک قرار دهید؛ اندازه‌گیری را
                <b>عصر</b> انجام دهید، چون پا در طول روز کمی بزرگ‌تر می‌شود؛ و
                اگر جوراب ضخیم می‌پوشید، با همان جوراب بایستید.
            </p>

            <h2>جدول تبدیل سایز</h2>

            <div class="vp-doc-table">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">سایز اروپایی (EU)</th>
                            <th scope="col">طول پا</th>
                            <th scope="col">آمریکایی (US)</th>
                            <th scope="col">انگلیسی (UK)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($chart as $row)
                            <tr>
                                <th scope="row">{{ fa_number($row['eu']) }}</th>
                                <td>{{ fa_number($row['cm']) }} سانتی‌متر</td>
                                <td>{{ fa_number($row['us']) }}</td>
                                <td>{{ fa_number($row['uk']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="vp-doc-note">
                این جدول یک راهنمای عمومی است. هر برند و حتی هر مدل کمی فرق
                دارد — کتانی معمولاً کمی بزرگ‌تر و کفش مجلسی کمی کوچک‌تر
                می‌آید — پس اگر طول پایتان درست بین دو سایز افتاد، سایز
                بزرگ‌تر را بگیرید.
            </p>

            <h2>هنوز شک دارید؟</h2>
            <p>
                طول پایتان و مدلی که می‌خواهید را به واتساپ
                <a href="{{ config('storefront.contact.whatsapp_href') }}" target="_blank" rel="noopener">{{ config('storefront.contact.whatsapp') }}</a>
                بفرستید یا با
                <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
                تماس بگیرید. برای همان مدل می‌گوییم کدام سایز را بردارید — این
                یک تماس، از تعویض کردن کفش ساده‌تر است.
            </p>

            <div class="vp-doc-links">
                <a class="vp-doc-link" href="{{ storefront_route('shop') }}">دیدن سایزهای موجود</a>
                <a class="vp-doc-link is-quiet" href="{{ storefront_route('faq') }}">سوالات متداول</a>
            </div>
        </div>
    </div>
</section>
@endsection
