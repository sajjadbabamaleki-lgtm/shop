/*
 * The panel's dates, in the calendar this shop actually keeps.
 *
 * «تاریخ ها هم باید ایرانی بشه». Every date the panel *prints* was already
 * Jalali — `fa_date()` formats through ICU's Persian calendar. The five that
 * were not were the ones you type into: `<input type="date">` hands the job
 * to the browser, and the browser draws whatever calendar the device is set
 * to, which here means Gregorian.
 *
 * **The real field is never replaced, only hidden.** Each input keeps its
 * name, its value format and its position in the form, so nothing on the
 * server changes and nothing had to be re-validated: `DateRange::parse`, the
 * discount window, the publish date and the shipped-at all still receive the
 * same `YYYY-MM-DD` they always did. This file draws a Jalali field beside
 * the hidden one and writes the Gregorian value back into it.
 *
 * With the script off nothing happens at all: `.vp-adm-jhidden` is a class this
 * file adds, so an input it never reached is still an ordinary date field in
 * the browser's calendar — which is what the panel had until today. Nothing
 * becomes unreachable.
 *
 * The conversion is the standard algorithm, not a library: two functions of
 * about a dozen lines each, exact for every date these forms can hold.
 */
(function () {
    'use strict';

    var DAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
    var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                  'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    function div(a, b) { return Math.floor(a / b); }

    /* Gregorian → Jalali. Returns [year, month, day], months 1-12. */
    function toJalali(gy, gm, gd) {
        var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var gy2 = (gm > 2) ? (gy + 1) : gy;
        var days = 355666 + (365 * gy) + div(gy2 + 3, 4) - div(gy2 + 99, 100)
                 + div(gy2 + 399, 400) + gd + g_d_m[gm - 1];
        var jy = -1595 + (33 * div(days, 12053));
        days %= 12053;
        jy += 4 * div(days, 1461);
        days %= 1461;
        if (days > 365) { jy += div(days - 1, 365); days = (days - 1) % 365; }
        var jm = (days < 186) ? 1 + div(days, 31) : 7 + div(days - 186, 30);
        var jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
        return [jy, jm, jd];
    }

    /* Jalali → Gregorian. Returns [year, month, day]. */
    function toGregorian(jy, jm, jd) {
        jy += 1595;
        var days = -355668 + (365 * jy) + (div(jy, 33) * 8) + div((jy % 33) + 3, 4)
                 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        var gy = 400 * div(days, 146097);
        days %= 146097;
        if (days > 36524) {
            gy += 100 * div(--days, 36524);
            days %= 36524;
            if (days >= 365) days++;
        }
        gy += 4 * div(days, 1461);
        days %= 1461;
        if (days > 365) { gy += div(days - 1, 365); days = (days - 1) % 365; }
        var gd = days + 1;
        var leap = ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) ? 1 : 0;
        var sal_a = [0, 31, 28 + leap, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm = 0;
        while (gm < 13 && gd > sal_a[gm]) { gd -= sal_a[gm]; gm++; }
        return [gy, gm, gd];
    }

    function jLength(jy, jm) {
        if (jm <= 6) return 31;
        if (jm <= 11) return 30;
        // Esfand: 30 in a leap year. A Jalali year is leap when the next
        // Farvardin 1 is 366 days away, which this asks directly rather than
        // hard-coding a cycle.
        var a = toGregorian(jy, 12, 30);
        return toJalali(a[0], a[1], a[2])[1] === 12 ? 30 : 29;
    }

    var fa = function (n) {
        return String(n).replace(/[0-9]/g, function (d) {
            return String.fromCharCode(1776 + Number(d));
        });
    };

    var pad = function (n) { return (n < 10 ? '0' : '') + n; };

    /* `2026-08-19` → [2026, 8, 19]; anything else → null. */
    function readIso(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');

        return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null;
    }

    /* The two month arrows, drawn rather than typed.
       ‹ and › are bidi-mirrored characters: in an RTL run the browser paints
       each one as the other, so «ماه قبل» — which in this direction points
       right — came out pointing left, and so did its neighbour. A path has no
       opinion about direction. */
    function arrow(label, back) {
        var button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" '
            + 'stroke="currentColor" stroke-width="2.4" stroke-linecap="round" '
            + 'stroke-linejoin="round" aria-hidden="true"><path d="'
            + (back ? 'M9 5l7 7-7 7' : 'M15 5l-7 7 7 7')
            + '"/></svg>';

        return button;
    }

    function build(input) {
        var withTime = input.type === 'datetime-local';

        // The field that is typed into. Read-only on purpose: a Jalali date
        // typed freehand is a parsing problem with no good failure, and the
        // calendar below is the whole point.
        var shown = document.createElement('input');
        shown.type = 'text';
        shown.className = 'vp-adm-jdate';
        shown.readOnly = true;
        shown.id = input.id ? input.id + '-fa' : '';
        shown.setAttribute('aria-haspopup', 'dialog');

        var wrap = document.createElement('div');
        wrap.className = 'vp-adm-jwrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(shown);
        wrap.appendChild(input);
        input.classList.add('vp-adm-jhidden');

        if (input.id) {
            var label = document.querySelector('label[for="' + input.id + '"]');
            if (label && shown.id) label.setAttribute('for', shown.id);
        }

        var pop = null;
        // The month on screen, which is not always the month selected.
        var view = null;

        function paint() {
            var iso = readIso(input.value);
            if (!iso) { shown.value = ''; return; }
            var j = toJalali(iso[0], iso[1], iso[2]);
            var text = fa(j[0]) + '/' + fa(pad(j[1])) + '/' + fa(pad(j[2]));
            if (withTime) {
                var t = /T(\d{2}):(\d{2})/.exec(input.value);
                if (t) text += ' — ' + fa(t[1]) + ':' + fa(t[2]);
            }
            shown.value = text;
        }

        function put(jy, jm, jd) {
            var g = toGregorian(jy, jm, jd);
            var iso = g[0] + '-' + pad(g[1]) + '-' + pad(g[2]);
            if (withTime) {
                var keep = /T(\d{2}:\d{2})/.exec(input.value);
                iso += 'T' + (keep ? keep[1] : '12:00');
            }
            input.value = iso;
            // So anything listening to the real field still hears about it.
            input.dispatchEvent(new Event('change', { bubbles: true }));
            paint();
        }

        function close() {
            if (pop) { pop.remove(); pop = null; }
            document.removeEventListener('click', onAway, true);
            document.removeEventListener('keydown', onKey);
        }

        function onAway(e) { if (pop && !pop.contains(e.target) && e.target !== shown) close(); }
        function onKey(e) { if (e.key === 'Escape') close(); }

        function draw() {
            pop.innerHTML = '';

            var head = document.createElement('div');
            head.className = 'vp-adm-jhead';

            var back = arrow('ماه قبل', true);
            back.addEventListener('click', function () {
                view[1]--; if (view[1] < 1) { view[1] = 12; view[0]--; } draw();
            });

            var title = document.createElement('span');
            title.textContent = MONTHS[view[1] - 1] + ' ' + fa(view[0]);

            var fwd = arrow('ماه بعد', false);
            fwd.addEventListener('click', function () {
                view[1]++; if (view[1] > 12) { view[1] = 1; view[0]++; } draw();
            });

            head.appendChild(back); head.appendChild(title); head.appendChild(fwd);
            pop.appendChild(head);

            var grid = document.createElement('div');
            grid.className = 'vp-adm-jgrid';

            DAYS.forEach(function (d) {
                var c = document.createElement('span');
                c.className = 'vp-adm-jday';
                c.textContent = d;
                grid.appendChild(c);
            });

            // Which weekday the first of this month falls on, counting from
            // Saturday, which is where a Persian week starts.
            var first = toGregorian(view[0], view[1], 1);
            var lead = (new Date(first[0], first[1] - 1, first[2]).getDay() + 1) % 7;

            for (var i = 0; i < lead; i++) grid.appendChild(document.createElement('span'));

            var picked = readIso(input.value);
            var pj = picked ? toJalali(picked[0], picked[1], picked[2]) : null;

            var today = new Date();
            var tj = toJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());

            for (var d = 1; d <= jLength(view[0], view[1]); d++) {
                (function (day) {
                    var cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'vp-adm-jcell';
                    if (pj && pj[0] === view[0] && pj[1] === view[1] && pj[2] === day) cell.classList.add('is-on');
                    if (tj[0] === view[0] && tj[1] === view[1] && tj[2] === day) cell.classList.add('is-today');
                    cell.textContent = fa(day);
                    cell.addEventListener('click', function () { put(view[0], view[1], day); close(); });
                    grid.appendChild(cell);
                })(d);
            }

            pop.appendChild(grid);

            var foot = document.createElement('div');
            foot.className = 'vp-adm-jfoot';

            var now = document.createElement('button');
            now.type = 'button';
            now.textContent = 'امروز';
            now.addEventListener('click', function () { put(tj[0], tj[1], tj[2]); close(); });

            var clear = document.createElement('button');
            clear.type = 'button';
            clear.textContent = 'خالی';
            clear.addEventListener('click', function () {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                paint();
                close();
            });

            foot.appendChild(now);
            if (!input.required) foot.appendChild(clear);
            pop.appendChild(foot);
        }

        function open() {
            if (pop) { close(); return; }

            var iso = readIso(input.value);
            var start = iso ? toJalali(iso[0], iso[1], iso[2])
                            : toJalali(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate());
            view = [start[0], start[1]];

            pop = document.createElement('div');
            pop.className = 'vp-adm-jpop';
            pop.setAttribute('role', 'dialog');
            pop.setAttribute('aria-label', 'انتخاب تاریخ');
            wrap.appendChild(pop);
            draw();

            // «تاریخ انتشار» is near the foot of a long form, and a calendar
            // that opens downwards from there opens off the bottom of the
            // screen. Flip it above the field when there is no room below and
            // more of it above; `is-up` is the only thing that moves, so a
            // field with room either way keeps the ordinary downward position.
            var box = pop.getBoundingClientRect();
            var field = shown.getBoundingClientRect();

            if (box.bottom > window.innerHeight && field.top > box.height + 12) {
                pop.classList.add('is-up');
            }

            document.addEventListener('click', onAway, true);
            document.addEventListener('keydown', onKey);
        }

        shown.addEventListener('click', open);
        shown.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
        });

        paint();
    }

    function start() {
        var fields = document.querySelectorAll('.vp-adm input[type="date"], .vp-adm input[type="datetime-local"]');
        Array.prototype.forEach.call(fields, build);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
