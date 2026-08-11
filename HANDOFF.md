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

**The home page is ported, and six of its bands render from the catalogue.**
`storefront/resources/views` renders the page: a layout that fixes the order
of the regions, one partial per region under `partials/` and `home/`, and
`home.blade.php` composing the seven sections. `/` goes through
`HomeController`. The 115 files the page actually reaches — 10.7MB of the
template's 40 — are under `storefront/public/assets`.

The category tiles, the hero deck, the stepped sale, the best sellers, the
daily deal and the brand strip are queried, not written down. The rest —
header, trust row, offer banner, footer — is copy and stays in the markup.

`CatalogueSeeder` puts what the page has always shown into the tables: eight
categories, five brands, five shoes with their photographs, and variants
priced in Rial with the live step's cut as a promotion window. Nothing in it
is new content — it is the JavaScript arrays out of `theme/make-rtl-page.js`,
moved. Run `php artisan migrate --seed`; the app is PostgreSQL and so is the
test suite, because the catalogue's invariants are CHECK constraints in the
database rather than checks in PHP.

Both ports are exact. Rendered at 992, 1200, 1440 and 1920 and compared over
the full scroll height, the Laravel page and the preview page differ by zero
pixels at every width — before the data went in and after. `node
theme/check-parity.js` is that check, committed. Two things moved in the DOM,
both deliberately: the modal explaining the ladder now sits next to the ladder
rather than after the script tags, and its script goes onto a stack the layout
empties in the place those tags used to sit.

Three things fall out of the wiring that are worth knowing:

- **The page's own numbers now agree with each other by construction.** The
  cut drawn on a deal card is `Variant::discountPercent()`, and the seeder
  set that variant's promotion from the same live step the board above the
  cards reads. Moving the sale on is one number in `config/storefront.php`.
- **«فقط ۱ عدد باقی مانده» is a count.** It follows `sellable_stock`, and a
  product whose stock reaches zero leaves the sale rather than being offered.
- **What is invented did not go into the catalogue.** The brand strip's
  counts, its mosaic photographs, and the pairing that puts a shoe's price
  under a category's photograph are all in `config/storefront.php` under
  `placeholders`. Seeding an invented number into the tables would make it
  indistinguishable from a counted one, which is the whole thing that block
  exists to prevent.

**What is still not wired:** nothing on the page needs a customer. There is no
cart, no product page, no search, no account — `page_url()` sends all
forty-odd of the template's links to `#` because there is one route. The
basket buttons on the deal cards and the best-seller tiles are markup.

**It is not deployed anywhere.** The link the client reviews is Netlify, which
publishes `download-version/` and cannot run PHP. `DEPLOY.md` has the
container, the environment it needs, and a plain account of which parts of it
were tested and which could not be.

Everything in this file is about the HTML page at 1440 unless it says
otherwise.

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

**Four rows of that table no longer describe the page, and were already wrong
before the port.** Measured at 1440 on the preview page and on the Laravel
page, which agree exactly:

| | the table says | 1440 renders | where the old number does appear |
|---|---|---|---|
| hero card | 1227 × 485 | 1226.5 × **450.4** | height, when `.hero-style6`'s `padding-block` was still 112 — it is 94 |
| the shoe | 80 clear top and foot | **41** and 40.9 | 85.5 / 85.4, at 1920 |
| category tiles | 157.5 each | **141** | 157.5, at 1920 |
| between the tiles | 48 | **36** | nowhere; 1920 gives 45 |

The tile rows have the plainest explanation: the table, and the sentence about
"the six category tiles" in `CLAUDE.md`, are both from when the row held six.
It holds eight. Eight tiles across the same measure are smaller and sit closer
together, so 157.5/48 became 141/36 without anyone deciding it.

They are left as they were rather than overwritten, because a number in this
table is a decision somebody argued for and a number off the page is only what
the page happens to do today. Someone has to say which of these four is a
regression and which is the intended state; until then, do not judge a change
against the four rows above.

