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
    usable width holds seven of them and no more, which is what the two rules
    below spend:

      phone    ‹ 1 … 7 … 13 ›        7 cells, 314px
      desktop  ‹ 1 … 6 7 8 … 13 ›    9 cells

    The difference is CSS, not markup — the neighbours carry `is-near` and are
    display:none under 576px. That works because dropping them never leaves a
    wrong gap: a gap is drawn wherever the numbers skip, and hiding 6 and 8
    from `1 … 6 7 8 … 13` leaves `1 … 7 … 13`, which is still true.
--}}
@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    // First, last, and the current page with a neighbour either side. Unique
    // and sorted, so a current page sitting next to either end simply folds
    // into it rather than printing the number twice.
    $numbers = array_merge([1], range(max(1, $current - 1), min($last, $current + 1)), [$last]);
    $numbers = array_values(array_unique($numbers));
    sort($numbers);
@endphp
@if ($paginator->hasPages())
<nav class="vp-pages" role="navigation" aria-label="صفحه‌بندی">

    @if ($paginator->onFirstPage())
        <span class="vp-page is-off" aria-disabled="true"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
    @else
        <a class="vp-page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="صفحه قبل"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    @endif

    @php $previous = null; @endphp
    @foreach ($numbers as $page)
        @if ($previous !== null && $page > $previous + 1)
            <span class="vp-page is-gap" aria-hidden="true">…</span>
        @endif

        @if ($page === $current)
            <span class="vp-page is-on" aria-current="page">{{ fa_number($page) }}</span>
        @else
            <a class="vp-page {{ $page === 1 || $page === $last ? '' : 'is-near' }}" href="{{ $paginator->url($page) }}" aria-label="صفحه {{ fa_number($page) }}">{{ fa_number($page) }}</a>
        @endif

        @php $previous = $page; @endphp
    @endforeach

    @if ($paginator->hasMorePages())
        <a class="vp-page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="صفحه بعد"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
    @else
        <span class="vp-page is-off" aria-disabled="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></span>
    @endif

</nav>
@endif
