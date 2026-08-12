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
| ~~dark strip~~ | ~~48 tall~~ — **removed**, see below |
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
- **And 375 and 320, every time the phone page is touched.** The template has
  bands of its own down there that nothing above 376 ever shows. One of them —
  `@media (max-width: 375px)` in `style.css` — throws away `.feature-card`'s row
  layout and stacks the icon above the copy, which met a stated `height` on the
  five trust badges and printed each card's second line inside the card below
  it. It shipped, and it shipped because the block that set that height records
  measurements at "360, 390, 575, 767 and 991" — 375 is the one width in the
  usual sweep that was skipped, and it was the only width that showed it. The
  client found it on their own phone. Sweep 320, 360, **375**, 390, 430, 575.
- **The preview server dies constantly.** Restart it with `setsid` rather than
  assuming it is up.
- **`check-parity.js` is not reliably zero any more, and the noise is not
  yours.** On an eleven-width sweep roughly every second run reports exactly one
  width differing: a few hundred pixels, worst channel gap around 18, the
  bounding box always inside a `.vp-deal-shot` photograph in the stepped sale,
  and a different width each time. Re-running clears it, which is what makes it
  dangerous — it is a guard whose failure looks like its noise. **Before
  believing a parity failure, run it again and check whether the bbox lands on
  a deal card's shot.** A real regression repeats at the same width with the
  same bbox.

  One cause was found and fixed: the reduced-motion guard for `.vp-deal-burst`
  was written above the rules it overrides, at the same specificity, so the six
  sale bursts kept rotating under `prefers-reduced-motion: reduce` and were
  caught at different angles on the two pages. The guard is now repeated below
  those rules and the bursts measure `animation: none`, `transform: none` on
  both. **The flake survived that fix**, so there is at least one more cause,
  and the remaining bbox is the photograph rather than the burst beside it — a
  sub-pixel settling difference is the obvious suspect and has not been proven.
  It is not the drawer work of this round: with that whole branch stashed, the
  same signature appeared at 375.


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


## The shop's own mark, in all three places

The template put a logo in three places and each one was a different file of
somebody else's: `logo-red2-gold.svg` in the footer, `logo-gold.svg` in the
drawer that opens on a phone, and its own wordmark in the header band. All three
are now `assets/img/vikyplus-appicon.png` with the shop's name in text beside
it — the same lockup, `.vp-logo`, differing only in how it is arranged:

- **header** — a row, tile leading from the right, 52px because the band's
  height is arithmetic on that number.
- **footer** — the same row with `.vp-logo-foot`, which adds only a hover
  (the column it sits in is all links, so a mark that did nothing would not
  read as one) and makes `.th-widget-about` a flex container, without which the
  column started 3px below the four beside it.
- **drawer** — `.vp-logo-stack`, the tile above the name rather than beside it,
  and larger at 68px: on a phone the band is down to the tile and the icons, so
  the drawer is the only place the shop's *name* is read.

Two things worth knowing before touching any of it:

- **The tile has a drop shadow now, not a line round it** — see «سایه، نه خط»
  below. What is left of the old note still matters: an *inset* box-shadow on a
  replaced element paints underneath its content, so on an opaque PNG it is
  invisible, and the tile was written that way for a long time without rendering
  once. `box-sizing: border-box` keeps the tile 52px if a border ever returns.
- **The mark links to `index.html`**, which has to stay mapped in
  `config/storefront.php`. It was not, and so every copy of the logo resolved
  to `'#'` — the most obvious link on the page is the last one anybody clicks.
  A test asserts all three lead home.

The icon set was regenerated from the same file by `theme/make-favicons.js`,
including `favicon.ico`, which Laravel ships as a zero-byte file at the public
root — so the tab was blank on first paint of every page whatever the `<link>`
tags said. `theme/sync-storefront-assets.js` copies it by name, since nothing
links to it and the crawl therefore cannot see it.

`manifest.json` and `browserconfig.xml` were regenerated with it. The template's
manifest said `"name": "App"` and pointed every icon at the domain root, where
none of them are.

## The phone drawer

The template's mobile menu survived the entire port. It was a directory of the
ThemeForest demo — «Electronics فروشگاه», «About Style 1», ten spellings of
blog-grid, `error.html` — and every row of it went to a page this shop does not
have. Nothing caught it: nobody opens the mobile menu on a desktop, the panel is
parked off-screen so the parity check renders it and sees nothing, and the
overflow check cannot reach it either. It was the only menu a phone visitor got.

