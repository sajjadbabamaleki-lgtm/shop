{{--
    Five story circles between the header and the hero.

    Phone only — the CSS hides the strip above 991.98, so the desktop page is
    unchanged. Asked for as a look to try: «۵ تا حالت استوری دایره ای بزار
    بالای هیرو و زیر هدر ببینیم چطور میشه».

    The five are the catalogue's own first five sections, with the photographs
    and names the tiles under the hero already use — the same `$categories` the
    tiles read, so the strip and the tiles cannot describe two different shops.

    Hand-owned: theme/make-blade.js does not generate this file.

    ---------------------------------------------------------------------------
    They open now. «تو بخش فروشگاه باید وقتی رو استوری زده میشه به شکل جزیره ای
    یه تصویر با تایم ۱۵ ثانیه باز بشه یعنی استوری باید کار کنه».

    **Each circle is still a link to its section, and that is the fallback, not
    a leftover.** The script takes the click and opens the viewer instead;
    with it blocked or still loading, tapping a circle goes to the category page
    exactly as it did before this round. Nothing here needs JavaScript to be a
    working control — it needs JavaScript to be a *story*.

    Everything the viewer shows rides on the link as `data-vp-story-*`, so the
    overlay below carries no copy of the catalogue and cannot drift out of step
    with the circles. The photograph is the same file at the same size the ring
    already loads, so opening a story fetches nothing new.
--}}
<section class="vp-stories" aria-label="دسته‌بندی‌ها">
        <div class="vp-stories-row">
            @foreach ($categories->take(5) as $category)
            {{-- No caption under the circle. The name becomes the link's
                 accessible name rather than being dropped — a link whose whole
                 content is a decorative photograph announces nothing. --}}
            <a class="vp-story" href="{{ storefront_route('category', $category) }}" aria-label="{{ $category->name }}"
               data-vp-story
               data-vp-story-src="{{ asset($category->image_path) }}"
               data-vp-story-name="{{ $category->name }}">
                <span class="vp-story-ring">
                    <img src="{{ asset($category->image_path) }}" alt="" loading="lazy">
                </span>
            </a>
            @endforeach
        </div>

        {{-- The viewer. One island, not five — the picture, the name and the
             link are swapped as it advances, and the bar row is built from the
             circles above on first open.

             `hidden` rather than a class, so it is inert to everything —
             assistive technology included — until the script opens it, and so
             the page it sits in is unchanged for anybody who never taps a
             circle. --}}
        <div class="vp-story-view" data-vp-story-view hidden>
            <div class="vp-story-scrim" data-vp-story-close></div>
            <figure class="vp-story-island" role="dialog" aria-modal="true" aria-label="استوری">
                <div class="vp-story-bars" data-vp-story-bars></div>
                <button type="button" class="vp-story-x" data-vp-story-close aria-label="بستن">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                {{-- The photograph goes in as supplied — the files are 420×420
                     and the frame is square, so `contain` never has anything to
                     crop. Same rule the category tiles are made under. --}}
                <img class="vp-story-shot" data-vp-story-shot src="" alt="">
                <figcaption class="vp-story-cap">
                    <a class="vp-story-go" data-vp-story-go href="#"></a>
                </figcaption>
            </figure>
        </div>
    </section>

@push('scripts')
<script>
    (function () {
        var view = document.querySelector("[data-vp-story-view]");
        var shot = view && view.querySelector("[data-vp-story-shot]");
        var barRow = view && view.querySelector("[data-vp-story-bars]");
        var go = view && view.querySelector("[data-vp-story-go]");
        var circles = [].slice.call(document.querySelectorAll("[data-vp-story]"));
        if (!view || !shot || !barRow || !go || !circles.length) return;

        // «تایم ۱۵ ثانیه». One number, used by the timer and written onto the
        // bar as its animation duration, so the two cannot disagree — a bar
        // that empties at a different rate than the story turns is the one
        // thing a viewer like this is judged on.
        var RUN = 15000;
        var at = -1;
        var timer = null;

        circles.forEach(function () {
            var bar = document.createElement("span");
            bar.className = "vp-story-bar";
            bar.appendChild(document.createElement("i"));
            barRow.appendChild(bar);
        });
        var bars = [].slice.call(barRow.children);

        function paint(i) {
            bars.forEach(function (bar, n) {
                var fill = bar.firstChild;
                // Restarting an animation needs the class off, a reflow, and
                // the class on again: re-adding it in the same frame is not a
                // change as far as the engine is concerned, and the bar for a
                // story you have come back to would sit still for 15 seconds.
                fill.className = "";
                void fill.offsetWidth;
                if (n < i) fill.className = "is-done";
                else if (n === i) { fill.style.animationDuration = RUN + "ms"; fill.className = "is-running"; }
            });
        }

        function show(i) {
            if (i < 0 || i >= circles.length) return close();
            at = i;
            var circle = circles[i];
            shot.src = circle.getAttribute("data-vp-story-src");
            shot.alt = circle.getAttribute("data-vp-story-name");
            go.href = circle.getAttribute("href");
            go.textContent = circle.getAttribute("data-vp-story-name");
            paint(i);
            clearTimeout(timer);
            timer = setTimeout(function () { show(at + 1); }, RUN);
        }

        function open(i) {
            view.hidden = false;
            // The page behind must not scroll under the island while a story
            // is up; taken off again on close, and only ever set by this pair.
            document.body.style.overflow = "hidden";
            show(i);
        }

        function close() {
            clearTimeout(timer);
            timer = null;
            at = -1;
            view.hidden = true;
            document.body.style.overflow = "";
            shot.src = "";
        }

        circles.forEach(function (circle, i) {
            circle.addEventListener("click", function (e) {
                // Only take the click when the viewer can actually run. Any
                // modified click — a new tab, a download, the middle button —
                // is left to the browser, because the circle is a real link to
                // a real page and people open those on purpose.
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button) return;
                e.preventDefault();
                open(i);
            });
        });

        view.addEventListener("click", function (e) {
            if (e.target.closest("[data-vp-story-close]")) { close(); return; }
            // The link out is the one thing inside the island that is not a
            // page turn.
            if (e.target.closest("[data-vp-story-go]")) return;
            if (e.target.closest(".vp-story-island")) show(at + 1);
        });

        document.addEventListener("keydown", function (e) {
            if (view.hidden) return;
            if (e.key === "Escape") close();
            else if (e.key === "ArrowLeft") show(at + 1);
            else if (e.key === "ArrowRight") show(at - 1);
        });
    }());
</script>
@endpush
