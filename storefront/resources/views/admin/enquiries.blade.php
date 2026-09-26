@extends('layouts.admin')

@section('title', 'درخواست‌ها')

{{--
    «درخواست‌های عمده و نمایندگی».

    This screen is not an extra on top of the two public forms — it is the only
    place their submissions can be read. There is no mail provider, so the row
    is the delivery.

    Not branch-scoped, on purpose: somebody asking to open a branch in Shiraz
    is not Shiraz's enquiry to answer.
--}}

@php
    $kind = request()->query('kind');
@endphp

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">
        {{ $waiting > 0 ? fa_number($waiting).' درخواست هنوز رسیدگی نشده' : 'همه درخواست‌ها رسیدگی شده' }}
    </p>

    <div class="vp-adm-head-side">
        <a class="vp-adm-clear{{ $kind === null ? ' is-on' : '' }}" href="{{ route('admin.enquiries') }}">همه</a>
        @foreach (\App\Models\Enquiry::kinds() as $slug => $label)
            <a class="vp-adm-clear{{ $kind === $slug ? ' is-on' : '' }}"
               href="{{ route('admin.enquiries', ['kind' => $slug]) }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

{{--
    One card per enquiry, and nothing boxed inside it. This was a table, and
    below 992 every table in the panel turns its rows into bordered cards — so
    on a phone it was a card of cards («کادر تو کادر»). A list of messages is
    not tabular data in the first place: it is read one at a time, answered,
    and put away, which is what a card per message is for.
--}}
@if ($enquiries->isEmpty())
    <section class="vp-adm-card">
        <p class="vp-adm-empty">
            @if ($kind === null)
                هنوز درخواستی ثبت نشده.
            @else
                درخواستی از این نوع ثبت نشده.
            @endif
        </p>
    </section>
@else
    <div class="vp-adm-enqs">
    @foreach ($enquiries as $enquiry)
        <article class="vp-adm-card vp-adm-enq" id="enquiry-{{ $enquiry->id }}">
            <header class="vp-adm-enq-head">
                <div class="vp-adm-enq-who">
                    <b>{{ $enquiry->name }}</b>
                    @if ($enquiry->organisation)
                        <span class="vp-adm-sub">{{ $enquiry->organisation }}</span>
                    @endif
                </div>
                <span class="vp-adm-badge is-{{ $enquiry->status === 'new' ? 'placed' : ($enquiry->status === 'closed' ? 'delivered' : 'paid') }}">
                    {{ $enquiry->statusLabel() }}
                </span>
            </header>

            <p class="vp-adm-enq-facts">
                <span>{{ $enquiry->kindLabel() }}</span>
                {{-- A telephone number is read left to right whatever the
                     page's direction. --}}
                <a href="tel:{{ $enquiry->phone }}"><bdi dir="ltr">{{ $enquiry->phone }}</bdi></a>
                @if ($enquiry->city)
                    <span>{{ $enquiry->city }}</span>
                @endif
                <span>{{ fa_date($enquiry->created_at, true) }}</span>
            </p>

            @if ($enquiry->message)
                <p class="vp-adm-enq-said">{{ $enquiry->message }}</p>
            @endif

            @foreach ($enquiry->replies as $reply)
                <div class="vp-adm-enq-reply{{ $reply->sent_at ? '' : ' is-unsent' }}">
                    <p>{{ $reply->body }}</p>
                    <span class="vp-adm-sub">
                        {{ $reply->author?->name ?? 'کارمند حذف‌شده' }}
                        — {{ fa_date($reply->created_at, true) }}
                        — {{ $reply->sent_at ? 'با پیامک فرستاده شد' : 'پیامک فرستاده نشد' }}
                    </span>
                </div>
            @endforeach

            <form class="vp-adm-enq-answer" method="post" action="{{ route('admin.enquiry.reply', $enquiry) }}">
                @csrf
                <label class="vp-adm-sub" for="enq-{{ $enquiry->id }}">پاسخ — با پیامک به <bdi dir="ltr">{{ $enquiry->phone }}</bdi> فرستاده می‌شود</label>
                <textarea id="enq-{{ $enquiry->id }}" name="body" rows="3" maxlength="500" required
                          placeholder="پاسخ خود را بنویسید…"></textarea>
                <div class="vp-adm-enq-acts">
                    <button type="submit" class="vp-adm-mini">ارسال پاسخ</button>
                    @foreach (\App\Models\Enquiry::statusLabels() as $to => $label)
                        @continue($enquiry->status === $to)
                        <button type="submit" class="vp-adm-mini is-quiet" formnovalidate
                                form="enq-status-{{ $enquiry->id }}-{{ $to }}">{{ $label }}</button>
                    @endforeach
                    @if ($enquiry->handler)
                        <span class="vp-adm-sub">رسیدگی: {{ $enquiry->handler->name }}</span>
                    @endif
                </div>
            </form>

            {{-- The status buttons sit in the reply form's row but post their
                 own forms: a form cannot be nested in another. --}}
            @foreach (\App\Models\Enquiry::statusLabels() as $to => $label)
                @continue($enquiry->status === $to)
                <form id="enq-status-{{ $enquiry->id }}-{{ $to }}" method="post" action="{{ route('admin.enquiry.status', $enquiry) }}" hidden>
                    @csrf
                    <input type="hidden" name="status" value="{{ $to }}">
                </form>
            @endforeach
        </article>
    @endforeach
    </div>

    <div class="vp-adm-pager">{{ $enquiries->links('pagination.vikyplus') }}</div>
@endif
@endsection
