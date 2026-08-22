{{--
    Pagination, in the page's own hand.

    Laravel ships Tailwind and Bootstrap markup for this; both would arrive
    with a second set of opinions about type and colour, and the numbers would
    come out in latin digits on a page that writes every other number in
    Persian. This is small enough to own.

    The arrows point the way the language reads: on an RTL page "next" is to
    the left, so the chevrons are set by direction rather than hard-coded.

    **The window is built here, not read from `$elements`.** Laravel's own
    window keeps every page number until there are fifteen of them, and the
    Basalam import took the shop from two pages to thirteen: on a 390px screen
    that wrapped into three rows of tiles under the last shoe — «این چه وضع
    افتضاحیه پایین فروشگاه». A cell is 38px wide with an 8px gap, so 350px of
    usable width holds seven of them and no more. Shrunk to 34px with a 6px gap
    it holds eight, which is six numbers and both arrows — and six pages is
    where `ShopController::PER_PAGE` puts a hundred and forty-three shoes, so
    the whole listing fits and nothing is hidden from anybody:

      six pages or fewer   ‹ 1 2 3 4 5 6 ›      8 cells, 314px on a phone
      more than six        ‹ 1 … 7 … 13 ›       7 cells on a phone
                           ‹ 1 … 6 7 8 … 13 ›   9 cells on a desktop

    The difference is CSS, not markup — the neighbours carry `is-near` and are
    display:none under 576px — except for the ellipses, which cannot be, because
    an ellipsis is a claim about the numbers either side of it and hiding a
    number can make that claim true where it was not. Both sets of skips are
    counted, and a gap only one of them needs is drawn only for that one.
--}}
@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    // Six numbers and two arrows is eight cells, which is what a 34px cell and
    // a 6px gap spend on the narrowest phone we draw for — 314px of the 336px
    // a 360px screen gives the listing. So six pages or fewer are all printed,
    // and nothing is hidden from anybody: «بنظرم سایز شماره ها جوری باشه که ۶
    // صفحه جا بشه». Above six, the window below.
    $whole = $last <= 6;

    // First, last, and the current page with a neighbour either side. Unique
    // and sorted, so a current page sitting next to either end simply folds
    // into it rather than printing the number twice.
    $numbers = $whole
        ? range(1, $last)
        : array_merge([1], range(max(1, $current - 1), min($last, $current + 1)), [$last]);
    $numbers = array_values(array_unique($numbers));
    sort($numbers);

    // What is left once the phone hides the neighbours. It is worked out here
    // rather than in the stylesheet because a gap is a *claim* — «the numbers
    // skip here» — and hiding a number can make that claim true where it was
    // not. `۱ ۲ ۳ ۴ … ۶` losing its 2 and 4 would read `۱ ۳ … ۶`, which says
    // one and three are neighbours. So the skips are counted twice, once for
    // each set, and a gap only the phone needs is drawn only for the phone.
    $onPhone = $whole
        ? $numbers
        : array_values(array_filter($numbers, fn ($page) => $page === 1 || $page === $last || $page === $current));
@endphp
@if ($paginator->hasPages())
<nav class="vp-pages" role="navigation" aria-label="صفحه‌بندی">

    @if ($paginator->onFirstPage())
        <span class="vp-page is-off" aria-disabled="true"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
    @else
        <a class="vp-page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="صفحه قبل"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    @endif

    @php
        $previous = null;
        $previousOnPhone = null;

        // Whether an ellipsis has already been drawn since the last number the
        // phone keeps. A real gap serves both sets, so the phone's own one is
        // only wanted where there is not one standing there already — without
        // this, `۱ … ۶ ۷ ۸ … ۱۳` comes out as `۱ … … ۷` with its 6 hidden.
        $gapSincePhone = false;
    @endphp
    @foreach ($numbers as $page)
        @php
            $skips = $previous !== null && $page > $previous + 1;
            $shownOnPhone = in_array($page, $onPhone, true);
            $skipsOnPhone = $shownOnPhone && $previousOnPhone !== null && $page > $previousOnPhone + 1;
        @endphp

        @if ($skips)
            <span class="vp-page is-gap" aria-hidden="true">…</span>
            @php $gapSincePhone = true; @endphp
        @elseif ($skipsOnPhone && ! $gapSincePhone)
            <span class="vp-page is-gap is-gap-phone" aria-hidden="true">…</span>
        @endif

        @if ($page === $current)
            <span class="vp-page is-on" aria-current="page">{{ fa_number($page) }}</span>
        @else
            <a class="vp-page {{ $shownOnPhone ? '' : 'is-near' }}" href="{{ $paginator->url($page) }}" aria-label="صفحه {{ fa_number($page) }}">{{ fa_number($page) }}</a>
        @endif

        @php
            $previous = $page;

            if ($shownOnPhone) {
                $previousOnPhone = $page;
                $gapSincePhone = false;
            }
        @endphp
    @endforeach

    @if ($paginator->hasMorePages())
        <a class="vp-page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="صفحه بعد"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
    @else
        <span class="vp-page is-off" aria-disabled="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></span>
    @endif

</nav>
@endif
