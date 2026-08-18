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

<section class="vp-adm-card">
    @if ($enquiries->isEmpty())
        <p class="vp-adm-empty">
            @if ($kind === null)
                هنوز درخواستی ثبت نشده.
            @else
                درخواستی از این نوع ثبت نشده.
            @endif
        </p>
    @else
        <table class="vp-admin-table">
            <thead>
                <tr>
                    <th>نوع</th><th>نام</th><th>تماس</th><th>توضیح</th>
                    <th>تاریخ</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($enquiries as $enquiry)
                <tr>
                    <td>{{ $enquiry->kindLabel() }}</td>
                    <td>
                        {{ $enquiry->name }}
                        @if ($enquiry->organisation)
                            <span class="vp-adm-sub">{{ $enquiry->organisation }}</span>
                        @endif
                    </td>
                    <td>
                        {{-- A telephone number is read left to right whatever
                             the page's direction. --}}
                        <a href="tel:{{ $enquiry->phone }}"><bdi dir="ltr">{{ $enquiry->phone }}</bdi></a>
                        @if ($enquiry->city)
                            <span class="vp-adm-sub">{{ $enquiry->city }}</span>
                        @endif
                    </td>
                    <td class="vp-admin-said">{{ $enquiry->message }}</td>
                    <td>{{ fa_date($enquiry->created_at) }}</td>
                    <td>
                        <span class="vp-adm-badge is-{{ $enquiry->status === 'new' ? 'placed' : ($enquiry->status === 'closed' ? 'delivered' : 'paid') }}">
                            {{ $enquiry->statusLabel() }}
                        </span>
                        @if ($enquiry->handler)
                            <span class="vp-adm-sub">{{ $enquiry->handler->name }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="vp-adm-inline">
                            @foreach (\App\Models\Enquiry::statusLabels() as $to => $label)
                                @continue($enquiry->status === $to)
                                <form method="post" action="{{ route('admin.enquiry.status', $enquiry) }}">
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

        <div class="vp-adm-pager">{{ $enquiries->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