It is now built from the shop's previous site, which the client asked for by
name, in this one's materials rather than that one's:

- **three shortcuts** — تخفیف‌دارها, جدیدترین‌ها, پرفروش‌ترین‌ها — each opening
  the listing on a real filter (`?sale=1`, `?sort=newest`, `?sort=bestselling`),
  none of them a page of its own. The old site colour-coded them pink, blue and
  orange; this page has one accent, so the tiles are its quiet tint with gold
  marks and only the sale is lit — the same gradient the running step of the
  stepped sale uses, ink on the gold.
- **the shop's sections**, from the same query the tiles under the hero use.
  Deliberately *not* the listing's stricter "only what has something in it":
  seven of the eight categories hold no products yet, so the strict rule would
  offer one section in the drawer and eight on the front page. A test asserts
  the two agree. It resolves itself as the catalogue fills.
- **two chips** — order tracking and the seller sign-up — rather than rows under
  a heading of their own, which cost 96px the drawer did not have.
- **the basket** at the foot, and not «ورود / ثبت‌نام» as the old site had it:
  this shop has no customer accounts, an order is found by its number, so a
  login button would be the one control in there that goes nowhere. It swaps
  back the day accounts exist.

Two things to know before touching it:

- **It is sized to fit, on both screens, and the sizes are not free.** The
  first build measured 1,024px of content on an 844px phone, so the basket was
  below the fold on the screen you open the menu to reach it. The second fit
  with 199px to spare, which read as a menu that stopped halfway. It now fills
  to 14px above the basket on a 390×844 and 11px on a 375×667 — the shortest
  screen still in service — and neither scrolls.

  Three things hold that together and have to move as a set: the panel is
  `min(86vw, 400px)`, the width of the shop's previous menu measured off the
  client's screenshot; `.vp-drawer-cats` is `flex: 1` with `space-between`, so
  the leftover height becomes the gaps between rows rather than a blank third
  at the bottom; and a `max-height: 730px` block brings every size down a step
  for short screens. **Height, not width, triggers that block** — two phones can
  share a width and want different rows, and only one of those is a width.

  `.vp-drawer-body` scrolls, so growing past the screen is silent. Measure it.
- **`partials/mobile-menu.blade.php` is hand-owned.** `make-blade.js` prints it
  in the left-alone list. A markup change made in `theme/make-rtl-page.js` has
  to be made in the Blade by hand as well, and `PhoneDrawerTest` asserts the two
  still name the same categories.

## What the drawer needed underneath it

Two things the listing could not answer before, both added with it:

- **`?sale=1`** — `BranchOffer::scopePromoted()`, the SQL twin of
  `hasActivePromotion()`. One rule written twice is a rule that will disagree
  with itself: if they drift, the sale page shows cards with no sale badge, or a
  badge on a card the sale page will not show, and nothing errors. A test walks
  both over the window's boundaries.
- **`?sort=bestselling`** — `Product::scopeCountingSales()`, counted off paid
  `order_items` rather than off the catalogue, and off *this branch's* orders.
  `coalesce` to 0, because a null sorts first on a descending order and would
  put everything that has never sold at the top of the best-seller list.


## Customer accounts, and the drawer's button

The drawer's foot was the basket, on the reasoning that this shop had no
accounts and «ورود / ثبت‌نام» would go nowhere. The client asked for the button
their previous site had, so the accounts were built rather than the button
changed back.

Almost all of it was already there: `customers` has been `Authenticatable` since
the first migration, with a nullable hashed password and a normalised phone
number, and `config/auth.php` has always carried a `customer` guard with nothing
signing into it. What was missing was four routes and two pages.

**The one real difficulty is registration.** `PlaceOrder` has been creating
`customers` rows all along, keyed on the phone typed at checkout — so most
people who would register already have a row, and that row carries their name,
their address and everything they have bought. Setting a password on it is
therefore handing that over, and a phone number is not a secret. The usual
answer is a one-time code by SMS and this shop has no SMS provider.

So claiming an existing customer asks for the number off one of their own
orders. It is the same proof `/orders` already accepts from somebody not signed
in, and it is a receipt, which a stranger with a phone number does not have. A
row with no orders has nothing to protect and is claimed without one. When SMS
arrives, that check becomes a code.

