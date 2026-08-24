{{--
    The size row, in the three states the client asked for:

        «همه سایزها باید باشن با ۳ حالت غیره فعال قابل انتخاب و سلکت شده»

    - **غیرفعال** — a size this shop carries and this shoe has not got. Drawn
      greyed and struck, and it is not a control at all: a disabled radio is
      still a radio in the tab order on some engines, and there is nothing here
      to choose.
    - **قابل انتخاب** — a size of this shoe, in stock here, sold by the branch.
      A `<label>` over a radio: the whole chip is the hit area, it works with
      the keyboard, and nothing has to run for it to hold its state.
    - **سلکت شده** — the first available one, checked on arrival, so the bar at
      the foot always has something to add.

    The row is every size *the branch sells across the catalogue*, not every
    size of this shoe — that is what «همه سایزها» asks for, and it is the same
    list the filter rail on the listing is built from.

    A size with more than one seller cannot be a radio in this form: the basket
    line is a size *from a seller* and the chip cannot say which. Those chips
    are links down to that size's own seller rows, which carry their own
    buttons. In today's catalogue there are none — the branch is the only
    seller of every size — but `MarketplaceTest` covers the case and this is
    what it lands on.
--}}
@php
    $byValue = $sizes->keyBy(fn ($variant) => (int) $variant->size_value);
    $simpleIds = $simple->pluck('id')->all();
    $first = true;

    /*
     * A product whose sizes are not numbers — a bag, which carries «تک‌سایز» —
     * has nothing to draw here, and drawing the row anyway would be worse than
     * empty: every chip would be a size it has not got, none of them would be
     * a radio, and the form would post no variant at all, so «افزودن به سبد»
     * would fail with nothing on the page to explain why. One size is not a
     * choice, so it is a hidden field and the row goes.
     */
    $sized = $sizes->contains(fn ($variant) => (int) $variant->size_value > 0);

    /*
     | Each chip carries all three units and the page shows one.
     |
     | The desktop reference has an EU/US/CM switch over the size grid. It is
     | done the way the sizes themselves are — radios and labels, no script —
     | and the chip renders three spans of which CSS shows one. The numbers are
     | `config('storefront.size_chart')`, the same table `/size-guide`
     | publishes; a size we have no row for shows its EU number in every unit
     | rather than a converted guess.
     */
    $chart = collect(config('storefront.size_chart'))->keyBy('eu');
@endphp

@php
    $units = function (int $value) use ($chart) {
        $row = $chart->get($value);

        return '<span class="vp-size-eu">'.fa_number($value).'</span>'
            .'<span class="vp-size-us">'.($row ? fa_number($row['us']) : fa_number($value)).'</span>'
            .'<span class="vp-size-cm">'.($row ? fa_number($row['cm']) : fa_number($value)).'</span>';
    };
@endphp

@if (! $sized)
    @php
        // A full block rather than `@php(...)`: the one-line form does not
        // survive the nested parentheses in this expression — it compiles to
        // `<?php(` and takes the rest of the template with it.
        $only = $simple->first() ?? $sizes->first();
    @endphp
    @if ($only)
        <input type="hidden" name="variant" value="{{ $only->id }}">
    @endif
@else
<div class="vp-pick-sizes">
    @foreach ($shopSizes as $value)
        @php
            $value = (int) $value;
            $variant = $byValue->get($value);
            $sellable = $variant && in_array($variant->id, $simpleIds, true);
        @endphp

        @if ($sellable)
            <label class="vp-pick-size">
                <input type="radio" name="variant" value="{{ $variant->id }}" @checked($first)>
                <span>{!! $units($value) !!}</span>
            </label>
            @php $first = false; @endphp
        @elseif ($variant)
            <a class="vp-pick-size is-elsewhere" href="#size-{{ $variant->id }}">
                <span>{!! $units($value) !!}</span>
            </a>
        @else
            <span class="vp-pick-size is-off" aria-disabled="true">
                <span>{!! $units($value) !!}</span>
            </span>
        @endif
    @endforeach

    {{-- The count, at the right-hand end of the same line — «فاصله بینشون کمتر
         بشه که اون مستطیل بیاد راستشون». It is the second of two copies: the
         one in `.vp-pick-head` is the desktop's, beside the heading, and
         exactly one of the two is ever shown. Rendering it twice rather than
         moving it is what keeps the desktop line untouched. --}}
    @if ($simple->isNotEmpty())
        <span class="vp-pick-note is-inline">{{ fa_number($simple->count()) }} سایز موجود</span>
    @endif
</div>
@endif
