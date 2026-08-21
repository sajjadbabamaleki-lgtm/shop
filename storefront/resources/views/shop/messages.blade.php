@extends('layouts.storefront')

@section('title', 'پیام‌های من')

{{--
    The customer's threads.

    In the shop's own materials, not the panel's: this is a page of the shop
    and a shopper is on it. `.vp-shop-panel` and the pane it is made of are the
    same ones the basket and the order page use.

    A thread may be about an order or about nothing in particular. Both are
    ordinary, so the list says which and the form offers the order number as
    optional rather than requiring one.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">پیام‌های من</h1>
                    <p class="vp-shop-count">گفت‌وگو با فروشگاه درباره سفارش‌ها و هر چیز دیگر</p>
                </div>
                <a class="vp-cart-keep" href="{{ storefront_route('account') }}">حساب من</a>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            @if ($conversations->isEmpty())
                <p class="vp-shop-empty">هنوز گفت‌وگویی نداری. با فرم پایین اولی را شروع کن.</p>
            @else
                <ul class="vp-threads">
                    @foreach ($conversations as $conversation)
                        @php $unread = $conversation->unreadFor(\App\Models\Conversation::CUSTOMER, $customer->id); @endphp
                        <li class="vp-thread">
                            <a class="vp-thread-what" href="{{ storefront_route('account.message', $conversation) }}">
                                <span class="vp-thread-title">{{ $conversation->subject }}</span>
                                <span class="vp-thread-meta">
                                    @if ($conversation->order)
                                        سفارش {{ $conversation->order->number }} ·
                                    @endif
                                    {{ $conversation->last_message_at ? fa_date($conversation->last_message_at, true) : '—' }}
                                    @unless ($conversation->isOpen())
                                        · بسته‌شده
                                    @endunless
                                </span>
                            </a>

                            @if ($unread > 0)
                                <span class="vp-thread-new">{{ fa_number($unread) }} پاسخ تازه</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div class="vp-shop-pages">{{ $conversations->links('pagination.vikyplus') }}</div>
            @endif
        </div>

        <div class="vp-shop-panel vp-track">
            <h2 class="vp-shop-title">گفت‌وگوی تازه</h2>

            <form class="vp-checkout" method="post" action="{{ storefront_route('account.messages.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="vp-field">
                    <label for="m-subject">موضوع</label>
                    <input id="m-subject" name="subject" value="{{ old('subject') }}" required maxlength="160"
                           placeholder="مثلاً: تعویض سایز">
                </div>

                <div class="vp-field">
                    <label for="m-order">شماره سفارش (اختیاری)</label>
                    <input id="m-order" name="order" value="{{ old('order') }}" maxlength="40" dir="ltr"
                           placeholder="VP-200023">
                </div>

                <div class="vp-field">
                    <label for="m-body">پیام</label>
                    <textarea id="m-body" name="body" rows="5" maxlength="4000" required>{{ old('body') }}</textarea>
                </div>

                <div class="vp-field">
                    <label for="m-files">پیوست (حداکثر {{ fa_number(\App\Support\Messaging\Thread::MAX_FILES) }} فایل، عکس یا PDF)</label>
                    <input id="m-files" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>

                <button type="submit" class="vp-filter-apply vp-cart-go">فرستادن</button>
            </form>
        </div>
    </div>
</section>
@endsection
