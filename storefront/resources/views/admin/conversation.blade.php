@extends('layouts.admin')

@section('title', $conversation->subject)

{{--
    One thread, as the shop reads it.

    Opening this page marked it read for whoever opened it — and for nobody
    else. The lines are oldest first, because a conversation is read in the
    order it happened; the box to answer in is at the foot, where the reading
    ends.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">
        {{ $conversation->customer?->name ?: 'مشتری' }} —
        <bdi dir="ltr">{{ $conversation->customer?->phone }}</bdi>
        @if ($conversation->order)
            · سفارش <a href="{{ route('admin.order', $conversation->order) }}">{{ $conversation->order->number }}</a>
        @endif
    </p>

    <div class="vp-adm-head-side">
        <span class="vp-adm-badge is-{{ $conversation->isOpen() ? 'placed' : 'delivered' }}">{{ $conversation->statusLabel() }}</span>
        <a class="vp-adm-clear" href="{{ route('admin.inbox') }}">بازگشت به پیام‌ها</a>
    </div>
</div>

<section class="vp-adm-card">
    <ol class="vp-adm-thread">
        @foreach ($conversation->messages as $message)
            <li @class(['vp-adm-said', 'is-us' => $message->isFrom(\App\Models\Conversation::STAFF), 'is-note' => $message->isFrom(\App\Models\Conversation::SYSTEM)])>
                <p class="vp-adm-said-who">
                    {{ $message->author() }}
                    <span class="vp-adm-said-when">{{ fa_date($message->created_at, true) }}</span>
                </p>

                <p class="vp-adm-said-body">{{ $message->body }}</p>

                @if ($message->attachments->isNotEmpty())
                    <ul class="vp-adm-files">
                        @foreach ($message->attachments as $file)
                            <li>
                                <a href="{{ route('admin.conversation.file', [$conversation, $file]) }}">
                                    {{ $file->name }}
                                </a>
                                <span class="vp-adm-sub">{{ $file->readableSize() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ol>
</section>

<section class="vp-adm-card vp-adm-answer">
    <div class="vp-adm-card-head">
        <h2 class="vp-adm-card-title">پاسخ</h2>
    </div>

    <form class="vp-adm-form" method="post" action="{{ route('admin.conversation.reply', $conversation) }}" enctype="multipart/form-data">
        @csrf

        <label class="visually-hidden" for="vp-reply">متن پاسخ</label>
        <textarea id="vp-reply" name="body" rows="4" maxlength="4000" required
                  placeholder="پاسخ شما همان‌جا در حساب مشتری دیده می‌شود.">{{ old('body') }}</textarea>

        <label for="vp-reply-files">پیوست (حداکثر {{ fa_number(\App\Support\Messaging\Thread::MAX_FILES) }} فایل)</label>
        <input id="vp-reply-files" type="file" name="files[]" multiple
               accept=".jpg,.jpeg,.png,.webp,.pdf">

        <button type="submit" class="vp-adm-apply">فرستادن</button>
    </form>
</section>

<div class="vp-adm-actions">
    <form method="post" action="{{ route('admin.conversation.status', $conversation) }}">
        @csrf
        <input type="hidden" name="status" value="{{ $conversation->isOpen() ? 'closed' : 'open' }}">
        <button type="submit" class="vp-adm-{{ $conversation->isOpen() ? 'out' : 'apply' }}">
            {{ $conversation->isOpen() ? 'بستن گفت‌وگو' : 'باز کردن دوباره' }}
        </button>
    </form>

    <span class="vp-adm-actions-say">
        بستن، گفت‌وگو را پاک نمی‌کند و از دید مشتری پنهان نمی‌کند — و اگر مشتری دوباره بنویسد، خودش باز می‌شود.
    </span>
</div>
@endsection
