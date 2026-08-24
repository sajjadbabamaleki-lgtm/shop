# VikyPlus — where this stands

> ⛔ **This file describes `claude/wiki-plus-latest-work-enpjl1`.** `main`
> carries the same work since PR #45 was merged; before that it was 88 commits
> behind, and a session spent an afternoon building on it. Check the two are
> still in step before you start, and never push to a `main` that is behind — a
> push to `main` deploys `main` and puts the old site live. See the block at the
> top of `CLAUDE.md`.

Read `CLAUDE.md` first: it says where things are, how the page is built, and
carries the codenames for problems that have already cost a day. This file says
what is finished, what is not, and what the finished part is not allowed to
lose.

## The gold moved, on 2026-08-16

The gold was sampled off the brand's own sale chart and stayed there for the
whole build. The client then sent a photograph of a yellow card and asked for
it — «گلدا بشن این گلد ببینم چطو میشه» — first on the phone to look at, then
everywhere. **The gold below is the gold now. Anything in this file or in
`tweaks.css` quoting the left-hand column is describing how a decision was
reached, not what the page currently paints.**

| was | is | job |
| --- | --- | --- |
| `#C0972F` | `#DAB226` | the fill, 42 declarations |
| `#C0972F` | `#BB9920` | the same value where it was text |
| `#E3B54A` | `#EFC94F` | the lit end of every gradient — this is the card's own colour |
| `#A47F25` | `#A08119` | the sampled midpoint, and `--theme-color` |
| `#8F7022` | `#8B7217` | text on white |
| `#6E5416` | `#6B550E` | text on white, darkest |
| `#CA9A24` | `#C49D16` | text on the footer's ink |
| `#CA9A24` | `#E5B71A` | `--gr-color2`, the gradient partner |
| `#7D6324` | `#93791F` | the button and icon ramp, dark stop |
| `#7D6324` | `#7A641A` | the same value where it was text |
| `#CE9E29` | `#E3B825` | the button and icon ramp, light stop |
| `#C29A31` | `#D9B42B` | the stepped sale's second podium |
| `#8C6E1F` | `#A38619` | its foot |

**One exception, asked for the same day: the discount star.** «ستاره تخفیف
بشه گلد قبلی» — so `.vp-burst` (the hero's) and `.vp-deal-burst` (the sale
cards' and the best sellers') keep `#C0972F → #E3B54A`, and they are the only
gold on the page still on the sampled ramp. They are held in
`--vp-gold-burst-top` and `--vp-gold-burst-foot` rather than written inline,
so that moving the ramp again turns them up as a question instead of sweeping
them along. Both stars, not one: they are the same mark at two sizes, and this
file already records a round where they were told apart by accident.
`make-rtl-page.js` writes those two values into the markup and the stylesheet
pins the same two onto the `stop-color` attributes, so neither half can drift
without the other noticing.

**The numbers the change was not allowed to lose, and did not.** The card read
`#EFC94F`; against `#E3B54A` that is H +3.8°, S +10.1pp, L +3.3pp, and fills
took it whole. Text did not, because the saturation alone costs contrast —
`#8F7022` carried straight through lands at 3.40:1 on white, under AA, from
4.66:1. So the six text golds took the hue and saturation and then had their
lightness solved to hold their original relative luminance. Every ratio this
repo had measured survived, none moving by more than 0.03:

| | on white | ink on it |
| --- | --- | --- |
| `#A08119` (was `#A47F25`) | 3.72 → 3.72 | 5.08 → 5.09 |
| `#8B7217` (was `#8F7022`) | 4.66 → 4.65 | 4.06 → 4.07 |
| `#6B550E` (was `#6E5416`) | 7.14 → 7.17 | 2.65 → 2.64 |
| `#C49D16` (was `#CA9A24`) | 2.57 → 2.57 | 7.35 → 7.37 |
| `#7A641A` (was `#7D6324`) | 5.70 → 5.73 | 3.32 → 3.30 |
| `#BB9920` (was `#C0972F`) | 2.72 → 2.73 | 6.94 → 6.93 |

**Where the gold lives now.** `tweaks.css` holds it in thirteen custom
properties in one `:root` block near the top — it used to be ten hex literals
across 128 declarations, which is why it had never moved before. Four places
carry it outside that block, all of them because CSS cannot reach inside an
`<img>`, and **all four must be re-run together if it ever moves again**:

    node theme/recolor-svg.js           # the trust badges and the lockups
    node theme/make-category-icons.js   # the eight category marks
    node theme/make-rtl-page.js         # the star — on the OLD gold, see above
    node theme/sync-storefront-assets.js

The two inline bursts are also pinned from the stylesheet — `stop-color` is a
presentation attribute and loses to any rule — so those follow the variables
even before the page is regenerated. The baked SVGs have no such reprieve.

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
  counts, which four brands it features, and the pairing that puts a shoe's
  price under a category's photograph are all
  in `config/storefront.php` under `placeholders`. Seeding an invented number into the tables would make it
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
| gold | `#DAB226 → #EFC94F` on the button, the search disc and the burst |

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

## Four off one message: the watermark, the shoe, the buttons, the brand card

All four are phone-only, all four were measured, and the desktop was read
rather than assumed afterwards — the product page renders 0 differing pixels at
1200, 1440 and 1920, and the home page differs on no row that two renders of
the *same* tree do not also differ on. (That noise is the known one: it starts
at the `.vp-deal` cards and their `.vp-enter` reveal, which is the signature the
parity note at the end of this file describes.)

**The brand's name behind the shoe is gone, and the tile is white by
construction.** «اون نوشته پشت کفش که نوشته گلدن گوس باید پاک بشه بکگراند محصول
و محیط باید سفید صددرصد باشه». `.vp-pdp-mark` is off the Blade, so its rule and
the `overflow: hidden` that clipped it both went with it. Nothing was painted
white to achieve the second half: the tile states no background, every
photograph in the catalogue is a cut-out on transparency, and the page under
both is #FFFFFF — sampled after at 255 across the band the word used to cross
and across the gap under the shoe. `brands.name_latin` is still a real column
and nothing reads it now.

**The shoe drops a tenth of its tile**, «باید تا جایی که خط کشیدم بیاد پایین».
The photograph was never off-centre — all five shots are 1400×990 with their ink
centred, and `contain` centres the canvas — so what was wrong was an even split
in a tile whose foot is the last thing above the dots. A tenth is where two
numbers meet: at 430 it puts the goose's foot at 231.0 against the 233.7 the
client's line reads, and it is also the smallest slack any photograph in the
catalogue has under its ink (Jordan, 9.9%), so at 10% the tallest shot lands on
the tile's foot and every other one above it. Clearance at 320/360/375/390/430/575
is 28.6/33.1/35.0/36.1/40.0/54.5 — 15.3–15.6% of the tile at every width, which
is the percentage doing its job.

**The three «خرید کنید» buttons are the hero button's now.** «شکل و اندازه و
نوشتشون باید مث اون دکمه تو هدر بشن فلش هم حذف بشه». They were three shapes and
none of them agreed with the button at the top of the page:

| | was | now |
|---|---|---|
| hero `.th-btn` | 120.05 × 40.78, 14.4/800, r 11.2 | untouched — it is the reference |
| offer banner `.th-btn` | 157.61 × 51, 18/500, r 48 | 98.72 × 40.78, 14.4/800, r 11.2 |
| offer banner `.line-btn` | 102.05 × 25, 16/600, no ground | the same |
| daily deal `.vp-daily-deal-cta` | 149.20 × 58, 17/700, r 48 | the same |

**Those numbers are the hero's and are not restated as its own decision.** They
live at `.heroSlide6 .hero-inner .th-btn`, in two blocks — the weight and the
family with the hero's type, the 0.8 measures with the rest — and if either
moves these move with it. The pair is written down because the last time two
blocks held each other up without being named, the best seller's strip and the
sale's drifted 5.28 apart and the client had to ask twice.

**The label is white because the hero's is, and that is the one part worth
arguing with.** This file measured the ramp and chose ink everywhere else it is
filled: white is 2.72:1 on the ramp's dark stop and 1.91:1 on its light one,
against ink's 6.99:1 and 9.95:1, and 4.5:1 is what text this size is asked for.
«مث اون دکمه» is the instruction and the hero has carried white through several
rounds without a word against it, so it is matched and noted rather than argued
— one property to put back.

The arrows come off in the stylesheet, all three, and **that is deliberate**:
the daily deal's is an `<i>` in the markup and taking it out of the Blade was
the first attempt, which would have removed it at every width. A phone request
is not licence to touch the desktop, so it is `display: none` below 992 beside
the two `content: none`s, and the desktop keeps all three.

**The brand card's shadow goes round all four sides.** «اون سایه ای هم که پایین
کارت برندها هست باید به همون مقدار ۲ طرف کارت هم باشه و بالا با شدت یکم کمتر»,
and in the same message «فاصله پایین آخرین عکسا و آیتم باید با بغلا یکسان و هم
اندازه باشه». **Those are one fault.** The padding is already 10 on all four
sides and measures 10 at 320 through 575; what was not equal was the ink.
`0 18px 32px -22px` is 18 of offset against 22 of negative spread, so the whole
shadow fell under the foot — the foot read as 10px of white and then 12 more of
grey, the sides as 10px of white and nothing. `0 1px 14px -2px rgba(16,17,17,0.15)`
instead, measured outward from each edge at 390:

```
top    254 254 254 253 252 251 250 248 247 245
foot   253 253 252 251 249 248 246 244 242 240
left   254 253 253 252 251 249 248 246 244 242
right  254 254 253 252 251 250 248 247 245 243
```

**The foot is exactly where it was** — 240 at the edge, to the level — which is
the point: the client asked for the sides to match what the foot already does.
The sides land within two or three of it and the top five lighter. They cannot
be made exactly equal, because a downward offset puts the sides at the average
of the top and the foot, so «sides like the foot» and «top lighter» pull against
each other; 1px is where both are satisfied and 2px costs the foot two levels.
The reach is a shade under 10px, which is all the room there is — the card sits
10 from each edge of the screen.

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
| brand strip | «برندهای موجود» | our layout, real content bar the counts | see below |
| footer | «Menu» | 0 of 5 | **template** |

So two blocks are still wholly the template's: **the offer banner and the
footer.** The banner reads BLACK FRIDAY / SPECIAL OFFER over ADIDAS SHOES on a
stock photograph; the footer carries «Menu», the column headings and an address
in Germany for a furniture company.

**The brand strip is ours in shape, and its content is real now bar one
thing.** The template's carousel is gone, replaced by four tiles on one white
card — a photo mosaic per tile with a glass plate floating in the middle
carrying the brand's mark, its name and a stock count. The layout is settled
and measured. Of the three things that used to be stand-ins here, two are
finished: the client sent a set of three photographs per brand and a logo for
every mark. **Only the counts are still invented**, and they are the one part
nobody can supply — they are a number the catalogue would have to hold. All of
it lives in one array (`BRANDS`) at the top of the brand block in
`theme/make-rtl-page.js`, mirrored by `placeholders.brand_strip` on the
Laravel side:

