{{--
    The questions, at the foot of the shop.

    «بنظرم قسمت انتهای سایت هم بزارش این سوالات متداولو خود سوالاتو البته به این
    شکل باشن که تو یه حالت باکس ۲ طرف گرد باشن که روشون زده میشه باکسه به شکل
    کشویی باز بشه» — the questions themselves, not a link to them, each in a
    rounded box that slides open when it is tapped.

    Then, having seen it: «وقتی رو یه سوال جدید زده میشه سوال قبلی کشوش بسته
    بشه», «کشو باید با یه انیمیشن نرم بازو بسته بشه», «کلمه همه سوالات باید
    برداشته بشه» and «اون متن هم باید با شماره تماس و لینک واتسپ بیاد زیر
    عنوان». All four are here: the lead is `partials/faq-lead`, the link out is
    gone, and the script below both animates the drawer and closes the one that
    was open.

    **The script rather than CSS.** `::details-content` with `interpolate-size`
    animates a `<details>` with no script at all, and that is what this band
    shipped with — but it is Safari 18.4 and later, and the phone this was
    reported from opened every box with a snap. `<details name>` would have
    closed the previous one, also natively, and is the same story. So the two
    things asked for are done in eleven lines of script that every browser here
    runs, and the boxes still open without it.

    **Phone only.** `.vp-home-faq` is `display: none` above 991.98 for the same
    reason the story strip is: the desktop page does not gain a band it was
    never designed with, and it stays pixel-identical, which is the standing
    rule and what `check-parity.js` measures at 992 and above. Say the word and
    it shows at every width — the band is built, only the media query holds it.

    **In Laravel and not in the preview page**, which is the one place the two
    copies of this page are allowed to differ: every answer in here is read off
    the application — the delivery charge out of `storefront.checkout`, the
    exchange window out of `storefront.content` — and the static preview has no
    application to read. Parity is unaffected because the band is not painted at
    the widths parity is measured at.

    The eight are `partials/faq-items.blade.php`, the same include `/faq` uses,
    so the shop cannot answer one question two ways. Closed, all of them: this
    is a band among eight and its job is to be small until it is asked.

    Hand-owned: `theme/make-blade.js` does not write this file.
--}}
<section class="vp-home-faq">
    <div class="container th-container">
        <div class="vp-home-faq-panel">
            <h2 class="vp-home-faq-title">سوالات متداول</h2>

            @include('partials.faq-lead')

            <div class="vp-faq vp-faq-boxes">
                @include('partials.faq-items')
            </div>
        </div>
    </div>
</section>

<script>
    (function () {
        var band = document.querySelector('.vp-faq-boxes');
        if (!band) return;

        var boxes = Array.prototype.slice.call(band.querySelectorAll('details'));
        var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var MS = 260;

        // The height of a closed <details> cannot be read, so it is opened
        // first and then animated from nothing. Closing is the same in reverse,
        // and `open` is only taken away once the drawer has finished shutting —
        // take it away first and there is nothing left to animate.
        function slide(box, open) {
            var body = box.querySelector('.vp-faq-a');
            if (!body || calm) { box.open = open; return; }

            window.clearTimeout(box.vpTimer);

            var from = box.open ? body.getBoundingClientRect().height : 0;
            if (open) box.open = true;

            body.style.overflow = 'hidden';
            body.style.height = from + 'px';
            void body.offsetHeight;

            body.style.transition = 'height ' + MS + 'ms cubic-bezier(0.2, 0.7, 0.2, 1)';
            body.style.height = (open ? body.scrollHeight : 0) + 'px';

            box.vpTimer = window.setTimeout(function () {
                body.style.transition = '';
                body.style.height = '';
                body.style.overflow = '';
                if (!open) box.open = false;
            }, MS);
        }

        boxes.forEach(function (box) {
            box.querySelector('summary').addEventListener('click', function (event) {
                // The browser's own toggle would fight the animation, and it is
                // what the keyboard fires too, so this covers Enter and Space.
                event.preventDefault();

                var opening = !box.open;

                boxes.forEach(function (other) {
                    if (other !== box && other.open) slide(other, false);
                });

                slide(box, opening);
            });
        });
    }());
</script>
