@section('store-name', $franchise->name)
@section('store-kind', 'پنل نمایندگی · '.$franchise->code)

@section('menu')
    <a href="{{ route('panel.franchise.dashboard', $franchise) }}" @class(['on' => request()->routeIs('panel.franchise.dashboard')])>نمای کلی</a>
    <a href="{{ route('panel.franchise.assortment.index', $franchise) }}" @class(['on' => request()->routeIs('panel.franchise.assortment.*')])>سبد کالا و قیمت</a>
    <a href="{{ route('panel.franchise.inventory.index', $franchise) }}" @class(['on' => request()->routeIs('panel.franchise.inventory.*')])>موجودی شعبه</a>
    <a href="{{ route('panel.franchise.orders.index', $franchise) }}" @class(['on' => request()->routeIs('panel.franchise.orders.*')])>سفارش‌ها</a>
    <a href="{{ route('panel.franchise.profile.edit', $franchise) }}" @class(['on' => request()->routeIs('panel.franchise.profile.*')])>اطلاعات شعبه</a>
@endsection