- **the marks** — **all four are real now**, and no abstract stand-in is left
  on this block. **A mark is the one part of a tile that lives in the
  database**, and that cost a round: the seeder was updated, the run went
  green, and the live site went on showing the template's marks with a broken
  image where On's should be, because a deploy migrates and does not re-seed
  (CLAUDE.md's deploy block spells this out). Correcting one is a migration —
  `2026_08_16_070000_put_the_real_marks_on_the_brands.php` is the one that did
  it. `HomePageTest` now asserts every brand on the strip has a `logo_path`
  *and that the file is in `public/`*, because a null and a path to a file
  nobody built read identically in the markup. `brand_5_2.png` is the template's own genuine Nike swoosh;
  the other three go through `theme/make-brand-marks.js`, which puts each in
  the page's ink on transparency — the plate is white glass, so a white or
  unpainted mark on it is an empty slot — whatever state it arrived in. Three
  states, one per mark, and the script names them: Jordan's PNG already
  carried its own alpha, New Balance's was black on opaque white, and On's had
  no file at all and had to be found and cut out of the poster it was sent
  inside. Everything is trimmed to its own content at the end, or the margin a
  file happened to arrive with would shrink the mark inside the slot by a
  different amount for each. The slot is a fixed 36×36 box rather than sized off the
  artwork, so a real logo drops in without touching the CSS — **and that only
  became true when On arrived.** `.vp-brand-logo` alone loses on specificity
  to the template's `img:not([draggable]) { height: auto }`, which is (0,1,1)
  against a lone class's (0,1,0), so every mark was being sized by its own file
  the whole time. It never showed because every mark until On's was wider than
  it was tall. On's is 51×104 and drew 73px tall, across the plate and over the
  name. The rule is `.vp-brand-plate .vp-brand-logo` now. Same trap as the
  phone drawer's tile; the note on `.vp-shop-cat img` names it too.
- **the photographs** — **all four tiles carry the brand's own now, and this
  part is finished.** The client supplied a set of three per brand («این ۳
  تصویر در ۳ کادر اول که نایک هستش بیاد») and named which one leads: the shoe
  on its own, «اون کفش تکی که پشتش نوشته نایک برای تصویر بزرگس». Every set
  therefore reads the same way down the tile — **shoe, kit, athlete** — so a
  set sent tomorrow needs no decision made about it. Sources live in
  `theme/brand-src`, `node theme/make-brand-photos.js` builds them into
  `assets/img/brand/vikyplus-*.webp`, and each path is named twice: in
  `BRANDS` for the static page and in `placeholders.brand_strip` for Laravel.
  `HomePageTest` asserts every brand carrying `photos` names all three *and
  that the files are in `public/`* — the build and the asset sync are two steps
  outside the application, and a src that points at nothing looks perfectly
  correct in the markup.

  **The fourth tile is On, not گلدن گوس.** The client's fourth set was On's, and
  «کادر چهارم آن رانینگ بشه». That is a change of which brand the strip
  *features* and nothing else: `placeholders.brand_strip` is the `whereIn` the
  query runs, so removing گلدن گوس from it takes it off this block while
  leaving it an active brand with its shoe, its product page, its place in the
  best-sellers filter and its hero slide. On was already in the catalogue —
  it sells the daily deal — so nothing was invented to make this work. **Its
  name on the tile reads «اون»**, which is the spelling the catalogue already
  uses in «کتونی اون کلادتیلت» on the same page; if the client wants «آن
  رانینگ» that is the `name` in `CatalogueSeeder::BRANDS` and a re-seed.

  The size is computed, not typed: each file is scaled to the smallest size
  that still *covers* its cell, which is the same arithmetic `object-fit`
  does, against the cell as measured at 1920 and doubled for a 2x screen —
  520×680 for the lead, 384×336 for a small one. That matters because the
  cells are two shapes and the sources are too: a square shot in the tall lead
  cell is bound by its height, a 4:5 poster in the same cell by its width, and
  one typed width would be wrong for one of them. The row is fluid and keeps
  growing past 1920, so this is a stated ceiling rather than a guarantee.

  **Three tiles now show photographs carrying the brand's real mark**, which
  makes the template's abstract stand-in on the plate beside them a good deal
  more visible than it used to be. That is the next thing to fix on this
  block, and it is a logo file each, nothing more.
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
therefore handing that over, and a phone number is not a secret.

The first answer was to ask for the number off one of their own orders — the
same proof `/orders` already accepts from somebody not signed in, a receipt,
which a stranger with a phone number does not have. It was a stand-in for the
usual answer, a one-time code by SMS, and the note here said «when SMS arrives,
that check becomes a code».

### It became a code — and then the password came back beside it

The first cut of this went all the way: **no password at all**, for anybody,
ever. One way in, a number and a five-digit code. The client looked at it and
gave the reason it could not be the only way:

> بنظرم ورود با رمز باشه، ورود با کد یبار مصرف هم یه آپشن باشه چون اصلا ممکنه
> اون شماره اون لحظه در دسترس شخص نباشه که کد بیاد

A one-time code is only a way in *while the telephone is in the room*. A phone
that is flat, lost, or on a desk in another city locks a customer out of their
own order history, and «wait until you are near your phone» is not an answer a
shop gets to give. So both, and the screen asks for the number first:

| the number | what the screen asks for next |
|---|---|
| has a password | the password — and **no SMS is sent**, because sending one costs money and the phone may not be to hand |
| has none | a code, straight away; asking twice would be a screen that says nothing |

The password step carries «ورود با کد یکبار مصرف» under it, which is the case
above said out loud. The code step carries the resend and «شماره را عوض کن».

**A password is only ever set behind a code.** On the code step for a number
that has none — which is every new number and every row checkout has made — and
on `/account` behind the old one. That is the receipt claim's job, done
properly: the reason a password could never simply be set on a
checkout-made row was that a phone number is not a secret, and a code sent to
the number is exactly the secret that was missing. There is still no
registration form. The code step is it.

**The password has no minimum length**, at the client's explicit instruction —
«رمز در اینجا کاربر هرچه زد اوکییه، مهم نیست حتما ۸ نویسه باشه». Nothing in the
application may lean on a shopper's password being hard to guess;
`test_a_password_of_any_length_is_allowed` says so out loud, because a
validation rule quietly growing back is the kind of thing nobody notices until
a customer cannot sign in with the password they were allowed to set. What
stands against guessing is the throttle on `/account/password` and the fact
that the number has to be established first.

What holds the code up is in `LoginCode` and nowhere else, so the answer to
"how long is a code good for" is one place:

| | |
|---|---|
| stored | **hashed** — a dump of `login_codes` must not sign anybody in, not even for two minutes |
| good for | 120 seconds |
| good for | **one** use — `consumed_at` is stamped the moment it works, because an SMS stays on a phone |
| survives | 5 wrong guesses |
| one at a time | a new code spends the one before it; the same number waits 90 seconds |

**The number is the session's, never the form's** — for the code *and* for the
password. Posting somebody else's number alongside your own code is the attack
this shape of screen has, and two tests hold each half of it down:
`test_the_number_comes_from_the_session_and_not_from_the_form` and
`test_a_password_posted_with_no_number_in_play_goes_back_to_the_start`. Every
route is throttled, and not all with the same number: sending is ten an hour per
browser because every request is an SMS somebody pays for; verifying and the
password are twenty per ten minutes, on top of the five attempts a code carries.

The reply to «send me a code» is the same whether or not the number is known
here. Saying «this number is not registered» would turn the form into a way of
asking the shop which of its customers a number belongs to.

### The SMS has a provider now — and it is still switched off by default

The client bought a registered service on **ملی پیامک**, so Melipayamak is
implemented and the code is written. What is left is settings, and they are the
client's to fill in.

`config('services.sms.driver')` is still `log` by default, so a code lands in
`storage/logs/laravel.log` and on no telephone. **`SmsServiceProvider` refuses
to build a sender at all if that is still true in production**, so the shop
cannot go live quietly swallowing its own sign-in codes — which also means that
until the settings below are on the Liara app, **the shopper sign-in 500s on the
live site**, deliberately and loudly. Nothing else does: the refusal is inside
the singleton's factory, so the catalogue, the basket and the checkout are
untouched.

Melipayamak is **two drivers**, because the provider has two doors and an
account has whichever it was sold:

| `SMS_DRIVER` | host | signs with | needs |
|---|---|---|---|
| `melipayamak` | console.melipayamak.com | an API key | `SMS_KEY`, `SMS_PATTERN` |
| `melipayamak.panel` | rest.payamak-panel.com | the panel's username and password | `SMS_USER`, `SMS_KEY`, `SMS_PATTERN` |

Prefer the first: the key is revocable from the panel on its own, so the server
and the owner do not share a credential. They are two names rather than one
class reading whichever credentials happen to be filled in, because "whichever
is set" is a runtime value and this application has been bitten by one of those
before.

`SMS_PATTERN` is the **id** of the approved pattern — «کد متن» in the panel,
`bodyId` in the documentation — not its text. The text lives with Melipayamak,
because an Iranian provider will not carry an unapproved transactional message,
and it has to be a *service* pattern rather than an advertising one: an
advertising line does not reach anybody who has opted out of advertising, which
for a sign-in code means those customers simply cannot get in.

None of these belong in the repository. The deploy ships no `.env`, so they are
environment variables on the Liara app.

**The interface carries the message twice**, and this is the part worth reading
before changing anything: `send(string $phone, string $message, array $args)`.
`$message` is the whole sentence, for a driver that sends text; `$args` is the
same information as data, in the order the pattern expects. Both are passed so
that no pattern driver has to dig a six-digit code back out of a Persian
sentence with a regular expression — that would work until somebody reworded
the sentence, and then it would put a blank code on a real telephone with
nothing anywhere going red.

`SMS_LINE` is unused today. Both Melipayamak drivers send a pattern on a shared
line, which is what an account gets without renting a number; the setting stays
for the day the shop rents one.

**`SmsSenderTest` fakes the HTTP layer, so a green run means the shop asked
correctly — not that a telephone rang.** Whether Melipayamak accepts the
account, the pattern and the line can only be checked on the live site, and this
container cannot reach the provider to check it early.

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

## The hero, three passes that were made and then unmade

**All three of these are reverted. The hero is back to where it stood before
the first of them**, and the check is the strongest kind: the whole page
rendered against commit `5d751f2` at 320, 375, 390, 575, 991, 1200 and 1920 —
pixel-identical at all seven. «هیرو هم به حالت قبل برگرده به قبل از دستور حذف
نوشته کوچیک رو هیرو».

They are written down because each was measured, and a number that was measured
once should not have to be measured again if the client changes their mind
back:

- **The eyebrow off the phone.** `.sub-title` hidden below 992. The card went
  436.9 → 393.2 — its 28px line box and the 15.71 under it — and nothing was
  re-spaced to hold the old height.
- **A third tenth off the name.** 30.15 → 27.135, line box 33.75 → 30.375,
  stroke 1.53 → 1.377 for a stem of 6.0625 against 6.075 asked. The stroke was
  re-measured, not scaled, and 1.377 happens to be 0.9 × 1.53 as well.
- **The shoe 5% down and the card 5% shorter.**

«سایز محصول تو هیرو ۵ درصد کوچیکتر بشه ارتفاع هیرو هم ۵ درصد کوتاهتر بشه».

- **The shoe is 299 → 284.05**, its height following on its own, 269.85 →
  256.36. That takes 13.49 off the card by itself; the remaining 5.86 of the 5%
  comes off the copy's gaps, all scaled by one factor (0.8929) exactly as the
  earlier tenth was taken. Card 386.95 → 367.52 against 367.60 asked, at 375,
  390, 430, 575, 767 and 991 alike.
- **The star had to be re-pinned, and this is the case the block warns about.**
  Narrowing the shoe walks the heel out from under it. Its place was taken as a
  *fraction of the shoe's own box* first — 0.7991 across, 0.2632 down, which is
  where (220, 31) had put it — and the new (215.548, 27.449) is that fraction
  read back off the resized photograph. Keep doing it that way: the screenshot
  the original numbers came from is a picture of a 299-wide shoe and cannot be
  re-read against any other.
- **Measure this card with motion disabled.** The photograph carries the
  template's `slideinrighthero`, which transforms it, so a reading taken while
  that is in flight reports the shoe 282 or 270 wide instead of 284.05 — and
  the first pin computed off one of those landed the star a pixel and a half
  out. `emulateMedia({reducedMotion:'reduce'})` and a wait, as
  `check-parity.js` does.

**Two things above outlive the revert.** Measuring this card with motion
disabled is true whatever the numbers are. And so is taking the star's place as
a fraction of the shoe's box rather than re-deriving it: the screenshot the
original (283, 157) came from is a picture of a 299-wide shoe and cannot be
read against any other.

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

## Five story circles — tried, and parked

«۵ تا حالت استوری دایره ای بزار بالای هیرو و زیر هدر ببینیم چطور میشه» — asked
for as a look to try. The answer came back «بنظرم استوری هارو هاید کن», so
**the strip is off**: one `display` in the phone block, and everything below is
still measured and still correct if it is ever wanted again.

It is parked rather than deleted on purpose. The markup lives in two places and
the make-blade region below exists only for it — deleting all of that means
rebuilding it to try the idea a second time, and the whole point of a look is
that it might come back.

- **Phone only.** `display: none` is the default and the phone turns it on. The
  desktop has a settled rhythm from the island to the hero and does not gain a
  band while nobody is looking; before-and-after over the full scroll height at
  992, 1200, 1440 and 1920 is zero differing pixels.
- **The five are the catalogue's own first five sections**, the photographs the
  tiles already use and the names they already carry — `$categories->take(5)`
  in the Blade, so the strip and the tiles cannot describe two different shops.
- **No caption under the circles** («نباید زیر عنوان داشته باشن استوری ها»).
  The name moved onto the link as `aria-label` rather than being deleted: each
  link's whole content is a photograph with `alt=""`, so without it the link
  announces itself as nothing.
- **It needed a region in `make-blade.js`, and the failure was silent.** The
  strip sits between the header and the hero, and the header's region ran to
  the hero's anchor — so the five circles were written into
  `partials/header.blade.php` *as well as* being rendered from
  `home/stories.blade.php`, twice on the page. Nothing threw: the anchors were
  all still unique and still in order. **Anything inserted between two existing
  regions needs its own entry in `REGIONS`**, and the tell is `git status`
  showing a generated partial modified when you did not touch its markup.

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
  sixth. It matches because it is painted with the same `#93791F → #E3B825`
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
- **The menu button's gold is the page's gold.** It carried the icon ramp
  (`#7D6324 → #CE9E29` then, `#93791F → #E3B825` since the gold moved), which
  this repo keeps for SVG icons and nowhere else. Beside the basket it read dark olive — the same fault
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

## The basket and the product page, off their cards

Three things in one message, all of them about a phone screen that had too many
drawn edges on it.

**«فاصله بین تعداد کالا و مثبت منفی باید نصف بشه».** `.vp-cart-qty`'s gap went
8 → 4. At 8 the `−`, the number and the `+` read as three controls sitting near
each other; at 4 they read as one stepper. The count keeps its own `min-width`,
so the stepper's overall width does not jump when the quantity goes from one
digit to two.

**«در صفحه سبد خرید ما نباید فوتر پایین داشته باشیم».** `body:has(.vp-cart-sum)
.footer-wrapper { display: none }`, below 992 only. The footer is 678px of
four link columns, payment marks and a copyright under a screen whose only job
is «ادامه و ثبت سفارش» — and the totals island is fixed over the top of it
anyway. The desktop basket keeps its footer: there the totals are a card in the
flow and the page ends properly.

**«اون کادرهای گوهو برداااار من بهت نمونه دادم».** The product page was drawing
a card of cards, the same mistake the basket made a round earlier: a
`.vp-shop-panel` with a 1.5px outline and a drop shadow around everything, the
photograph inside it in a *second* framed card with its own 40px corner and
shadow, and the related shelf under it in a third. The reference has none of
them. Below 992 both panels keep their side margins and nothing else, and
`.vp-pdp-shot` loses its ring, its corner and its shadow.

Two things had to move with the frame:

- The photograph's insets. `.vp-pdp-shot img` is `inset: 18% 12% 18%; height:
  64%` on the desktop — the air a framed pane needs around its subject. With no
  frame that is a shoe stranded in a third of the screen, so on a phone it is
  `inset: 0; width/height: 100%`, still `contain`.
- The colourway tiles. They measured 58.8 × 76 and then 66 × 76 through two
  attempted fixes, and there were two separate causes stacked: the desktop rule
  sets a fixed `height: 76px`, which beats any `aspect-ratio`; and the image
  inside was `height: 100%` of a parent whose height is auto, so the percentage
  resolved to `auto`, the image came in at its natural height and the tile grew
  to fit it. The tile now takes `height: auto` and the image is positioned
  `inset: 6px` out of the tile's flow. A ratio on a box loses to its own content
  every time — that is the general shape of it.

Measured after: panel and shot both `background: none; box-shadow: none;
border-radius: 0`, tile 66 × 66, footer `display: none` on `/cart` and present
everywhere else. 233 tests, parity identical at all four widths, no overflow at
390/768/1200/1920.

## One row, one glass sheet, one footer too many

Four from one message, three of them things an earlier round had left behind.

**«قیمت و تعداد باید تو یک ردیف باشن».** There was a `max-width: 375.98px`
block giving the basket line's price and its stepper a row each, and on a
360-wide phone that is what the client was looking at. It was right when it was
written: the quantity was then a number box with a «به‌روزرسانی» button beside
it, about 148px of control, and the two middle columns have 130 at 320 — the
price track collapsed to 0 and the figure ran off the card. That control has
been a `− ۱ +` stepper for two rounds. **A breakpoint that exists for a
component's width has to go when the component does**; left behind it is a rule
nobody can explain from the page.

Deleting it was not enough on its own — 100 of stepper plus 121 of price plus a
12px gap is one pixel more than a 360 line has. Three small things bought the
room:

- The `×` came out of the grid and is absolutely positioned in the card's
  corner. As a column it cost 30 plus a gap on every row — 42 of a 360-wide
  phone for one glyph.
- The column gap went 12 → 8, and `.vp-cart-count`'s `min-width` 24 → 20 (two
  Persian digits measure 18, so the `+` still does not move).
- `.vp-cart-money` may wrap. Below about 340 «تومان» drops under the figure,
  which keeps the price *beside* the quantity instead of above it.

Measured at 320/360/375/390/430/575: one row at every width, price whole and
inside the card, nothing overflowing. One line of price from 360 up.

**«تو این صفحه ثبت سفارش این شیشه ای اضافست».** The checkout's summary is the
same `.vp-cart-sum` as the basket's, so it inherited the whole fixed-glass
island treatment — and landed on top of the form, over the mobile number field,
on a page whose only content is fields to type in. The island now asks for
itself by name: `.vp-cart-sum.is-island`, set on the basket's aside only. A
floating sheet is right where the page is a list you scroll and wrong where the
page is a form.

Taking it off exposed the order underneath: stacked in source order the customer
met «ثبت سفارش» before «قابل پرداخت» — the button to pay above the number being
paid. `.vp-cart:has(.vp-checkout) .vp-cart-sum { order: -1 }` puts the summary
first below 992. On the desktop they are side by side and it never came up.

**The product page's footer and shelf.** `body:has(.vp-pdp-body)` joins the
basket in hiding `.footer-wrapper` below 992 — the page ends on a fixed
«افزودن به سبد» bar and the footer was 678px of somewhere-else behind it. The
related shelf's gutters go to 10 (`--bs-gutter-x/y` on
`.vp-pdp-related .vp-shop-grid`), the number everything else on this phone
stands at; `gy-4` was 24, which is the desktop's air. And its heading is
«نمونه‌های مشابه».

## The phone's own rhythm: 60 from the trust row down

«اون فاصله ۳۰ باید بشه ۶۰ و از اون ۶ آیتم به پایین همه فاصله بین بخش های مختلف
بشه ۶۰».

The desktop has had a rhythm of **150** for a long time — six gaps down the page,
all the same, each carried by exactly one property with the other side zeroed,
so a gap can be read off a single number instead of summed from two paddings
nobody remembers. The rhythm block in `tweaks.css` names all six and says which
property carries which. **That block is the map for this one.**

The phone now takes the same six at **60**, in the same one-property-each way,
plus the gap above the trust row — which was never part of the rhythm and is now
the seventh member of it:

| gap | property | was |
| --- | --- | --- |
| tiles → trust row | `.vp-trust-row-wrap` margin-top | 30 |
| trust row → sale | `.collection-area` padding-block-start | 30 |
| sale → best sellers | `.vp-best-panel` margin-top | 150 |
| best sellers → banner | `.vp-best-section` padding-bottom | 150 |
| banner → daily deal | `.vp-daily-deal-section` padding-top | 150 |
| daily deal → brands | `.vp-brands-panel` margin-top | 150 |
| brands → footer | `.vp-brands-section` padding-bottom | 150 |

The number arrived in three steps and every one of them was looked at on the
page: 13.5 («این دوتا فاصله بشه اندازه فاصله هدر با هیرو» — the header-to-hero
distance, then «جالب نشد»), 30, then 60. It is its own number now, not a copy of
anything else's, which is why nothing in the file says "the same as".

**The order trap.** All seven overrides sit in one `max-width: 991.98px` block
far below the rhythm block they override, at the same specificity — source order
is the only thing deciding it. Moving that block up the file turns it off
silently. The desktop's 150s are untouched and still read 40/150/150/150/150/150
at 992 and up.

Measured at 360, 390 and 768: all seven exactly 60. At 992 and 1440: unchanged.
Parity identical at all four widths.

### …and then the first of the seven left it again, at 13.5

«فاصله این ۶ تا آیتم با اون ۸ آیتم بالا بشه اندازه فاصله ۸ آیتم با هیرو».

Asked by geometry rather than by a number: the gap **under** the eight tiles is
to equal the gap **above** them. That one is the hero's foot to the first tile
— 523.1 to 536.6 at 390, so **13.5**. `.vp-trust-row-wrap` margin-top goes
60 → 13.5 and the other six of the seven stay at 60.

**This is a reversal, and it is the second time this number has been here.**
The paragraph above records 13.5 being tried under almost the same instruction
(«این دوتا فاصله بشه اندازه فاصله هدر با هیرو») and rejected with «جالب نشد» —
that time it was the header-to-hero distance, this time the hero-to-tiles one,
and the two happen to be the same 13.5. So the tiles and the badges now read as
one block under the hero rather than as two bands 60 apart, which is what was
asked for. If it reads too tight a second time, it is one line in
`tweaks.css`, and the numbers already passed through on the way are 30 and 60.

Measured at 360, 390 and 430: tile foot to badge top exactly 13.5 at all three,
equal to the hero's foot to the tile top at all three. Parity identical at
992/1200/1440/1920, no sideways scroll at 390/768/1200/1920.

## The mini basket as an island, and its X

«بنظرم این سبد خرید باید جزیره ای بشه اندازه اون ضبدر هم اندازه ضبدر منو بشه» —
the panel behind the header's basket button.

Two things, and the second is why the first was noticed. **This panel and the
phone drawer are the same object seen twice**, one behind each of two buttons
sitting side by side in the same header island, so anything true of one is read
as a promise about the other. The drawer became an island three rounds ago; this
stayed a full-bleed sheet with a 30px X in the corner of it.

The island takes the drawer's own numbers — 10 off every edge, a 19.2 corner —
on `.sidemenu-content`, not the wrapper: the wrapper is the fixed sheet that
dims the page behind it and has to stay edge to edge. The drawer moves 10 and
keeps its width because it is narrower than the screen; this one *is* the
screen, so `width: calc(100% - 20px)` comes with `height`.

The X renders at **39.9 / 12.0175 / 21** below 576 and **52 / 15.18 / 26** from
there to 991 — the drawer's, which are the header band's squares. Note the
drawer's own base 42 is dead below 992 (overridden at both ends), so those two
bands are what it actually draws.

**The trap.** Those numbers are written in the mini basket's own section rather
than added to the drawer's selector lists, where they belong. They cannot go
there: `.sidemenu-cart .closeButton` sets this button's desktop shape 30 lines
further down the file at the same specificity, so a shared rule above it loses
at every width. The desktop 30 has to stay — the basket opener is in the header
above 992, where the drawer does not exist. **So if the drawer's X moves, this
one has to be moved by hand.** Nothing checks the pair: `PhoneDrawerTest`
watches the drawer's contents rather than its metrics, and both panels are
parked off-screen where `check-parity.js` and `check-overflow.js` cannot see
them.

Measured at 360, 390 and 768: panel 10 off every edge with a 19.2 corner, and
the X byte-identical to the drawer's at all three. At 1440: unchanged — a 450
side panel with the 30px X.

## The product grids: 10 across, 16 down

«این فاصله چنده؟» about the gap between rows of product cards, then «۲۴ بشه ۱۶».

It was **24**, and it was nobody's decision: both grids carry Bootstrap's own
`gy-4`, which is `--bs-gutter-y: 1.5rem`. The x had been set to 10 by hand when
the two-up phone grid was built and the y was never looked at, so the two grids
were 10 across and 24 down without anyone choosing either number against the
other.

Now `--bs-gutter-y: 16px` beside the existing `--bs-gutter-x: 10px` on
`.vp-ladder-deals, .vp-best-row`, below 992. **They are deliberately not equal**:
a card is a photograph with a name and a price under it, so the space below one
card separates two products while the space beside it separates two columns the
page's own 10 margin already frames.

Measured at 360, 390 and 768: 16 down, 10 across, both grids. At 992 and 1440:
still 24, untouched.

## The hero's third mark, and the ladder's flag

Two on a phone, both of them a thing placed against a wide card and never
re-looked-at on a narrow one.

**«اون شکل چسبیده به دایره زیر هیرو در موبایل باید حذف بشه».** The hero carries
three blurred marks in the shoe's own colour: `.m-fall`, a 160 disc on the
card's centre line; `.m-near`, a 204 bar 29 off the left edge; `.m-far`, the
same bar 29 off the right. At 1440 they are three separate things with air
between them. At 390 the card is 370 wide, so `.m-far` spans 157 → 361 while the
disc spans 115 → 275 — **118px of overlap**, and the two blurs merge into one
lobed blob hanging off the disc's shoulder. That is the shape being pointed at,
and nobody drew it.

`.m-far` is `display: none` below 992. The other two stay: they do not touch,
and they are what puts the shoe's colour on the card. Above 992 all three are
unchanged.

**The ladder's chip row — four rounds, one rule.**

«اون لودینگ باید بیاد کنار هفته دوم زیرش نباید باشه», then «چرا موارد زیر ۳ تا
مربع از مربع ها زده بیرون؟», then «باید تو یه ردیف باشن», then — about the round
that answered *that* by growing the tiles — «چرا مربع های بخش حراج پله ای رو
بزرگ کردی؟ برای اینکه لودینگ و تیک کنار هفته و پله قرار بگیره باید فضای خالی دو
طرف مستطیلی که این کلمات توش قرار میگیره رو کم کنی، نه اینکه مربع هارو بزرگ
کنی.»

The rule, complete:

> **The three chips sit on one row; that row is exactly the width of the three
> tiles above it; and the tiles do not move.**

The base CSS ties the first two together — `.vp-step-tags { width:
calc(var(--tile) * 3 + 8px) }` — so the row is the board and cannot be widened
without widening the board. Three wrong answers, all of them the same wrong
instinct, which is why they are worth keeping written down:

1. **Widen the step to 170.** Chips on one row, hanging out past the squares:
   the tiles are sized by `--tile` and did not move with it.
2. **Break the row in two** (grid, name spanning, week + flag under it).
   Nothing overflowed; it was two rows.
3. **Grow the tile, 44 → 58.** One row, inside the board, and the board was now
   a third bigger than the client had ever asked for. This is the one they
   caught, and the correction is the right one: *the room comes out of the
   labels' own chrome, not out of the board.*

What it is now. Tile back at 44, row back at 140, and two things give up space,
both chrome rather than type:

| | from | to | saves |
| --- | --- | --- | --- |
| label side padding | 7px | 3px | 16 across the row |
| label size | 12px | 11px | 22 on the widest pair |

**The padding alone is not enough**, which is worth knowing before trying it
again: it can only ever return the 28 the two labels spend on it, and «پله
چهارم» + «هفته چهارم» at 12px need 162 of a 140 row. The 11px is not a new
number either — it is what the labels are above 992; the 12 was a phone-only
bump, free while the mark had a line to itself and not free now that it shares
theirs.

Measured: «پله دوم» 49 + «هفته دوم» 59 of type, 12 of padding, the 25 mark and
two 4px gaps = 137 inside 140.

**The last step is the one that cannot fit and must not be shrunk until it
does.** «پس از هفته چهارم» is 81 of type against the other weeks' 59, so its
three come to 165 in a 140 row; 10px type does not close that and neither does a
smaller mark. That step alone takes two lines — name on its own, week and mark
beside each other under it (87 + 4 + 25 = 116). `width: 100%` on its name is
safe *because* the row's width is the tiles' width, so a full-width label is
exactly the board and never a pixel wider. That is the same property the failed
grid round violated by putting `width: 100%` on the row itself.

Measured at 320, 360, 390, 700, 768, 991, 992 and 1440: tiles 140 below 992 and
182 above, no chip outside its own tiles anywhere, nothing clipped, the mark
beside the week on every step, and no page overflow.

## The live step's mark, two pixels too much colour

«اندازه مربع لودینگ ارتفاعش از کارت هفته دوم که کنارشه بیشتره... باید اندازه اون
مربعی باشه که کنار هفته اول تیک خورده».

**The boxes were never different.** Measured at 8× device scale, the mark and
the label beside it both paint 448.953 → 473.953: 25.000 exactly, top and bottom
on the same line, at 360, 390, 768 and 1440, on all five steps. The first answer
to this question was "they are the same, it is contrast" — which was true and
not the whole truth.

What differs is **how much of the 25 is coloured**. Every other rectangle in the
row is white inside a 1px inset ring at 5% ink, so its white core measures 23 and
the ring reads as part of the card under it. The live mark carries
`box-shadow: none` — a grey ring on gold looks like dirt — so all 25 of it is
saturated gold. 25 of colour beside 23 of colour, and the gold is the
high-contrast one, which is what "taller" was.

The fill is inset rather than the ring drawn: `border: 1px solid transparent`
with `background-clip: padding-box`. The box stays 25, the gold becomes 23 — the
same coloured area as the tick's white, which is the square the client named.
Nothing in the row changes size.

**The lesson worth keeping**: when someone says two things are different sizes
and the boxes measure equal, measure the *painted* area next, not just the
layout box. A 1px ring on one and not the other is a 2px difference in what the
eye is actually given, and at 25px that is 8%.

## The sale card, rebuilt twice and put back

**Where it stands: the card is what it was before 13 August 17:49.** Photograph
on a light tile, the «٪۳۰» burst on the top corner, a glass strip under the
shoe carrying the name and both prices, no favourite, no rating, and no basket
below 992. If you are looking at `git log` and wondering why two commits were
reverted the same evening, this is the entry.

**What happened, in order.** The client sent a reference and «تو بخش حراج پله
ای اینو برام بساز بجای چیز فعلی که داریم». Commit `48947a4` built it: the strip
and the burst came off the photograph, the name went under it with the price
and a rating, a white favourite went on one corner and the gold basket on the
other, and the basket came back on the phone. `3808c28` followed with the tile
turned to glass and the rating moved across from the name. Then a second
reference arrived — a Tailwind card with a frosted band fading up out of the
photograph's foot, the name, the price and the controls all sitting on it —
and that was built too, scoped to `.vp-ladder-deals` below 992, with the tile
darkened five percent so the band had something to frost. The client's answer
to the result was «افتضاح شده برگردون به همون نسخه ای که ۵ ساعت پیش داشتیم»,
with a screenshot of the live site. Both commits are reverted and the third
build was never committed.

**What that means for the next reference.** Two rebuilds of this card were
accepted in the brief and rejected on the phone, and in both cases the thing
that came back was the version that had been living on the site for days. The
useful reading is not "the client changes their mind" — it is that a reference
drawn for one big card does not survive being taken at 53% into a two-up grid,
and the parts that fail are the ones the mockup had room for and the card does
not. The second reference is a case in point: at 340px wide it has a labelled
«Add to Cart» pill, a 24px title and a rating with a review count. At 180 the
pill does not fit beside the heart, the title lands at 12.5, and the review
count cannot exist because there is no review table. Three of the reference's
own elements were gone before the first pixel was drawn, and what is left is
not really that card any more.

So: **before building the next one, put it on the phone at 180px wide and show
it, and get that agreed before touching `tweaks.css`.** A render costs
minutes. Both of these rounds cost an evening each and ended where they
started.

**The cache-bust was kept.** `3808c28` carried one change that has nothing to
do with the card: `partials/head.blade.php` now fingerprints `tweaks.css` with
a hash of its contents. Before it, a returning visitor got new HTML — never
cached — against their own cached copy of the old stylesheet, which is what
made the first rebuild look broken on the client's phone and fine here. The
revert deliberately leaves that hunk in place. Do not take it out with the
next revert either.

**What the reverts also took back**, so nothing here is a surprise later:
`config('storefront.placeholders.rating')` and `DEAL_RATING` are gone, along
with the `HomePageTest` assertions on them; the rating existed only for the
rebuilt card and had no review table behind it. The generated preview pages
were rebuilt from `theme/make-rtl-page.js` rather than reverted by hand.

**And a bug this round surfaced that is still open.** `shop/card.blade.php` —
the listing, the category pages and the related shelf — is on the old
`.vp-deal-label` / `.vp-deal-lines` markup. `48947a4`'s message said those
pages took the rebuild with them. They did not: only the shared stylesheet
moved. While those two commits were on the branch, `/products` at 390 rendered
with its names clipped mid-word, its strip across the top of the photograph and
its basket fallen out from under the card. **The revert fixes that too**, since
the stylesheet those pages read is back to the one their markup was written
for. It is written down because it will come back the moment this card is
rebuilt again without `shop/card.blade.php` being rebuilt with it — and because
"the catalogue took it with it" was in a commit message once already without
being true.

### The phone card's five numbers, after the revert

The revert put the card back; these five moved it on from there, and they are
the numbers the phone card now holds. Below 992 only — **the desktop card is
untouched and still reads 1:1, 28px, a 1px ring and a shadowed strip.**

| | before | now |
|---|---|---|
| tile outline | `0 0 0 1px` @ 5% | `0 0 0 2px` @ 5% |
| tile shape | `aspect-ratio: 1` (180 × 180) | `10 / 11` (180 × 198) |
| tile corner | 28px | 22.4px |
| burst | 48px | 43.2px |
| strip's drop shadow | `0 4px 14px -8px` @ 35% | none |

Three things about that table are worth more than the numbers themselves.

**The 10px حریم did not have to be re-measured.** The clearance between the
shoe's box and the strip is `H − 59.52 − (H − 69.52)`, which is 10 at any tile
height — the shoe's box was written as a percentage *minus the fixed stack*
rather than as two percentages, so a taller tile carries the client's number
through unchanged. Measured after: 10.0 at 390 and 10.0 at 360. If the shoe's
box is ever restated in plain percentages, this stops being true and the
height becomes a two-number change.

**The burst moved below 992 and not at 992–1199.** The burst and the basket
were deliberately set to one size, at the client's request. Between 992 and
1199 both are 48 and both on screen, so shrinking the burst there would break
that pair; below 992 the basket is `display: none` and the burst is alone.
A future "make the badge smaller" that is applied at `max-width: 1199px`
silently undoes an instruction nobody will remember.

**The strip lost its drop shadow and kept its ring.** The ring is the strip's
edge, not its shadow. Dropping both is the exact move that started «لبه پنهان»
on the hero card — read that entry before deciding the ring should go too.

The outline reads, and was checked rather than assumed: scanned across the
tile's side at 390 the row goes `255 255 | 243 243 | 255`, two pixels of line
where there was one, twelve levels under the page.

### The catalogue loses its frame; the card loses its second bottom line

Three more in the same evening, and two of them touch decisions recorded
elsewhere in this file, so read this before undoing either.

**The catalogue's cards have no frame, at every width.** `.vp-shop-grid
.vp-deal-shot` is `box-shadow: none` — the listing, the category pages, the
search results and the related shelf. The stepped sale keeps its frame; it is
on `.vp-ladder-deals` and the rule does not reach it. Measured first, because
«کادر» could have meant several things: the catalogue tile is `#FFFFFF` and
`.vp-shop-panel` under it is also `#FFFFFF`, so a scan across a tile's side
read `255 …255 | 243 243 | 255 …` — page, ring, tile. **The ring was the
entire frame**; there was no background to take off. If that panel ever stops
being white, the tile's own white becomes a box again and this needs redoing.

**The foot's ink is gone, and «لبه پنهان» still holds.** The
`inset 0 -1.4px 0 rgba(16,17,17,0.07)` under the card was that entry's remedy
and the client asked for it deleted («یه خط اضافه تیره»). It is safe *only
because the ring went to 2px in the round before*. Measured down a sale card's
foot at 390:

    with the ink   255 255 255 255 246 238 | 243 243 | 255 255
    without        255 255 255 255 255 255 | 243 243 | 255 255

The edge into the page is the ring's `243 → 255`, one pixel, twelve levels,
with or without the ink. The ink was a second line inside the first, which is
what the client was looking at. **Taking the ring back to 1px means putting
this ink back**, or the foot dissolves and «لبه پنهان» starts over.

**The strip is 6 from the card's edge, not 12** — inline start, inline end and
block end, below 992. Above 992 the inline end is 70/74.8 and stays: that is
room held for the basket, not a distance from the edge.

**And the حریم is 16 now, not 10.** The shoe's box is measured from the tile's
foot, so moving the strip 6px down moved the gap above it to 16. The client's
instruction behind the 10 was that the product must not run under the box;
16 satisfies it further. The shoe was left where it is rather than grown 6px
to keep the number exact — that would be a bigger photograph, which is not
what the message asked for. If the 10 is ever wanted back exactly, it is
`calc(77% - 63.52px)` in the حریم block.

### The corner button is WhatsApp now, not scroll-to-top

«بجای این باید یه آیکون واتسپ بیاری با گوشه های کرو», with a screenshot of the
template's gold ring. Replaced, not joined — asked and confirmed.

**The number is written in one place: `theme/make-rtl-page.js`.** It goes into
the generated preview page, and `theme/make-blade.js` ports that into
`partials/whatsapp.blade.php`. Not `config/storefront.php`: that would make the
Blade hand-owned and let the two copies of the page drift, and the footer's
landline is already hardcoded the same way. `wa.me` wants the international
form — 09918905993 is written 989918905993, no plus, no leading zero.

`WhatsAppButtonTest` is what guards it, and it is there because this is the
only link on the site that sends a customer somewhere the site does not
control. A wrong digit does not 404, does not look broken, and is invisible to
`check-parity.js` — two buttons with different `href`s are the same picture.
The test was checked against a changed digit and fails on it.

Three things that were decided rather than defaulted:

- **`left: 30px`, not `inset-inline-start`.** The logical property is right
  almost everywhere in `tweaks.css` and wrong here: the template's rule is
  `left`, and on an rtl page the inline start is the right-hand side. Written
  logically the button measured 310 from the left in a 390 viewport instead of
  30 — the other corner, from a change that looks like a tidy-up.
- **Shown on the first scroll, and this was decided twice.** The first build
  parked it on screen from the top, on the reasoning that a back-to-top control
  is useless at the top of a page while a way to ask a question is most wanted
  there. The client looked at that and asked for the ring's old behaviour back
  — «آیکون واتسپ وقتی اولین اسکرول شروع میشه باید ظاهر بشه» — so the same
  scroll handler drives it again, pointed at `.vp-whatsapp` and toggling on
  `y > 0` rather than the ring's `y > 50`: the ask is the *first* scroll, not a
  little way into one. Both were asked for, in that order, and the second
  stands.

  That hidden default is half of a mechanism whose other half is a JS
  selector, and **nothing else in this repo can see either half**. PHPUnit does
  not run the script; `check-parity.js` compares the two pages against each
  other, so it stays at zero if both go blank; `check-overflow.js` only asks
  whether the page scrolls sideways. A drifted selector would make the button
  invisible on every page, forever, with every check green.
  `WhatsAppButtonTest::test_the_scroll_handler_can_still_find_the_button`
  is what notices — checked against a typo'd selector, it fails.
- **16px on a 50px square.** «گوشه های کرو» against something that was
  `border-radius: 50%`, so the ask is that corners exist — 25 is the circle
  again, 8 is a box.

Two loose ends, both harmless and both deliberate. The scroll script in
`partials/scripts.blade.php` still looks for `.scroll-top`; every use of it is
guarded (`toTop &&`, `if (toTop)`), so it finds nothing and carries on — this
was read, not assumed. And the theme-colour lists near the top of `tweaks.css`
still name `.scroll-top svg` and `.scroll-top:after`, now dead selectors, left
in a shared list of forty where picking them out risks more than it gains.

### The cache-bust is generated now, not typed

Worth its own note because it was lost twice in one evening, the same way both
times.

`tweaks.css` is fingerprinted in the head so a returning visitor cannot get new
HTML against their cached copy of the old stylesheet. The fix was first typed
into `partials/head.blade.php` — **a file `theme/make-blade.js` generates** —
and the next run of that script deleted it silently while porting an unrelated
change. A generated file cannot hold a hand correction; that is exactly why
`make-blade.js` prints the list of files it leaves alone.

The transformation lives in `make-blade.js` now, applied *after* `toBlade`
(before it, the link is still a plain relative path and the regex matches
nothing), and the script throws if it cannot find the link to rewrite. And
`ShippedAssetsTest::test_the_stylesheet_that_changes_is_fingerprinted` reads
the rendered page and checks the hash matches the file on disk, so it holds
whoever wrote the link and however it got there. Checked against the fix being
removed: it fails.

**The general lesson, for the next hand-edit:** if a correction has to be made
to something under `partials/` other than `mobile-menu.blade.php`, it belongs
in `theme/make-blade.js` or `theme/make-rtl-page.js`. Making it in the Blade
works, passes every check, deploys — and disappears the next time anyone
touches unrelated markup.

### «یه چیز سفید افتاده رو پایین کارتها» — a clipped edge, not «لبه پنهان»

The words are that codename's, the cause is not, and the difference is worth
keeping because the next report will sound identical.

«لبه پنهان» is four greys within a few levels of each other and no step
anywhere — a composite problem. This was arithmetic. Measured at 390, down the
middle of a sale card's foot, six pixels inside the tile to five below it:

    middle row   255 255 255 255 255 255 | 243 243 | 255 255
    last row     255 255 255 255 255 255 | 255 255 | 255 255

The 243s are the card's 2px ring. **The last row had none.**
`.vp-ladder-area` carries the template's `overflow-hidden` and its
`padding-bottom` is 0 on the phone, so the section ends on exactly the pixel
the cards end on — and the ring is `0 0 0 2px` *without* `inset`, painted
outside the border box, so it fell in the two pixels the clip removed. Three
sides ringed, the fourth cut, a white tile on a white page: a card with nothing
under it.

`padding-block-end: 2px` on `.vp-ladder-area`, which is exactly the ring's
width — the same remedy «لبه پنهان» ends with for a different reason, that the
room has to be made *inside* the clip.

**Any change to the ring's width has to change that padding too.** A 3px ring
in a 2px gap puts the fault straight back, and it will look like a colour
problem again — which is how an hour goes.

`.vp-best-panel`'s `margin-top: 60` is left alone: measured from the card's own
edge the gap between the bands is still 60, and it is the border box that is
now 2px further off. If that 60 was ever meant box-to-panel, it is 58 and one
line.

**And a second thing the same round caught.** Turning the hover zoom off took
the `transform` and left the `box-shadow`, so a tapped card still grew a gold
`rgba(164,127,37,0.28)` ring and kept it until something else was tapped —
same sticky-`:hover` fault, half-fixed. All three components are now set back
to their resting shadow rather than to `none`; `none` would have deleted the
card's own ring along with the gold one, which is the fault above. Those
resting values were read off the rendered page: the best seller and the
category tile rest on a 1px ring *plus* a `0 8px 22px -12px` drop shadow, which
a first pass guessed away.

### The best sellers are the sale card now, inverted

«کارتهای قسمت پر فروش ترینها هم دقیقا مث حراج پله بشه اما برعکس یعنی کارت شیشه
ای بشه و باکس قیمت و اسم سفید / از همون عکس های قسمت حراج پله ای استفاده کن».
Below 992; the desktop card keeps its own layout.

They were two different objects: the sale card is a tile with the strip lying
*on* it, the best seller was a tile with the strip in a row *under* it beside a
round button. The second is now the first, with the two surfaces swapped —
tile glass, strip white — and **every other number quoted from the sale card
rather than re-derived**. 10/11, 22.4, the 2px ring, 47.52 of strip 6 from
three edges, 19.1862 on its corner, the photograph's box, the 2px optical lift
on the type. They are one component with two skins; a number recalculated here
instead of copied will drift the next time the sale card moves, and it has
moved four times in one evening.

**The basket was kept for one round and then removed.** Read literally, «دقیقا
مث حراج پله» deletes it — `.vp-deal-cart` is `display: none` below 992 — but the
client had designed that button two rounds earlier, so deleting it as a side
effect of a sentence about glass looked like reading one instruction over
another. It was kept, said so, and the next message was «دقیقا همون کارت حراج
پله برای پر فروش ترینها اجرا بشه». It is `display: none` below 992 now and the
strip runs the tile's full width, 6 from both edges, exactly as the sale card's
does. The markup is untouched, so the button is still there above 992.

The inversion stayed through that, because «برعکس» was asked for in the message
before and this one does not mention colour: tile glass, strip white.

**The lesson, since this fork will come again**: «دقیقا مث X» says nothing
about what to do with a thing X has not got. Asking costs a round and assuming
costs a round — so next time, render both and put the question in the same
message as the work.

**The photograph change reached the desktop and the fitting rule had to follow
it.** The tile's picture is a cut-out on white now, and `.vp-best-shot img` was
`object-fit: cover` — rendered at 1440 it took a bite out of every one of the
six shoes. That is exactly the failure the sale card's «fitted, not filled»
note guards against. `contain` in a centred box, at every width, because the
photograph changed at every width even though the round was a phone round.

**Two orders now have to agree, and nothing in CI checks it.**
`theme/make-rtl-page.js`'s `BEST_ORDER` must list the same shoes in the same
sequence as `config/storefront.php`'s `placeholders.best_sellers.priced_from`.
The first cut of this ran the generator in `LADDER_DEALS`' order and the
storefront in the config's: same six shoes, different tiles, 46,629 pixels
apart at 1440. `check-parity.js` caught it — and `check-parity.js` is not in the
deploy workflow, which runs PHPUnit and Pint only. **Run it after touching
either list.** The generator at least throws if a name in `BEST_ORDER` is not
in `LADDER_DEALS`.

`HomePageTest::test_a_best_seller_tile_shows_the_shoe_it_names` covers the
other half — that a tile shows its own product's photograph and that no
category photograph is left in the band. Not parity's job: parity compares this
page against the preview, so if the two ever agree on the wrong photograph it
reports zero. Checked against the old markup: it fails.

### The generator's declaration order, four times over

`theme/make-rtl-page.js` builds its bands top to bottom, and `const` is not
hoisted — a table declared below a band that reads it throws
`ReferenceError: Cannot access 'X' before initialization` on load, before a
single page is written. Four constants have had to be lifted to the top of the
file for this, all in one evening: `LADDER_DEALS`, `fa`, the burst primitives
(`BURST_PATH` / `BURST_LOBES` / `BURST_STUDS`) and `dealBurst`.

The pattern is always the same and always a surprise, because the file reads as
if it were declarative. **If a band needs something another band already has,
move the declaration to the top rather than copying it** — and expect that
error first.

### The best sellers' heart and star

- **The heart** is at the tile's inline end (the left on this rtl page), in a
  39.9px square with a 12.0175 corner — the header's basket button, which is
  what the client named. Not the card's own 19.1862: on a 39.9 box that is 48%
  and draws a circle, and the word was «مربع». It is outside the `<a>`, because
  a `<button>` inside a link is invalid and a favourite is not a navigation —
  the same reason the sale card's basket sits outside its own link. Outline
  heart: there is no wishlist behind it.

- **The star** is the sale cards' own `deal-burst` at `percent => 25`, inside
  the tile's link where the sale card puts its own. It was drawn white for one
  round — the message that added it also asked for the hero's star to go white,
  and that read as one instruction — and «اون ستاره تخفیف فقط در هیرو باید سفید
  بشه» corrected it. Once it is gold there is nothing left that differs from the
  sale cards' badge but the number, so the parallel component, its partial and
  its class were deleted rather than kept.

- **Which cards carry one: every other tile.** Nothing in the catalogue says
  which of the six is discounted — none is, on this band; the price shown is
  the pre-sale one — so «بعضی» had to be a rule rather than a fact. It is
  `$loop->index % 2 === 0` in the Blade and `i % 2 === 0` in the generator, and
  **the two have to stay in step or `check-parity.js` fails.**

- **The shoe moved 40px down** to clear the top of the tile for them, keeping
  its height — a move, not a resize. That leaves its box 1.6px above the
  strip's head where it had 41.6. Tight as a box and not as a picture, since
  `object-fit: contain` letterboxes it, but **there is no room left down
  there**; anything else that moves down has to take height off first.

- Both are hidden above 992. The desktop tile is a different layout and nobody
  has decided where they go on it.

### The header's menu square

White with a gold glyph, so the three header controls read as one set. Two
things about the rule, because it silently did nothing twice:

- It has to be written `.th-header .th-menu-toggle.d-block`. Unlike the search
  and basket beside it this button carries **no `.icon-btn` class** — it is
  `class="th-menu-toggle d-block d-lg-none"` — and the rule that fills it gold
  further up this file is itself three class levels, so a two-level rule loses
  to it however far down the file it sits.
- `background-image: none` explicitly. The gold is a gradient, so the computed
  `background-color` reads transparent and setting only a colour leaves the
  gradient painting over it.

### White on the gold ramp, and a mark measured by its ink

Two things from the same round, both of which overturn a number this file had
argued for.

**The live step's digits and its spinner are white.** «اعداد داخل مربع های گلد
حراج پله ای باید سفید بشن اون لودینگی که میچرخه هم سفید بشه». They were ink,
and the comments on both rules argued for it from a measurement: white reads
2.6:1 on this gold and ink 5.1, and 2.6 is under the 3:1 WCAG asks of a
graphical control. The client has now asked for white on this ramp twice — here
and on the header's menu square — so it is white. **The measurement is kept in
the comments rather than deleted**: if these ever read faint in daylight, that
is why, and ink is the fix.

**The hero's star went white for one round and came back gold.** Both rules
were deleted rather than overridden — the gold comes from a gradient the
generator writes into the markup, so with nothing to beat the presentation
attribute the star is simply itself again.

**The search mark is 23px, and the number is arithmetic rather than taste.** It
was 17 — the same number as the box the other two glyphs sit in, which is
exactly why it looked matched and was not. The other two are icon-font glyphs
that fill their boxes; this one is an SVG whose magnifier spans about 13 of its
20 view-box units. Scanning the painted pixels of all three:

    search   13 x 13        (before)
    basket   18 x 20
    bars     16 x 14

23 = 17.5 ÷ (13 ÷ 17), the box that puts the search's ink at the basket's size.
Measured after: 17 x 17.

That is the third time this repo has been caught by equal boxes with unequal
ink. **When two things are meant to look the same size, measure what they
paint, not what they occupy.**

## The shop, rebuilt to a reference — and what it has no data for

«برای قسمت شاپ اینو پیاده سازی کن ... دسته بندی های خودمون باشه ... اون قسمت
پاپلر و لاتستو اینا هم باید باشه / جایی که محصول قرار میگیره باید مربع باشه»,
then «باید بدونه قاب باشه».

Below 992. Above it the listing keeps its heading, its sidebar and its
count-and-sort bar; the phone gets a top bar, a tab row, a category strip and a
new card laid over that.

**`check-parity.js` does not reach any of this.** It compares the *home* page
against the static preview, and the shop has no preview — it is Blade written
by hand. `check-overflow.js` and the feature tests are the whole of this page's
automated cover. Screenshot it after changing it.

### What is real and what is missing

| on the card | backed by |
|---|---|
| name, price, cut, was-price | the catalogue |
| «جدید» badge | `Product::isNew()` — `published_at` inside 21 days |
| «۵۶ فروش» | `units_sold`, paid order items — **hidden while it is 0** |
| ★ 4.9 | **nothing. Not built.** |

There is no review table, so the reference's rating is the one element left
out. A star with a number beside a real price is a claim, and an invented one
has already been put in and taken out of this repo once.

The sales line is wired and simply absent until something sells. The demo
catalogue has no paid orders, so today it never shows — printing «۰ فروش» on
every card of a shop that has not opened says less than nothing.

### Two tabs that look identical and are not

«پرطرفدار» sorts on `units_sold_recent` (a 30-day window) and «پرفروش‌ترین» on
`units_sold` (all time). They return the same order today because every product
ties on nought. **That is not a reason to collapse them into one sort** — on a
shop that has been trading a while they are genuinely different lists, and the
scopes are written and commented so the difference arrives on its own.

### Three faults this round produced, all caught by something

- **`url()->previous()` in the back link.** It puts the referrer into the
  page's HTML, so one URL renders differently for two visitors and nothing
  downstream can cache it. `CataloguePagesTest` caught it by requesting the
  same listing twice. The arrow goes one step out now — category or search to
  the shop, shop to home.
- **The listing lost add-to-cart.** The reference's card carries only a
  favourite, so the form went with the old card. Recorded in
  `CataloguePagesTest`, whose assertion moved to the product page rather than
  being deleted: the invariant it protects — a branch that stopped stocking the
  default size can still sell one — is still checked end to end.
- **The filter rail is rendered twice**, once in the phone's `<details>` and
  once in the desktop sidebar, with exactly one shown. Two forms with the same
  field names; only the visible one can submit, so nothing disagrees. **A third
  copy would be a real bug.**

### The frame

`.vp-listing-panel` is the listing's own hook and the extra class is the point:
`.vp-shop-panel` is shared with the basket, the product page, the checkout and
the account, and «باید بدونه قاب باشه» was about the listing. Measured at 390,
the cards ran 34..356 inside the frame and 14..376 without it — 44px of
picture back.

### The tab row is pills, and the empty left was arithmetic

«چرا با وجود اینکه فضا هست اون ردیف فیلترها سمت چپشون خالیه؟ بنظرم ردیف
فیلترهارو تو بیضی قرار بدیم».

The six labels paint 265.5px of type. Five 12px gaps took the row's content to
325.5, and the row is the card grid's width — 336 at 360, 366 at 390, 388 at
412, 406 at 430. The row was `nowrap` with fixed type and fixed gaps, so none of
that grew, and on an rtl page the leftover falls at the inline end, which is the
left: **31px at 360, 41 at 390, 63 at 412, 81 at 430.** The row's own hairline
ran the full width underneath, so the emptiness was drawn rather than merely
present. That is what the client was looking at.

`flex: 1 0 auto` on all six spends it. The surplus is shared out as padding
inside the pills instead of being stranded past the last one, and the row is
exactly full at every width — measured `ink` and `row` now start and end on the
same pixel from 360 to 430. Painted side padding: 4.2 at 360, 5.3 at 390, 6.3 at
402, 7.1 at 412, 8.6 at 430.

Two states, `.vp-chip`'s: outlined black off, gold solid on. The row's hairline
and the per-tab underline both went — a pill marks itself, and a rule under a
row of pills only redraws the problem above it.

**The floor is a scroll, and it is newly available.** `flex-shrink: 0` with
`overflow-x: auto` means that when the six genuinely do not fit — under about
350 — the row scrolls instead of pushing the page sideways. The old block ruled
scrolling out for a good reason: the price and brand panels were absolutely
positioned *inside* this row, and a scroll container clipped them the moment
they opened. They are `position: fixed` sheets over a scrim now, so they are not
this box's to clip. Verified rather than assumed — both sheets measured at 390
and 430 with the row scrolling: full width, `checkVisibility()` true, and the
sheet's box 60–110px below the row's bottom edge.

**Six is still the ceiling.** Under 381 the type drops to 11 and the gap to 4 to
keep one line at 360 (333.9 needed against 336). A seventh control does not fit,
and the answer then is a shorter label — 11px is as small as this row reads at
arm's length.

### The top bar is one white box, and the pills' black came down

«رنگ مشکی که برای بیضی و مواردی که توش گذاشتی انتخاب کردی خیلی پررنگه باید
متعادلتر بشه / سرچ بار باید سفید بشه و دورش سایه بیاد و فیلتر هم بیاد داخل باکس
سرچ سمت چپش», then «خط ننداز دور سرچ / باید همون سایه ای که دور مربع منو تو هدر
انداختیرو بندازی دورش».

**The tab pills.** The ring is `rgba(16,17,17,0.2)` (204 on white) and the label
`0.55` (124), down from a full `#101111` on both. Six hard black outlines on a
white row were competing with the one gold pill that is actually saying
something. `.55` is where this page already keeps secondary type — the category
strip under the row is `.62`, and the tab's own label was `.5` before it became
a pill. **The brand chips inside the sheet still carry the full black**, which
was asked for by name («برندهای مختلف تو بیضی باشن به رنگ مشکی»); if they should
follow the row, it is one value in `.vp-chip`.

### The gold pill's two-tone edges were the ramp painted at the wrong end

«این بیضی هایی که برای فیلتر ها و پاپ ها ساختی چرا بالا و پایینش خرابی و دو رنگی
داره». A lighter line along the top of the gold pill and a darker band along its
foot — on the filter row's chosen tab and on the chips inside the sheets.

Not a shadow, not a ring, not a second gold. `background-origin` defaults to
`padding-box` while `background-clip` defaults to `border-box`; these pills carry
a 1px transparent border to hold the on state to the same size as the off
state's ring, so those two boxes differ by a pixel on each side, and
`background-repeat` fills the shortfall by tiling the ramp. The strip above the
pill got the ramp's *bottom* and the strip below it got the ramp's *top* — the
two ends of the gold, 34 levels apart, one on each edge.

Measured down the middle of the live `/products` pill at DPR 3, where the strip
is three device pixels:

```
before   top  226 226 227 → 192      foot  226 226 227 → 192 193
after    top  192 192 193 193 194    foot  224 225 225 226 227
```

Worst step at either edge, 35 → 1. `background-origin: border-box` on
`.vp-shop-tab.is-on` and `.vp-chip.is-on` is the whole fix. Not
`background-clip: padding-box`, which keeps the ramp honest by leaving the
border pixel unpainted and so puts a white hairline round a solid pill. The
transparent border stays either way; nothing changed size, and parity stayed
zero at all four widths.

**The box is `.vp-shop-top`, not the search form, and that is load-bearing.**
`shop.filters` is a `<form>`. Putting the filter's `<details>` inside the search
form would nest one form in another, and browsers do not tolerate that — the
inner one is dropped, and the phone's whole filter rail would stop submitting
with nothing visible to explain it. So the bar is the box, the form goes
transparent inside it, and the two stay siblings. The form is first and the
filter second, which on this rtl page puts the field right and the filter left.

Two things moved with it. `.vp-shop-filter` is `position: static` and the panel
takes `inset-inline: 0` off the bar, because the button is at the *left* end
now and a 320-wide panel pinned to the left edge of a 66-wide button runs off
the screen — the same fault, and the same fix, as the price sheet's. And the
button dropped 42 → 34 so it sits inside a 46 box with 6 of air rather than
filling it like a lid.

**No ring. The shadow is the header menu square's, copied literally.** The first
attempt put a 1px ring shadow under two blurred layers on «لبه پنهان»'s
reasoning that white on white needs a drawn edge; the client does not want a
line there and named the shadow they do want. Measured across this box's left
edge at 390: `253 251 248 → 240 → 255`. Across the menu square's on the same
page: `245 244 242 240 → 232 → 255`. Same shadow — the 8 levels between them are
the ground, since the header island is a tinted pane at 245 and this bar is on
the white page. 15 levels of step here against 23 there, which is softer than
the hero card's 13-level edge was allowed to be, and is the trade the
instruction asked for.

Side effect worth having: the field went from 215 wide to 266.

### The category icons are Microsoft's now, not hand-drawn

«یه دسته بندی آیکون حرفه ای برام پیدا کن دانلود کن که از اونا استفاده کنیم», then
«نه اینایی که پیدا کردی بدرد نمیخورن دوباره بگرد چیزای بهتر پیدا کن», then «B»
off the second sheet.

The eight were drawn by hand in this repo by a session with no illustrator.
That is what «حرفه‌ای» was about, and no amount of measuring fixes it.

**What the search actually found, so nobody repeats it.** Nine sets were
measured against these eight categories, by name, out of the full Iconify
bundle. The obvious modern line sets each cover **six of eight** and each is
missing one that matters:

| set | licence | gaps |
|---|---|---|
| Phosphor | MIT | صندل، کالج |
| Huge Icons | MIT | کالج، and its only boot is `armored-boot` |
| IconPark Outline | Apache 2.0 | **ونس و کتونی** |
| Material Design Icons | Apache 2.0 | صندل، بوت |

A shoe shop whose sneaker tile is a gap is not a set, which is what ruled out
IconPark. The sets that have **all eight drawn** are the emoji families —
Fluent, Noto, OpenMoji, Streamline, Twemoji — because the Unicode footwear
block happens to be exactly this shop's catalogue: `high-heeled-shoe`,
`running-shoe`, `mans-shoe`, `womans-sandal`, `womans-boot`, `handbag`,
`watch`, `running-shirt`.

**The client chose Fluent Emoji High Contrast** — Microsoft's monochrome cut of
Fluent Emoji, MIT, so it takes the page's gold and needs no attribution line in
the footer. `theme/make-category-icons.js` writes the eight, recoloured to
`#A08119`, under the filenames that were already in `config/storefront.php` and
`theme/make-rtl-page.js` — so swapping the artwork touched no markup and no
config. Re-run it after changing the map, then `sync-storefront-assets.js`.

**MIT is not the same as free, and the notice is a shipped file.**
`LICENSE-fluent-emoji.txt` sits beside the icons in both trees. It is written
into `storefront/public/` directly by the generator rather than carried by the
sync, because `sync-storefront-assets.js` copies what the *page reaches* and
nothing links a licence file — the reachability walk cannot see it.
`ShippedAssetsTest::test_the_category_icons_ship_with_the_licence_they_are_under`
is what fails if either the notice or the provenance line inside the SVGs goes.

The eight land in two places at once: the shop's category strip and the phone
drawer's list. Same files, so they cannot come apart.

### Three changes to the set, and a second source

«کتونی باید آیکونش عوض بشه بری یه آیکون دیگه براش پیدا کنی / ست ورزشی اون خط کج
وسطش پاک بشه بجاش ۳۰ درصد از قسمت پایینش پر بشه / از بالای قسمت بوت هم ۲۰ درصد
حذف بشه».

All three live in `theme/make-category-icons.js` as named, explained overrides.
None of them is a hand-edit to a generated file: a re-run would silently undo
one, and nothing on this project would say so.

**The sneaker is now Phosphor's**, `sneaker-move-fill` — «شماره ۳ خوبه اونکه
انگار در حال دویدنه», off a sheet of ten monochrome candidates rendered in gold
beside their neighbours. So the generator has two sources, each icon names its
own in a comment at the top of its file, and each set's MIT notice is written
beside the icons. One catch worth knowing: **Phosphor puts `fill="currentColor"`
on the `<svg>` element, not on the paths**, so dropping the wrapper drops the
fill and the icon renders black. The colour is carried back in on a `<g>`.

**The sport vest's sash was never drawn.** The vest is one compound path — an
outer outline plus two inner subpaths that are holes — and the diagonal is the
*gap left between the two holes*. So removing it is not deleting a stroke, it is
merging the holes into one, tracing the two outlines end to end with the sash's
own two diagonals dropped. Then three tenths: the ink runs y 1..31, so 9 units,
and the interior's walls are vertical below the shoulders — a plain rectangle
from y 22 to the interior floor at 29 leaves the icon solid from 22 to 31.

**The boot is a crop, not a redraw.** Its shaft is a long straight wall, so
moving the top of the view box down that same wall shortens it and leaves every
curve untouched: ink y 2..30.5 is 28.5 units, a fifth is 5.7, box opens at 7.7.
The box stays 32 wide on purpose — `object-fit: contain` and
`preserveAspectRatio` both fit the whole box, so keeping the width means the
boot keeps the width it had beside the other seven and loses only height.

### One line under the shop's icons, and a shorter boot

«۲۰ درصد از بالای اون آیکون بوت و نیم بوتو ببری بنظرم خیلی بلنده / تو قسمت
فروشگاه همه آیکونها باید از پایین تو یک سطح قرار بگیرن».

**The two rounds on the boot compound, they do not add.** The second twenty per
cent was asked of the boot as it then stood, which was already four fifths — so
the crop is 0.2 + 0.8 × 0.2 = **0.36** of the drawn ink, not 0.4. It is written
in the generator as that arithmetic rather than as `0.36`, because the next
round of this will be a third fifth and the sum has to stay readable.

**The baseline is half in the files and half in the CSS, and neither half works
alone.** The icon boxes were already the same 26px square and their *feet* were
not: these eight are drawn on different amounts of built-in padding, and
`object-fit: contain` centres what it fits. A loafer and a sandal are wide and
flat — ink 53 and 51.5 of 128 against a sneaker's 120 — so contain floated them
in the middle of the box and their soles hung above everyone else's. So
`make-category-icons.js` now **crops every view box to its own ink**, which
makes a file's bottom edge the sole of the shoe in it, and the strip adds
`object-position: center bottom`. Without the crop, `bottom` only lines up eight
different amounts of padding; without the CSS, the crop only recentres them.

**The generator measures the ink itself**, by rasterising each finished SVG
through sharp — not by reading the path, because a path's numbers are control
points and a curve leaves them behind, and because two of these eight are
altered so the drawn `d` is not the shipped one.

**The trap, and it cost a round.** An `<img>`'s intrinsic ratio comes from the
`width`/`height` attributes, **not** from the view box. With both left at 32 on
a `0 16 32 13.25` box the browser believes the picture is square: `contain` fits
that square, `preserveAspectRatio` letterboxes the real artwork inside it, and
`object-position: bottom` bottoms the square the artwork is floating in the
middle of. Measured with them at 32, the eight soles landed on 203, 203, 204,
205, 207, 208, 208, 209 — six pixels of scatter on a 26px icon, which is the
fault this was meant to fix, still there and now invisible in the CSS. With
`width`/`height` following the view box: **210, 210, 210, 210, 210, 211, 211,
211** — one pixel, and that one is the antialiased edge.

The width is what must never be trimmed. Trim a side and that icon alone starts
scaling by height, and it becomes the only one of the eight at a different size
— the same fault arriving from the other direction.

### One rule replaced two: the view box is the ink, on all four sides

«الان سایز مجلسی و کتونی ۵ درصد کوچیکتر بشن / در قسمت منو باید آیکونها تو مربع
هم اندازه قرار بگیرن».

The second of those turned out to be the *same fault* as the shop's baseline,
seen from the side. Both are the sets' built-in padding: eight icons drawn on
eight different amounts of canvas, fitted into one box by something that
centres. In the strip that showed up as soles at different heights; in the
drawer's square it showed up as ink 11.5 to 14 wide and 5.5 to 13.5 tall, so a
sandal read as a sliver beside a handbag.

So the crop is now tight on all four sides, not just the bottom, and that one
rule answers both:

- the strip keeps `object-position: center bottom`, and because a file's bottom
  edge *is* the sole, every sole lands on one line;
- **the drawer needs no CSS at all.** `preserveAspectRatio` already fits and
  centres; once the box is the ink, "fits" means every icon is as large as its
  square allows — which is what equal size means for eight objects that are not
  the same shape.

It is a change of a few per cent, not a resize, because both boxes fit by the
longer side: measured in the drawer, +3% on the handbag, +5% on the watch, +6%
on the loafer, +14% on the heel.

**The five per cent is slack, not a scale.** `shrink: 0.05` grows the view box
around the ink in both dimensions, so the artwork paints smaller inside whatever
box it is fitted to and its shape is untouched. The extra height comes off the
top rather than being shared, because the bottom edge is load-bearing — it is
the line the strip stands every icon on.

**One measuring trap, for the next person.** The strip scrolls horizontally, so
only five of the eight are on screen at 390. A scan that walks each icon's
bounding rect will happily read the three off-screen ones as 44, 120 and 196
pixels wide, because their rects lie outside the viewport and the window lands
on whatever else is painted there. The visible five are the ones to trust; check
`getBoundingClientRect()` against the viewport before believing a number.

### The drawer's tiles were never squares — a declaration that had never run

«گفتم تو منو باید آیکون ها تو مربع قرار بگیرن و همه مربع ها یه اندازه باشن».

`.vp-cat-icon` has asked for `width: 28px; height: 28px` since it was written,
and **the height has been losing the whole time.** The template resets
`img:not([draggable]) { height: auto }`, and an element plus a pseudo-class
beats a single class — so the tile's height came from the picture's own aspect
ratio and not from the number in this file.

That was invisible for as long as every icon's view box was square: auto height
off a 1:1 picture is the same 28, so the eight tiles agreed **by accident rather
than by instruction**. Cropping the boxes to their ink ended the accident, and
the column measured 28 wide by 19.66, 20.09, 22.75, 26.88, 27.00, 27.39, 29.47
and 31.75 tall — eight different rectangles where eight equal squares were
written.

The fix is `.vp-drawer-cats a .vp-cat-icon`, one class more specific than the
reset, carrying the height and `object-fit: contain`. Both sizes — 28, and 25
inside the `max-height: 730px` block — live in that one place, so neither can be
undercut by source order later in the file. Verified at 844 and at 700: eight
tiles of 28 × 28, and eight of 25 × 25.

**Worth carrying as a class of bug, not a bug.** A declaration that has never
taken effect is indistinguishable from one that has, right up until something
else changes and it is finally asked to do its job. Nothing in this repo checks
computed style against written style, so the only way this surfaces is somebody
measuring — which is why every visual claim here gets measured.

### The heel: a triangle out of its middle, not a different heel

«فقط آیکون مجلسی زشته» → «چنتا آیکون مجلسی پیدا کن جایگذین کنیم».

**Twenty-four replacement heels were shown across two rounds and every one was
refused.** All twelve monochrome heels in the whole Iconify catalogue, then
twelve more taken from the coloured cuts and flattened to gold. The catalogue is
exhausted; do not go looking again without reading this.

What the client wanted was not a different shoe: «میتونیم همین آیکون مجلسی
فعلیرو یکم توشو خالی کنیم که **هم وزن** بشه با باقی آیکونامون». The complaint was
weight. The heel is the only one of the eight that is a solid mass — the loafer
carries a sole line, the boot a shaft seam, the sneaker its motion marks, and
every one of them shows some ground through itself. This one showed none, so it
read heavier than its neighbours at the same size.

**The first attempt was refused too, and the reason is worth keeping.** It was
two straight bars, one along the sole and one along the vamp: «خیلی مصنوعی
هستن» — a ruled slot across a drawing whose every other edge is a curve. So the
cut is a `roundedTriangle`, each corner turned with a quadratic through the
corner itself, which is the same curve a rounded join is.

**The corners are the client's own, measured off the drawing they sent.** Their
image put the shoe's ink at x 285..835 and y 750..1290 — 20.95 and 22.04 pixels
to the unit against this 32 grid — and the triangle they drew came to x
12.3..20.2, y 12.6..20.1. What is not theirs is the slope: «وتر موازیِ رویه», so
the hypotenuse is parallel to the shoe's own upper edge, which runs (10.37,
5.13) to (24.23, 21.41) at 1.175. That lifts the apex from 12.6 to 10.9 and is
what makes the cut read as drawn rather than as placed.