Two smaller things fell out of it:

- **Every staff route now says `auth:web`, never bare `auth`.** The bare form
  means "whichever guard is default", and the default is a runtime value. With a
  second guard in the application that is the last thing the panel's
  authentication should depend on.
- **`actingAs()` signs a user in on a guard and leaves the others alone.**
  Signing a customer in and then a staff user in inside one test leaves both
  signed in — a state no browser can produce, and it reported the panel's guard
  as broken when the test was what was broken. Two tests, one each.

## What «تمیز» turned out to mean

The drawer was rebuilt once for structure and then twice more for feel, and the
second of those is the one worth recording, because the fault was invisible
until it was measured against the client's own screenshot as *fractions of the
panel's width* — the only comparison that survives two different screen sizes:

| | their menu | ours, before |
|---|---|---|
| icon tile ÷ panel width | 0.067 | **0.096** |
| type size ÷ panel width | 0.046 | **0.040** |
| type weight | regular | **600** |
| filled surfaces in the panel | 2 | **6** |

The tiles were half again as large in a tint twice as dark, and the words beside
them were smaller and heavier — so the marks shouted and the names whispered,
which is the hierarchy backwards. And six different fills (a grey head band,
grey shortcut tiles, grey chips, two golds and white) meant nothing in a panel
whose whole quality is that it reads as white paper.

The fix was mostly subtraction: the head band and the shortcut tiles and the
chips became white with hairlines, the icon tiles came down to 28px at half the
tint with the SVG strokes thinned from 1.5 to 1.2, the names went up to 15px at
regular, and the gold moved off the tiles and onto the two section labels, where
it is the only colour on the panel and does the sorting by itself.

**`.vp-enter` was already taken** — it is this file's scroll-reveal class,
`opacity: 0` until a script adds `.vp-entered` — so the sign-in page's wrapper,
named `.vp-enter`, rendered as a heading, a footnote, and 325px of nothing. Every
element was present and measured its correct size. The family is `vp-signin-`
now. Check the class list before naming a block.


## The dark strip above the header, removed

«این هدر تیره رو حذف کن», with a red cross drawn through it. It was the
template's, entire: a German company's telephone number, `helloerna@mail.com`,
and two select menus offering English/Spanish/Hindi and USD/Euro/GBP on a shop
written in Persian that prices everything in Toman. Neither picker had a handler
behind it — no second locale, no second currency — so the strip's whole content
was either false or inert.

It carried two links that were real, and both survive:

- **«پیگیری سفارش»** is a chip in the phone drawer, and is now the footer's
  «سفارش‌های من» as well — that item pointed at `contact.html` and therefore at
  `'#'`, and the strip was the only other way to the page.
- **«ورود / ثبت‌نام»** is an icon beside the basket in the header island,
  `.vp-account-btn`, sharing every rule with `.sideMenuToggler` so the two read
  as one control twice. It exists from `lg` up only: below that the drawer is
  what a visitor opens and the drawer's foot is the same link, so a third icon
  in the island bought nothing and cost 17px of the band's height.

The page is **48px shorter** at every width, which is exactly the strip and
nothing else — 7502→7454 at 992, 5107→5059 at 1200, 4636→4588 at 1440,
4892 at 1920 — and the two copies still differ by zero pixels. The island itself
is untouched at 76.

`theme/make-blade.js` caught the one thing that would otherwise have gone quiet:
its `LIVE` list rewrote the strip's account link to follow the signed-in state,
and with the strip gone the rewrite had nothing to match. It threw rather than
skipping. That rule now targets the icon's `aria-label`, which is the control's
only text and the only way a screen reader learns which of the two states it is
in.

**Still the template's, and visible on a desktop:** the main navigation is the
demo's mega-menu — «Electronics فروشگاه», the six demo shops with screenshots,
ten blog layouts. It is the same content the phone drawer carried until it was
rebuilt, and it has the same problem. It is the next thing in that row.


## «سایه، نه خط» — the mark and the basket, unlined

«چه تو نسخه موبایل چه دستاپ نمیخوام دور لوگو و سبد خرید خط کشیده باشه ترجیح
میدم دورشون سایه باشه برای مشخصتر شدنشون». **This is the one instruction in this
run that names both breakpoints**, so it was written on the base rules rather
than inside `max-width: 991.98px` like everything above it. The standing
«به هیچ عنوان به نسخه دستاپ دست نزنی» still governs every other line.

