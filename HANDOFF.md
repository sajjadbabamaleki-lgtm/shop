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
| island → hero card | 40 |
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
The marks behind the glass used to be the second of these, and are no longer:
they were placed in the page's own coordinates as fixed offsets from the body,
which held only at 1440 — the disc sat 240px off the card's centre at 1920, and
the low bar, pinned to y 600 while the card's foot moved with the shoe, climbed
137px off the foot and onto the shoe. They now live in `.slider-area`, whose box
is the card's box vertically and the page's horizontally, so `top: 0` is the
card's top edge, `bottom: 0` its foot and `left: 50%` its centre line. Nothing
about them has to be re-measured when the header or the card changes height.

## Not finished

This table used to say everything below the category row was the template's.
That is no longer true, and it had drifted far enough to be misleading — the
row it called «Men’s Collections» has been the Persian ladder for some time.
What follows was read off the rendered page, section by section, rather than
carried forward: the heading each block actually shows, and how many of its
photographs are ours rather than the template's.

| section, top to bottom | heading it renders | photographs | whose |
|---|---|---|---|
| trust row | «ارسال سریع» | 8 of 13 ours | ours |
| ladder — was collection-area | «حراج پله‌ای ویکی پلاس» | 5 of 5 ours | ours |
| best sellers | «پرفروش‌ترین‌ها» | 6 of 6 ours | ours |
| offer banner | «SPECIAL OFFER» / «BLACK FRIDAY» | 0 of 1 | **template** |
| daily deal — was today's deals | «قبل از تمام شدن بخرش!» | 1 of 1 ours | ours |
| brand strip | «برندهای موجود» | our layout, placeholder content | see below |
| footer | «Menu» | 0 of 5 | **template** |

So two blocks are still wholly the template's: **the offer banner and the
footer.** The banner reads BLACK FRIDAY / SPECIAL OFFER over ADIDAS SHOES on a
stock photograph; the footer carries «Menu», the column headings and an address
in Germany for a furniture company.

**The brand strip is ours in shape and borrowed in content.** The template's
carousel is gone, replaced by four tiles on one white card — a photo mosaic
per tile with a glass plate floating in the middle carrying the brand's mark,
its name and a stock count. The layout is settled and measured. Three things
in it are stand-ins, each chosen by the client rather than waited for, and all
three live in one array (`BRANDS`) at the top of the brand block in
`theme/make-rtl-page.js`:

- **the marks** — `brand_5_2.png` is genuinely the Nike swoosh and sits where
  it belongs; the other three are the template's own abstract marks. The slot
  is a fixed 30×30 box rather than sized off the artwork, so a real logo drops
  in without touching the CSS.
- **the photographs** — the eight category tiles from the top of the page. We
  hold one product photograph per brand and this shape wants three, so twelve
  slots against eight images means four repeat. No two tiles open on the same
  lead image.
- **the counts** — invented. There is no inventory behind this page; the
  Laravel app has the tables, the static page has no data. They are shaped
  like real numbers and are not real numbers.

**The dark strip at the very top is not done either**, though the "Finished"
section above covers its geometry. It still shows the template's
`helloerna@mail.com`, its English/Spanish/Hindi language list and its USD/Euro
currency list. That is copy, not layout, which is why the measurements above
still hold.

**The note about two curly-apostrophe dictionary keys is gone with them.**
`Men’s Collections` and `Today’s Best Deals` do not render anywhere on the
page any more — the ladder and the daily deal took those two slots — so there
is nothing left to fix in the dictionary for them.

**Four sections came off the page entirely.** «نظرات مشتریان», «محصولات منتخب»,
«تازه‌ترین مطالب» and «اینستاگرام» — testimonials, feature products, blog,
instagram — were taken off the home page at the client's request. They were
the four that had nothing of ours in them at all, only template faces,
products, posts and photographs, and no real content was coming for them.

They are cut in `theme/make-rtl-page.js` by `dropSection()`, which finds each
block by its **heading** and walks out to the enclosing top-level element:
three of the four wrappers carry no class worth aiming at, so the heading is
the only durable handle. It throws if a heading stops matching rather than
silently leaving the section on the page. If any of these is ever wanted back,
delete its heading from the list — the markup is still in the template's own
`shoe-shop.html`, untouched.

The hero deck no longer carries any placeholders — this used to say four of
the six slides were the template's grey shoes and that the one real photograph
was `vikyplus-hero-1.png`, which is not even in the repo any more. All six
slides are ours now: three photographs — `vikyplus-hero-nb530.webp`,
`-jordan.webp`, `-goldengoose.webp` — each used twice, so the deck reads as a
loop of three rather than six of anything.

The deck runs two slides to a view and shows 83px of the neighbouring
cards at each margin — that is the template working as designed and it is
wanted. It has now been cut twice and put back twice. See «همسایه» in
`CLAUDE.md` before touching it.

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
