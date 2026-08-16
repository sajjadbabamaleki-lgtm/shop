{{--
    The line above the questions, in one place.

    «اون متن هم باید با شماره تماس و لینک واتسپ بیاد زیر عنوان» — it was on
    `/faq` only, and the band at the foot of the home page is the more likely
    of the two to be read by somebody whose question is not in the list. Both
    render this, for the same reason both render `faq-items`: a telephone
    number written twice is a telephone number that gets changed once.

    Hand-owned: `theme/make-blade.js` does not write this file.
--}}
<p class="vp-doc-lead vp-faq-lead">
    اگر جواب سؤالتان این‌جا نبود، با
    <a href="{{ config('storefront.contact.phone_href') }}">{{ config('storefront.contact.phone') }}</a>
    تماس بگیرید یا در
    <a href="{{ config('storefront.contact.whatsapp_href') }}" target="_blank" rel="noopener">واتساپ</a>
    بپرسید.
</p>
