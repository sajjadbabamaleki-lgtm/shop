@extends('layouts.storefront')

@section('title', $conversation->subject)

{{--
    One thread, as its customer reads it.

    The same lines the shop sees, in the same order, with the same attachments
    — there is one record and both sides read it. A closed thread still shows
    the box: writing in it re-opens the thread, which is what somebody with one
    more question expects and what «بسته شد» must not stand in the way of.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">{{ $conversation->subject }}</h1>
                    <p class="vp-shop-count">
                        @if ($conversation->order)
                            درباره سفارش
                            <a href="{{ storefront_route('order', $conversation->order) }}">{{ $conversation->order->number }}</a>
                        @else
                            گفت‌وگو با فروشگاه
                        @endif
                        @unless ($conversation->isOpen())
                            — بسته‌شده
                        @endunless
                    </p>
                </div>
                <a class="vp-cart-keep" href="{{ storefront_route('account.messages') }}">همه پیام‌ها</a>
            </div>

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            <ol class="vp-said-list">
                @foreach ($conversation->messages as $message)
                    <li @class(['vp-said', 'is-me' => $message->isFrom(\App\Models\Conversation::CUSTOMER), 'is-note' => $message->isFrom(\App\Models\Conversation::SYSTEM)])>
                        <p class="vp-said-who">
                            {{ $message->isFrom(\App\Models\Conversation::CUSTOMER) ? 'شما' : $message->author() }}
                            <span class="vp-said-when">{{ fa_date($message->created_at, true) }}</span>
                        </p>

                        <p class="vp-said-body">{{ $message->body }}</p>

                        @if ($message->attachments->isNotEmpty())
                            <ul class="vp-said-files">
                                @foreach ($message->attachments as $file)
                                    <li>
                                        <a href="{{ storefront_route('account.message.file', [$conversation, $file]) }}">{{ $file->name }}</a>
                                        <span class="vp-said-size">{{ $file->readableSize() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ol>

            <form class="vp-checkout vp-said-form" method="post" action="{{ storefront_route('account.message.reply', $conversation) }}" enctype="multipart/form-data">
                @csrf

                <div class="vp-field">
                    <label for="r-body">پاسخ</label>
                    <textarea id="r-body" name="body" rows="4" maxlength="4000" required>{{ old('body') }}</textarea>
                </div>

                <div class="vp-field">
                    <label for="r-files">پیوست</label>
                    <input id="r-files" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>

                <button type="submit" class="vp-filter-apply vp-cart-go">فرستادن</button>
            </form>
        </div>
    </div>
</section>
@endsection
