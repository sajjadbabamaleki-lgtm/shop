@extends('layouts.admin')

{{--
    Most specific rule wins: product, then category, then vendor, then the
    platform's default. No rule at all means no commission — a marketplace with
    nothing configured should take nothing rather than invent a rate.
--}}

@section('content')
<section class="vp-shop-panel">
    <h1 class="vp-shop-title">کارمزد</h1>

    <table class="vp-admin-table">
        <thead><tr><th>دامنه</th><th>روی</th><th>نرخ</th><th>اولویت</th><th>فعال</th></tr></thead>
        <tbody>
        @forelse ($rules as $rule)
            <tr>
                <td>{{ ['global' => 'پیش‌فرض پلتفرم', 'vendor' => 'فروشنده', 'category' => 'دسته‌بندی', 'product' => 'محصول'][$rule->scope] }}</td>
                <td>{{ $rule->scope_id ? fa_number($rule->scope_id) : '—' }}</td>
                <td>{{ $rule->describe() }}</td>
                <td>{{ fa_number($rule->priority) }}</td>
                <td>{{ $rule->is_active ? 'بله' : 'خیر' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">هیچ قاعده‌ای ثبت نشده، پس فعلاً کارمزدی گرفته نمی‌شود.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="vp-shop-panel vp-track">
    <h2 class="vp-shop-title">قاعده تازه</h2>

    <form class="vp-checkout" method="post" action="{{ route('admin.commissions.store') }}">
        @csrf
        <div class="vp-field">
            <label for="c-scope">دامنه</label>
            <select id="c-scope" name="scope" class="vp-admin-pick">
                <option value="global">پیش‌فرض پلتفرم</option>
                <option value="vendor">یک فروشنده</option>
                <option value="category">یک دسته‌بندی</option>
                <option value="product">یک محصول</option>
            </select>
        </div>
        <div class="vp-field">
            <label for="c-target">شناسه (برای پیش‌فرض خالی بگذار)</label>
            <input id="c-target" name="scope_id" type="number" min="1">
        </div>
        <div class="vp-field-row">
            <div class="vp-field">
                <label for="c-type">نوع</label>
                <select id="c-type" name="type" class="vp-admin-pick">
                    <option value="percent">درصدی</option>
                    <option value="fixed">مبلغ ثابت به ازای هر عدد (تومان)</option>
                </select>
            </div>
            <div class="vp-field">
                <label for="c-value">مقدار</label>
                <input id="c-value" name="value" inputmode="decimal" required>
            </div>
        </div>
        <div class="vp-field">
            <label for="c-priority">اولویت</label>
            <input id="c-priority" name="priority" type="number" min="0" value="0">
        </div>
        <button type="submit" class="vp-filter-apply vp-cart-go">ثبت</button>
    </form>
</section>
@endsection
