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
            var $ = window.jQuery;
            var wrap = document.querySelector(".sticky-wrapper");
            var header = wrap && wrap.closest(".th-header");
            var menu = wrap && wrap.querySelector(".menu-area");
            var catMenu = document.querySelector(".category-menu");
            var toTop = document.querySelector(".scroll-top");
            var ring = toTop && toTop.querySelector("path");
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
                if (toTop) toTop.classList.toggle("show", y > 50);
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
        }());
    </script>