Three rules, one shadow between them:

- `.vp-logo img` — the tile, in all three lockups.
- `.icon-btn.sideMenuToggler` and `.icon-btn.vp-account-btn` — the basket and
  the account icon, which had an inset 1px ring for the same reason.
- `.vp-logo-foot:hover img` — the footer's hover, which used to deepen the
  border's colour and now lifts the tile instead.

```
0 1px 2px  rgba(16, 17, 17, 0.10)     the edge
0 3px 8px -2px rgba(16, 17, 17, 0.14)  the lift
```

Two stops because one is not enough: the tight one gives the tile a defined foot
and the wide one is what stops the result reading as a grey outline at one
remove — which is the thing being asked against.

Measured across the tile's left edge, identical at 390 and 1440:

```
247 247 247 247 246 245 244 242 240 232 | 253 253 253 253 253
```

A soft approach into a 21-level step at the tile itself. The border read
`247 247 247 223 253` — a single dark pixel, which is the line the client did not
want. Rendered at 3× at both widths and confirmed by eye as well, because a
step this shape is exactly what a screenshot settles and a column of numbers
does not.

**This is not «لبه پنهان» being reopened.** That codename is about
`.heroSlide6 .hero-inner` and `.th-header .menu-area` — the two big panes, where
a drop shadow was tried, rejected in the client's own words and removed for
reasons that are still true. A 52px tile inside the band is a different element
against a different ground. Read the codename before putting any shadow back on
the panes.

Parity zero at all four widths; heights unchanged (7454 / 5059 / 4588 / 4892).
226 tests green, no page overflows.


## The star, and two traps under it

The client circled where the star should sit. Getting it there took three
attempts and both of the reasons are worth keeping:

- **`.discount-wrapp` is 325px tall.** An SVG inside it overflows a long way
  below, so the wrapper's centre is 120px from the star anybody can see.
  `.discount-tag` is the 85×85 box that draws — measure that one.
- **`.hero-inner` is `overflow: hidden`.** A star pushed above the card's top
  edge does not sit there, it disappears — while `getBoundingClientRect` still
  cheerfully reports the box exactly where it was asked to be. The screenshot is
  the check, not the rectangle.

The numbers were bisected against the star's rendered centre, not derived:
`top: 31px; left: 220px` puts it at (283, 157), which is where the circle is.
They are tied to the card's height, which the copy sets — if the copy changes,
re-measure.

The shoe's toe points **left** in this photograph; the high right end is the
heel. I put the star over the toe first because «پوزه» is the toe, and had the
end wrong.

**And one the parity check cannot catch.** The lockup's gap came down a quarter,
13 → 9.75, and the first version changed the base rule — which moved the desktop
with it. `check-parity.js` renders the two *copies of this page* against each
other, so a change that lands on both is still zero difference. It is not a
guard against changing the desktop. Only reading the desktop is.

## The hero card on a phone, second pass

- **The shoe and the star are above the words.** The template stacks the copy
  column first, which put two lines of heading and a button between the top of
  the card and the thing the card is selling. Done with `order` on the two
  columns, not `flex-direction: column-reverse` — the row is a *wrapping* flex
  row, and reversing its direction fights the wrap instead of reordering the two
  lines it makes.
- **The star is 30% off 121.2, so 84.84.**
- **The card sits in the header's margins, 9 a side.** The band is 9 in and the
  card ran edge to edge, so the two biggest things on the screen disagreed about
  where the page's margin was.

**The card is 440 now, not the 455 the 25% cut landed on**, and that is the
star: 46px came off its height and the image column is only as tall as what it
holds. It was not padded back, because that would be adding empty space to hit
a number the content no longer needs.

Above 992 none of this applies, and the margin in particular must not: the deck
runs `margin: 0 -36%` with two slides to a view so the neighbouring cards show
past the page's margins — «همسایه», cut by mistake twice — and a margin on the
card would eat into that rather than into the page. At 390 the deck's own margin
computes to 0 and one card fills the width, so there is nothing there to
disturb.

## Five off one message

- **The island's top margin follows its sides.** 18 above against 9 either side
  on a phone, which is the thing the island exists to avoid. 9 on all three now;
  still nothing below, because the gap down to the hero is the hero's own
  padding and a margin here would stack on it. Desktop stays 18.