**Clearance is what to preserve if those numbers are ever touched.** At the
apex's height the shoe's upper edge is at x 15.28 and the cut stops at 12.4; at
the base the edge is at 23.12 and the cut stops at 20.2. Under about two units
the wall between the cut and the edge stops being visible at 26px.

And then «سایزش ۵ درصد کوچیکتر بشه» a second time, which compounds like the
boot's crops: 0.05 + 0.95 × 0.05, so the artwork ends at 0.9025 of its box.
Measured in the strip: 25.00 wide before, 23.50 after, soles still on 211.

## The basket, rebuilt to the client's reference

«دقیقا این ui بساز ولی از راست چین و به شکل جزیره ای فعلی» — a screenshot of a
basket card and its summary, to be built exactly, mirrored, and in the island
treatment this page already has.

**The card was five columns and is now two.** Photograph, then one stack beside
it: name, the price under it in the shop's gold, then the specification lines.
The bin is in the far corner and the stepper is at the foot of the stack. What
went: the price as a separate column aligned to the line's far edge, and the
stepper as a column of its own. `.vp-cart-line` carries the reasoning.

Three things about it are worth knowing before changing it:

- **Two specification lines: the size on one of its own, the colour on the
  bottom row beside the stepper.** «حالا سایز کفش بره بالاتر که بتونی رنگ هم
  بنویسی» — the reference's own arrangement. A pass before this one left the
  colour out whenever a variant had no colourway and let the stepper take
  whichever spec line came last; that was a judgement about the data, and the
  row had been asked for.
