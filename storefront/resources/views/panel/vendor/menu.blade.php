@section('store-name', $vendor->name)
@section('store-kind', 'پنل فروشنده')

@section('menu')
    <a href="{{ route('panel.vendor.dashboard', $vendor) }}" @class(['on' => request()->routeIs('panel.vendor.dashboard')])>نمای کلی</a>
    <a href="{{ route('panel.vendor.offers.index', $vendor) }}" @class(['on' => request()->routeIs('panel.vendor.offers.*')])>محصولات من</a>
    <a href="{{ route('panel.vendor.orders.index', $vendor) }}" @class(['on' => request()->routeIs('panel.vendor.orders.*')])>سفارش‌ها</a>
    <a href="{{ route('panel.vendor.settlements.index', $vendor) }}" @class(['on' => request()->routeIs('panel.vendor.settlements.*')])>مالی و تسویه</a>
    <a href="{{ route('panel.vendor.profile.edit', $vendor) }}" @class(['on' => request()->routeIs('panel.vendor.profile.*')])>پروفایل و مینی‌سایت</a>
@endsection
