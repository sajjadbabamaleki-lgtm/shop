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

            {{-- The gateway's own reference, which is what a customer quotes
                 on the telephone and the only thing that ties this order to a
                 line on the shop's ZarinPal statement. --}}
            @if ($receipt)
                <li><span>شماره پیگیری</span><b><bdi dir="ltr">{{ $receipt->ref_id }}</bdi></b></li>
                @if ($receipt->card_pan)
                    <li><span>کارت</span><b><bdi dir="ltr">{{ $receipt->card_pan }}</bdi></b></li>
                @endif
            @endif
        </ul>

        {{-- Failed and abandoned attempts, listed rather than hidden. «چرا این
             سفارش دو بار پرداخت شد» has an answer only if the ones that did not
             work are on the record too. --}}
        @if ($attempts->isNotEmpty())
            <p class="vp-adm-form-label">تلاش‌های پرداخت</p>
            <ul class="vp-adm-list">
                @foreach ($attempts as $attempt)
                    <li>
                        <span>{{ fa_date($attempt->created_at, true) }}</span>
                        <b>
                            <span class="vp-adm-badge is-{{ $attempt->isPaid() ? 'delivered' : ($attempt->status === \App\Models\Payment::FAILED ? 'cancelled' : 'placed') }}">
                                {{ $attempt->statusLabel() }}
                            </span>
                        </b>
                    </li>
                @endforeach
            </ul>
        @endif

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

    {{-- --- the promise, §2 and §6 -------------------------------------- --}}
    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">زمان‌بندی</h2>
            @if ($overdue)
                <span class="vp-adm-badge is-cancelled">از مهلت گذشته</span>
            @elseif ($order->is_delayed)
                <span class="vp-adm-badge is-placed">تأخیر ثبت شده</span>
            @endif
        </div>

        @if (! $order->confirmed_at)
            {{-- §19: «No shipping/deadline date is hardcoded in UI.» There is
                 no date here to print because none has been calculated — the
                 clock starts at confirmation, and saying so is better than
                 showing a guess. --}}
            <p class="vp-adm-empty">این سفارش هنوز تأیید نشده، پس مهلتی برایش حساب نشده.</p>

            <form method="post" action="{{ route('admin.order.confirm', $order) }}">
                @csrf
                <button type="submit" class="vp-adm-apply">تأیید سفارش و حساب کردن تاریخ‌ها</button>
            </form>
        @else
            <ul class="vp-adm-list">
                <li><span>تأیید</span><b>{{ fa_date($order->confirmed_at) }}</b></li>
                <li>
                    <span>مهلت ارسال</span>
                    <b>{{ $order->estimated_ship_by ? fa_date($order->estimated_ship_by) : '—' }}</b>
                </li>
                @if ($order->actual_shipped_at)
                    <li><span>ارسال واقعی</span><b>{{ fa_date($order->actual_shipped_at, true) }}</b></li>
                @endif
                <li>
                    <span>تحویل تخمینی</span>
                    <b>
                        @if ($order->estimated_delivery_from && $order->estimated_delivery_to)
                            {{ fa_date($order->estimated_delivery_from) }} تا {{ fa_date($order->estimated_delivery_to) }}
                        @else
                            —
                        @endif
                    </b>
                </li>
                @if ($order->carrier)
                    <li><span>حمل با</span><b>{{ $order->carrier }}</b></li>
                @endif
                @if ($order->is_delayed && $order->delay_reason)
                    <li><span>علت تأخیر</span><b>{{ $order->delay_reason }}</b></li>
                @endif
            </ul>
        @endif
    </section>

    {{-- --- §7's Action A: the parcel has actually gone ------------------- --}}
    @if ($order->confirmed_at && ! $order->actual_shipped_at && in_array($order->status, [\App\Models\Order::PAID, \App\Models\Order::PLACED], true))
        <section class="vp-adm-card">
            <div class="vp-adm-card-head">
                <h2 class="vp-adm-card-title">ارسال کردم</h2>
            </div>

            <p class="vp-adm-empty">فقط بعد از اینکه بسته واقعاً تحویل پست شد.</p>

            <form class="vp-adm-form" method="post" action="{{ route('admin.order.ship', $order) }}">
                @csrf

                @if ($methods->isNotEmpty())
                    <label for="sh-method">روش ارسال</label>
                    <select id="sh-method" name="shipping_method_id">
                        @foreach ($methods as $method)
                            <option value="{{ $method->id }}" @selected($order->shipping_method_id === $method->id)>
                                {{ $method->name }}{{ $method->carrier ? ' — '.$method->carrier : '' }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <label for="sh-carrier">شرکت حمل</label>
                <input id="sh-carrier" type="text" name="carrier" maxlength="80" value="{{ old('carrier', $order->carrier) }}">

                <label for="sh-track">کد رهگیری</label>
                <input id="sh-track" type="text" name="tracking_number" dir="ltr" maxlength="60"
                       value="{{ old('tracking_number', $order->tracking_number) }}">

                <label for="sh-when">زمان تحویل به پست</label>
                <input id="sh-when" type="datetime-local" name="shipped_at"
                       value="{{ old('shipped_at', now()->format('Y-m-d\TH:i')) }}"
                       max="{{ now()->format('Y-m-d\TH:i') }}">

                <button type="submit" class="vp-adm-apply">ثبت ارسال</button>
            </form>
        </section>
    @endif

    {{-- --- §7's Action B: the deadline moves, the lifecycle does not ----- --}}
    @if ($order->confirmed_at && ! $order->actual_shipped_at && $delaysEnabled)
        <section class="vp-adm-card">
            <div class="vp-adm-card-head">
                <h2 class="vp-adm-card-title">ثبت تأخیر</h2>
            </div>

            <p class="vp-adm-empty">مهلت ارسال و بازه تحویل جابه‌جا می‌شوند؛ وضعیت سفارش عوض نمی‌شود.</p>

            <form class="vp-adm-form" method="post" action="{{ route('admin.order.delay', $order) }}">
                @csrf

                <label for="dl-days">چند روز کاری (حداکثر {{ fa_number($maxDelay) }})</label>
                <input id="dl-days" type="number" name="days" min="1" max="{{ $maxDelay }}" value="1" required>

                <label for="dl-reason">علت</label>
                <input id="dl-reason" type="text" name="reason" maxlength="200" placeholder="مثلاً کالا از انبار نرسید">

                <button type="submit" class="vp-adm-apply">ثبت تأخیر</button>
            </form>
        </section>
    @endif

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
