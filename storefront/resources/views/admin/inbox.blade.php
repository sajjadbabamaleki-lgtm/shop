@extends('layouts.admin')

@section('title', 'پیام‌ها')

{{--
    §11's inbox.

    Sorted by when somebody last spoke, not by when the thread started: a
    question asked this morning on a thread from last month is this morning's
    work. «خوانده‌نشده» is per person — the cursor is on the participant row —
    so two people working the same inbox each see their own.
--}}

@section('content')
<form class="vp-adm-filters" method="get" action="{{ route('admin.inbox') }}" role="search">
    <label class="visually-hidden" for="vp-in-q">جست‌وجو</label>
    <input id="vp-in-q" class="vp-adm-search" type="search" name="q" value="{{ $filters['q'] }}"
           placeholder="موضوع، نام یا تلفن مشتری، شماره سفارش">

    <label class="visually-hidden" for="vp-in-status">وضعیت</label>
    <select id="vp-in-status" name="status">
        <option value="">همه</option>
        <option value="open" @selected($filters['status'] === 'open')>باز</option>
        <option value="closed" @selected($filters['status'] === 'closed')>بسته</option>
    </select>

    <label class="vp-adm-check">
        <input type="checkbox" name="unread" value="1" @checked($filters['unread'])>
        <span>فقط خوانده‌نشده‌ها ({{ fa_number($unreadTotal) }})</span>
    </label>

    <button type="submit" class="vp-adm-apply">اعمال</button>

    @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['unread'])
        <a class="vp-adm-clear" href="{{ route('admin.inbox') }}">پاک کردن</a>
    @endif
</form>

<section class="vp-adm-card">
    @if ($conversations->isEmpty())
        <p class="vp-adm-empty">
            @if ($filters['q'] === '' && $filters['status'] === '' && ! $filters['unread'])
                هنوز پیامی از مشتری نیامده. مشتری‌ها از «پیام‌های من» در حسابشان می‌نویسند.
            @else
                گفت‌وگویی با این فیلترها پیدا نشد.
            @endif
        </p>
    @else
        <table class="vp-admin-table">
            <thead>
                <tr><th>موضوع</th><th>مشتری</th><th>سفارش</th><th>آخرین پیام</th><th>وضعیت</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($conversations as $conversation)
                @php $unread = $conversation->unreadFor(\App\Models\Conversation::STAFF, auth()->id()); @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.conversation', $conversation) }}">{{ $conversation->subject }}</a>
                        @if ($unread > 0)
                            <span class="vp-adm-badge is-cancelled">{{ fa_number($unread) }} تازه</span>
                        @endif
                    </td>
                    <td>
                        {{ $conversation->customer?->name ?: 'بی‌نام' }}
                        <span class="vp-adm-sub"><bdi dir="ltr">{{ $conversation->customer?->phone }}</bdi></span>
                    </td>
                    <td>
                        @if ($conversation->order)
                            <a href="{{ route('admin.order', $conversation->order) }}">{{ $conversation->order->number }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $conversation->last_message_at ? fa_date($conversation->last_message_at, true) : 'ندارد' }}</td>
                    <td>
                        <span class="vp-adm-badge is-{{ $conversation->isOpen() ? 'placed' : 'delivered' }}">
                            {{ $conversation->statusLabel() }}
                        </span>
                    </td>
                    <td><a class="vp-adm-mini is-quiet" href="{{ route('admin.conversation', $conversation) }}">باز کن</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $conversations->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