- **Every card reads «رنگ: نامشخص» today**, because every variant in the
  catalogue is still `color_family = 'unspecified'`. The field is
  `display_color` on the product screen in `/admin`; nothing in the view needs
  changing when real colourways are typed in. The seller line is separate and
  appears only on a vendor's line.
- **The desktop caps the stack at 560.** The reference is a phone card and the
  desktop's line is about 1400 wide; `1fr` stretched it the whole way and
  `space-between` threw the stepper a screen's width from the size line.

**The summary is the reference's four rows** — جمع کالاها, تخفیف, هزینه ارسال,
then مبلغ قابل پرداخت under a rule — and the button carries the figure, «ادامه
(… تومان)».

**This is also a reversal.** «کلا اینجا یدونه فقط تعداد خرید باشه و جمع کل» had
cut this block down to two rows on the grounds that delivery was decided at the
next step and the code was typed there; the reference has all four, so all four
are back. The row that was hardest to make honest is delivery, and that is what
`App\Support\Checkout\Shipping` was for:

> The fee is now printed twice on the way to an order — quoted on the basket,
> charged by `PlaceOrder`. It used to be a private method on `PlaceOrder`, so a
> basket printing it would have needed a second copy of the rule. It is one
> class now and both read it. It is also applied to the subtotal **before** the
> discount, because that is the number `PlaceOrder` passes it; the first draft
> here passed the discounted total, which would have put a basket one code away
> from quoting a fee the order then contradicted.

