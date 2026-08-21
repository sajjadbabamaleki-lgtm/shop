@extends('layouts.admin')

@section('title', 'کارمزد')

{{--
    Most specific rule wins: product, then category, then vendor, then the
    platform's default. No rule at all means no commission — a marketplace with
    nothing configured should take nothing rather than invent a rate.
--}}

@section('content')
<div class="vp-adm-stack">

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">قاعده‌های کارمزد</h2>
        </div>

        @if ($rules->isEmpty())
            <p class="vp-adm-empty">هیچ قاعده‌ای ثبت نشده، پس فعلاً کارمزدی گرفته نمی‌شود.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>دامنه</th><th>روی</th><th>نرخ</th><th>اولویت</th><th>فعال</th></tr></thead>
                <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td>{{ ['global' => 'پیش‌فرض پلتفرم', 'vendor' => 'فروشنده', 'category' => 'دسته‌بندی', 'product' => 'محصول'][$rule->scope] }}</td>
                        <td>{{ $rule->scope_id ? fa_number($rule->scope_id) : '—' }}</td>
                        <td>{{ $rule->describe() }}</td>
                        <td>{{ fa_number($rule->priority) }}</td>
                        <td>
                            <span class="vp-adm-badge is-{{ $rule->is_active ? 'delivered' : 'cancelled' }}">
                                {{ $rule->is_active ? 'فعال' : 'خاموش' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">قاعده تازه</h2>
        </div>

        <form class="vp-adm-form" method="post" action="{{ route('admin.commissions.store') }}">
            @csrf

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="c-scope">دامنه</label>
                    <select id="c-scope" name="scope">
                        <option value="global">پیش‌فرض پلتفرم</option>
                        <option value="vendor">یک فروشنده</option>
                        <option value="category">یک دسته‌بندی</option>
                        <option value="product">یک محصول</option>
                    </select>
                </div>
                <div class="vp-adm-form">
                    <label for="c-target">شناسه (برای پیش‌فرض خالی بگذار)</label>
                    <input id="c-target" name="scope_id" type="number" min="1">
                </div>
            </div>

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="c-type">نوع</label>
                    <select id="c-type" name="type">
                        <option value="percent">درصدی</option>
                        <option value="fixed">مبلغ ثابت به ازای هر عدد (تومان)</option>
                    </select>
                </div>
                <div class="vp-adm-form">
                    <label for="c-value">مقدار</label>
                    <input id="c-value" name="value" inputmode="decimal" required>
                </div>
            </div>

            <label for="c-priority">اولویت</label>
            <input id="c-priority" name="priority" type="number" min="0" value="0">

            <button type="submit" class="vp-adm-apply">ثبت</button>
        </form>
    </section>
</div>
@endsection