- **The cursor follower is gone**, desktop and phone. There were *three*
  elements, not two: the `.magic-cursor` wrapper with its pair inside, and a
  loose `.cursor-follower` sitting under it — the guard on the second removal is
  what found that. `main.js` needs no change; it wraps the whole block in
  `if ($('.cursor-follower').length > 0)`.
- **The drawer's search is out.** The client meant the field, not only its
  button, and said so twice. **The phone now has no search anywhere** — the
  header's own field is `display: none` below 992 — which is a decision rather
  than an oversight, and is written here so nobody re-adds it by accident.
- **The drawer's lockup is the header's lockup.** It had a 36px tile and a 14px
  name of its own, so the shop's sign was one size at the top of the page and
  another the moment the menu opened over it. It now carries the phone header's
  numbers exactly — 14.52 at 900, 9.35 on one line — and they move together.

  **The tile follows the header band per width, and the close button with it.**
  The tile was 42 across the drawer's whole range, which agreed with the header
  below 576 and quietly did not at 576–991, where the band's squares are 52.
  Both are now the band's own pair — 42/12.65 below 576, 52/15.18 above it — so
  the drawer's head is the header's head at every width the drawer opens at.
  «آیکون لوگو باید تو قسمت منو هم اندازه هوم اصلی باشه و مربع ضبدر».
- **The close button's cross is centred by its box**, the same fix the header's
  three bars needed. The box was 30 with a 9px glyph and is now one of the
  header's squares, glyph at half the box — a control that grows and keeps its
  old mark is a bigger control with a small glyph rattling in it.

**`make-blade.js`'s first region was anchored on `<div class="magic-cursor`.**
Removing the element threw the anchor rather than silently swallowing the whole
chrome region into the one after it — which is exactly what that assertion is
for. It anchors on `<div class="slider-drag-cursor">` now.

## The trust row is six on a phone and five above it

«ببین اون پنج آیتمو به این شکل ولی ۲ تا دوتا کنار هم» put the badges two to a
line in the stacked shape below 576, and «بنظرم یه آیتم تکراری بزار ۶ تایی بشه»
added a sixth so the grid comes out even — three rows of two, no odd card.

- **The sixth badge is real, not the repeat that was asked for.** Two identical
  cards side by side on a live shop read as a mistake. «خرید تکی و عمده» is the
  half of the page's own «تضمین کیفیت، ارسال سریع و امکان خرید تکی و عمده» that
  the row did not already say, and the strapline restates the claim rather than
  extending it. **No trust badge in this row invents a promise** — a badge is a
  commitment to a customer and this repository does not write those.

- **It is hidden from 992 up, and that is deliberate.** Five across cannot hold
  six without leaving one alone on a second line, and six across wraps «ضمانت
  بازگشت کالا» at every width below 1750 — so a sixth badge on the desktop means
  redesigning a band that is finished. That redesign was done for one round
  (three across, two rows) and the client stopped it in the sharpest terms:
  «ما داریم در مورد نسخه گوشی حرف میزنیم چرااااا میری سراغ دستاپ؟». **A phone
  request is not licence to touch the desktop**, even when the phone change
  makes the desktop awkward — hide the difference and say so.

  Verified rather than asserted: the whole `.feature-area2` band rendered before
  and after this round at 992, 1200, 1440 and 1920 is pixel-identical.

  The cost is content that differs by width — the desktop does not make the
  wholesale claim. That is a heavier kind of `display: none` than the sale
  card's basket (a control the desktop still has) and is on the record here so
  the next round can undo it in one rule if the client would rather the desktop
  carried six.

- **The sixth badge's mark is a FontAwesome glyph, and it had to be.** It was
  `bag.svg` first, picked because it came from the same folder as the other
  five — and it read as a wire drawing beside five solid ones: «اون آیکون هم
  باید مث اون ۵ تا توپر باشه». `bag.svg` has no `stroke` attribute, so a check
  for one passes it; it is an *outline drawn as a filled path*. **Look at an
  icon, do not test it for strokes.**

  Every icon in that folder was rendered and looked at afterwards. The
  genuinely solid ones are the five already in use plus a credit card, three
  flames and some user silhouettes — the template's set is line art, and it has
  no filled box or bag at all. FontAwesome 6 is already shipped and the phone
  drawer's marks already come out of it, so `fa-solid fa-boxes-stacked` is the
  sixth. It matches because it is painted with the same `#7D6324 → #CE9E29`
  ramp the SVGs carry, clipped to the glyph, at the same 32px the five files are
  capped to.

