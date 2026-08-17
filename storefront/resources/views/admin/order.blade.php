@extends('layouts.admin')

@section('title', 'سفارش '.$order->number)

{{--
    §21's full order page. On a phone this is the whole screen and the actions
    sit at its foot, which is «Persistent primary actions should remain
    reachable near the bottom of the screen» — on a telephone held in one hand,
    the top of a long order is the hardest place to reach.

    §4's list of what an order must contain, against what this one has:
    number, customer, phone, address, items with variant, size, quantity, unit
    price, discounts, shipping, total, payment method and status, tracking
    number, internal notes and the timeline — all present. Seller and shipping
    method are not: the order line carries no vendor and the order carries no
    shipping method, so they are absent rather than blank.
--}}

@php
    $moves = [
        \App\Models\Order::PLACED => [\App\Models\Order::PAID, \App\Models\Order::CANCELLED],
        \App\Models\Order::PAID => [\App\Models\Order::SHIPPED, \App\Models\Order::CANCELLED],
        \App\Models\Order::SHIPPED => [\App\Models\Order::DELIVERED],
    ][$order->status] ?? [];

    $labels = \App\Models\Order::statusLabels();

    // §32: «Confirmation dialogs should explain the consequence, not merely ask
    // "Are you sure?"». These two move money and stock.
    $consequence = [
        \App\Models\Order::PAID => 'این سفارش پرداخت‌شده ثبت می‌شود و موجودی رزروشده فروخته می‌شود. برگشت‌پذیر نیست.',
        \App\Models\Order::CANCELLED => 'این سفارش لغو می‌شود و موجودی رزروشده به انبار برمی‌گردد. برگشت‌پذیر نیست.',
    ];
@endphp

@section('content')

<div class="vp-adm-grid">

    {{-- --- the goods --------------------------------------------------- --}}
    <section class="vp-adm-card vp-adm-span-2">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">اقلام</h2>
            <span class="vp-adm-card-more">{{ $order->placed_at ? fa_date($order->placed_at, true) : '—' }}</span>
        </div>

        <table class="vp-admin-table">
            <thead><tr><th>کالا</th><th>کد</th><th>سایز</th><th>تعداد</th><th>واحد</th><th>جمع</th></tr></thead>
            <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_title }}</td>
                    {{-- §26: «Technical identifiers such as SKUs may use LTR
                         isolation within RTL UI» — without it a code ending in
                         digits reorders itself against the Persian around it. --}}
                    <td><bdi dir="ltr">{{ $item->sku }}</bdi></td>
                    <td>{{ $item->size_value }}</td>
                    <td>{{ fa_number($item->quantity) }}</td>
                    <td>{{ toman($item->unit_price) }}</td>
                    <td>{{ toman($item->line_total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <ul class="vp-adm-list vp-adm-totals">
            <li><span>جمع اقلام</span><b>{{ toman($order->subtotal) }}</b></li>
            @if ($order->discount_total > 0)
                <li><span>تخفیف</span><b>−{{ toman($order->discount_total) }}</b></li>
            @endif
            <li><span>ارسال</span><b>{{ toman($order->shipping_total) }}</b></li>
            <li><span>مبلغ کل</span><b>{{ toman($order->grand_total) }} تومان</b></li>
        </ul>
    </section>

    {{-- --- who and where ----------------------------------------------- --}}
    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">مشتری</h2>
        </div>

        <ul class="vp-adm-list">
            <li><span>نام</span><b>{{ $order->contact_name }}</b></li>
            <li><span>تلفن</span><b><bdi dir="ltr">{{ $order->contact_phone }}</bdi></b></li>
            <li><span>وضعیت</span><b><span class="vp-adm-badge is-{{ $order->status }}">{{ $order->statusLabel() }}</span></b></li>
            <li><span>پرداخت</span><b>{{ $order->payment_status === 'paid' ? 'پرداخت‌شده' : 'پرداخت‌نشده' }}</b></li>
            @if ($order->payment_method)
                <li><span>روش پرداخت</span><b>{{ $order->payment_method }}</b></li>
            @endif
        </ul>

        <p class="vp-adm-address">
            {{ collect([$order->province, $order->city, $order->address])->filter()->implode('، ') }}
            @if ($order->postal_code)
                <br><small>کد پستی: <bdi dir="ltr">{{ $order->postal_code }}</bdi></small>
            @endif
        </p>

        @if ($order->note)
            <p class="vp-adm-empty"><b>یادداشت مشتری:</b> {{ $order->note }}</p>
        @endif
    </section>

    {{-- --- tracking and the shop's own note, §4 ------------------------- --}}
    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">رهگیری و یادداشت</h2>
        </div>

        <form method="post" action="{{ route('admin.order.annotate', $order) }}" class="vp-adm-form">
            @csrf

            <label for="vp-track">کد رهگیری مرسوله</label>
            <input id="vp-track" type="text" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}"
                   dir="ltr" maxlength="60" placeholder="مثلاً ۲۴۱۲۳۴۵۶۷۸۹۰۱۲۳۴۵۶۷۸">

            <label for="vp-note">یادداشت داخلی</label>
            <textarea id="vp-note" name="staff_note" rows="4" maxlength="2000"
                      placeholder="فقط کارکنان این را می‌بینند.">{{ old('staff_note', $order->staff_note) }}</textarea>

            <button type="submit" class="vp-adm-apply">ذخیره</button>
        </form>
    </section>

    {{-- --- the timeline, §4 -------------------------------------------- --}}
    <section class="vp-adm-card vp-adm-span-3">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">تاریخچه</h2>
            <span class="vp-adm-card-more">از دفتر ممیزی</span>
        </div>

        @if ($trail->isEmpty())
            <p class="vp-adm-empty">هنوز تغییری برای این سفارش ثبت نشده.</p>
        @else
            <ol class="vp-adm-trail">
                @foreach ($trail as $entry)
                    <li>
                        <span class="vp-adm-trail-when">{{ fa_date($entry->created_at, true) }}</span>
                        <span class="vp-adm-trail-what">
                            <b>{{ $entry->action }}</b>
                            @if ($entry->actor)
                                — {{ $entry->actor->name }}
                            @endif
                            @php
                                $before = $entry->old_values['status'] ?? null;
                                $after = $entry->new_values['status'] ?? null;
                            @endphp
                            @if ($after && $before !== $after)
                                <br><small>{{ $labels[$before] ?? $before ?? '—' }} ← {{ $labels[$after] ?? $after }}</small>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>

{{-- --- what can be done next, §21 ------------------------------------- --}}
@if ($moves !== [])
    <div class="vp-adm-actions">
        <span class="vp-adm-actions-say">از «{{ $order->statusLabel() }}» می‌توان رفت به:</span>

        @foreach ($moves as $to)
            <form method="post" action="{{ route('admin.order.update', $order) }}"
                  @if (isset($consequence[$to])) onsubmit="return confirm('{{ $consequence[$to] }}')" @endif>
                @csrf
                <input type="hidden" name="status" value="{{ $to }}">
                <button type="submit" class="{{ $to === \App\Models\Order::CANCELLED ? 'vp-adm-danger' : 'vp-adm-apply' }}">
                    {{ $labels[$to] }}
                </button>
            </form>
        @endforeach
    </div>
@else
    <div class="vp-adm-actions">
        <span class="vp-adm-actions-say">این سفارش در «{{ $order->statusLabel() }}» است و حرکت بعدی ندارد.</span>
    </div>
@endif
@endsection
