{{--
    The questions, at the foot of the shop.

    «بنظرم قسمت انتهای سایت هم بزارش این سوالات متداولو خود سوالاتو البته به این
    شکل باشن که تو یه حالت باکس ۲ طرف گرد باشن که روشون زده میشه باکسه به شکل
    کشویی باز بشه» — the questions themselves, not a link to them, each in a
    rounded box that slides open when it is tapped.

    **Phone only.** `.vp-home-faq` is `display: none` above 991.98 for the same
    reason the story strip is: the desktop page does not gain a band it was
    never designed with, and it stays pixel-identical, which is the standing
    rule and what `check-parity.js` measures at 992 and above. Say the word and
    it shows at every width — the band is built, only the media query holds it.

    **In Laravel and not in the preview page**, which is the one place the two
    copies of this page are allowed to differ: every answer in here is read off
    the application — the delivery charge out of `storefront.checkout`, the
    exchange window out of `storefront.content` — and the static preview has no
    application to read. Parity is unaffected because the band is not painted at
    the widths parity is measured at.

    The eight are `partials/faq-items.blade.php`, the same include `/faq` uses,
    so the shop cannot answer one question two ways. Closed, all of them: this
    is a band among seven and its job is to be small until it is asked.

    Hand-owned: `theme/make-blade.js` does not write this file.
--}}
<section class="vp-home-faq">
    <div class="container th-container">
        <div class="vp-home-faq-panel">
            <div class="vp-home-faq-head">
                <h2 class="vp-home-faq-title">سوالات متداول</h2>
                <a class="vp-home-faq-all" href="{{ storefront_route('faq') }}">همه سوال‌ها</a>
            </div>

            <div class="vp-faq vp-faq-boxes">
                @include('partials.faq-items')
            </div>
        </div>
    </div>
</section>
