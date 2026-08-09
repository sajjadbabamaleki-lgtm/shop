@extends('layouts.panel')
@include('panel.franchise.menu')
@section('title', 'موجودی شعبه')
@section('heading', 'موجودی شعبه')

@section('content')
    <form method="get" class="pane pane--tight row">
        <input type="search" name="q" value="{{ $search }}" placeholder="نام کالا">
        <label class="row" style="gap:6px"><input type="checkbox" name="reorder" value="1" @checked(request()->boolean('reorder')) style="width:auto"> فقط زیر نقطه سفارش</label>
        <button class="btn btn--small">فیلتر</button>
    </form>

    <div class="pane scroller" style="margin-top:18px">
        <table>
            <thead><tr><th>کالا</th><th>رنگ / سایز</th><th>موجود</th><th>رزرو</th><th>نقطه سفارش</th><th>شرح</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <form method="post" action="{{ route('panel.franchise.inventory.update', [$franchise, $row]) }}">
                        @csrf @method('put')
                        <td>{{ $row->variant->product->title ?? '—' }}</td>
                        <td class="faint">{{ $row->variant->display_color }} / {{ $row->variant->size_value }}</td>
                        <td><input type="number" name="stock_on_hand" value="{{ $row->stock_on_hand }}" min="0" style="max-width:110px"></td>
                        <td class="num">{{ $row->stock_reserved }}</td>
                        <td><input type="number" name="reorder_point" value="{{ $row->reorder_point }}" min="0" style="max-width:110px"></td>
                        <td><input name="note" placeholder="مثلاً: شمارش هفتگی"></td>
                        <td><button class="btn btn--small">ذخیره</button></td>
                    </form>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">موجودی محلی ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $rows->links() }}
@endsection
