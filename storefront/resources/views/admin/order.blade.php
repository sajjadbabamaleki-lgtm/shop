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

    // §32's «explain the consequence, not merely ask "Are you sure?"» now lives
    // on each card, in the line above its own button, where it is read before
    // the button is pressed rather than in a dialog after.
@endphp

@section('content')

<div class="vp-adm-grid">

    {{-- --- the goods --------------------------------------------------- --}}
    <section class="vp-adm-card vp-adm-span-2">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">اقلام</h2>
            <span class="vp-adm-card-more">{{ $order->placed_at ? fa_date($order->placed_at, true) : 'ثبت نشده' }}</span>
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
            <li><span>ارسال</span><b>{{ $order->shippingLabel() }}</b></li>
            @if ($order->shippingMethod)
                <li><span>روش ارسال</span><b>{{ $order->shippingMethod->name }}</b></li>
            @endif
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
            <li><span>پرداخت</span><b>{{ $order->paymentLabel() }}</b></li>
            @if ($order->payment_method)
                <li><span>روش پرداخت</span><b>{{ $order->methodLabel() }}</b></li>
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
                    <b>{{ $order->estimated_ship_by ? fa_date($order->estimated_ship_by) : 'ندارد' }}</b>
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

            @if ($order->shippingMethod)
                {{-- The choice the shopper made, said out loud here rather than
                     left implicit in the select below. A پس‌کرایه parcel has to
                     be handed to the carrier as پس‌کرایه — the shop took no
                     money for it — and getting that wrong means the shop pays
                     the postage and finds out at the end of the month. --}}
                <p class="vp-adm-note {{ $order->shippingMethod->isCollect() ? 'is-bad' : '' }}">
                    مشتری «{{ $order->shippingMethod->name }}» را انتخاب کرده —
                    @if ($order->shippingMethod->isCollect())
                        <b>پس‌کرایه</b>: هزینه ارسال از فروشگاه گرفته نشده و باید هنگام تحویل از مشتری گرفته شود.
                    @else
                        هزینه ارسال {{ $order->shippingLabel() }} در سفارش حساب شده است.
                    @endif
                </p>
            @endif

            <form class="vp-adm-form" method="post" action="{{ route('admin.order.ship', $order) }}">
                @csrf

                @if ($methods->isNotEmpty())
                    <label for="sh-method">روش ارسال</label>
                    <select id="sh-method" name="shipping_method_id">
                        @foreach ($methods as $method)
                            <option value="{{ $method->id }}" @selected($order->shipping_method_id === $method->id)>
                                {{ $method->name }}{{ $method->carrier ? ' — '.$method->carrier : '' }} ({{ $method->chargeLabel() }})
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

    {{-- --- the money arrived -------------------------------------------- --}}
    @if ($order->status === \App\Models\Order::PLACED)
        <section class="vp-adm-card">
            <div class="vp-adm-card-head">
                <h2 class="vp-adm-card-title">پول را گرفتم</h2>
            </div>

            <p class="vp-adm-empty">موجودی رزروشده فروخته می‌شود. برگشت‌پذیر نیست.</p>

            <form class="vp-adm-form" method="post" action="{{ route('admin.order.pay', $order) }}">
                @csrf

                <label for="pay-method">چطور پرداخت شد</label>
                <select id="pay-method" name="method">
                    @foreach (\App\Models\Order::methodLabels() as $value => $label)
                        <option value="{{ $value }}" @selected($order->payment_method === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="pay-ref">شماره پیگیری یا مرجع (اختیاری)</label>
                <input id="pay-ref" type="text" name="reference" dir="ltr" maxlength="60" value="{{ old('reference') }}">

                <button type="submit" class="vp-adm-apply">ثبت پرداخت</button>
            </form>
        </section>
    @endif

    {{-- --- it reached the customer --------------------------------------- --}}
    @if ($order->status === \App\Models\Order::SHIPPED)
        <section class="vp-adm-card">
            <div class="vp-adm-card-head">
                <h2 class="vp-adm-card-title">به دستش رسید</h2>
            </div>

            <p class="vp-adm-empty">فقط بعد از اینکه بسته واقعاً به مشتری رسید.</p>

            <form class="vp-adm-form" method="post" action="{{ route('admin.order.deliver', $order) }}">
                @csrf

                <label for="del-when">زمان تحویل به مشتری</label>
                <input id="del-when" type="datetime-local" name="delivered_at"
                       value="{{ old('delivered_at', now()->format('Y-m-d\TH:i')) }}"
                       max="{{ now()->format('Y-m-d\TH:i') }}">

                <button type="submit" class="vp-adm-apply">ثبت تحویل</button>
            </form>
        </section>
    @endif

    {{-- --- the order is off ----------------------------------------------- --}}
    @if (in_array($order->status, [\App\Models\Order::PLACED, \App\Models\Order::PAID], true))
        <section class="vp-adm-card">
            <div class="vp-adm-card-head">
                <h2 class="vp-adm-card-title">لغو سفارش</h2>
            </div>

            <p class="vp-adm-empty">
                @if ($order->status === \App\Models\Order::PAID)
                    موجودی برمی‌گردد و پرداخت «برگشت‌خورده» ثبت می‌شود. برگشت‌پذیر نیست.
                @else
                    موجودی رزروشده به انبار برمی‌گردد. برگشت‌پذیر نیست.
                @endif
            </p>

            <form class="vp-adm-form" method="post" action="{{ route('admin.order.cancel', $order) }}"
                  onsubmit="return confirm('این سفارش لغو می‌شود و موجودی رزروشده به انبار برمی‌گردد. برگشت‌پذیر نیست.')">
                @csrf

                {{-- Required, and it is the only part of a cancellation nobody
                     can reconstruct later: the stock movement and the hour are
                     both written down, the reason is only in somebody's head. --}}
                <label for="cancel-why">علت لغو</label>
                <input id="cancel-why" type="text" name="reason" maxlength="200"
                       placeholder="مثلاً مشتری منصرف شد" value="{{ old('reason') }}" required>

                <button type="submit" class="vp-adm-danger">لغو سفارش</button>
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
                                <br><small>{{ $labels[$before] ?? $before ?? 'خالی' }} ← {{ $labels[$after] ?? $after }}</small>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>

{{-- --- where this order has got to, §21 -------------------------------- --}}
{{--
    What used to be here was a row of bare status buttons under the sentence
    «از «ثبت شد» می‌توان رفت به:» — the state machine described to somebody who
    does not think in state machines. It also sat beside «ارسال کردم», which is
    written the other way round, as a job the shop does, so one screen spoke two
    languages about the same order.

    Every move it offered is now a card above, named for what happened in the
    shop and carrying what that event needs: «پول را گرفتم» writes a payment,
    «لغو سفارش» writes the reason, «به دستش رسید» records the hour. What is left
    here is the one thing that row said which nothing else does — that an order
    has arrived somewhere final and there is nothing more to do to it.
--}}
@if ($moves === [])
    <div class="vp-adm-actions">
        <span class="vp-adm-actions-say">این سفارش «{{ $order->statusLabel() }}» است و کار دیگری روی آن نمانده.</span>
    </div>
@endif
@endsection
