@extends('layouts.admin')

@section('title', 'نظرها')

{{--
    «نظر خریداران», before the shop prints them.

    Nothing on the storefront publishes a comment — the form writes `pending`
    and this screen is the only thing that moves it. So this is not a report on
    a feature that already works; it is the half of it that decides.

    Not branch-scoped: a comment is about the shoe, and the shoe is the same
    shoe at every branch.
--}}

@php
    $status = request()->query('status');
@endphp

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">
        {{ $waiting > 0 ? fa_number($waiting).' نظر در انتظار بررسی' : 'نظری در انتظار بررسی نیست' }}
    </p>

    <div class="vp-adm-head-side">
        <a class="vp-adm-clear{{ $status === null ? ' is-on' : '' }}" href="{{ route('admin.comments') }}">همه</a>
        @foreach (\App\Models\ProductComment::LABELS as $slug => $label)
            <a class="vp-adm-clear{{ $status === $slug ? ' is-on' : '' }}"
               href="{{ route('admin.comments', ['status' => $slug]) }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

<section class="vp-adm-card">
    @if ($comments->isEmpty())
        <p class="vp-adm-empty">
            @if ($status === null)
                هنوز کسی نظری ننوشته.
            @else
                نظری با این وضعیت نیست.
            @endif
        </p>
    @else
        <table class="vp-admin-table">
            <thead>
                <tr>
                    <th>کالا</th><th>مشتری</th><th>نظر</th>
                    <th>تاریخ</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($comments as $comment)
                <tr>
                    <td>
                        {{-- The product page, so whoever is reading can see
                             what the sentence is about. --}}
                        <a href="{{ storefront_route('product', $comment->product) }}">
                            {{ $comment->product->cardName() }}
                        </a>
                    </td>
                    <td>
                        {{ $comment->customer?->name ?: '—' }}
                        @if ($comment->customer)
                            {{-- The shop's own staff may see the whole number:
                                 this is the panel, and it is how they ring
                                 somebody about what they wrote. The storefront
                                 masks it — see `ProductComment::authorName()`. --}}
                            <span class="vp-adm-sub"><bdi dir="ltr">{{ $comment->customer->phone }}</bdi></span>
                        @endif
                    </td>
                    <td class="vp-admin-said">{{ $comment->body }}</td>
                    <td>{{ fa_date($comment->created_at) }}</td>
                    <td>
                        <span class="vp-adm-badge is-{{ $comment->status === \App\Models\ProductComment::PENDING ? 'placed' : ($comment->status === \App\Models\ProductComment::PUBLISHED ? 'delivered' : 'cancelled') }}">
                            {{ \App\Models\ProductComment::LABELS[$comment->status] }}
                        </span>
                    </td>
                    <td>
                        <div class="vp-adm-inline">
                            @foreach (\App\Models\ProductComment::LABELS as $to => $label)
                                @continue($comment->status === $to)
                                <form method="post" action="{{ route('admin.comment.status', $comment) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $to }}">
                                    <button type="submit" class="vp-adm-mini is-quiet">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $comments->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