**That is now history: the basket quotes no delivery fee at all.** See
«روش‌های ارسال» below — there are three methods, two of them پس‌کرایه, and
which one is chosen is a decision made a page later. Folding *any* figure into
«مبلغ قابل پرداخت» would guarantee the contradiction the paragraph above exists
to prevent, so the button is the goods less the discount and a line under it
says delivery is chosen next. `Shipping::on()` survives as the fallback for an
order placed without a method — nothing on the storefront can do that, the field
is required — and is what `demo:orders` charges.

`CheckoutTest::test_the_basket_quotes_the_goods_and_says_delivery_comes_next`
replaced the test that pinned the old promise, and
`test_the_basket_summary_shows_the_three_kept_rows` pins the rows — worth
having, since the shape they replaced was asked for by name too.

### And then to the reference's proportions, not just its arrangement

«کسخل من نسخه موبایلو بهت تصویر دادم / دقیقا شبیه این بساز جموجور مرتب فقط راست
چین بشه». The first pass built the picture's *arrangement* and not its
*proportions* — right parts, wrong sizes: a photograph too small for its card,
type too large, lines too far apart, a stepper half again the reference's size.

The block at the very end of `tweaks.css` is measured off the picture rather
than chosen. In the picture the card is 264 wide and 93 tall, the photograph is
64 square and the stepper's circles are 17 across; as fractions of the card's
own width — the thing that survives the screenshot having been scaled — that is
0.242, 0.352 and 0.064. Ours, at 390: **0.243, 0.332 and 0.066.**

