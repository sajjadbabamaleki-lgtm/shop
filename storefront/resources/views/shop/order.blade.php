@extends('layouts.storefront')

{{--
    One order, as its customer reads it.

    Everything on this page comes off the order's own rows, not the catalogue.
    A product renamed or repriced next month must not change what this says
    happened today.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">سفارش {{ $order->number }}</h1>
                    <p class="vp-shop-count">{{ $order->statusLabel() }} — {{ fa_date($order->placed_at) }}</p>
                </div>
                <a class="vp-cart-keep" href="{{ storefront_route('shop') }}">ادامه خرید</a>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            {{-- What the shop is actually waiting for.

                 **The shop no longer offers paying at the door**, at the
                 client's instruction — «پرداخت در محل از وبسایت حذف بشه» — so
                 the second sentence is not an alternative arrangement any
                 more. It is what a shopper sees if the shop has no gateway
                 configured, which on a card-only shop is a fault rather than a
                 choice: it says so and points at the telephone, instead of
                 inviting somebody to pay a courier the shop is not expecting.
                 `Order::methodLabels()` keeps «پرداخت در محل» for the panel,
                 because orders that really were paid that way still exist. --}}
            @if ($order->status === \App\Models\Order::PLACED)
                @if ($canPayOnline)
                    <p class="vp-note">سفارشت ثبت شد و کالاها برایت کنار گذاشته شده. برای نهایی شدن، مبلغ را پرداخت کن.</p>

                    <form class="vp-order-pay" method="post" action="{{ storefront_route('order.pay', $order) }}">
                        @csrf
                        <button type="submit" class="vp-filter-apply vp-cart-go">
                            پرداخت {{ toman($order->grand_total) }} تومان
                        </button>
                    </form>

                    {{-- «اگه فیلترشکنش روشنه خاموش کنه تا در مراحل ثبت سفارش و
                         پرداخت اختلال ایجاد نشه» — said about this page, which
                         is the last one the shopper sees before the bank's.

                         **Here rather than at checkout**, because this is the
                         step that leaves the site: an Iranian card gateway is
                         reached from inside Iran, and a shopper whose VPN is on
                         meets either a page that will not open or a payment
                         that dies half way — and the half-way one is the
                         expensive kind, because their money has moved and the
                         order has not.

                         A `<dialog>` rather than the shop's own `<details>`
                         sheets: those are phone-only and they are filters,
                         which a person opens. This one has to arrive by itself,
                         at every width, and take the keyboard with it — which
                         `showModal()` does and nothing else here does. With no
                         script it simply never opens: the page and its pay
                         button behave exactly as they did before, which is the
                         right way for an advisory to fail. --}}
                    <dialog class="vp-vpn" id="vp-vpn" aria-labelledby="vp-vpn-title">
                        <h2 class="vp-vpn-title" id="vp-vpn-title">قبل از پرداخت</h2>
                        <p class="vp-vpn-say">
                            اگر فیلترشکن (VPN) روشن است، خاموشش کن و بعد پرداخت را بزن.
                            درگاه بانکی با فیلترشکن ممکن است باز نشود یا وسط کار قطع شود.
                        </p>
                        <form method="dialog">
                            <button class="vp-filter-apply vp-cart-go vp-vpn-ok" autofocus>متوجه شدم</button>
                        </form>
                    </dialog>

                    <script>
                        // Next to the dialog rather than in the shared script
                        // file: that one is generated from the static page and
                        // runs at the foot of the document, and this has to be
                        // armed the moment the element exists.
                        (function () {
                            var box = document.getElementById('vp-vpn');

                            if (box && typeof box.showModal === 'function') {
                                box.showModal();
                            }
                        }());
                    </script>
                @else
                    <p class="vp-note">سفارشت ثبت شد و کالاها برایت کنار گذاشته شده. پرداخت اینترنتی همین حالا در دسترس نیست؛ برای هماهنگی پرداخت با پشتیبانی تماس بگیر.</p>
                @endif
            @endif

            {{-- The receipt, once there is one. The reference number is what a
                 customer quotes on the telephone, so it is on their own page
                 rather than only in the panel. --}}
            @if ($receipt)
                <p class="vp-note is-good">
                    پرداخت شد — شماره پیگیری <bdi dir="ltr">{{ $receipt->ref_id }}</bdi>
                    @if ($receipt->card_pan)
                        · کارت <bdi dir="ltr">{{ $receipt->card_pan }}</bdi>
                    @endif
                </p>
            @endif

            <div class="vp-cart">
                <div class="vp-cart-lines">
                    @foreach ($order->items as $item)
                        <div class="vp-cart-line">
                            <div class="vp-cart-what">
                                <span class="vp-cart-name">{{ $item->product_title }}</span>
                                <span class="vp-cart-meta">سایز {{ fa_number((int) $item->size_value) }} — کد {{ $item->sku }}</span>
                            </div>
                            <div class="vp-cart-qty-static">{{ fa_number($item->quantity) }} عدد</div>
                            <div class="vp-cart-money"><strong>{{ toman($item->line_total) }} <span>تومان</span></strong></div>
                        </div>
                    @endforeach
                </div>

                <aside class="vp-cart-sum">
                    <h2 class="vp-filter-title">جمع سفارش</h2>
                    <div class="vp-cart-row"><span>جمع کالاها</span><span>{{ toman($order->subtotal) }}</span></div>
                    @if ($order->discount_total > 0)
                        <div class="vp-cart-row"><span>تخفیف</span><span>− {{ toman($order->discount_total) }}</span></div>
                    @endif
                    <div class="vp-cart-row"><span>هزینه ارسال</span><span>{{ $order->shipping_total === 0 ? 'رایگان' : toman($order->shipping_total) }}</span></div>
                    <div class="vp-cart-row is-total"><span>قابل پرداخت</span><span>{{ toman($order->grand_total) }} تومان</span></div>

                    <h2 class="vp-filter-title vp-order-to">تحویل به</h2>
                    <p class="vp-order-address">
                        {{ $order->contact_name }}<br>
                        {{ $order->contact_phone }}<br>
                        @if ($order->province || $order->city){{ $order->province }} {{ $order->city }}<br>@endif
                        {{ $order->address }}
                        @if ($order->postal_code)<br>کد پستی {{ $order->postal_code }}@endif
                    </p>

                    @if ($order->isCancellable())
                        <form method="post" action="{{ storefront_route('order.cancel', $order) }}">
                            @csrf
                            <button type="submit" class="vp-order-cancel">لغو سفارش</button>
                        </form>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</section>
@endsection
