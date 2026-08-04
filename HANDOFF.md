# VikyPlus — where this stands

Read `CLAUDE.md` first: it says where things are, how the page is built, and
carries the codenames for problems that have already cost a day. This file says
what is finished, what is not, and what the finished part is not allowed to
lose.

## What is being built, and what this HTML is

**The deliverable is the Laravel app in `storefront/`.** It is Laravel 13 on
PostgreSQL with the data core already standing — brands, categories, products,
variants, media, inventory, with the invariants pushed into the database. It
has no storefront views yet: one route, returning `welcome.blade.php`.

`download-version/shoe-shop-rtl.html` is **not the product**. It is the
ThemeForest template with the design decisions layered on top, and it exists
because settling a look costs a fraction as much on a static page as it does in
Blade — every argument in this repo's history was settled by rendering that page
and reading pixels.

**Nothing has been ported yet.** The finished top of the page lives entirely in
`download-version/assets/css/tweaks.css` and `theme/make-rtl-page.js`, and the
Laravel app does not render any of it. Porting it — a layout, partials for the
header, hero and category row, the assets moved under `public/`, the tweaks
carried over — is the next piece of real work, and it is what turns this
handoff into a storefront.

The numbers below are what that port has to reproduce. Everything in this file
is about the HTML page at 1440.

---

## Finished: the top of the page

The dark strip, the header island, the hero deck and the six category tiles.
Judge any change to them against these numbers — they were each argued for and
measured, and a change that quietly moves one of them is a regression even if it
looks fine:

| | |
|---|---|
| dark strip | 48 tall |
| header island | 76 tall, 18 from the top and both sides, corner 24 |
| app icon | 52, with equal air on the three sides it touches — 12 |
| island → hero card | 36 |
| hero card | 1227 × 485, corner 72 |
| the shoe | 80 clear of the card's top edge and 80 of its foot |
| card → category row | 36 |
| category tiles | 157.5 each, 48 between them |
| glass | `rgba(16,17,17,0.034)`, blur 10 — composites to 247 on white |
| gold | `#C0972F → #E3B54A` on the button, the search disc and the burst |

Two of those hold each other up and have to be changed together:

- **The card's height is set by the copy column, not by the shot.** The shot is
  sized off the column's width and does not move. So anything added to or taken
  out of the copy changes the card's height, and `.heroSlide6 .hero-style6`'s
  `padding-block` has to answer it or the shoe's 80 goes with it. That padding
  has been 107, 112.5, 116.5, 117, 120 and is 112 — every one of those numbers
  was the answer to something else changing.
- **The marks behind the glass are placed in the page's own coordinates**, not
  the card's. Shorten the header and the card slides out from under them. When
  anything above them changes height, move all three.

## Not finished

Everything below the category row is the template's, in the template's English,
with the template's stock photography:

| section | |
|---|---|
| collection-area | «Men’s Collections» — still English, see below |
| best sellers | Persian heading, template products |
| offer banner | «BLACK» — English |
| today's deals | «Today’s Best Deals» — still English |
| testimonials | Persian heading, template faces |
| brand strip | «The Official Store of the Amazing Brand» — English |
| feature products | Persian heading, template products |
| blog | Persian heading, template posts |
| instagram | Persian heading, template photographs |
| footer | «Menu» and the column headings — English |

**Two headings are already in the dictionary and still render English.** The
template writes them with a curly apostrophe — `Men’s`, `Today’s` — and the
dictionary in `theme/make-rtl-page.js` has the straight one. Fixing the two
keys is the first five minutes of the next session.

The hero deck's other five slides still carry the template's grey placeholder
shoes; only the slide the deck opens on has a real product photograph
(`assets/img/hero/vikyplus-hero-1.png`). The deck runs two slides to a view and
shows 83px of the neighbouring cards at each margin — that is the template
working as designed, and it has been changed once for no reason. See «همسایه»
in `CLAUDE.md` before touching it.

## How to work on this

- **Never edit the generated HTML.** Edit `theme/make-rtl-page.js` and re-run
  `node theme/make-rtl-page.js`.
- **Every deviation from the template goes in
  `download-version/assets/css/tweaks.css`**, loaded last, one block per
  decision, with the reasoning and the measurement in the comment above it.
- **Measure, don't eyeball.** Render in Chromium and read pixels. Every number
  in the table above came from a render, and most of the arguments in this
  repo's history were only settled that way. The tooling is in `CLAUDE.md`.
- **Check the whole set of widths.** 992, 1200, 1440, 1920 at least. The header
  has broken onto three lines and overflowed the document twice.
- **The preview server dies constantly.** Restart it with `setsid` rather than
  assuming it is up.
