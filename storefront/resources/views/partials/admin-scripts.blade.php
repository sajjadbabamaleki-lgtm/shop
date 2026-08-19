{{--
    What the panel's shell needs a script for, and nothing else.

    Four things, all of them state the server cannot know: whether the sidebar
    is collapsed, whether the drawer is open on a phone, which half of the
    bottom sheet is showing, and the cell labels the stacked tables read from
    their own headings.

    Everything here degrades to the shell working without it: no collapse
    toggle, no drawer — but the sidebar is still in the page and still
    navigable, the bottom bar is still links, and the tables stay tables. The
    one thing that must never depend on this file is *reaching* a screen.

    §27: «Important actions must not depend on hover.» Nothing here does.
--}}
<script>
    (function () {
        var root = document.body;
        if (!root || !root.classList.contains('vp-adm')) return;

        var sheet = document.getElementById('vp-adm-sheet');
        var scrim = document.querySelector('.vp-adm-scrim');
        var drawerBtn = document.querySelector('[data-adm-drawer]');

        // The scrim and the sheet are `hidden` in the markup so that a page
        // whose script never runs cannot end up with an invisible layer over
        // it, or a sheet's contents dumped at the foot of the document.
        if (scrim) scrim.hidden = false;
        if (sheet) sheet.hidden = false;

        function shut() {
            root.removeAttribute('data-drawer');
            root.removeAttribute('data-sheet');
            if (drawerBtn) drawerBtn.setAttribute('aria-expanded', 'false');
        }

        // --- the desktop sidebar's width, remembered ----------------------
        //
        // §17 gives the collapsed sidebar a width, which means it is a state
        // somebody chooses — and one they should not have to choose again on
        // the next screen. localStorage rather than a cookie: nothing on the
        // server needs to know, and a cookie would be sent with every request
        // including the ones for photographs.
        var TIGHT = 'vp-adm-side';

        try {
            if (localStorage.getItem(TIGHT) === 'tight') root.dataset.side = 'tight';
        } catch (e) {
            // Private browsing refuses storage. The sidebar simply opens wide.
        }

        var tightBtn = document.querySelector('[data-adm-tight]');

        if (tightBtn) {
            tightBtn.addEventListener('click', function () {
                var tight = root.dataset.side === 'tight';
                root.dataset.side = tight ? 'wide' : 'tight';
                tightBtn.setAttribute('aria-label', tight ? 'جمع کردن منو' : 'باز کردن منو');
                try { localStorage.setItem(TIGHT, root.dataset.side); } catch (e) {}
            });
        }

        // --- the phone's drawer -------------------------------------------
        if (drawerBtn) {
            drawerBtn.addEventListener('click', function () {
                var open = root.getAttribute('data-drawer') === 'open';
                root.removeAttribute('data-sheet');
                if (open) {
                    root.removeAttribute('data-drawer');
                } else {
                    root.setAttribute('data-drawer', 'open');
                }
                drawerBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
            });
        }

        // --- the bottom sheet, one panel at a time ------------------------
        //
        // «افزودن» and «بیشتر» are the same sheet showing different halves.
        // One sheet rather than two because they are never open together and
        // two would be two sets of the same open/close/escape handling.
        document.querySelectorAll('[data-adm-sheet]').forEach(function (button) {
            button.addEventListener('click', function () {
                var part = button.getAttribute('data-adm-sheet');
                var already = root.getAttribute('data-sheet') === part;

                root.removeAttribute('data-drawer');

                if (already) { shut(); return; }

                document.querySelectorAll('[data-adm-sheet-part]').forEach(function (el) {
                    el.hidden = el.getAttribute('data-adm-sheet-part') !== part;
                });

                root.setAttribute('data-sheet', part);
            });
        });

        document.querySelectorAll('[data-adm-close]').forEach(function (el) {
            el.addEventListener('click', shut);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') shut();
        });

        // --- select all, §24 ----------------------------------------------
        //
        // The only part of the grid that needs a script. Without it the bulk
        // bar still works — the row boxes are ordinary checkboxes in an
        // ordinary form — you simply tick them one at a time. So the box is
        // hidden until this runs rather than sitting there looking broken.
        var all = document.querySelector('[data-adm-all]');

        if (all) {
            var rows = [].slice.call(document.querySelectorAll('[data-adm-row]'));

            all.closest('label').hidden = false;

            all.addEventListener('change', function () {
                rows.forEach(function (row) { row.checked = all.checked; });
            });

            // Ticking every row by hand should tick the header box too, or the
            // two disagree about a state they are both showing.
            rows.forEach(function (row) {
                row.addEventListener('change', function () {
                    all.checked = rows.every(function (r) { return r.checked; });
                });
            });
        }

        // --- the tables' cell labels --------------------------------------
        //
        // Below 992 every row becomes a card, and a card of bare values —
        // «۳۷», «۴۰», «۲» — says nothing without the heading each one sat
        // under. Reading them off the table's own <thead> covers all seventeen
        // tables in the panel and the next one somebody adds.
        document.querySelectorAll('.vp-admin-table').forEach(function (table) {
            var headings = [].map.call(
                table.querySelectorAll('thead th'),
                function (th) { return th.textContent.trim(); }
            );

            if (!headings.length) return;

            [].forEach.call(table.querySelectorAll('tbody tr'), function (row) {
                var column = 0;

                // `row.cells`, not `row.children`. Several of these rows wrap
                // their controls in a <form>, which is not allowed as a child
                // of <tr> — the parser leaves it, and its hidden inputs,
                // sitting among the cells as siblings. Counting `children` as
                // columns shifted every label after them, and the inventory
                // screen's «شمارش», «حد هشدار» and «توضیح» — the three cells
                // somebody actually types into — came out blank.
                [].forEach.call(row.cells, function (cell) {
                    var span = cell.colSpan || 1;

                    if (span > 1) {
                        cell.setAttribute('data-vp-full', '');
                    } else if (headings[column] !== undefined) {
                        cell.setAttribute('data-label', headings[column]);
                    }

                    column += span;
                });
            });

            table.setAttribute('data-vp-stack', '');
        });

        // --- §14's Ctrl+K -------------------------------------------------
        //
        // The field works without this: it is a form in the topbar and Enter
        // submits it. All the shortcut does is put the cursor in it, which is
        // why nothing here is conditional on the key working.
        //
        // `metaKey` as well as `ctrlKey`, because on a Mac the same shortcut
        // is Cmd+K and a panel that only answered to one of them would be
        // broken for whoever is on the other. The Escape gives the page back:
        // a shortcut that traps the cursor in a box is worse than none.
        var find = document.querySelector('[data-adm-find]');

        if (find) {
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                    e.preventDefault();
                    find.focus();
                    find.select();
                } else if (e.key === 'Escape' && document.activeElement === find) {
                    find.blur();
                }
            });
        }

        // --- the bell closes when you look elsewhere ------------------------
        //
        // A <details> stays open until its own summary is pressed again, which
        // for a menu in the chrome means it follows you to the next thing you
        // click. Closing it on any press outside is what every other menu on
        // this page does; without JavaScript it simply stays open, which is
        // untidy rather than broken.
        var bell = document.querySelector('.vp-adm-bell');

        if (bell) {
            document.addEventListener('click', function (e) {
                if (bell.open && !bell.contains(e.target)) {
                    bell.open = false;
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && bell.open) {
                    bell.open = false;
                }
            });
        }
    }());
</script>
