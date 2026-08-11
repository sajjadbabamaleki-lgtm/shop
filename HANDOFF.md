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

- **The hairline round the tile has to be a `border`, not an inset shadow.**
  An inset box-shadow on a replaced element paints *underneath* its content, so
  on an opaque PNG it is invisible. It was written as a shadow for a long time
  and never rendered once: measured across the tile's edge the band stepped
  `247 247 247 253 253` straight into the tile with no line anywhere. As a
  border it reads `247 223 253`. `box-sizing: border-box` keeps the tile 52px.
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
