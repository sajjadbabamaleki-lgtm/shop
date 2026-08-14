{{--
    The strip of square thumbnails, rendered twice on purpose.

    The phone's reference puts it in a «گالری» section under the size chips;
    the desktop's puts it under the photograph, in the left column. Those are
    two different parents in two different columns of `.vp-pdp-body`'s grid, so
    no amount of `order` moves one into the other — the same reason
    `shop.filters` is included twice, once in the phone's top bar and once in
    the sidebar. Exactly one copy is ever shown; the other is `display: none`.

    Two <img> per photograph costs one request, not two: same URL, same cache
    entry, and both are lazy.
--}}
<div class="vp-pdp-thumbs">
    @foreach ($shots as $shot)
        <span @class(['vp-pdp-thumb', 'is-on' => $loop->first])><img src="{{ asset($shot->path) }}" alt="" loading="lazy"></span>
    @endforeach
</div>
