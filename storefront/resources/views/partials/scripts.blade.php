{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}

    <!-- Jquery -->
    <script src="{{ asset('assets/js/vendor/jquery-3.7.1.min.js') }}"></script>
    <!-- Swiper Js -->
    <script src="{{ asset('assets/js/swiper-bundle.min.js') }}"></script>

    <!-- Bootstrap -->
    <script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
    <!-- Magnific Popup -->
    <script src="{{ asset('assets/js/jquery.magnific-popup.min.js') }}"></script>
    <!-- Counter Up -->
    <script src="{{ asset('assets/js/jquery.counterup.min.js') }}"></script>
    <!-- Tilt -->
    <script src="{{ asset('assets/js/tilt.jquery.min.js') }}"></script>
    <!-- Isotope Filter -->
    <script src="{{ asset('assets/js/imagesloaded.pkgd.min.js') }}"></script>
    <script src="{{ asset('assets/js/isotope.pkgd.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery-ui.min.js') }}"></script>
    <!-- nice select -->
    <script src="{{ asset('assets/js/nice-select.min.js') }}"></script>

    <!-- Gsap -->
    <script src="{{ asset('assets/js/gsap.min.js') }}"></script>

    <!-- Main Js File -->
    <script src="{{ asset('assets/js/main.js') }}"></script>

    @stack('scripts')
    
    <script>
        (function () {
            var items = document.querySelectorAll(".vp-category, .vp-trust-row .feature-card, .th-product, .vp-deal, .vp-best, .blog-card, .sec-title");
            if (!items.length || !("IntersectionObserver" in window)) return;
            var phone = window.matchMedia("(max-width: 991.98px)").matches;
            items = Array.prototype.filter.call(items, function (el) {
                return !(phone && el.classList.contains("vp-category"));
            });
            if (!items.length) return;
            items.forEach(function (el) {
                el.classList.add("vp-enter");
                var row = el.closest(".row") || el.parentElement;
                var peers = row ? row.children : [];
                var i = 0;
                for (var n = 0; n < peers.length; n++) {
                    if (peers[n] === el || peers[n].contains(el)) { i = n; break; }
                }
                var count = peers.length;
                var delay = Math.min(i, 5) * 60;
                if (el.classList.contains("vp-category")) {
                    var half = count / 2;
                    var fromRight = i < half;
                    el.style.setProperty("--enter-x", (fromRight ? 56 : -56) + "px");
                    delay = (fromRight ? i : count - 1 - i) * 70;
                }
                el.style.setProperty("--enter-delay", delay + "ms");
            });
            var seen = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add("vp-entered");
                    seen.unobserve(entry.target);
                });
            }, { rootMargin: "0px 0px -80px 0px", threshold: 0.12 });
            items.forEach(function (el) { seen.observe(el); });
        }());
    </script>
    <script>
        (function () {
            var strip = document.querySelector(".vp-category-row");
            if (!strip) return;
            if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
            var tiles = strip.querySelectorAll(".col");
            if (tiles.length < 2) return;
            var touched = 0;
            ["pointerdown", "touchstart", "wheel", "keydown"].forEach(function (ev) {
                strip.addEventListener(ev, function () { touched = Date.now(); }, { passive: true });
            });
            function step() {
                var room = strip.scrollWidth - strip.clientWidth;
                if (room < 4) return;
                if (Date.now() - touched < 6000) return;
                if (document.hidden) return;
                var a = tiles[0].getBoundingClientRect();
                var b = tiles[1].getBoundingClientRect();
                var pitch = Math.abs(a.left - b.left);
                if (!pitch) return;
                var atEnd = strip.scrollLeft <= -(room - pitch / 2);
                strip.scrollTo({ left: atEnd ? 0 : strip.scrollLeft - pitch, behavior: "smooth" });
            }
            setInterval(step, 6000);
        }());
    </script>
    <script>
        (function () {
            var $ = window.jQuery;
            var wrap = document.querySelector(".sticky-wrapper");
            var header = wrap && wrap.closest(".th-header");
            var menu = wrap && wrap.querySelector(".menu-area");
            var catMenu = document.querySelector(".category-menu");
            // The corner button. It was the template's scroll-to-top ring
            // and is the WhatsApp link now; the name says which, because a
            // variable called toTop that shows a chat button is a trap.
            var corner = document.querySelector(".vp-whatsapp");
            var ring = null;
            var screenEl = document.querySelector(".th-screen");
            if (!$ || !wrap || !header) return;

            $(window).off("scroll");

            var ringLen = 0;
            if (ring) {
                ringLen = ring.getTotalLength();
                ring.style.strokeDasharray = ringLen + " " + ringLen;
                // The ring is written once a frame; a transition on it only
                // means every one of those writes is also a transition.
                ring.style.transition = ring.style.WebkitTransition = "none";
            }

            // Everything the frame needs to know about the page's size.
            // Taken here so the scroll path never reads layout.
            var docH = 0, winH = 0, screenTop = 0, screenH = 0, reserve = 0;
            function remeasure() {
                winH = window.innerHeight;
                docH = document.documentElement.scrollHeight;
                if (screenEl) {
                    var r = screenEl.getBoundingClientRect();
                    screenTop = r.top + window.pageYOffset;
                    screenH = r.height;
                }
                // The island's flow height: its own box plus the top margin
                // that collapses through the wrapper. Only meaningful while
                // it is still in the flow.
                if (menu && !stuck) {
                    reserve = menu.offsetHeight +
                        (parseFloat(getComputedStyle(menu).marginTop) || 0);
                }
            }

            var stuck = false;
            function apply(y) {
                var nowStuck = y > 500;
                if (nowStuck !== stuck) {
                    stuck = nowStuck;
                    wrap.classList.toggle("sticky", stuck);
                    if (catMenu) catMenu.classList.toggle("close-category", stuck);
                    header.style.setProperty("--vp-sticky-reserve",
                        (stuck ? reserve : 0) + "px");
                }
                if (ring) {
                    var run = docH - winH;
                    ring.style.strokeDashoffset =
                        run > 0 ? ringLen - (y * ringLen / run) : ringLen;
                }
                // «آیکون واتسپ وقتی اولین اسکرول شروع میشه باید ظاهر بشه»,
                // so the threshold is the first pixel rather than the
                // ring's old 50. The button fades in on its own transition,
                // so "the first scroll" is where the fade starts, not where
                // it finishes.
                //
                // And out again at the footer: «اسکرول وقتی به فوتر میرسه
                // اون آیکون شناور واتسپ باید حذف بشه». The footer carries
                // its own WhatsApp mark, so down there the floating one is
                // a second copy of a button sitting right behind it — and
                // on a phone it lands on top of the copyright line. The
                // test is the footer's top crossing the bottom of the
                // viewport; screenTop is already measured for the band
                // below, so this costs no layout read.
                var atFoot = screenEl ? (y + winH > screenTop) : false;
                if (corner) corner.classList.toggle("show", y > 0 && !atFoot);
                if (screenEl) {
                    // The template's own test, unchanged: the footer is left
                    // alone while it sits whole in the viewport, allowing 200.
                    var whole = screenTop + screenH - 200 <= y + winH && screenTop >= y;
                    screenEl.classList.toggle("th-visible", !whole);
                }
            }

            var queued = false;
            window.addEventListener("scroll", function () {
                if (queued) return;
                queued = true;
                requestAnimationFrame(function () {
                    queued = false;
                    apply(window.pageYOffset);
                });
            }, { passive: true });

            // The page keeps growing after load — images arrive, the footer
            // animates its own width. A ResizeObserver is told about that
            // after layout has already run, so keeping the cache fresh this
            // way costs nothing; polling it from the scroll path would not.
            window.addEventListener("resize", remeasure);
            if ("ResizeObserver" in window) {
                var ro = new ResizeObserver(remeasure);
                ro.observe(document.body);
                if (screenEl) ro.observe(screenEl);
            }
            window.addEventListener("load", remeasure);

            remeasure();
            apply(window.pageYOffset);

            // The shop's price slider writes into the «تا» box as it moves,
            // so a drag and a typed number are the same filter arriving by
            // different hands. It sits here, after the scroll handler and
            // inside the same guard-free tail, rather than in the middle of
            // that handler — the first attempt spliced it into the frame
            // function and left the footer's own block inside an
            // `if (false)`, which nothing would have reported.
            //
            // The range carries no name and submits nothing on its own — a
            // named one would post a maximum on every apply, including one
            // nobody dragged. So this handler is not decoration: without it
            // the slider does nothing at all and the two boxes are the whole
            // filter. It also repaints the track, which is gold to the left
            // of the thumb and white to its right.
            var priceRange = document.querySelector("[data-vp-price-range]");
            var priceMax = document.querySelector("[data-vp-price-max]");
            // The shop's filter sheets close on their scrim and on their
            // own X. The tab that opened one is behind the scrim, so without
            // this there is no way back out except the browser's.
            document.addEventListener("click", function (e) {
                var hit = e.target.closest && e.target.closest("[data-vp-sheet-close]");
                if (!hit) return;
                var sheet = hit.closest("details");
                if (sheet) { e.preventDefault(); sheet.open = false; }
            });

            if (priceRange && priceMax) {
                priceRange.addEventListener("input", function () {
                    priceMax.value = Number(priceRange.value).toLocaleString("fa-IR");
                    var lo = Number(priceRange.min), hi = Number(priceRange.max);
                    var pct = hi > lo ? (Number(priceRange.value) - lo) / (hi - lo) * 100 : 100;
                    priceRange.style.setProperty("--vp-fill", pct + "%");
                });
            }
        }());
    </script>
