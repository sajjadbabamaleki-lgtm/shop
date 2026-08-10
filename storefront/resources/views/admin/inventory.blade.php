@extends('layouts.admin')

{{--
    Counting the shelf.

    The box asks what is actually there, not what to add or subtract. The
    difference becomes a movement in the ledger with a reason, so stock is
    always the sum of its own history — «چهار جفت کجا رفت» has an answer.

    Reserved units are shown but not editable: they belong to orders, and the
    only things that may move them are the checkout and the order screen.
--}}

@section('content')
<section class="vp-shop-panel">
    <div class="vp-shop-head">
        <div class="vp-shop-heading">
            <h1 class="vp-shop-title">موجودی</h1>
            <p class="vp-shop-count">{{ $branch->name }}</p>
        </div>
        <form class="vp-shop-search" method="get" action="{{ route('admin.inventory') }}" role="search">
            <input type="search" name="q" value="{{ $q }}" placeholder="نام کالا یا کد" aria-label="جست‌وجو">
            <button type="submit" aria-label="جست‌وجو"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
        </form>
    </div>

    @if ($rows->isEmpty())
        <p class="vp-shop-empty">چیزی پیدا نشد.</p>
    @else
        <table class="vp-admin-table is-wide">
            <thead>
                <tr><th>کالا</th><th>کد</th><th>سایز</th><th>رزرو</th><th>قابل فروش</th><th>شمارش</th><th>حد هشدار</th><th>توضیح</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->variant?->product?->title }}</td>
                    <td>{{ $row->variant?->sku }}</td>
                    <td>{{ fa_number((int) $row->variant?->size_value) }}</td>
                    <td>{{ fa_number($row->stock_reserved) }}</td>
                    <td>{{ fa_number($row->sellable_stock) }}</td>
                    <form method="post" action="{{ route('admin.inventory.update', ['q' => $q]) }}">
                        @csrf
                        <input type="hidden" name="inventory" value="{{ $row->id }}">
                        <td><input class="vp-admin-num" type="number" name="counted" value="{{ $row->stock_on_hand }}" min="{{ $row->stock_reserved }}"></td>
                        <td><input class="vp-admin-num" type="number" name="low_stock_threshold" value="{{ $row->low_stock_threshold }}" min="0"></td>
                        <td><input class="vp-admin-text" type="text" name="note" maxlength="200" placeholder="مثلاً: شمارش انبار"></td>
                        <td><button type="submit" class="vp-admin-save">ثبت</button></td>
                    </form>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $rows->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