- **The badges' gold icons are reproducible now.** `theme/recolor-svg.js` only
  ever recoloured what the *template's* own page referenced in one of three
  reds; the trust row's icons carry #FD5B44 and #0077FF and their gold siblings
  had been made by hand, so five files in the repo were produced by no command.
  The script now carries an explicit `EXTRAS` list naming each source and its
  colour, and re-running it rewrites the five that already existed **byte for
  byte** — which is the check that the list describes what was done by hand.

## The drawer as an island, and two heights that had drifted

- **The panel is an island, at the header island's numbers.** «منو بجای اینکه از
  بالا پایین و راست بچسبه باید جزیره ای بشه». 10 from the top, the foot and the
  outer edge, corner 19.2, the template's 3px gold side rule gone — the scrim
  behind it (rgba(0,0,0,0.6)) draws the boundary at 255 against 102 and needs no
  hairline, and no drop shadow either: the panel is not sitting on white, so
  «لبه پنهان» has nothing to rescue here.

  **It costs 20px of the drawer's height budget.** That was paid for in the same
  round by «دسترسی سریع» coming off — the label over the three shortcuts and
  the gold rule that ran off the end of it, both asked for by name — which gave
  back its 19.5 line and its 10 margin. Measured after, and these are the two
  numbers that budget states: **14px above the button on a 390×844 and 11 on a
  375×667**, both unchanged, neither scrolling. A 320×568 scrolls by 48 and
  scrolled by 50 before any of this; it is below the shortest screen still in
  service and was never in the budget.

  With that label gone, every `.vp-drawer-label` left is inside
  `.vp-drawer-heading`, which had always turned the gold rule off — so the
  `::after` that drew it was drawing nothing anywhere and went with it.

- **The best seller's strip and the stepped sale's are one height, and it broke
  once already.** The client levelled them by hand — «ارتفاع باکس اسم محصول و
  قیمت … مث حراج پله ای بشه» — and `.vp-best-label`'s own rule says the number
  is read off `.vp-deal-label` and that "if that one moves again these move with
  them". A later round took a tenth off the sale strip below 992 and the best
  seller's stayed where it was, so the pair drifted 5.28 apart on a phone and
  nowhere else, and the client asked for it a second time. Both are 47.52 with a
  23.76 corner below 992 now, and the browse circle and the swatch pill are
  47.52 with them — **all four are one height by construction**, which is why
  the number is stated in one place. Desktop is 52.8 throughout and is level.

  The corner is half the height on purpose: these are pills, and a height moved
  without its radius leaves one of them a rounded rectangle beside a pill. The
  shape is what is being matched, not only the number.

## The hero card on a phone

Four changes, **all inside `max-width: 991.98px`, and the desktop card is not
touched by any of them.** Its 72px corner, its 67px title and its 94px of
`padding-block` are the decisions this file records above and they stand —
verified rather than asserted: `check-parity.js` renders at 992 and up and the
four page heights did not move by a pixel.

| | desktop | phone, before | phone, now |
|---|---|---|---|
| card height | 450.4 | 607 | **436.9** |
| corner | 72 | 72 | **43.2** |
| the shoe's name | 67 | 67 | **30.15** |
| its stem | — | 5.75 | **6.75** |
| the star | — | left | **right** |
| gap under the band | 40 | 22 | **13.5** |

The last column is after the tenth described below. It was 455 / 33.5 / 7.50 /
9 when this section was first written; the client asked for another tenth off
the card, a tenth off the name in both size and stem, and half as much again
under the band, and the table is the current numbers rather than the first
ones.

**The height is the copy column's, not the shot's** — the same rule the desktop
card has always had. The template pads that column 120 above and 20 below on a
phone and the card is whatever that plus the type comes to. Halving the title
took 607 to 538 on its own; the rest is the top padding, 120 → 37. Any further
change to the copy lands there.

### The tenth, and where it came from

**Measure the card before taking a percentage of it.** It was 485.5 when this
was asked, not the 455 recorded above — the three passes after that number was
written (the shoe above the words, the star at 84.84, the card inset 9 a side)
each moved it and none updated the table. A tenth of 485.5 is 48.55.

