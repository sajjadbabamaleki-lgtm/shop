{{--
    اسنپ‌پی's own payment method, drawn to their guideline rather than to this
    site's.

    **Three parts and they are not ours to rearrange**: the logo, a title, and
    a description under it. «کامپوننت اسنپ‌پی در صفحهٔ توضیحات محصول ۳ بخش
    دارد: لوگو، عنوان و زیرعنوان» — and the two lines of text are printed
    exactly as the `eligible` service returns them, never written here. The
    second one is the sentence a shopper decides on («۴ قسط ماهیانه ۶۲۷٬۰۰۰
    تومان (بدون کارمزد)»), it changes with the amount, and nothing in this
    application could compute it.

    That is also why this starts **hidden**. The page cannot know whether
    SnappPay will finance this order without asking them, and asking them
    during the render would put a round trip to Tehran in front of every
    shopper looking at an order. So the page draws, the script below asks, and
    the button appears with their words in it — or does not appear at all,
    which is what a `false` means and what their reviewers check.

    The logo is their file, at the two sizes they supply: 40px above 576 and
    32px below it. `<picture>` rather than two images and a `display: none`,
    so only the one being drawn is ever fetched. They differ by 4,503 of 49,152
    bytes when rendered into the same 32px box — the small one is redrawn, not
    scaled, which is why both are shipped.
--}}
<form class="vp-order-pay vp-snapp-form" method="post" hidden
      data-vp-instalments="{{ storefront_route('order.instalments', $order) }}"
      action="{{ storefront_route('order.pay', ['order' => $order, 'gateway' => $gateway->name()]) }}">
    @csrf
    <button type="submit" class="vp-snapp">
        <picture class="vp-snapp-mark">
            <source media="(max-width: 575.98px)" srcset="{{ asset('assets/img/snapppay/logo-32.svg') }}">
            <img src="{{ asset('assets/img/snapppay/logo-40.svg') }}" alt="اسنپ‌پی" width="40" height="40">
        </picture>
        <span class="vp-snapp-lines">
            <span class="vp-snapp-title"></span>
            <span class="vp-snapp-say"></span>
        </span>
    </button>
</form>

@include('shop.pay.ask-lender')