Three things make it read as tidy, and none of them are sizes:

1. **The text block stretches to the photograph's height.** `align-items:
   stretch` with the `margin-block-start: auto` already on `.vp-cart-last` puts
   the name on the card's top line and the stepper on its bottom one. Loose,
   unaligned space under the text was most of what looked untidy.
2. **The lines are 2 apart.** Four short lines beside a photograph are a block;
   the same four spread out are a list.
3. **Both stepper buttons are quiet circles.** The gold `+` was right for the
   earlier reference; this one draws both grey, so both are grey — and round,
   which is what turned two rounded squares into one control.

It is scoped to `.vp-cart-line`, because the same stepper classes are on the
order pages, and it sits at the end of the file so source order cannot lose it
to the four earlier basket blocks.

**The fourth row is paid for out of the leading, not out of the height.**
«باید فاصله بین ردیفارو کم میکردی که رنگ هم تو همون ابعاد ارتفاع جا میشد چرا
چند پیکسل به ارتفاع اضافه کردی» — adding the colour line had taken the card from
112 to 120.25. The card is 88 of photograph and 24 of padding, so the text block
has exactly 88 to fit four rows into, and it had grown to 96.25. The 8.25 came
back off the spacing and **nothing on the card is smaller to read**:

| | was | now |
| --- | --- | --- |
| row gap, over three gaps | 2 | 1 |
| price line box | 26 (`normal`) | 20.8 (`line-height: 1.3`) |

The price was much the loosest line on the card — 26px of box around 15px of
figure, where the name sat in 21 and the size in 17.25 — so it is where the
room was. Text block 88.05 into the photograph's 88: the photograph sets the
card's height again and the card is back to **112 with four rows in it**.

244 tests, Pint clean, parity identical at 992/1200/1440/1920, and no sideways
scroll on the basket at 320/360/390/430/575/768/992/1200/1920.


## The header's basket panel draws the page's card

«سبد خریدی که تو هدر بصورت جزیره ای باز میشه نسخه قدیمیه باید بشه شکل این چیز
جدیدی که ساختی» — the island behind the header's basket button was still the
old row: a 56px photograph, «۱ × سایز ۳۷», the price off to one side and a `×`.

It is not styled *like* the basket page's card now, it **is** it. The panel's
lines are `.vp-cart-line` with the same children, so they take the same block
measured off the client's reference and cannot drift again. `.vp-mini-*` is now
only the panel *around* the cards — head, foot, empty state, and the scrolling
list. The 97 lines of dead line rules were deleted rather than left: they were
more specific than the card's own and would have gone on winning.

**The stepper made `cart.update` and `cart.remove` return to where they were
pressed** (`redirect()->back(fallback: cart)`). From the basket page "back" *is*
the basket, so nothing there changed; from the drawer it is the page the shopper
was reading. Before, nudging a quantity from a panel floating over the home page
threw them onto the basket page — which the `×` had always done too.

Measured in the open drawer at 390: panel 370 wide, card 350, nothing clipped,
and the card is the page's — photograph 88, stepper circles 24, bin in the
corner. 244 tests, Pint clean, parity identical at 992/1200/1440/1920.


## The two filter sheets get a way back out, at one size

«این دو جا باید کنار دکمه اعمال فیلتر یه دکمه سفید باشه پاک کردن فیلتر / ارتفاع
و طول هر دو دکمه هر دوجا باید یک اندازه باشه» — a white «پاک کردن فیلتر» beside
the gold «اعمال فیلتر», in both the price sheet and the brand sheet, and the
same size in both.

**The two applies were never the same button.** The price sheet had
`.vp-price-apply` and the brand sheet `.vp-sheet-apply`, 36px tall at 12px in a
10px radius against 44 at 14 in 13.25 — two rules for one control, drifting
apart quietly because nothing rendered them side by side. The class is gone;
both feet are now `.vp-sheet-actions` with the same pair inside it, so the size
is written down once and cannot come apart again.

**The clear is an inset ring, not a border, and that is the equal width.** With
`flex: 1 1 0` on both, a border on one of them is not inside the share it is
given — a border-box item's flex base is clamped up to its own borders, so the
ring comes off the free space and then goes back on. Measured at 390 the first
attempt ran 160 and 162 in a 334 row, which is exactly what the instruction
rules out. `box-shadow: inset 0 0 0 1px` is painted rather than laid out: both
bases are 0 and both buttons come to **161 × 44, in both sheets, at the same
y**. Matching it with a transparent border on the gold would have evened the
widths and re-opened «بالا و پایینش دو رنگی داره» — a gradient in a box with a
transparent border tiles its own ramp into the border strip. See `.vp-chip.is-on`.

**Each clear clears its own sheet and keeps everything else.** They are links,
not buttons: clearing is a place — the same listing without this sheet's filter
— so it belongs in the URL and in the back button, and it works with no script.
The price clear drops the two boxes and the price sort, but a sort from the row
above («پرطرفدار», «تازه‌ترین») is not the price filter and rides along. The
brand clear drops every brand and keeps the sort.

**It turned up a filter this page was already losing.** `$carry` — what every
control in that row hands on — never held `min` and `max`, so a typed price was
thrown away by the next tap on a sort tab, on a brand, or on «حراج پله‌ای». It
filtered correctly, said nothing, and came back unfiltered. The price form
below already excluded them by name, which says plainly they were meant to be
there. They are in it now, in Toman, because that is the unit the boxes are
read in and a Rial figure would have filtered at ten times the price.

`CataloguePagesTest` is what watches all of it: both clears' query strings, the
price sort going and the other sorts staying, and every link in the row keeping
a typed price. 306 tests, Pint clean, parity identical at 992/1200/1440/1920,
and no sideways scroll at 390/768/1200/1920.

**The pills came down 15% after it.** «این بیضی ها خیلی بزرگن اندازشون باید ۱۵
درصد کوچیکتر بشه», in the filter panel and the brand sheet alike — the two are
the same pill in two panels, kept in step by hand. Every number times 0.85:
34 → 28.9 tall, 14 → 11.9 of padding, 12 → 10.2 of type, radius still half the
height at 14.45. The type had to come with it or the pill would have stayed as
wide as it was, since the word inside is most of its width. Measured at 390,
«ونس و کتونی» went 34 × 92.3 → 28.9 × 78.7 and «نایک» 34 × 52.1 → 28.9 × 44.6,
and the filter panel is 507 → 486 tall in an 844 screen. `.vp-sheet-picked`'s
`min-height` followed, or the empty space above the rule would have been the
one thing that grew.

## The template stops being able to paint

**«قبلا یکبار بهت گفته بودم هر اثری از قالب قبلی تو این کد لعنتی هستو بکلی پاک
کن ... پس چرا الان دوباره وقتی سایت داشت آپدیت میشد برای چند لحظه اینارو نمایش
داد؟؟؟؟!!!!»** — two photographs of a phone taken during a deploy: this shop's
photographs and Persian, laid out as the template. A mint hero, red headings
and buttons, the logo and the four header icons stacked in a bare column, and
the five story rings printed as five loose «٪۳۰»s.

**Nothing had gone back.** The markup is the shop's; the *paint* was the
template's. The page is `style.rtl.css` with `tweaks.css` on top, and the first
of those styles this page completely on its own — it is a ThemeForest template,
that is what it is for. `tweaks.css` is the 644KB that turns it into this shop.
When the second file does not arrive the browser does not stop; it paints the
first. Reproduced in Chromium by aborting the one request: the header goes to
its bare column with the title in the template's red, the hero loses its card
and goes full-bleed, and the hero's own pastel — `#D3FBD9` on
`.heroSlide6 .swiper-slide:nth-child(3)`, the mint in the photograph — comes
back. The client's two frames are that state, at two moments.

