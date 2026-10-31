{{--
    ترب‌پی's payment method.

    Their document puts it plainly: call `eligible` before showing the option,
    and «اگر مقدار eligible برابر با false باشد، مرچنت نباید گزینه خرید
    اعتباری را به کاربر نمایش دهد». So this starts hidden and the shared script
    reveals it with `title_message` and `description` as they sent them — the
    same arrangement as اسنپ‌پی's button, and the same card shape, because it
    is the same kind of thing: somebody else's payment method resting on this
    page, not one of this shop's gold buttons.

    **No logo yet.** Their screenshot shows one, and the files have to come
    from ترب‌پی rather than be redrawn here — a lender's mark drawn by the
    merchant is exactly what a reviewer refuses. When they send it, it goes in
    a `<picture class="vp-snapp-mark">` before the lines, as اسنپ‌پی's does.
--}}
<form class="vp-order-pay vp-snapp-form" method="post" hidden
      data-vp-instalments="{{ storefront_route('order.instalments', ['order' => $order, 'gateway' => $gateway->name()]) }}"
      action="{{ storefront_route('order.pay', ['order' => $order, 'gateway' => $gateway->name()]) }}">
    @csrf
    <button type="submit" class="vp-snapp" aria-label="{{ $gateway->label() }}">
        <span class="vp-snapp-lines">
            <span class="vp-snapp-title"></span>
            <span class="vp-snapp-say"></span>
        </span>
    </button>
</form>

@include('shop.pay.ask-lender')