Two of the table's rows hold each other up and have to be changed together:

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

**Nor is the head.** The `author`, `description` and `keywords` metas still
say "Erna - Multi-Purpose Modern & Minimal WooCommerce Template". The port
left them alone for the same reason it left the dark strip alone — replacing
copy is a separate decision. The `<title>` is the exception: it had to become
per-page for a layout to be a layout, so it is now
`@yield('title', config('app.name'))` and reads «VikyPlus».

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

## The preview and the Blade are two copies of the same page

This is the cost of the port and it is worth naming. The static page is still
the surface the client reviews, and the Blade is a second copy of it. The two
have to be kept in step, and how depends on which half of the page a change
lands in.

**The six data-driven partials are hand-owned.** `home/hero`, `categories`,
`ladder`, `best-sellers`, `daily-deal` and `brands` are no longer copies of
anything — they render from the catalogue. `make-blade.js` knows this and
leaves them alone; it prints which ones it skipped. A design change to any of
them has to be made in the Blade by hand, and in `theme/make-rtl-page.js` too
if the preview is to keep showing it.

**Everything else is still generated.** Two scripts carry those changes across:

- `node theme/make-blade.js` — after any change to the markup of the header,
  the footer, the offer banner or the page's chrome. It cuts
  `shoe-shop-rtl.html` at its section boundaries and overwrites those
  partials.
- `node theme/sync-storefront-assets.js` — after any change to `tweaks.css`,
  and after any new photograph. It walks the page's references, the `url()`s
  inside the stylesheets it finds, and the fonts those name, and copies that
  set into `public/`.

Neither touches a hand-written file: the layout, `home.blade.php`, the
controller, `config/storefront.php`, the six above. Anything hand-edited
*inside* a generated partial is lost on the next run.

**`node theme/check-parity.js` is what says the two have not come apart.** It
renders both pages at four widths and reports how many pixels differ; zero is
the expected answer and it exits non-zero on anything else. Run it after a
change to either side. Both servers have to be up:

```
cd download-version && setsid nohup python3 -m http.server 8811 &
cd storefront && php artisan serve --port=8812
```

When the last generated partial has been taken over by hand, `make-blade.js`
should be deleted and the preview page becomes history.

`tests/Feature/HomePageTest.php` is the cheap guard underneath: it names every
section the page is composed of and the four that were taken off it, and it
holds the data-driven ones to their data — that renaming a category renames a
tile, that a card prices its own variant, that a sold-out product leaves the
sale, and that the brand counts are config placeholders rather than anything
the catalogue claims to know.

The suite needs PostgreSQL (`vikyplus_testing`), for the same reason the app
does. `storefront/.github/workflows/tests.yml` is set up for that but **does
not run**: GitHub only reads workflows at the repository root, and that file
is a level down inside `storefront/`. Moving it up is a loose end.

## How to work on this

- **Never edit the generated HTML.** Edit `theme/make-rtl-page.js` and re-run
  `node theme/make-rtl-page.js` — then the two scripts above.
- **Links go through `page_url()`.** The ported markup still points at the
  template's demo filenames. `config/storefront.php` maps the ones with a
  route behind them; everything else resolves to `#`, so no link on the page
  walks a visitor into a 404. Add a line there as each page gets built.
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


## The footer's contact block, removed

The template's footer carried a German company, a street in California and a
`+00 123 456 789` telephone number. Everything else in it has been translated —
the labels are generic shop words, so translating them invented nothing — but
those three could not be, because the shop's real address, telephone number and
email address are facts nobody has supplied.

A footer with no address is ordinary. A footer with a false one is a lie the
shop tells on every page, so the block was removed rather than translated. It
is one edit in `theme/make-rtl-page.js` to put back once the real details
arrive, and the central branch's own record (`/admin/settings`) already has
fields for all three.

The logo in that column is still the template's ERNA mark, for the same reason:
there is no VikyPlus logo file in the repository.
