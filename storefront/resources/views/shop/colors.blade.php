{{--
    The colour row — «یه لاین انتخاب رنگ هم نیاز داریم».

    Every variant in the catalogue says «نامشخص», so there is no colour to
    draw: these are `placeholders.colors`, a stand-in for the row, and nothing
    in it is selectable or named. `aria-hidden`, because a screen reader
    hearing three unnamed colours would be hearing a claim the catalogue has
    not made. The day a variant carries a real colour, the named «رنگ» block
    higher up is what the page draws and this row is not reached.

    Included twice — inside the size form, where it sits between the sizes and
    the buy row the client asked for in that order, and again on a shoe with no
    sellable size, which has no form to sit in. Phone only either way.
--}}
@php
    $swatches = $colorways->count() > 1 ? [] : config('storefront.placeholders.colors', []);
@endphp

@if ($swatches)
    <div class="vp-pdp-colors">
        <h2 class="vp-pdp-choice-title">رنگ</h2>
        <div class="vp-pdp-swatches" aria-hidden="true">
            @foreach ($swatches as $swatch)
                <span @class(['vp-pdp-swatch', 'is-on' => $loop->first]) style="--vp-swatch: {{ $swatch }}"></span>
            @endforeach
        </div>
    </div>
@endif
