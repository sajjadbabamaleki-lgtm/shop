{{--
    Pagination, in the page's own hand.

    Laravel ships Tailwind and Bootstrap markup for this; both would arrive
    with a second set of opinions about type and colour, and the numbers would
    come out in latin digits on a page that writes every other number in
    Persian. This is small enough to own.

    The arrows point the way the language reads: on an RTL page "next" is to
    the left, so the chevrons are set by direction rather than hard-coded.
--}}
@if ($paginator->hasPages())
<nav class="vp-pages" role="navigation" aria-label="صفحه‌بندی">

    @if ($paginator->onFirstPage())
        <span class="vp-page is-off" aria-disabled="true"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
    @else
        <a class="vp-page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="صفحه قبل"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    @endif

    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="vp-page is-gap" aria-hidden="true">{{ $element }}</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="vp-page is-on" aria-current="page">{{ fa_number($page) }}</span>
                @else
                    <a class="vp-page" href="{{ $url }}">{{ fa_number($page) }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    @if ($paginator->hasMorePages())
        <a class="vp-page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="صفحه بعد"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
    @else
        <span class="vp-page is-off" aria-disabled="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></span>
    @endif

</nav>
@endif
