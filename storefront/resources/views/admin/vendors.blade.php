@extends('layouts.admin')

@section('content')
<section class="vp-shop-panel">
    <h1 class="vp-shop-title">فروشندگان</h1>

    @if ($vendors->isEmpty())
        <p class="vp-shop-empty">هنوز فروشنده‌ای ثبت نشده.</p>
    @else
        <table class="vp-admin-table is-wide">
            <thead><tr><th>نام</th><th>تماس</th><th>کالاها</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            @foreach ($vendors as $vendor)
                <tr>
                    <td>{{ $vendor->name }}<br><span class="vp-cart-meta">{{ $vendor->legal_name }}</span></td>
                    <td>{{ $vendor->phone }}<br><span class="vp-cart-meta">{{ $vendor->email }}</span></td>
                    <td>{{ fa_number($vendor->offers_count) }}</td>
                    <td>{{ $vendor->statusLabel() }}</td>
                    <td>
                        <div class="vp-admin-inline">
                            @foreach ([\App\Models\Vendor::APPROVED => 'تأیید', \App\Models\Vendor::SUSPENDED => 'تعلیق', \App\Models\Vendor::REJECTED => 'رد'] as $to => $label)
                                @continue($vendor->status === $to)
                                <form method="post" action="{{ route('admin.vendor.status', $vendor) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $to }}">
                                    <button type="submit" class="vp-admin-save">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $vendors->links('pagination.vikyplus') }}</div>
    @endif
</section>

<section class="vp-shop-panel">
    <h2 class="vp-shop-title">در انتظار تأیید</h2>
    <p class="vp-shop-count">§۴: عرضهٔ فروشنده وقتی روی سایت می‌آید که یک نفر تأییدش کند.</p>

    @if ($pendingOffers->isEmpty())
        <p class="vp-shop-empty">چیزی در صف نیست.</p>
    @else
        <table class="vp-admin-table is-wide">
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
                        <div class="vp-admin-inline">
                            @foreach ([\App\Models\VendorOffer::ACTIVE => 'تأیید', \App\Models\VendorOffer::REJECTED => 'رد'] as $to => $label)
                                <form method="post" action="{{ route('admin.vendor.offer.status', $offer) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $to }}">
                                    <button type="submit" class="vp-admin-save">{{ $label }}</button>
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
@endsection
