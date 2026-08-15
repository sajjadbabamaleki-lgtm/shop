{{--
    Five story circles between the header and the hero.

    Phone only — the CSS hides the strip above 991.98, so the desktop page is
    unchanged. Asked for as a look to try: «۵ تا حالت استوری دایره ای بزار
    بالای هیرو و زیر هدر ببینیم چطور میشه».

    Hand-owned: theme/make-blade.js does not generate this file.

    ---------------------------------------------------------------------------
    **They are products, not categories.**

    They were the catalogue's first five sections until «اصلا استوری ها نباید
    دسته بندی باشن باید همون ماهیت استوری اینستاگرامو داشته باشن». A story is
    something you look at and can buy — which is what the two buttons under the
    picture do — and a category has no price, no stock and no variant, so
    «افزودن به سبد خرید» on one had nothing to add.

    `$stories` comes from a view composer, not from a controller: this partial is
    included by the listing, where the strip is shown, and by the home page,
    where it is still parked, and a variable passed from one of those is a strip
    that breaks on the other. The composer only offers shoes this branch can
    sell today, so neither button can offer something the checkout would refuse.

    ---------------------------------------------------------------------------
    They open. «به شکل جزیره ای یه تصویر با تایم ۱۵ ثانیه باز بشه یعنی استوری
    باید کار کنه».

    **Each circle is still a link to the product, and that is the fallback, not
    a leftover.** The script takes the click and opens the viewer instead; with
    it blocked, tapping a circle goes to the product page, where both buttons
    exist as real controls anyway. Nothing here needs JavaScript to be a working
    control — it needs JavaScript to be a *story*.

    Everything the viewer shows rides on the link as `data-vp-story-*`, so the
    overlay carries no second copy of the catalogue and cannot drift out of step
    with the circles. The photograph is the same file at the same size the ring
    already loaded, so opening a story fetches nothing new.
--}}
<section class="vp-stories" aria-label="استوری‌ها">
        <div class="vp-stories-row">
            @foreach ($stories as $story)
            @php
                $shot = $story->primaryMedia();
                $addable = $story->addableVariant();
            @endphp
            {{-- No caption under the circle. The name becomes the link's
                 accessible name rather than being dropped — a link whose whole
                 content is a decorative photograph announces nothing. --}}
            <a class="vp-story" href="{{ storefront_route('product', $story) }}" aria-label="{{ $story->title }}"
               data-vp-story
               data-vp-story-src="{{ $shot ? asset($shot->path) : '' }}"
               data-vp-story-name="{{ $story->title }}"
               data-vp-story-product="{{ $story->id }}"
               data-vp-story-variant="{{ $addable?->id }}">
                <span class="vp-story-ring">
                    @if ($shot)<img src="{{ asset($shot->path) }}" alt="" loading="lazy">@endif
                </span>
            </a>
            @endforeach
        </div>

        {{-- The viewer. One island, not five — the picture, the name and the
             two hidden fields are swapped as it advances.

             `hidden` rather than a class, so it is inert to everything —
             assistive technology included — until the script opens it, and so
             the page it sits in is unchanged for anybody who never taps a
             circle.

             **The two forms are real POSTs to the real routes**, with the
             product and the variant carried in hidden fields the script writes.
             They are not siblings by accident: a <form> inside a <form> is
             dropped by the browser, which is the same constraint the search
             line and the filter rail are built around. --}}
        <div class="vp-story-view" data-vp-story-view hidden>
            <div class="vp-story-scrim" data-vp-story-close></div>
            <figure class="vp-story-island" role="dialog" aria-modal="true" aria-label="استوری">
                <div class="vp-story-bars" data-vp-story-bars></div>
                <button type="button" class="vp-story-x" data-vp-story-close aria-label="بستن">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>

                <img class="vp-story-shot" data-vp-story-shot src="" alt="">

                {{-- Under the picture: the two buttons, and nothing else.
                     «دکمه ها باید کنار هم باشن جلوی یکیش قلب باشه یکیش سبد
                     خرید» — one row, a mark in front of each, short labels so
                     the two fit on one line.

                     No price and no name. Both were written here and both were
                     told off: «نیاز به نوشتن قیمت نیست», and then «مگه بهت
                     گفتم اون پایین فقط اون دوتا دکمه باشن چرا اسم مینویسی».
                     The foot of a story is two controls.

                     The name is not lost to anybody who cannot see the
                     photograph — the script writes it onto the picture's `alt`,
                     which is where the name of a picture belongs. --}}
                <figcaption class="vp-story-cap">
                    <div class="vp-story-acts">
                        <form method="post" action="{{ storefront_route('cart.add') }}">
                            @csrf
                            <input type="hidden" name="variant" data-vp-story-variant value="">
                            <button type="submit" class="vp-story-buy">
                                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>سبد خرید
                            </button>
                        </form>

                        {{-- Signed out, this posts to a route the guest
                             redirect sends to `/account/enter` — the shopper's
                             sign-in, because the route is named `account.*`.
                             So the button never lies about what it does; it
                             asks who you are first. --}}
                        <form method="post" action="{{ storefront_route('account.wishlist.add') }}">
                            @csrf
                            <input type="hidden" name="product" data-vp-story-product value="">
                            <button type="submit" class="vp-story-fav">
                                <i class="fa-regular fa-heart" aria-hidden="true"></i>علاقمندی
                            </button>
                        </form>
                    </div>
                </figcaption>
            </figure>
        </div>
    </section>

@push('scripts')
<script>
    (function () {
        var view = document.querySelector("[data-vp-story-view]");
        var circles = [].slice.call(document.querySelectorAll("[data-vp-story]"));
        if (!view || !circles.length) return;

        var shot = view.querySelector("[data-vp-story-shot]");
        var barRow = view.querySelector("[data-vp-story-bars]");
        var variantField = view.querySelector("[data-vp-story-variant]");
        var productField = view.querySelector("[data-vp-story-product]");
        var buy = view.querySelector(".vp-story-buy");
        if (!shot || !barRow || !variantField || !productField || !buy) return;

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
            productField.value = circle.getAttribute("data-vp-story-product") || "";

            // A shoe with no sellable size cannot be added, and a button that
            // posts an empty variant would be a 404 the shopper has to read.
            // The composer only offers purchasable products, so this is a
            // guard rather than an expected state — but it is the difference
            // between an unavailable button and an error page.
            var variant = circle.getAttribute("data-vp-story-variant") || "";
            variantField.value = variant;
            buy.disabled = !variant;

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
            // The caption is controls — a link out and two forms — so a tap in
            // it is never a page turn. Only the picture advances the story,
            // which also means a mis-tap near a button cannot skip a shoe
            // somebody was about to buy.
            if (e.target.closest(".vp-story-cap")) return;
            if (e.target.closest(".vp-story-shot")) show(at + 1);
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
