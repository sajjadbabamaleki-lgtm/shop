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

    **`live` says whether these are pictures or a stand-in.** Where the product
    really has several photographs each thumbnail is an anchor to its own frame
    in the strip, and clicking one turns the page. Where the row is the single
    photograph repeated — `placeholders.colorway_shots`, still there while the
    catalogue's own photography is coming — they stay what they were: spans,
    with nothing to link to and nothing to say.
--}}
<div class="vp-pdp-thumbs">
    @foreach ($shots as $shot)
        @if ($live ?? false)
            <a @class(['vp-pdp-thumb', 'is-on' => $loop->first])
               data-vp-shot="{{ $loop->index }}"
               href="#vp-shot-{{ $product->id }}-{{ $loop->index }}"
               aria-label="عکس {{ fa_number($loop->iteration) }}"><img src="{{ asset($shot->path) }}" alt="" loading="lazy"></a>
        @else
            <span @class(['vp-pdp-thumb', 'is-on' => $loop->first])><img src="{{ asset($shot->path) }}" alt="" loading="lazy"></span>
        @endif
    @endforeach
</div>

@once
    @if ($live ?? false)
        {{-- The two things the strip cannot do on its own.

             Swiping already works — `scroll-snap` is the browser's, and the
             anchors under it work with the script removed. What this adds is
             the jump: an anchor inside a scroller drags the whole page up to
             meet it, which on a phone throws the photograph off the screen it
             was tapped from. And it lights the mark that is actually showing,
             which nothing in CSS can follow.

             RTL is why the arithmetic looks odd: this page is `dir="rtl"`, and
             a right-to-left scroller counts `scrollLeft` from 0 at the right
             edge downwards into negatives. `Math.abs` is the whole fix, and
             `Math.round` rather than a threshold so a half-turned strip lights
             the one it is nearest. --}}
        <script>
            (function () {
                var strip = document.querySelector(".vp-pdp-frames");

                if (!strip) {
                    return;
                }

                var marks = Array.prototype.slice.call(document.querySelectorAll("[data-vp-shot]"));
                var rtl = getComputedStyle(strip).direction === "rtl";

                function step() {
                    return strip.clientWidth || 1;
                }

                marks.forEach(function (mark) {
                    mark.addEventListener("click", function (event) {
                        event.preventDefault();

                        var index = Number(mark.getAttribute("data-vp-shot")) || 0;

                        strip.scrollTo({ left: index * step() * (rtl ? -1 : 1), behavior: "smooth" });
                    });
                });

                strip.addEventListener("scroll", function () {
                    var index = Math.round(Math.abs(strip.scrollLeft) / step());

                    marks.forEach(function (mark) {
                        mark.classList.toggle("is-on", Number(mark.getAttribute("data-vp-shot")) === index);
                    });
                }, { passive: true });
            }());
        </script>
    @endif
@endonce
