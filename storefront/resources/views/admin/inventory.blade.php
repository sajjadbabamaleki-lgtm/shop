@extends('layouts.admin')

@section('title', 'موجودی')

{{--
    Counting the shelf.

    The box asks what is actually there, not what to add or subtract. The
    difference becomes a movement in the ledger with a reason, so stock is
    always the sum of its own history — «چهار جفت کجا رفت» has an answer.

    Reserved units are shown but not editable: they belong to orders, and the
    only things that may move them are the checkout and the order screen.

    On a phone every row becomes a card and each cell is labelled from its own
    column heading — the shell's script does it. That is what makes the three
    boxes somebody types into («شمارش», «حد هشدار», «توضیح») reachable at 390px
    at all; before it they were the seventh, eighth and ninth columns of a
    table scrolling sideways inside a panel, which nothing failed on.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">{{ $branch->name }}، عدد «شمارش» یعنی الان چند تا روی قفسه هست</p>

    <div class="vp-adm-head-side">
        <form class="vp-adm-filters" method="get" action="{{ route('admin.inventory') }}" role="search">
            <label class="visually-hidden" for="vp-inv-q">جست‌وجو</label>
            <input id="vp-inv-q" class="vp-adm-search" type="search" name="q" value="{{ $q }}" placeholder="نام کالا یا کد">
            <button type="submit" class="vp-adm-apply">جست‌وجو</button>
            @if ($q !== '')
                <a class="vp-adm-clear" href="{{ route('admin.inventory') }}">پاک کردن</a>
            @endif
        </form>
    </div>
</div>

<section class="vp-adm-card">
    @if ($rows->isEmpty())
        <p class="vp-adm-empty">
            @if ($q === '')
                هنوز کالایی برای این شعبه باز نشده.
            @else
                چیزی با این نام یا کد پیدا نشد.
            @endif
        </p>
    @else
        <table class="vp-admin-table">
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
                    <td>
                        @if ($row->low_stock_threshold > 0 && $row->sellable_stock <= $row->low_stock_threshold)
                            <span class="vp-adm-badge is-low">{{ fa_number($row->sellable_stock) }}</span>
                        @else
                            {{ fa_number($row->sellable_stock) }}
                        @endif
                    </td>
                    {{-- The `<form>` is not allowed as a child of `<tr>`: the parser
                         leaves it and its hidden input among the cells as siblings.
                         That is why the labelling script walks `row.cells` and not
                         `row.children` — see AdminResponsiveTest. --}}
                    <form method="post" action="{{ route('admin.inventory.update', ['q' => $q]) }}">
                        @csrf
                        <input type="hidden" name="inventory" value="{{ $row->id }}">
                        <td><input class="vp-adm-cell" type="number" name="counted" value="{{ $row->stock_on_hand }}" min="{{ $row->stock_reserved }}"></td>
                        <td><input class="vp-adm-cell" type="number" name="low_stock_threshold" value="{{ $row->low_stock_threshold }}" min="0"></td>
                        <td><input class="vp-adm-cell is-text" type="text" name="note" maxlength="200" placeholder="مثلاً: شمارش انبار"></td>
                        <td><button type="submit" class="vp-adm-mini">ثبت</button></td>
                    </form>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $rows->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