**Why during a deploy, and only then.** Liara replaces the container; either
side of the swap a request can be answered by nothing at all. The returning
visitor's other six stylesheets are cached and answered off their own disk, so
they cannot fail. `tweaks.css` is fingerprinted with the md5 of its contents —
which is right, and is why an edited stylesheet reaches anybody at all — so a
deploy that touched it has changed its URL and made it the one file that *must*
cross the network. The single file the shop's whole appearance lives in is the
single file exposed to the one minute it is not there. Nothing anywhere goes
red: the run is green, the HTML is correct, and the failure is which of eight
requests got dropped.

**The design signs itself, and nothing paints unsigned.** `tweaks.css` now ends
with `:root { --vp-design: ok }` — a declaration that cannot be read unless the
whole file arrived. The head, immediately after the last `<link>`, asks the
computed style for it. That position is the whole trick: a script in the head
runs only after every stylesheet above it has finished, loaded or failed, and
before `<body>` is parsed — so its answer is final and nothing has been painted
when it gets it. Missing:

- **first time** — hide the document, reload once 1.2s later. The window is
  seconds wide, so the reload lands on a served file and the visitor gets a
  page that was slow instead of a page that was wrong.
- **second time** — stay hidden and say «سایت در حال به‌روزرسانی است» with a
  «تلاش دوباره» button, on a plain white panel drawn from inline styles so it
  needs none of the stylesheets it is reporting on.

Measured at 390 with the request intercepted: **dead** — `visibility: hidden`
at first paint, `--vp-design` empty, two loads, then the notice; **dropped once
then served** — the correct page, no notice, nothing left behind; **served** —
visible, one load, no overlay, and the gate returns before it touches the
document. Parity is unaffected for the same reason: `check-parity.js` serves
the CSS, so the gate opens on both pages.

**`DesignGateTest` is what watches it**, because nothing else can. Every check
we have — parity, overflow, the suite — renders a page whose stylesheet
arrived, and so none of them can see a rule that only matters when it does not.
It holds the gate's presence in all three shells (storefront, panel, error),
the gate sitting *after* the `<link>` rather than before it, the signature
being the **last** declaration in the shipped stylesheet and in the preview's
copy, and the preview carrying the same gate as the Blade.

**What this does not do.** The template's stylesheet is still the foundation of
the page — 648KB, and the markup's `th-*` classes, the grid, the swiper skins
and the icon set are all its. The gate means it can no longer be *seen*; it
does not mean it is gone. Removing it is not a tidy-up but a rebuild of the
page's CSS from nothing, and it should be decided as one, not slipped into a
round. Anything appended below the signature in `tweaks.css` is a rule the gate
cannot vouch for — put it above.

## Six corrections to the phone, and nothing to the desktop

The list came as one message about the home page on a phone, and the first
reading took one of its items to be a page-wide number. The correction was
immediate — «من در مورد نسخه موبایل دستور دادم فقط» — so every rule in this
round is inside `@media (max-width: 991.98px)` and the one script change is
behind the same `phone` test that the category strip already used. Two of the
six describe things the desktop also has (the stock rail, the heart) and the
desktop keeps its own numbers for both.

- **«گوشه اون ۸ آیتم زیر هیرو باید ۵ درصد کروتر بشه»** — the tiles turn at 18px
  below 992, so **18.9**.
- **«گوشه کارت بزرگی که برندا روشن باید ۵ درصد کروتر بشه»** — 32.4 → **34.02**.
  Worth knowing: 32.4 is the hero card's own corner, and the daily deal and the
  brand panel were put on it deliberately in one commit so the phone would turn
  at one radius. This takes the brand card 1.62px off that agreement because it
  was named by itself. The other two are unchanged.
- **«اون قسمت بالایی همین کارت که عنوان روشه ۳ پیکسل بهش اضافه بشه»** — the head
  strip carrying «برندهای موجود» goes 28 → **31px**, the 3 above the title. Its
  margin-bottom is untouched, so nothing inside the card moves relative to
  anything else; the panel grows by 3.
