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
@endphp

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
                <span>{{ fa_number($value) }}</span>
            </label>
            @php $first = false; @endphp
        @elseif ($variant)
            <a class="vp-pick-size is-elsewhere" href="#size-{{ $variant->id }}">
                <span>{{ fa_number($value) }}</span>
            </a>
        @else
            <span class="vp-pick-size is-off" aria-disabled="true">
                <span>{{ fa_number($value) }}</span>
            </span>
        @endif
    @endforeach
</div>
