{{--
    The shop's own pay button: the site's one gold gradient, with the figure
    on it.

    Used for every gateway that does not bring a presentation of its own. The
    first one on the page says what pressing it costs — which is the sentence a
    shopper needs on the ordinary way to pay — and any later one says what it
    is instead, because only the first can honestly quote the whole amount.
--}}
<form class="vp-order-pay" method="post"
      action="{{ storefront_route('order.pay', ['order' => $order, 'gateway' => $gateway->name()]) }}">
    @csrf
    <button type="submit" class="vp-filter-apply vp-cart-go">
        @if ($first)
            پرداخت {{ toman($order->grand_total) }} تومان
        @else
            {{ $gateway->label() }}
        @endif
    </button>
</form>
