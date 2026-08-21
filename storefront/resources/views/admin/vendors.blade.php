@extends('layouts.admin')

@section('title', 'فروشندگان')

{{--
    The marketplace's people, and the queue of what they want to sell.

    Nothing here is branch-scoped: a vendor sells across the platform, so this
    screen sits outside the branch group and asks for a platform permission.
--}}

@section('content')
<div class="vp-adm-stack">

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">فروشندگان</h2>
        </div>

        @if ($vendors->isEmpty())
            <p class="vp-adm-empty">هنوز فروشنده‌ای ثبت نشده.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>نام</th><th>تماس</th><th>کالاها</th><th>وضعیت</th><th></th></tr></thead>
                <tbody>
                @foreach ($vendors as $vendor)
                    <tr>
                        <td>{{ $vendor->name }}<span class="vp-adm-sub">{{ $vendor->legal_name }}</span></td>
                        <td>
                            <bdi dir="ltr">{{ $vendor->phone }}</bdi>
                            <span class="vp-adm-sub"><bdi dir="ltr">{{ $vendor->email }}</bdi></span>
                        </td>
                        <td>{{ fa_number($vendor->offers_count) }}</td>
                        <td>
                            <span class="vp-adm-badge is-{{ $vendor->status === \App\Models\Vendor::APPROVED ? 'delivered' : ($vendor->status === \App\Models\Vendor::REJECTED ? 'cancelled' : 'placed') }}">
                                {{ $vendor->statusLabel() }}
                            </span>
                        </td>
                        <td>
                            <div class="vp-adm-inline">
                                @foreach ([\App\Models\Vendor::APPROVED => 'تأیید', \App\Models\Vendor::SUSPENDED => 'تعلیق', \App\Models\Vendor::REJECTED => 'رد'] as $to => $label)
                                    @continue($vendor->status === $to)
                                    <form method="post" action="{{ route('admin.vendor.status', $vendor) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $to }}">
                                        <button type="submit" class="vp-adm-mini is-quiet">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="vp-adm-pager">{{ $vendors->links('pagination.vikyplus') }}</div>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">در انتظار تأیید</h2>
            <span class="vp-adm-card-more">عرضهٔ فروشنده وقتی روی سایت می‌آید که یک نفر تأییدش کند</span>
        </div>

        @if ($pendingOffers->isEmpty())
            <p class="vp-adm-empty">چیزی در صف نیست.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>فروشنده</th><th>کالا</th><th>سایز</th><th>قیمت</th><th>موجودی</th><th></th></tr></thead>
                <tbody>
                @foreach ($pendingOffers as $offer)
                    <tr>
                        <td>{{ $offer->vendor?->name }}</td>
                        <td>{{ $offer->variant?->product?->title }}</td>
                        <td>{{ fa_number((int) $offer->variant?->size_value) }}</td>
                        <td>{{ toman($offer->price) }}</td>
                        <td>{{ fa_number($offer->stock_on_hand) }}</td>
                        <td>
                            <div class="vp-adm-inline">
                                @foreach ([\App\Models\VendorOffer::ACTIVE => 'تأیید', \App\Models\VendorOffer::REJECTED => 'رد'] as $to => $label)
                                    <form method="post" action="{{ route('admin.vendor.offer.status', $offer) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $to }}">
                                        <button type="submit" class="vp-adm-mini{{ $to === \App\Models\VendorOffer::REJECTED ? ' is-quiet' : '' }}">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
