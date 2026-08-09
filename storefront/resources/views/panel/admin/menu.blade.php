@section('store-name', 'ویکی‌پلاس')
@section('store-kind', 'مدیریت پلتفرم')

@section('menu')
    <a href="{{ route('panel.platform.dashboard') }}" @class(['on' => request()->routeIs('panel.platform.dashboard')])>نمای کلی</a>
    <a href="{{ route('panel.platform.vendors.index') }}" @class(['on' => request()->routeIs('panel.platform.vendors.*')])>فروشندگان</a>
    <a href="{{ route('panel.platform.commissions.index') }}" @class(['on' => request()->routeIs('panel.platform.commissions.*')])>قواعد کمیسیون</a>
@endsection