It is also 485.5 at 375, 390, 430, 575, 767 and 991 alike, which the card on
`main` never was. The shot is stated at `width: 299px` on a phone rather than
sized off its column, so neither half of the card follows the viewport and one
number is a tenth at every width.

Where the 48.55 came from, and where it deliberately did not:

- **Not the shot, and not the star.** The shoe is 299 wide and the star is
  pinned to it at (283, 157), read off a screenshot the client marked up and
  bisected against the star's own rendered centre over three rounds. Narrowing
  the shoe moves the heel out from under the star and reopens all of it.
- **6.93 from the name** coming down a tenth — the line box 75 → 67.5, and the
  heading's `-0.17em` margin handing 0.57 back as the type shrinks.
- **41.62 off the copy's four gaps, all four by the same factor** — 112 →
  70.38, which is 0.6284 on each, so the block keeps its proportions and is
  closed up rather than re-spaced: 37 → 23.25 above the eyebrow, 25 → 15.71 to
  the name, 30 → 18.85 to the button, 20 → 12.57 below it.

**The stroke had to be re-measured, not scaled.** It does not follow the type,
so a tenth off the size alone would have left the name *thicker* in proportion.
Same method as the 1.7: render at 4×, take every run of ink across every row of
the heading, read the median as the stem. 7.50 before, a tenth off is 6.75, and
`-webkit-text-stroke-width: 1.53px` lands it on 6.75 exactly.

**The star did not move and did not need to.** It is pinned inside the image
column and that column is `order: 1`, so it sits at the card's top edge whatever
the copy below it does: 31 from the card's top before and after, the shoe still
spanning 45.5 to 344.5. Its *page* coordinate went 157 → 161.9, which is the
band gap going 9 → 13.5 carrying the whole card down 4.5. The circle was drawn
around a place on the shoe, not a place on the screen.

**The gap under the band is now the one number that is not 9.** The band sits 9
from the top and both sides and the gap beneath it was 9 to match; «فاصله هدر
با هیرو ۵۰ درصد بیشتر بشه» takes it to 13.5 on its own. The other three sides
are untouched, so this is one line to change if it is ever wanted back.

Verified: `check-parity.js` identical at all four widths; the whole desktop page
screenshotted and diffed pixel by pixel before and after at 992, 1200, 1440 and
1920 — 0 differing, which is the check parity cannot make, since a change
landing on both copies is still zero difference; `check-overflow.js` clean;
226 tests, 879 assertions green.

**«۳۰ درصد ضخیم‌تر» could not come from the weight.** This is Vazirmatn — the
rule names Cairo first and Cairo is not loaded — and it was already at 900, the
top of the variable axis. It comes from a stroke instead, and the number was
measured rather than reasoned: the stems read 5.75px across a 4× render at
33.5/900, and `-webkit-text-stroke-width: 1.7px` puts them at 7.50, which is
+30.4%. **The stroke does not scale with the type**, so re-measure if the size
moves again.

**The swap is one property.** The image column is `direction: rtl`, so its two
children lay out from the right — the photograph is first in the DOM and took
the right, leaving the star the left. `direction: ltr` on that one box swaps
them without either being moved by hand, and it is that box's own writing
direction, so nothing else in the card is affected. The star's
`right: calc(9% + 41px)` was placing it against the photograph in the old order
and pulled the wrong way in the new one, so it is cleared on the phone.

## The phone header's final numbers

The band has been through four rounds with the client and these are where it
landed. Everything below 992 unless it says otherwise; the desktop is untouched
at 18 / 24 / 76 and never had a complaint against it.

| | desktop | phone |
|---|---|---|
| island margin, both sides | 18 | **9** |
| island corner | 24 | **19.2** |
| band height | 76 | **66** |
| air round the mark, all three sides | 12 | **12** |
| mark, basket, menu | 52 / 48 / — | **42 each, square, radius 10** |
| «ویکی پلاس» | 19, weight 800 | **14.52, weight 900** |
| the line under it | 13 | **9.35, `nowrap`** |

Two of those hold each other up. **The band's height is arithmetic on the
mark**: air is `(height − mark) / 2` and has to equal the container's
`padding-inline`, which is 12. Change the mark and the height changes with it or
the air stops being equal on the three sides it touches.

