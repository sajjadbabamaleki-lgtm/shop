{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}

    <!-- Jquery -->
    <script src="{{ asset('assets/js/vendor/jquery-3.7.1.min.js') }}"></script>
    <!-- Swiper Js -->
    <script src="{{ asset('assets/js/swiper-bundle.min.js') }}"></script>

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
                if (!phone) return true;
                return !(el.classList.contains("vp-category") || el.closest(".vp-trust-row"));
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
            var band = document.getElementById('vp-dice');
            if (!band) { return; }

            var pair = band.querySelector('[data-dice-pair]');
            var go = band.querySelector('[data-dice-go]');
            if (!pair || !go) { return; }

            var dice = pair.querySelectorAll('.vp-die');
            var url = band.getAttribute('data-dice-url') || '/game/dice';
            var shop = band.getAttribute('data-shop-url') || '/products';
            var busy = false;

            // The dice tumble for this long whatever the network does. An
            // answer that arrives in 40ms and stops them dead is not a throw;
            // a slow one keeps them going until it lands.
            //
            // It was 1100 and «خیلی بیخودو کوتاهه» — so 2400, about as long as
            // a real pair takes to leave a hand, hit the table and settle. The
            // face churns underneath every CHURN ms, which is what makes it a
            // throw rather than a shape wobbling: the pips have to be moving
            // or the eye reads the die as being pushed, not rolled.
            var TUMBLE = 2400;
            var CHURN = 90;
            var LAND = 260;
            // A double six is the whole point of the band, so it is allowed to
            // be looked at. «وقتی جفت شیش میشه باید ۲ ثانیه رو جفت شیش بمونه
            // بعد این پاپاپ باز بشه» — the card covers the dice, and opening
            // it the instant they stop means nobody ever sees what they threw.
            //
            // Then «اون مکث نصف بشه»: two seconds was long enough to read as
            // the page having stalled rather than as a pause for effect, which
            // is the failure this number can have in either direction. One
            // second still clears the landing animation (LAND + 60 = 320ms) by
            // a wide margin, so the dice are settled and still for two thirds
            // of the wait before the card arrives.
            var GLOAT = 1000;

            function fa(text) {
                return String(text).replace(/[0-9]/g, function (d) {
                    return String.fromCharCode(1776 + Number(d));
                });
            }

            function csrf() {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return band.getAttribute('data-dice-token') || (meta ? meta.getAttribute('content') : '');
            }

            // The button is spent after one throw, so it is replaced by the
            // sentence rather than left sitting there disabled.
            function spend(text) {
                var line = document.createElement('p');
                line.className = 'vp-dice-done';
                line.textContent = text;
                if (go.parentNode) { go.parentNode.replaceChild(line, go); }
            }

            // A throw that never reached the server is not a throw. The button
            // comes back and the reason goes under it — spending it here would
            // charge somebody their one go for a dropped packet.
            function excuse(text) {
                var line = band.querySelector('.vp-dice-done');

                if (!line) {
                    line = document.createElement('p');
                    line.className = 'vp-dice-done';
                    go.parentNode.insertBefore(line, go.nextSibling);
                }

                line.textContent = text;
                go.disabled = false;
                busy = false;
            }

            function copy(text, button) {
                function done() {
                    button.textContent = 'کپی شد';
                    setTimeout(function () { button.textContent = 'کپی'; }, 1600);
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done, function () {});
                    return;
                }

                // http, or an older browser. execCommand is deprecated and is
                // the only thing that works there.
                var box = document.createElement('textarea');
                box.value = text;
                box.setAttribute('readonly', 'readonly');
                box.style.position = 'fixed';
                box.style.insetBlockStart = '-1000px';
                document.body.appendChild(box);
                box.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(box);
            }

            function confetti(into) {
                // The page's gold, its ink and its grey. Confetti in colours
                // the site does not otherwise own is how the band ended up
                // looking like somebody else's site the first time.
                var colours = ['#DAB226', '#EFC94F', '#A08119', '#101111', '#E7E9EC'];
                var field = document.createElement('div');
                field.className = 'vp-confetti';

                for (var i = 0; i < 24; i++) {
                    var piece = document.createElement('i');
                    piece.style.insetInlineStart = (Math.random() * 100) + '%';
                    piece.style.background = colours[i % colours.length];
                    piece.style.animationDelay = (Math.random() * 0.8) + 's';
                    piece.style.animationDuration = (2.2 + Math.random() * 1.2) + 's';
                    field.appendChild(piece);
                }

                into.appendChild(field);
            }

            function prize(answer) {
                var scrim = document.createElement('div');
                scrim.className = 'vp-prize-scrim';
                scrim.setAttribute('role', 'dialog');
                scrim.setAttribute('aria-modal', 'true');
                scrim.setAttribute('aria-label', 'جایزه تاس شانس');

                var card = document.createElement('div');
                card.className = 'vp-prize';
                scrim.appendChild(card);

                var shut = document.createElement('button');
                shut.type = 'button';
                shut.className = 'vp-prize-shut';
                shut.setAttribute('aria-label', 'بستن');
                shut.textContent = '×';
                card.appendChild(shut);

                // The shop's own star, not a disc — the hero's mark and the
                // sale cards' mark are this same shape, and the path and the
                // studs are interpolated from the very constants that draw
                // them, so the three can never drift apart. The figure is
                // Latin on purpose, the way the hero's «25% OFF» is.
                var badge = document.createElement('div');
                badge.className = 'vp-prize-badge';
                badge.innerHTML = '<svg class="vp-prize-burst" viewBox="0 0 150 150" aria-hidden="true">'
                    + '<defs><linearGradient id="vp-prize-burst-gold" x1="0" y1="0" x2="0" y2="1">'
                    + '<stop offset="0%" stop-color="#C0972F"></stop><stop offset="100%" stop-color="#E3B54A"></stop>'
                    + '</linearGradient></defs>'
                    + '<g class="vp-burst-star">'
                    + '<path fill="url(#vp-prize-burst-gold)" d="M 75,3 C 80.73,3 85.7,14.57 92.19,16.47 C 98.67,18.38 109.11,11.33 113.93,14.43 C 118.75,17.53 116.67,29.94 121.1,35.05 C 125.53,40.16 138.11,39.88 140.49,45.09 C 142.87,50.3 134.42,59.63 135.38,66.32 C 136.34,73.01 147.08,79.58 146.27,85.25 C 145.45,90.92 133.3,94.19 130.49,100.34 C 127.68,106.49 133.17,117.82 129.41,122.15 C 125.66,126.48 113.67,122.66 107.98,126.32 C 102.29,129.97 100.78,142.47 95.28,144.08 C 89.79,145.7 81.76,136 75,136 C 68.24,136 60.21,145.7 54.72,144.08 C 49.22,142.47 47.71,129.97 42.02,126.32 C 36.33,122.66 24.34,126.48 20.59,122.15 C 16.83,117.82 22.32,106.49 19.51,100.34 C 16.7,94.19 4.55,90.92 3.73,85.25 C 2.92,79.58 13.66,73.01 14.62,66.32 C 15.58,59.63 7.13,50.3 9.51,45.09 C 11.89,39.88 24.47,40.16 28.9,35.05 C 33.33,29.94 31.25,17.53 36.07,14.43 C 40.89,11.33 51.33,18.38 57.81,16.47 C 64.3,14.57 69.27,3 75,3 Z"></path>'
                    + '<circle class="vp-burst-stud" cx="75.00" cy="19.50" r="2.4"></circle><circle class="vp-burst-stud" cx="105.01" cy="28.31" r="2.4"></circle><circle class="vp-burst-stud" cx="125.48" cy="51.94" r="2.4"></circle><circle class="vp-burst-stud" cx="129.94" cy="82.90" r="2.4"></circle><circle class="vp-burst-stud" cx="116.94" cy="111.34" r="2.4"></circle><circle class="vp-burst-stud" cx="90.64" cy="128.25" r="2.4"></circle><circle class="vp-burst-stud" cx="59.36" cy="128.25" r="2.4"></circle><circle class="vp-burst-stud" cx="33.06" cy="111.34" r="2.4"></circle><circle class="vp-burst-stud" cx="20.06" cy="82.90" r="2.4"></circle><circle class="vp-burst-stud" cx="24.52" cy="51.94" r="2.4"></circle><circle class="vp-burst-stud" cx="44.99" cy="28.31" r="2.4"></circle>'
                    + '</g>'
                    + '<text class="vp-burst-num" x="75" y="72"></text>'
                    + '<text class="vp-burst-off" x="75" y="98">OFF</text>'
                    + '</svg>';
                badge.querySelector('.vp-burst-num').textContent = answer.percent + '%';
                card.appendChild(badge);

                var what = document.createElement('p');
                what.className = 'vp-prize-what';
                what.textContent = 'جفت شیش آوردی 🎲';
                card.appendChild(what);

                var title = document.createElement('h3');
                title.className = 'vp-prize-title';
                title.textContent = answer.fresh ? 'تبریک! 🎉' : 'جایزه‌ات همین‌جاست';
                card.appendChild(title);

                var say = document.createElement('p');
                say.className = 'vp-prize-say';
                say.textContent = fa(answer.percent) + '٪ تخفیف روی سفارشت، برای شما فعال شد.';
                card.appendChild(say);

                var box = document.createElement('div');
                box.className = 'vp-prize-code';
                var label = document.createElement('span');
                label.textContent = 'کد تخفیف';
                var value = document.createElement('b');
                value.textContent = answer.code;
                var take = document.createElement('button');
                take.type = 'button';
                take.className = 'vp-prize-copy';
                take.textContent = 'کپی';
                take.addEventListener('click', function () { copy(answer.code, take); });
                box.appendChild(label);
                box.appendChild(value);
                box.appendChild(take);
                card.appendChild(box);

                var use = document.createElement('a');
                use.className = 'vp-prize-go';
                use.href = shop;
                use.textContent = 'استفاده از تخفیف';
                card.appendChild(use);

                var when = document.createElement('p');
                when.className = 'vp-prize-when';
                when.textContent = '⏱ اعتبار کد: ' + fa(answer.hours) + ' ساعت';
                card.appendChild(when);

                // Around the card, not across it: inside the card itself
                // these twenty-four opaque flakes landed on the code and
                // the title.
                confetti(scrim);

                function shutIt() {
                    if (scrim.parentNode) { scrim.parentNode.removeChild(scrim); }
                    document.body.classList.remove('vp-prize-open');
                    document.removeEventListener('keydown', onKey);
                    go.focus && go.focus();
                }

                function onKey(event) {
                    if (event.key === 'Escape') { shutIt(); }
                }

                shut.addEventListener('click', shutIt);
                scrim.addEventListener('click', function (event) {
                    if (event.target === scrim) { shutIt(); }
                });
                document.addEventListener('keydown', onKey);

                // The corner's WhatsApp button is fixed and outranks nothing,
                // so without this it sits on the scrim as a green square over
                // the celebration. tweaks.css hides it on this class.
                document.body.classList.add('vp-prize-open');
                document.body.appendChild(scrim);
                shut.focus();
            }

            // While the dice are in the air their faces are meaningless, so
            // they may as well churn. Nothing here decides anything — the
            // server has already decided, or is about to — and land() writes
            // the real faces over whatever this left behind.
            var churn = null;

            function startChurn() {
                stopChurn();
                churn = setInterval(function () {
                    for (var i = 0; i < dice.length; i++) {
                        dice[i].setAttribute('data-face', String(1 + Math.floor(Math.random() * 6)));
                    }
                }, CHURN);
            }

            function stopChurn() {
                if (churn) { clearInterval(churn); churn = null; }
            }

            function land(answer) {
                stopChurn();
                pair.classList.remove('is-rolling');

                if (answer.dice && answer.dice.length === 2) {
                    for (var i = 0; i < dice.length && i < 2; i++) {
                        dice[i].setAttribute('data-face', String(answer.dice[i]));
                    }
                }

                // The settle. Removed afterwards so the next throw can play it
                // again — an animation that is already on an element does not
                // restart when the class is merely still there.
                pair.classList.add('is-landing');
                setTimeout(function () { pair.classList.remove('is-landing'); }, LAND + 60);

                if (answer.won && answer.code) {
                    // The code goes on the band as well as in the card, so
                    // closing the card does not take it away.
                    spend('جفت شیش! کد تخفیفت: ' + answer.code);
                    // Two seconds on the two sixes before the card covers
                    // them. Winning is the moment; it should be seen.
                    setTimeout(function () { prize(answer); }, GLOAT);
                    return;
                }

                if (answer.won) {
                    // Won, but the code has been spent or has expired.
                    spend('قبلاً بازی کردی و برده بودی؛ کد تخفیفت دیگر معتبر نیست.');
                    return;
                }

                // A loss with a throw still owed is not the end of the game,
                // so the button stays and the line goes under it. Ending it
                // here is what «۲ شانس» would look like as one.
                if (answer.left > 0) {
                    excuse(answer.left === 1
                        ? 'این بار جفت شیش نیامد؛ یک شانس دیگر داری.'
                        : 'این بار جفت شیش نیامد؛ ' + fa(answer.left) + ' شانس دیگر داری.');
                    return;
                }

                spend(answer.fresh
                    ? 'این بار هم جفت شیش نیامد. شانس‌هایت تمام شد.'
                    : 'شانس‌هایت تمام شده بود.');
            }

            go.addEventListener('click', function () {
                if (busy) { return; }
                busy = true;

                go.disabled = true;
                pair.classList.remove('is-landing');
                pair.classList.add('is-rolling');
                startChurn();

                var thrown = Date.now();
                var landed = function (answer) {
                    var left = Math.max(0, TUMBLE - (Date.now() - thrown));
                    setTimeout(function () { land(answer); }, left);
                };

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        if (!response.ok) {
                            throw new Error(body.error || (response.status === 429
                                ? 'کمی صبر کن و دوباره بزن.'
                                : 'الان نشد؛ کمی بعد دوباره امتحان کن.'));
                        }
                        return body;
                    });
                }).then(landed, function (error) {
                    var left = Math.max(0, TUMBLE - (Date.now() - thrown));
                    setTimeout(function () {
                        stopChurn();
                        pair.classList.remove('is-rolling');
                        excuse(error && error.message ? error.message : 'الان نشد؛ کمی بعد دوباره امتحان کن.');
                    }, left);
                });
            });
        })();
    </script>
    <script>
        (function () {
            var scrim = null;
            var camefrom = null;

            function shut() {
                if (!scrim) { return; }
                if (scrim.parentNode) { scrim.parentNode.removeChild(scrim); }
                scrim = null;
                document.body.classList.remove("vp-soon-open");
                document.removeEventListener("keydown", onKey);
                if (camefrom && camefrom.focus) { camefrom.focus(); }
            }

            function onKey(event) {
                if (event.key === "Escape") { shut(); }
            }

            function show(name) {
                shut();

                scrim = document.createElement("div");
                scrim.className = "vp-soon-scrim";
                scrim.setAttribute("role", "dialog");
                scrim.setAttribute("aria-modal", "true");
                scrim.setAttribute("aria-label", "به‌زودی");

                var card = document.createElement("div");
                card.className = "vp-soon-card";
                scrim.appendChild(card);

                var x = document.createElement("button");
                x.type = "button";
                x.className = "vp-soon-shut";
                x.setAttribute("aria-label", "بستن");
                x.textContent = "×";
                card.appendChild(x);

                var mark = document.createElement("span");
                mark.className = "vp-empty-mark";
                mark.setAttribute("aria-hidden", "true");
                mark.innerHTML = '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="17" fill="none" stroke="currentColor" stroke-width="3"></circle><path d="M24 14 V25 L31 29" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
                card.appendChild(mark);

                var say = document.createElement("p");
                say.className = "vp-empty-say";
                say.textContent = "«" + name + "» به‌زودی راه‌اندازی می‌شود.";
                card.appendChild(say);

                var ok = document.createElement("button");
                ok.type = "button";
                ok.className = "vp-soon-ok";
                ok.textContent = "باشه";
                card.appendChild(ok);

                x.addEventListener("click", shut);
                ok.addEventListener("click", shut);
                scrim.addEventListener("click", function (event) {
                    if (event.target === scrim) { shut(); }
                });
                document.addEventListener("keydown", onKey);

                document.body.classList.add("vp-soon-open");
                document.body.appendChild(scrim);
                ok.focus();
            }

            // **Capture, not bubble.** The template's mobile-menu plugin
            // binds `stopPropagation` to the drawer and to every div inside
            // it, to keep a tap in the panel from reaching its
            // close-on-outside-click handler. A listener on the document
            // therefore never hears a tap on a drawer row: the first version
            // of this bubbled, worked on the tiles and the listing strip, and
            // navigated straight past the card in the drawer. Capture runs
            // before any of that and cannot be stopped from below.
            document.addEventListener("click", function (event) {
                var target = event.target;
                if (!target || !target.closest) { return; }

                var link = target.closest("[data-vp-soon]");
                if (!link) { return; }

                // A modified click is somebody asking for the page in a tab
                // of their own, and the page exists and says the same thing.
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.button) { return; }

                event.preventDefault();
                camefrom = link;

                // Tapped inside the phone drawer, which is a full-screen
                // panel: shut it with its own button rather than laying the
                // card over it, so closing the card does not leave the
                // visitor back in a menu they had finished with.
                var drawer = link.closest(".th-menu-wrapper");
                var close = drawer && drawer.querySelector(".th-menu-toggle");
                if (close) { close.click(); }

                show(link.getAttribute("data-vp-soon"));
            }, true);
        })();
    </script>
    <script>
        (function () {
            var $ = window.jQuery;
            var wrap = document.querySelector(".sticky-wrapper");
            var header = wrap && wrap.closest(".th-header");
            var menu = wrap && wrap.querySelector(".menu-area");
            var catMenu = document.querySelector(".category-menu");
            // The corner button. It was a scroll-to-top ring once
            // and is the WhatsApp link now; the name says which, because a
            // variable called toTop that shows a chat button is a trap.
            // Both corner buttons: the support square and the WhatsApp
            // link. They show together — one appearing without the other
            // reads as a fault rather than as two controls.
            var corner = document.querySelectorAll(".vp-whatsapp, .vp-support-fab");
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
                for (var c = 0; c < corner.length; c++) {
                    corner[c].classList.toggle("show", y > 0 && !atFoot);
                }
                if (screenEl) {
                    // The original test, unchanged: the footer is left
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
    <script>
        if ("serviceWorker" in navigator && window.isSecureContext) {
            window.addEventListener("load", function () {
                navigator.serviceWorker.register("/sw.js").catch(function () {});
            });
        }
    </script>