- **«سایه پایین اون کارت پیشنهاد امروز که کفش روشه باید پاک بشه»** — the drop
  shadow goes, the 1px inset hairline stays. Measured down a one-pixel column
  through the card's foot at 390, three pixels of card first, then the pane:
  `255 255 · 243 · 225 226 226 227 228 229 230 231 232 233` before,
  `255 255 · 243 · 247 …` after. The pane is 247; the shadow was a 225 smudge
  climbing ten levels across ten pixels. The card's own edge — 255 → 243 → 247
  — is identical either way, which is what keeps this clear of «لبه پنهان».
- **«اون رنج خطی که بالای تایمر همین کارته باید ۳۰ درصد پر بشه»** — 8% → **30%**
  on the phone. Both numbers are drawn, not read: the 8 was chosen so the bar
  would not contradict the «فقط ۱ عدد باقی مانده» above it, and the desktop
  keeps it.
- **«اون دوتا بنر وسط سایت هم باید گوشه هاشون کرو بشه و از بغل بیاد تو»** — the
  two `.cta-area4` bands were the only things on the phone still running edge to
  edge with square corners. **10px in on each side** (the gutter the trust row
  and the brand panel already use) and **32.4** of corner.
- **«در حالت فعلی ... اون ۶ آیتم پایین ۸ آیتم باید اسکرول کنیم تا پدیدار بشه ولی
  باید از اول باشه»** — the six trust badges leave the scroll reveal on phones,
  the way the eight tiles did a round earlier and by the same line of code. At
  390×844 two of them landed at y=634 and the other four at 760 and 885, so
  opening the shop showed two badges and four holes.
- **«آیکون قلب فیوریت هم خیلی پررنگه»** — `.vp-best-fav` is phone-only already.
  Its glyph goes from `#101111` to 0.45 of the same ink: measured on the darkest
  pixel, **16 → 147** on the disc's white.

320 tests, Pint clean, parity identical at 992/1200/1440/1920, no sideways
scroll at 390/768/1200/1920.

**Noticed while measuring, not touched:** the two banners' artwork is
`assets/img/normal/cta_11_1.png` and `cta_11_2.png`, and both are flat grey
placeholders with their own pixel dimensions printed across them — «1189 × 600»
and «707 × 600». They ship, so that is what the live page shows behind those
two headings. Nobody has asked about them and there is no photograph to put
there yet, but the round that gives those bands their corners is the round to
say it out loud.

## The account page, rebuilt — «این چه حساب کاربری داغونیه»

The complaint came with a photograph of `/account` on a telephone: «این چه حساب
کاربری داغونیه برای کاربر زدی خیلی داغونه». It is fair, and it is not really a
design complaint — the page had never been designed. It was written on the day
the orders list existed and everything the account grew afterwards was pushed
into the margin beside the customer's name: «پیام‌های من»، «لیست علاقمندی» and
«خروج» as three gold words at body size, then a grey fold, then a grey box
saying «هنوز سفارشی ثبت نکرده‌ای»، then a button, then 700px of nothing.

**It is three panels now**, in the order somebody reads them:

1. **who you are** — the first letter of the name in a gold tile, the name, the
   number, «خروج» as a quiet chip; then four figures on the glass — سفارش، در
   جریان، علاقه‌مندی، پیام تازه; then four doors to the messages, the wishlist,
   the tracking form and the shop, each with its mark, a line of its own and
   (for the messages) the unread count in red on the door itself.
2. **the orders** — a card each, with the panel's own status chip.
3. **the settings** — the name, and the password fold.

**Nothing new was drawn.** The pane is `.vp-shop-panel`, which is
`.vp-best-panel`'s. The figures sit on `rgba(16,17,17,0.063)`, the tint the
header island wears. The doors and the order cards are white with the sign-in
field's own 1.5px ring, and they take a gold ring on hover. The empty state is
`.vp-empty`, which the wishlist wrote. The status chip's five tones are
**`.vp-adm-badge`'s, quoted** — amber while it waits, blue while it moves, green
when it arrives, red when it is off — so one order is one colour to the shopper
and to the shop; if those move in `admin.css` they move here too.

**Three things the page could not do before, which are the actual complaint:**

- **«در جریان» did not exist.** The page could not say how many orders were
  still moving — placed, paid or shipped, as against delivered or cancelled.
  That is the number somebody opens an account to check.
- **The name could not be changed at all.** It is set once on the code step, and
  a shopper who typed it in a hurry — or whose row came from a checkout somebody
  else filled in — was stuck with it. `POST /account/profile` takes the name and
  **only** the name: the number is the credential, and a form that could change
  it would be a takeover with no code involved. There is a test that posts one
  anyway.
- **A refused password change said nothing.** `changePassword` redirected back
  to a page that printed no errors anywhere, so «رمز فعلی درست نیست» was flashed
  into the session and thrown away — the fold shut and nothing happened. The
  errors are printed under the field that failed now, and the fold is `open`
  when it is the thing that failed.

**Layout, measured rather than guessed.** The doors are 1 column on a phone, 2
from 576, 4 from 992 — fixed counts, not `auto-fit`, because there are exactly
four and a floor that fits three leaves one on a line of its own. The order card
is two rows on a phone (number and chip above, date, count and money below) and
**one row of five fixed columns from 768** — `132px 1fr 150px 104px 16px` — so
the dates, the money and the chips line up down the list instead of every card
arranging itself. Stacked on a desktop it left the middle of a 1100px card
empty, which is the same shape the whole page was being complained about for.
The settings panel is two columns from 768 for the same reason.

`.vp-shop-head` is gone from this page, and with it `.vp-acct-head`,
`.vp-account-acts` and the `:not(.vp-acct-head)` exemption in the phone rule
that hides that head: the account is drawn from `.vp-acct-top` and
`.vp-acct-sect` now, both laid out for 390 first. **The other five pages that
share `.vp-shop-head` still lose their heading on a phone** — the cart's «ادامه
خرید» is the next one that matters.

533 tests, Pint clean, parity identical at 992/1200/1440/1920, no sideways
scroll at 360/390/576/768/992/1200/1920 with the page signed in and holding
eight orders — and none on an account holding nothing, where the empty state is
the parcel mark and «رفتن به فروشگاه».

**Not built, and worth saying:** there is still no address book — `/account` has
nothing to offer between «پیگیری سفارش» and the settings, and every order
carries an address that was typed again at checkout. That is a table, a screen
and a checkout change, so it is a round of its own rather than something to
squeeze into this one.

## The green gold — «رنگ گلد ما گلد زرده»

«رنگ گلد ما گلد زرده چرا از اون گلد سبز برای آیکونا و دکمه ها استفاده میکنی»,
said on the account page an hour after it shipped. **It is the second time this
has been said** — the first was «دکمه ادامه هم نباید اون رنگی باشه باید همرنگ
باقی دکمه های سایت باشه», about the basket's «ادامه» button, and the comment
written above that fix says in as many words that the filter button «was not
asked about». It got asked about. `CLAUDE.md` now carries this as the codename
**«گلد سبز»**, with the table.

**There is no green.** Every gold in `:root` is one hue: 46.2° to 46.8° across
`--theme-color`, `--vp-gold-fill` and `--vp-gold-fill-ink`, all at ~71%
saturation. What separates them is lightness — 36.3% against 50.2% — and a dark
yellow reads olive. Nothing in the hue can be adjusted to save a fill that is
simply too dark; the fill has to get lighter.

**What moved, all of it measured after:**

| | was | is |
| --- | --- | --- |
| `.vp-filter-apply` — **nine views**: checkout, cart, order, track, messages, message, vendor-apply, filters, enquiry | flat `#A08119` | the ramp |
| `.vp-shop-search button` | flat `#A08119` | the ramp |
| `.vp-empty-out` — the account's and the wishlist's «رفتن به فروشگاه» | flat `#A08119` | the ramp |
| `.vp-seller-add` | flat `#A08119` | the ramp |
| `.vp-page.is-on` — the paginator's live page | flat `#A08119` | the ramp |
| `.vp-empty-mark` glyph | `#A08119` on a tint of the *old* gold | `#BB9920` on `rgba(218,178,38,0.14)` |
| `.vp-acct-door-mark` glyph | `#8B7217` on the same old tint | `#BB9920` on `rgba(218,178,38,0.16)` |
| the account's hover rings | `rgba(164,127,37,0.5)` | `rgba(218,178,38,0.65)` |

The ramp is `linear-gradient(90deg, #DAB226, #EFC94F)` — `.vp-enter-go`'s, which
is `.vp-pick-go`'s and `.vp-cart-go`'s. Hover was `--gr-color2`; it is
`brightness(1.04)` now, the same as the buttons it is joining. Read back off the
rendered page, the door glyph is `rgb(187,153,32)` — **the identical value the
header's three icon squares paint**, which is the point.

`.vp-pdp-buy` was flat too and is now the ramp, but it is on **no page**: the
product page's «افزودن به سبد» is `.vp-pick-go`. Corrected rather than deleted,
so a rule named `-buy` cannot come back wearing the one colour a buy button may
not be.

**Three left on the dark gold, deliberately, all to be raised before touching:**
`.vp-pdp-cut` and `.vp-seller-tag` are white-on-gold labels at 5.09:1 — on the
fill gold white would be 1.9:1, so they would have to flip to ink, which is a
bigger change than a colour; and `.vp-pdp-dot.is-on` is a 3px indicator bar on
white, where the fill gold reads 2.0:1 and vanishes.

**Left alone on purpose: text.** `--vp-gold-ink` and `--vp-gold-ink-deep` had
their lightness solved to hold measured contrast on white (the table at the top
of this file). Repainting a heading or a price to the fill gold trades a legible
page for a bright one.

**Still there, and worth a round of its own:** ~30 `rgba(164,127,37,…)` literals
— the *previous* gold, `#A47F25`, which really is a different hue at 42.5° and
63% saturation. The 2026-08-16 sweep moved the tokens and could not see tints
written as raw channels. Two of them were on the account page and moved here;
the rest are why some tints on this site read a shade muddier than others.

533 tests, Pint clean, parity identical at 992/1200/1440/1920, no sideways
scroll at 390/768/1200/1920.

## روش‌های ارسال — three methods, chosen at checkout

«در بخش ثبت سفارش، امکان انتخاب روش ارسال اضافه شود» with three of them:
**پست پیشتاز — پس‌کرایه**, **تیپاکس — پس‌کرایه**, **پست معمولی — ۲۰۰,۰۰۰
تومان**. Required, added to the total, kept on the order, shown to the shopper
and to the shop, and editable from the panel «تا مبلغ ۲۰۰ هزار تومان به‌صورت
Hard-code داخل سیستم نباشد».

### The one distinction the whole feature turns on

**A method's `price` is not what it adds to the order.** پس‌کرایه means the
carrier collects their own tariff at the door: the shop takes nothing, so the
order's `shipping_total` is 0 — but the parcel is *not* free and the order still
has to point at the method, or whoever packs it cannot tell the courier which
parcels go collect. That is `charge`, an enum of `prepaid`/`collect`, and
`ShippingMethod::costAtCheckout()`. **Read through `costAtCheckout()`, never off
`price`.** The two differ for exactly the methods where being wrong means
charging somebody twice or not at all.

### What holds the money, and what holds the kind

`orders.shipping_total` holds the money and `orders.shipping_method_id` holds
the kind, and `Order::shippingLabel()` reads one from each. That is deliberate:
the shop may reprice پست معمولی any afternoon it likes, and an invoice that took
its amount from the relation would silently restate what a customer agreed to
last week. `test_raising_a_methods_price_leaves_a_placed_order_alone` is the one
that says so.

### The basket stopped quoting delivery

It had to. With three methods and the choice a page away, any figure the basket
printed would be one the checkout contradicted. So «مبلغ قابل پرداخت» and the
«ادامه (…)» button are the goods less the discount, and `.vp-cart-ship-note`
under the button says the rest is chosen next. Two other places quoted the old
flat fee and now describe the methods instead: the FAQ's delivery answer (read
off the `shipping_methods` rows, so renaming or repricing one rewrites the
answer) and the product page's `.vp-pdp-ship` line, which used to promise free
delivery above a threshold that no longer exists.

### The radio had to be drawn from nothing

The base stylesheet is `input[type="radio"] { display: none }` for the whole
site, and what it draws instead is an icon-font circle on a **sibling**
`~ label::before`. This markup puts the input inside its label, so that rule
never matched: measured, all three controls were 0×0, `display: none`,
`visibility: hidden` — three plain boxes with nothing to tap. `.vp-ship
input[type="radio"]` un-hides it by name, `appearance: none` drops the platform
dial, and the dot is a `::before` scaling 0 → 8px on `:checked`. Not a
specificity fight; the base rule simply set a property the new one had not.

### Where the defaults live

`ShippingMethod::DEFAULTS` — the three, in checkout order. `Branch::booted()`
creates them for every new branch, so a franchise opens sellable; a branch with
no method cannot take an order at all now, because the field is required. The
migration that put them on the branches that already existed carries its own
frozen copy on purpose: a migration that read a constant would change meaning
the next time somebody edited the constant.

**It is a migration and not a seeder** for the reason at the top of `CLAUDE.md`:
`catalogue:seed` fills only an empty catalogue, and production has not been
empty for weeks. Editing a seeder there ships green and changes nothing.

### The panel

`/admin/fulfilment`, «روش‌های ارسال». A card per method — name, state, toggle,
its facts, and a form for `charge`, price and transit. It was a table until the
sixth column: measured at 1200, this card sits in a ~300px column of the panel's
grid and the last two columns were **outside** it, the toggle among them. Price
is typed and read in Toman like everywhere else in the panel and stored in Rial
like everywhere else in the database; a number typed next to «پس‌کرایه» is
dropped rather than stored, or the row would carry an amount nothing charges.

The «ارسال کردم» section prints what the shopper chose, and prints it in the red
tone when it is پس‌کرایه — hand a collect parcel to the carrier as prepaid and
the shop pays the postage and finds out at the end of the month.

627 tests, Pint clean, parity identical at 992/1200/1440/1920, no sideways
scroll at 390/768/1200/1920.
