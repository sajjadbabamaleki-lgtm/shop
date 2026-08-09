@extends('layouts.panel')
@include('panel.admin.menu')
@section('title', 'قواعد کمیسیون')
@section('heading', 'قواعد کمیسیون')

@section('content')
    <p class="faint">
        نرخ پایه پلتفرم <b>{{ $defaultPercent }}٪</b> است. قاعده‌ها از خاص به عام خوانده می‌شوند:
        محصول، سپس دسته، سپس فروشنده، و در آخر نرخ روی پرونده فروشنده و نرخ پایه.
        نرخ در لحظه فروش روی قلم سفارش ثبت می‌شود، پس تغییر این جدول سفارش‌های گذشته را عوض نمی‌کند.
    </p>

    <form method="post" action="{{ route('panel.platform.commissions.store') }}" class="pane">
        @csrf
        <h2>قاعده تازه</h2>
        <div class="row">
            <div class="field" style="flex:1"><label for="scope">دامنه</label>
                <select id="scope" name="scope">
                    <option value="vendor">فروشنده</option>
                    <option value="category">دسته</option>
                    <option value="product">محصول</option>
                </select></div>
            <div class="field" style="flex:1"><label for="vendor_id">فروشنده</label>
                <select id="vendor_id" name="vendor_id"><option value="">—</option>
                    @foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                </select></div>
            <div class="field" style="flex:1"><label for="category_id">دسته</label>
                <select id="category_id" name="category_id"><option value="">—</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select></div>
            <div class="field" style="flex:1"><label for="product_id">محصول</label>
                <select id="product_id" name="product_id"><option value="">—</option>
                    @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->title }}</option>@endforeach
                </select></div>
        </div>
        <div class="row">
            <div class="field" style="flex:1"><label for="percent">نرخ (٪)</label>
                <input id="percent" type="number" step="0.01" name="percent" value="{{ old('percent', $defaultPercent) }}" required></div>
            <div class="field" style="flex:1"><label for="priority">اولویت</label>
                <input id="priority" type="number" name="priority" value="{{ old('priority', 0) }}" required></div>
            <div class="field" style="flex:1"><label for="starts_at">از تاریخ</label>
                <input id="starts_at" type="date" name="starts_at"></div>
            <div class="field" style="flex:1"><label for="ends_at">تا تاریخ</label>
                <input id="ends_at" type="date" name="ends_at"></div>
        </div>
        <div class="field"><label for="note">یادداشت</label><input id="note" name="note"></div>
        <button class="btn btn--gold">افزودن</button>
    </form>

    <div class="pane scroller" style="margin-top:18px">
        <table>
            <thead><tr><th>دامنه</th><th>هدف</th><th>فروشنده</th><th>نرخ</th><th>اولویت</th><th>بازه</th><th></th></tr></thead>
            <tbody>
            @forelse($rules as $rule)
                <tr>
                    <td>{{ ['platform' => 'پایه', 'vendor' => 'فروشنده', 'category' => 'دسته', 'product' => 'محصول'][$rule->scope] }}</td>
                    <td>{{ $rule->category?->name ?? $rule->product?->title ?? '—' }}</td>
                    <td class="faint">{{ $rule->vendor?->name ?? 'همه' }}</td>
                    <td class="num">{{ $rule->rate_bps / 100 }}٪</td>
                    <td class="num">{{ $rule->priority }}</td>
                    <td class="faint">
                        {{ $rule->starts_at?->format('Y/m/d') ?? '—' }} … {{ $rule->ends_at?->format('Y/m/d') ?? '—' }}
                    </td>
                    <td>
                        <form method="post" action="{{ route('panel.platform.commissions.destroy', $rule) }}">@csrf @method('delete')
                            <button class="btn btn--small">حذف</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">قاعده‌ای ثبت نشده است؛ نرخ پایه اعمال می‌شود.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $rules->links() }}
@endsection