**The strapline's single line is why the margins halved.** «فروشگاه کیف و کفش
زنانه» was breaking after «و» — and the break point moves with the phone's
width, so it read differently on every screen. `nowrap` needs the room, and 9 a
side rather than 18 is where the room came from. Measured at 375, the narrowest
screen still in service.

The name's weight is real, not synthetic: `font-family` names Cairo first and
Cairo is not loaded, so this is Vazirmatn — a variable font, where 900 is a
weight the file actually carries.

## Equal air, and the three bars

The band states its own height and centres everything in it, so the air above
and below the mark is `(height − mark) / 2` and the air beside it is the
container's `padding-inline`. That padding is 12, derived from (76 − 52) / 2 when
the mark was 52 — and 12 on all three sides is the number the desktop band has
had since the beginning.

On a phone the mark is 42 in the same 76: 17 above and below against 12 at the
side. The band is **66** below 576 now — (66 − 42) / 2 = 12 — and the three are
one number again. The basket and the menu are 42 there too, so they take the
same 12 with them. This is the whole reason the height is stated once rather
than falling out of somebody's padding.

**Centring a glyph's box is not centring its ink.** The menu button's three bars
were nine from the top and fifteen from the foot — `display: block` with a 26px
line-height in a 42px button. Flex centring fixed the box and left the ink 14/16,
because FontAwesome's `fa-bars` sits high in its own em box; one pixel of
`translateY` makes it 15/15. Read off the rendered pixels, because the element
was already exactly where it should be.

The bars are white, as asked. Noting rather than arguing: this file measured
white on this gold at 2.6:1 and ink at 5.1, and chose ink everywhere else it
fills the ramp. 2.6:1 is under the 3:1 WCAG asks of a graphical control, so if
it ever reads faint in daylight, that is why and ink is the fix.

## The header on a phone, corrected

I read «لوگو باید سایزش نصف بشه» as the whole lockup and halved the mark with
it. It was the **writing** that was meant, and both of its lines are the sign:
«ویکی پلاس» and «فروشگاه کیف و کفش زنانه». The mark is back at full size, and
only the type moved — 20 → 11 on the name, 11 → 8.5 on the line under it, which
is as near half as the second line goes and still reads. At 5.5 it is not small
type, it is a grey smudge.

Three more, from the same pass:

- **The basket and the menu are squares the size of the mark.** They were 48 to
  the mark's 42, and the menu was a *circle* — three controls in one band at two
  sizes and two shapes. The radius follows the tile's own ratio, 10 of 42.
- **The menu button's gold is the page's gold.** It carried `#7D6324 → #CE9E29`,
  the ramp sampled off the template's chart, which this repo keeps for SVG icons
  and nowhere else. Beside the basket it read dark olive — the same fault
  «خرید با قیمت فعلی» had, for the same reason.
- **The drawer's search has no submit button.** Enter searches, and a phone's own
  keyboard puts a search key where return would be. The gold block in the field
  was a third gold on a panel that has one.

**The sizing block lives at the end of `tweaks.css` on purpose.** `.th-header
.header-button .icon-btn.sideMenuToggler` is already set to 48/18 nine hundred
lines above at exactly that specificity, so the only thing deciding between them
is which comes last. Put it up with the rest of the header's rules and the
basket silently stays 48 while the menu beside it goes to 42 — which is what
happened on the first attempt.

## The header on a phone

«هدر نسخه موبایل به هم ریخته است», with a photograph: the shop's name broken
across two lines, the basket and the menu adrift on a second row under it, the
band 93 tall instead of 76.

One cause. The header's search field is given a fixed width — 320, and 185 below
1400 — because left to itself it takes whatever is going and pushes the
navigation off centre. Below 992 its *contents* collapse to nothing (measured:
the field is 0 tall on a phone) but the element went on claiming its 185px, and
185 plus two 48px buttons plus the lockup does not cross a 354px island.
Bootstrap did the only thing it could and wrapped the row.

Width with no height is the worst of both — room reserved for something nobody
can see or use. It is `display: none` below 992 now, and the island measures 76
on a phone, the same as at 1440 and the same as the number this file records.

That left the phone with no search at all, so **the drawer has one**:
`.vp-drawer-search`, at the top of its body, posting to the same `/search` the
header's field does. It cost 6px more than a 375×667 screen had, which came back
off the short-screen block — the drawer still fits with nothing scrolling.
