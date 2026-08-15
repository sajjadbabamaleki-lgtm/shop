@extends('layouts.admin')

{{--
    «درخواست‌های عمده و نمایندگی».

    This screen is not an extra on top of the two public forms — it is the only
    place their submissions can be read. There is no mail provider, so the row
    is the delivery.
--}}

@section('content')
<section class="vp-shop-panel">
    <div class="vp-shop-head">
        <div class="vp-shop-heading">
            <h1 class="vp-shop-title">درخواست‌های عمده و نمایندگی</h1>
            <p class="vp-shop-count">
                {{ $waiting > 0 ? fa_number($waiting).' درخواست هنوز رسیدگی نشده' : 'همه درخواست‌ها رسیدگی شده' }}
            </p>
        </div>

        <div class="vp-admin-inline">
            <a class="vp-admin-save" href="{{ route('admin.enquiries') }}">همه</a>
            @foreach (\App\Models\Enquiry::kinds() as $slug => $label)
                <a class="vp-admin-save" href="{{ route('admin.enquiries', ['kind' => $slug]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @if (session('status'))
        <p class="vp-note is-good">{{ session('status') }}</p>
    @endif

    @if ($enquiries->isEmpty())
        <p class="vp-shop-empty">درخواستی ثبت نشده.</p>
    @else
        <table class="vp-admin-table is-wide">
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
                            <br><span class="vp-cart-meta">{{ $enquiry->organisation }}</span>
                        @endif
                    </td>
                    <td>
                        {{-- A telephone number is read left to right whatever
                             the page's direction. --}}
                        <a class="vp-admin-tel" href="tel:{{ $enquiry->phone }}">{{ $enquiry->phone }}</a>
                        @if ($enquiry->city)
                            <br><span class="vp-cart-meta">{{ $enquiry->city }}</span>
                        @endif
                    </td>
                    <td class="vp-admin-said">{{ $enquiry->message }}</td>
                    <td>{{ fa_date($enquiry->created_at) }}</td>
                    <td>
                        {{ $enquiry->statusLabel() }}
                        @if ($enquiry->handler)
                            <br><span class="vp-cart-meta">{{ $enquiry->handler->name }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="vp-admin-inline">
                            @foreach (\App\Models\Enquiry::statusLabels() as $to => $label)
                                @continue($enquiry->status === $to)
                                <form method="post" action="{{ route('admin.enquiry.status', $enquiry) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $to }}">
                                    <button type="submit" class="vp-admin-save">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-shop-pages">{{ $enquiries->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
