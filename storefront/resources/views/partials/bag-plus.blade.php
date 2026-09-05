{{--
    `shopping-bag-plus` from **Tabler Icons**, MIT, unchanged — the icon the
    client picked off a sheet of eleven («یک عالیه»), and the answer to «ما یدونه
    آیکون سبد خرید که روش بعلاوه داره تو صفحه فروشگاه داریم چرا آیکون جدیدی
    میاری؟». Its notice is in
    `download-version/assets/img/icon/LICENSE-tabler.txt`.

    A partial because two places on the home page draw it — the best sellers'
    phone square and the special offer's button — and two copies of a 500-byte
    path is two things to keep in step for no reason. Same argument as
    `partials/deal-burst.blade.php`.

    **`shop/card.blade.php` deliberately keeps its own copy.** That one carries
    no class, is pretty-printed across five lines, and is sized by
    `.vp-card-add svg`; folding it in here would change the whitespace inside
    that button on a page with no pixel baseline to notice it. Its own comment
    explains the icon; this one is not a second source of truth for it, and if
    the path ever changes both have to move.

    **This file must not end in a newline.** Every caller puts the mark inside
    an inline element with no space around it, and a trailing newline is a
    whitespace text node that moves the layout by a space. check-parity.js is
    what notices on the home page; the listing has no pixel baseline, so it was
    checked by diffing the rendered HTML byte for byte.

    $class is the caller's: the marks are shown and hidden by different rules in
    each place, so they cannot share one name.
--}}
<svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><g stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12.5 21H8.574a3 3 0 0 1-2.965-2.544l-1.255-8.152A2 2 0 0 1 6.331 8H17.67a2 2 0 0 1 1.977 2.304l-.263 1.708M16 19h6m-3-3v6"></path><path d="M9 11V6a3 3 0 0 1 6 0v5"></path></g></svg>