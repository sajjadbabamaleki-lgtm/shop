@extends('layouts.admin')

@section('title', 'قیمت‌ها')

{{--
    What this branch charges. Typed and read in Toman; stored in Rial.

    «قیمت قبل از تخفیف» is the struck-through number on the card. Leaving it
    empty is how a sale ends.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">{{ $branch->name }}، همه مبلغ‌ها به تومان</p>

    <div class="vp-adm-head-side">
        <form class="vp-adm-filters" method="get" action="{{ route('admin.pricing') }}" role="search">
            <label class="visually-hidden" for="vp-pri-q">جست‌وجو</label>
            <input id="vp-pri-q" class="vp-adm-search" type="search" name="q" value="{{ $q }}" placeholder="نام کالا یا کد">

            {{-- **The brand, by its own id and not by its name in the title.**
                 «قیمت گلدن گوس هارو» is a question about a make, and a search
                 for those words answers it with whatever happens to have them
                 written in its name. This is the list the bulk change below
                 counts, so it has to be the exact set. --}}
            <label class="visually-hidden" for="vp-pri-brand">برند</label>
            <select id="vp-pri-brand" name="brand">
                <option value="">همه برندها</option>
                @foreach ($brands as $option)
                    <option value="{{ $option->slug }}" @selected($brand === $option->slug)>{{ $option->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="vp-adm-apply">جست‌وجو</button>
            @if ($q !== '' || $brand !== '')
                <a class="vp-adm-clear" href="{{ route('admin.pricing') }}">پاک کردن</a>
            @endif
        </form>
    </div>
</div>

{{-- **Every price the filter above is showing, moved at once.**

     «نباید دونه دونه همه رنگاشو برم جدا جدا قیمتشونو ببرم بالا» — this shop
     imports a shoe one colourway per product and prices it one row per size,
     so «Golden Goose, up twenty percent» was thirty forms filled in by hand.

     What it changes is what the page is showing: the same filter builds the
     list, the count on the button and the rows that are written. The count is
     said twice — on the button and in the question the browser asks — because
     the number of rows is the only thing that distinguishes «the Golden Geese»
     from «the whole shop», and there is no undo on a price except the audit
     trail.

     `confirm()` is the panel's own way of asking before something
     irreversible, the same one the orders screen has used since it was
     written. --}}
@if ($matched > 0)
    <section class="vp-adm-card vp-adm-bulk">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">تغییر گروهی قیمت</h2>
            <span class="vp-adm-card-more">
                روی {{ fa_number($matched) }} قیمتی که همین الان فیلتر شده‌اند اعمال می‌شود، و به نزدیک‌ترین هزار تومان رند می‌شود
            </span>
        </div>

        <form class="vp-adm-form vp-adm-bulk-form" method="post"
              action="{{ route('admin.pricing.bulk', ['q' => $q, 'brand' => $brand]) }}"
              onsubmit="return confirm('قیمت {{ fa_number($matched) }} مورد تغییر می‌کند. مطمئنی؟')">
            @csrf

            <label for="vp-bulk-dir">جهت</label>
            <select id="vp-bulk-dir" name="direction">
                <option value="up">افزایش</option>
                <option value="down">کاهش</option>
            </select>

            {{-- A text box and not `type="number"`: this panel is typed in
                 Persian digits and a number input refuses «۲۰» outright, with
                 no message and nothing to press. The controller folds them,
                 the same way the price boxes on this screen already do. --}}
            <label for="vp-bulk-percent">درصد</label>
            <input id="vp-bulk-percent" name="percent" value="{{ fa_number(20) }}" required inputmode="numeric" maxlength="4">

            <button type="submit" class="vp-adm-apply">اعمال روی {{ fa_number($matched) }} قیمت</button>
        </form>
    </section>
@endif

<section class="vp-adm-card">
    @if ($offers->isEmpty())
        <p class="vp-adm-empty">
            @if ($q === '')
                هنوز کالایی برای فروش در این شعبه باز نشده.
            @else
                چیزی با این نام یا کد پیدا نشد.
            @endif
        </p>
    @else
        <table class="vp-admin-table">
            <thead>
                <tr><th>کالا</th><th>کد</th><th>سایز</th><th>قیمت فروش</th><th>قبل از تخفیف</th><th>وضعیت</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($offers as $offer)
                <tr>
                    <td>{{ $offer->variant?->product?->title }}</td>
                    <td>{{ $offer->variant?->sku }}</td>
                    <td>{{ fa_number((int) $offer->variant?->size_value) }}</td>
                    <form method="post" action="{{ route('admin.pricing.update', ['q' => $q]) }}">
                        @csrf
                        <input type="hidden" name="offer" value="{{ $offer->id }}">
                        <td><input class="vp-adm-cell is-wide" type="text" inputmode="numeric" name="price" value="{{ intdiv($offer->price, 10) }}"></td>
                        <td><input class="vp-adm-cell is-wide" type="text" inputmode="numeric" name="compare_at_price" value="{{ $offer->compare_at_price ? intdiv($offer->compare_at_price, 10) : '' }}"></td>
                        <td>
                            <select name="status" class="vp-adm-cell">
                                <option value="active" @selected($offer->status === 'active')>فروش</option>
                                <option value="inactive" @selected($offer->status === 'inactive')>متوقف</option>
                            </select>
                        </td>
                        <td><button type="submit" class="vp-adm-mini">ثبت</button></td>
                    </form>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $offers->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
