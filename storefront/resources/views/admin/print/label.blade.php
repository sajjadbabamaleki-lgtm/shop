@extends('admin.print.sheet')

@section('title', 'برچسب '.$order->number)

{{--
    The address label — «یک طرف ادرس خودمون باشه، یک طرفم ادرس مشتری».

    Two halves of one box, the sender at the reading edge and the recipient
    beside it, with the order number on both so a parcel and its invoice can
    be matched without opening either. The recipient's half is the larger
    type: that is the one a courier reads at arm's length.

    A پس‌کرایه parcel says so in a black band across the recipient's half,
    because the shop took no money for delivery and a courier who does not
    know that collects nothing at the door — the same warning the order
    screen gives, on the one surface the courier actually sees.
--}}

@section('content')
<section class="vp-label" aria-label="برچسب مرسوله">
    <div class="vp-label-half vp-label-from">
        <p class="vp-label-tag">فرستنده</p>
        <p class="vp-label-name">{{ $sender['name'] }}</p>
        <p class="vp-label-line">{{ $sender['address'] }}</p>
        <p class="vp-label-line">تلفن: <bdi dir="ltr">{{ $sender['phone'] }}</bdi></p>

        <div class="vp-label-foot">
            <span>سفارش <b><bdi dir="ltr">{{ $order->number }}</bdi></b></span>
        </div>
    </div>

    <div class="vp-label-half vp-label-to">
        <p class="vp-label-tag">گیرنده</p>
        <p class="vp-label-name">{{ $order->contact_name }}</p>
        <p class="vp-label-line">{{ collect([$order->province, $order->city])->filter()->implode('، ') }}</p>
        <p class="vp-label-line">{{ $order->address }}</p>
        @if ($order->postal_code)
            <p class="vp-label-line">کد پستی: <b><bdi dir="ltr">{{ $order->postal_code }}</bdi></b></p>
        @endif
        <p class="vp-label-line">تلفن: <b><bdi dir="ltr">{{ $order->contact_phone }}</bdi></b></p>

        @if ($order->shippingMethod?->isCollect())
            <p class="vp-label-collect">پس‌کرایه — هزینه ارسال هنگام تحویل از گیرنده گرفته شود</p>
        @endif

        <div class="vp-label-foot">
            <span>سفارش <b><bdi dir="ltr">{{ $order->number }}</bdi></b></span>
            @if ($order->shippingMethod)
                <span>{{ $order->shippingMethod->name }}</span>
            @endif
            @if ($order->tracking_number)
                <span>رهگیری <bdi dir="ltr">{{ $order->tracking_number }}</bdi></span>
            @endif
        </div>
    </div>
</section>
@endsection
