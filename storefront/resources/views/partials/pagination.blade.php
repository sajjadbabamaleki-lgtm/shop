{{-- Laravel's bundled pagination views are Tailwind markup; this project has
     no Tailwind, so paging gets its own three lines rather than a build step. --}}
@if ($paginator->hasPages())
    <nav class="pagination">
        @if ($paginator->onFirstPage())
            <span class="muted">قبلی</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
        @endif

        <span class="muted num">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
        @else
            <span class="muted">بعدی</span>
        @endif
    </nav>
@endif
