# VikyPlus — notes for whoever picks this up next

## ⛔ `main` is the work and `main` is the site — read this before you touch anything.

**The client runs several sessions on this repository at once, from more than
one account.** You are probably not the only one working right now. Everything
in this block exists because of that.

**`main` is the only branch that reaches the live site.** Work wherever you
like; a push to a working branch runs the whole test suite and deploys
*nothing*. A change is «فرستاده شده» when `main` carries it and not before.

### Start every session here

```
git fetch origin main
git merge origin/main          # or branch from it, if you are starting fresh
```

### Ship like this

```
git fetch origin main
git merge origin/main          # again — somebody may have shipped meanwhile
# run the checks, then:
git push origin HEAD:main
```

If that push is rejected, `main` moved while you worked. Merge it and push
again. **Never force.** A rejected push is the system working; a forced one is
somebody's afternoon deleted.

### Why it is arranged this way

The workflow used to name four branches as deploy branches, and every one of
them deployed *its own tree* to the same Liara app. There is no merge step in a
deploy, so the live site was never a combination of two branches — it was
whichever pushed last, whole. On 2026-08-15 two sessions ran in parallel and the
second one's pushes silently replaced the first one's work on the live site
while losing nothing in git:

```
#214  07:12  shop-search-filter-height   its work live
#215  08:59  shoe-store-ui-review        that work gone from the site
```

The client found it on their phone hours later — «چرا موارد برمیگرده به نسخه
قبل؟ این چه ایراد مزخرفیه؟» — because nothing anywhere had gone red. Before
that, the opposite failure: `main` sat **88 commits behind**, a session spent an
afternoon building on it, and the reply was «این دیگه چه کوفتیه؟ داری رو جای
اشتباه کار میکنی».

Both are the same disease — no single answer to "what is the site?" — and one
deploy branch is the cure for both. A guard in the deploy job closes the last
gap: it refuses to deploy anything that is not the current tip of `main`, so two
pushes landing together cannot end with the older tree going live. A superseded
run goes red on purpose. Red means "this did not ship", which is the truth, and
the truth on a screen is the whole point.

**Do not add branch names back to `on.push`.** If a branch needs to be seen
running, that is what `php artisan serve` and the Netlify preview are for.

**`HANDOFF.md` says what is finished, what is not, and which numbers the
finished part is not allowed to lose. Read it after this.**

An Iranian shoe and bag storefront (vikyplus.ir) built on the ThemeForest
"Erna" HTML template. The page being worked on is the RTL Persian preview.

## The build goes to Liara. It does not go to Netlify.

**The deliverable is the Laravel app in `storefront/`, and the only place a
change is «فرستاده شده» is Liara.** One session sent a build to Netlify instead;
the client saw an old page and had no way to tell why. So, plainly:

- **Deploy target: Liara**, app `vikyplus`, at <https://vikyplus.liara.run>,
  eventually vikyplus.ir. It is driven by
  `.github/workflows/deploy-liara.yml` — tests, then Pint, then
  `liara deploy` — and by nothing else. Nobody runs a deploy by hand.
- **The only trigger is a push to `main`.** This bullet used to name a list of
  deploy branches and it is the list that caused the accident in the block at
  the top of this file; there is no list any more. `on.push` has no branch
  filter at all, so **every** branch runs the whole suite — that is the signal
  a working branch needs — and the deploy job alone is gated, on
  `github.ref == 'refs/heads/main'`. A pull request is barred from deploying
  explicitly, and so is the "Run workflow" button pointed at another ref.
  Pushing a working branch therefore ships *nothing*, on purpose: to ship,
  bring `main` up and push `main`.
- **After a push, say what happened.** The client asks «بیلد تغییراتو بفرست»
  after every change and means the Liara deploy specifically. Read the run's
  two jobs (Tests, Deploy to Liara) and report the conclusion of both. Note
  that this container's proxy returns 403 for vikyplus.liara.run, so the run's
  own result is the check — curl is not available as a second opinion.
- **`netlify.toml` is not the deploy.** It publishes `download-version/` — the
  static design preview, which is a *copy* of the home page and has no PHP, no
  database and none of the catalogue pages. It is kept because the design is
  still argued on it and three scripts in `theme/` read from it. Publishing it
  is not shipping the shop, and a change that only reaches Netlify has not
  reached the client's site.
- **A deploy runs migrations. It does not re-seed.** `liara_pre_start.sh` runs
  `php artisan migrate --force` every time, but `php artisan catalogue:seed`
  seeds **only when the catalogue is empty** — deliberately, so a redeploy
  never puts a seeded price back over an edited one. Production has not been
  empty for weeks. So **editing `CatalogueSeeder` changes nothing on the live
  site**: it describes a fresh install, and production is not one. Anything
  seeded that later has to move — a brand's mark, a name, a slug — moves in a
  **migration**, or it ships green and changes nothing. This is silent in both
  directions: the tests pass (they migrate *and* seed a fresh database, so they
  see the new value) and the site keeps the old one. It has already cost a
  round: three brand marks were corrected in the seeder, the run went green,
  and the client photographed a live tile still wearing the template's mark
  with a broken image next to it. See
  `2026_08_16_070000_put_the_real_marks_on_the_brands.php`.

## Where things are

- `download-version/shoe-shop-rtl.html` — **generated**. Never edit it by hand;
  edit `theme/make-rtl-page.js` and re-run `node theme/make-rtl-page.js`.
- `download-version/assets/css/tweaks.css` — every deliberate deviation from
  the template, loaded last. One block per decision, with the reasoning and the
  measurements in the comment above it.
- `theme/make-category-photos.js` — the category tiles. The photographs go
  in exactly as supplied: resize only, no crop, no cut-out.
- `theme/make-favicons.js` — the whole icon set, `favicon.ico`, `manifest.json`
  and `browserconfig.xml`, all from **`assets/img/vikyplus-appicon-1024.png`**.
  Same rule as the category photographs: resize only. Re-run it if the mark ever
  changes. **Two sources, deliberately:** the 208px `vikyplus-appicon.png` is
  also the logo in the header, the footer and the phone drawer, so it is left
  alone — changing it changes the visible site and breaks parity. The 1024 is
  the same mark at the resolution the client later supplied, framed identically
  (the gem is 59.62% of the canvas in both), and Android's required 512px icon
  is resized from it rather than upscaled from 208.
- **The shop is installable on a telephone, and three separate things make it
  so**: `start_url` in the manifest, a 512px icon (plus a `maskable` one so the
  launcher does not draw a white box), and `public/sw.js` registered from the
  page. Miss any one and Chrome will not offer «نصب برنامه» — it mints a
  throwaway APK instead, which Google Play Protect blocks with «built for an
  older version of Android». That is what the client photographed.
  **`sw.js` caches nothing and must not start**: this site's whole appearance
  is one stylesheet, and a cache-first worker would make «قالب قبلی» permanent
  and unfixable by deploying. `InstallableTest` holds all of it, including that
  the worker stores nothing.
  The shop's mark appears in three places (header, footer, phone drawer) and all
  three are the same lockup — see HANDOFF.md before adjusting any of them.
- `storefront/resources/views/` — the Laravel app renders the same page, and
  six of its bands come out of the database. The six under `home/` and
  `partials/mobile-menu.blade.php` are hand-owned; the rest of `partials/` is
  **generated** — never hand-edit
  those, run `node theme/make-blade.js` after a markup change and
  `node theme/sync-storefront-assets.js` after a `tweaks.css` or photograph
  change. `make-blade.js` prints which files it left alone.
  Serve it with `cd storefront && php artisan serve --port=8812`, after
  `php artisan migrate --seed`.
- `node theme/check-parity.js` — renders the preview page and the Laravel page
  at four widths and counts the pixels that differ. **Zero is the expected
  answer.** Run it after changing either side; it is the only thing that
  notices when the two copies of this page come apart. It compares the *home*
  page only — the catalogue pages below exist in Laravel and nowhere else, and
  nothing checks their pixels.
- The storefront is more than that one page now. `/products`, `/products/{slug}`,
  `/categories/{slug}` and `/search` are Blade written by hand under
  `resources/views/shop/`, in the home page's own materials — the pane is
  `.vp-best-panel`'s and a product card is the stepped sale's `.vp-deal`. If
  either of those changes, these change with it.
- **Price and stock belong to a branch, not to a variant.** `variants` has no
  price column; `branch_offers` and `branch_inventory` do, and every read goes
  through the branch bound for the request. `$variant->offer`, `$variant->stock`
  and `$product->offerHere()` are the way in. A query with no branch bound
  returns nothing rather than everything, on purpose.
  `php artisan branch:open <slug> <name> --markup= --stock=` opens a franchise
  at `/<slug>` with the central catalogue at its own prices.
- **Stock only ever moves in two places**: `PlaceOrder` reserves it and
  `SettleOrder` sells or releases it, both under `FOR UPDATE`. Anything else
  that writes `branch_inventory` is a bug waiting to be an oversell. Every
  movement is also a row in `inventory_movements`, so a shelf can explain
  itself.
- **Two sign-ins, two guards.** Staff are `web` at `/admin/login`; shoppers are
  `customer` at `/account/enter`. Every staff route says `auth:web` explicitly —
  the bare `auth` means "whichever guard is default", which is a runtime value.
  **The shopper's sign-in is the number first, then a password or a code.**
  «ورود با رمز باشه، ورود با کد یکبار مصرف هم یه آپشن باشه چون اصلا ممکنه اون
  شماره اون لحظه در دسترس شخص نباشه که کد بیاد» — so a number with a password is
  asked for it (and **no SMS is sent**), a number without one is sent a code
  straight away, and the password step carries «ورود با کد یکبار مصرف» for when
  the telephone is not in the room. **A password is only ever set behind a
  code**: on the code step for a number that has none, or on `/account` behind
  the old one. That is not ceremony — checkout has always created `customers`
  rows keyed on a phone number, a phone number is not a secret, and letting
  anybody set a password on one of those rows would hand over a stranger's order
  history. There is no registration form; the code step is it. The password has
  **no minimum length**, at the client's explicit instruction, so the throttles
  on `/account/password` and `/account/verify` are most of what stands against
  guessing. See `AccountController` and `LoginCode`.
- **The code is a credential and is stored hashed**, good once, for two minutes,
  for five guesses; the number it was sent to lives in the *session*, never in
  the form, or a code could be verified against a number of the sender's
  choosing. **The SMS goes nowhere yet**: `config('services.sms.driver')` is
  `log`, and `SmsServiceProvider` **throws at boot** if that is still true in
  production, so the shop cannot go live silently swallowing its own sign-in
  codes. Going live is a provider account, `SMS_KEY`, a service line and an
  approved pattern, plus a `Sender` implementation registered in that provider's
  `DRIVERS` map — the interface is one method. **Melipayamak is already written**
  (`melipayamak` for the API key, `melipayamak.panel` for username/password), so
  connecting it is four `SMS_*` variables in the Liara panel and no code at all.
  **`php artisan sms:test 09xxxxxxxxx` is how anybody finds out whether it
  worked**: `Sender` swallows a provider's refusal on purpose — a 500 in front
  of a shopper is worse than a message that did not arrive — so a wrong key and
  a message still in flight look identical from the outside, and without this
  command the only test is signing a real customer in and hoping.
- **The content pages are `/about`, `/contact`, `/size-guide`, `/faq`, `/terms`
  and `/privacy`** — `PageController`, one view each under `resources/views/pages/`,
  copy and no database. They exist because the footer had been linking to them
  since the template arrived: `page_url()` resolves an unmapped filename to `'#'`,
  and **21 of the footer's 47 links were one**. `ContentPagesTest` counts that
  failure directly, so adding a footer item for a page nobody built fails the
  suite rather than shipping a link that goes nowhere. Everything the pages
  state is read off the application — the FAQ quotes `storefront.checkout`'s own
  delivery charge, the cancellation answer is `Order::isCancellable()` in words.
  **The legal text on `/terms` and `/privacy` is a draft nobody qualified has
  read.** It is accurate about the software; that is not the same thing.
- **`/wholesale` and `/franchise` are the two things the shop advertises and
  had no way of hearing about.** «خرید تکی و عمده» has been on the front page's
  trust row and in the footer's strap since the template was dressed with no
  page behind it; the branch network is the largest thing in this application
  and a prospective franchisee had the telephone number and nothing else. Both
  are `EnquiryController`, one `enquiries` table with a `kind`, **not
  branch-scoped** (a franchise application is not Shiraz's to answer). The kind
  comes from the route, never the request body. **There is no mail provider, so
  the row is the delivery** — `/admin/enquiries` under
  `platform.enquiry.manage` is the other half of the feature, not an extra.
  Neither page quotes a wholesale price: there is no wholesale tier in the
  catalogue, so any number printed there would be one nobody decided.
- **The error pages have a shell of their own**, `layouts/error.blade.php`, and
  it must stay that way. The storefront shell's composers query the database and
  the mini basket's *throws* when no branch is bound — which is exactly the state
  a 404 for an unmatched route is in, so rendering the ordinary shell there turns
  a 404 into a 500. `ErrorPagesTest` asks for one with the tenant forgotten.
- **`.vp-page` is one link in the paginator**, not a page. It carries
  `display: grid; height: 38px`, and a panel that wore the name came out 64px
  tall with its whole content spilling out under the footer. The content pages
  are `.vp-doc`. Check a class name against `tweaks.css` before choosing it —
  the file is 15,000 lines and the collision is silent.
- The panel is at `/admin`, hand-built (no Filament, at the client's request)
  in the storefront's own materials — `resources/views/admin/` and
  `layouts/admin.blade.php`. Its branch comes from the **signed-in user**, not
  the URL; `php artisan staff:invite` makes an account, and
  `php artisan staff:password <email>` sets one — **not** a query-builder
  `update(['password' => ...])`, which skips the `hashed` cast and writes the
  plaintext, after which every sign-in 500s on `Hash::check`. That has
  happened; `BrokenPasswordTest` and `App\Support\Auth\Passwords` are what
  came of it.
- **`php artisan demo:orders` fills the panel with pretend orders**, one in
  every state the shop can produce — unconfirmed, confirmed, late, shipped,
  delivered, cancelled before payment, refunded after it — because against an
  empty shop every screen in `/admin` reads zero and none of them can be
  judged. They go through `PlaceOrder` and `SettleOrder`, so the stock they
  take is really taken and the panel's own counts agree with them; they carry
  `MakeDemoOrders::NOTE` in `staff_note` and a 0999 telephone, which is all
  `--remove` trusts. **`--remove` gives the stock back**, walking a shipped or
  delivered one to «paid» first so the sale reverses. Every size keeps
  `--floor` units (1 by default): eight demo orders on a shop holding one of
  each size emptied «پرفروش‌ترین‌ها» and cut the home page by 1800px, which
  `check-parity.js` caught and `DemoOrdersTest` now holds. **On a shop with
  nothing above the floor — which is what production holds, one of each size —
  use `--lend`, never `--floor=0`.** `--lend` puts the units on the shelf
  first, as `inventory_movements` rows carrying `MakeDemoOrders::LENT`, and
  `--remove` takes them back off; measured on that shop, every size stays
  sellable and `check-parity.js` prints zero the whole time. `--floor=0` there
  empties all eight sizes and collapses the home page. Never wire any of it
  into a seeder or into `liara_pre_start.sh`.
- **The panel's dates are Jalali on both sides.** `fa_date()` prints them, and
  `public/assets/js/admin-jalali.js` — loaded from `layouts/admin.blade.php`,
  fingerprinted like admin.css — puts a Persian calendar on the five fields
  you *type* into, which the browser would otherwise draw in the device's own
  calendar. **It hides the real `<input type="date">`, never replaces it**: the
  field keeps its name and its `YYYY-MM-DD`, so nothing on the server knows
  this exists. Add a date field anywhere under `.vp-adm` and it is covered.
  `PanelDatesTest` holds that promise, because a rewrite that swapped the input
  for a text field would look identical and would post a Persian date to a
  parser expecting a Gregorian one.
- The marketplace is at `/vendor` (a vendor's own panel) and under `/admin`
  for the platform's side. **Nothing in it is branch-scoped** — a vendor sells
  across the platform, so those screens sit outside the branch group and use
  `RequirePlatformPermission`. A vendor's money is `ledger_entries`, which is
  append-only: the model throws on update and delete, and a balance is
  `SUM(amount)`, never a column. `php artisan vendor:invite` registers one.
- **Two kinds of promotion, kept apart.** The stepped sale is a price on an
  offer; a discount code is `discount_codes` + `discount_redemptions`, typed
  into the basket, and applies to the branch's own lines only — never to a
  vendor's price. Whether it applies is recomputed on every page and again
  inside the order transaction; nothing about it is stored on the basket.
- **`ResolveTenant` and `ResolveAdminTenant` must run before `SubstituteBindings`** — set in
  `bootstrap/app.php`'s priority list. Branch-scoped models fail closed, so a
  binding resolved before the tenant finds nothing and the page 404s for
  everybody. Tests can hide this by leaving a branch bound in the container;
  forget it before the request when testing a page that binds one.
- **The phone drawer is invisible to every check we have.** It is parked
  off-screen, so `check-parity.js` cannot see it and `check-overflow.js` cannot
  either — which is how the template's demo menu («About Style 1», ten
  blog-grids) survived the whole port as the only menu a phone visitor gets.
  `PhoneDrawerTest` is what watches it now, including that the static preview
  and the Blade still list the same categories. It is also sized to fit a
  375×667 screen with no scrolling; anything added there has to be measured
  again, because growing past the screen is silent.
- `node theme/check-overflow.js` — loads every page at 390/768/1200/1920 and
  fails if any of them scrolls sideways, naming the outermost element that
  sticks out. The pages after the home page have no pixel baseline to compare
  against, so this checks the one thing that is objectively wrong rather than a
  matter of taste. `VP_LOGIN=email:password` and `VP_PAGES=/a,/b` reach the
  panel.
- **Persian is typed three ways.** `fold_persian()` in PHP and
  `App\Support\Search::fold()` in SQL fold ي/ی, ك/ک, zero-width joiners,
  harakat and both sets of digits to one spelling. Search folds *both* sides;
  folding one and not the other fails for exactly the rows somebody typed on a
  different keyboard, and nobody can see why.
- Preview server:
  `cd download-version && setsid nohup python3 -m http.server 8811 &`
  It dies often; restart it with `setsid` rather than assuming it is up.
- **`.claude/skills/` carries 30 vendored design skills** — `ui-ux-pro-max`,
  `impeccable`, `design-taste-frontend`, eight `gsap-*`, Emil Kowalski's ten,
  and nine `hyperframes-*`.
  They are committed rather than installed as plugins because the container is
  thrown away and the repo is not. `.claude/skills/INSTALL-NOTES.md` says where
  each came from, what was deliberately left out (impeccable's hooks and
  sub-agents, eleven HyperFrames workflows) and how to update them. Every one of
  them hands out taste; this file and `HANDOFF.md` hand out measurements. **When
  the two disagree, the measurement wins** — and `check-parity.js` must still
  print zero afterwards.

## Measure, don't eyeball

Every visual claim in this repo's history was settled by rendering the page in
Chromium and reading pixels, not by looking. Playwright is at
`/opt/node22/lib/node_modules/playwright`, Chromium at
`/opt/pw-browsers/chromium-1194/chrome-linux/chrome`, sharp at
`theme/node_modules/sharp`. Screenshot, take the raw buffer, print the column
or row of values across the thing being argued about. It is faster than a
round trip with the client, and it is the only way most of these questions have
an answer.

---

# Codenames

Problems that have already cost real time. If the client names one of these,
read the entry before touching anything.

## «لبه پنهان» — the hidden edge

**Symptom, in the client's words:** the bottom of the hero card is not
defined; there is a horrible fade under it; extra white seems stuck to the
bottom of the cards. Reported three separate times over one afternoon.

**What it is not.** It is not extra white, not a stray element, not the
neighbouring carousel slides, and not a clipped shadow. Each of those was
investigated and each was a dead end. The last one cost a full change that had
to be reverted.

**What it is.** The pane's tint is `rgba(16,17,17,0.034)`, which composites to
247 on white. Its own lit hairline is white at about half alpha, which reads
250. A drop shadow's value at the element's own edge is roughly half its alpha,
because the other half falls under the element and is clipped away — with an
alpha low enough not to look like a drawn line, that came to 244. So down the
card's foot the page read

```
247 (pane)   250 (lit hairline)   244 (shadow)   … 30px climbing … 255 (page)
```

Four values within a few levels of each other and no step anywhere. The card
dissolved into the page, and the long climb is the "fade". The top edge, which
nobody has ever complained about, does 254 → 247 in a single pixel.

**Why a shadow cannot fix it.** At the edge a soft shadow contributes about two
levels; everything else it does is the fade. Raising the alpha buys the edge
back and immediately puts a hard line under the card, which is where the whole
thread started — the client rejected that too, in the same words ("خطی و تیز").
Removing the shadow does not fix it either: that leaves the white hairline
sitting on the boundary and the edge is still soft. Both were tried.

**The fix.** Draw the edge as an edge.

- The lit hairline stops short of the foot: the `:after` ring's padding is
  `1.4px 1.4px 0`, so the mask draws no band along the bottom.
- The foot is ink: `box-shadow: inset 0 -1.4px 0 rgba(16,17,17,0.07)`, the same
  thickness as the hairline on the other three sides.
- No drop shadow on either pane.

Measured after: card `247 → 234 → 255`, header island `247 → 230 → 255`.
A panel lit from above and resting on a surface is darker where it meets it,
so this is also what the light would do.

Both panes — `.heroSlide6 .hero-inner` and `.th-header .menu-area` — carry it,
and they must stay in step: they are meant to read as one material.

**Do not** put the drop shadow back without re-reading this. If a shadow ever
does return, note that `.th-hero-wrapper` and the swiper are both
`overflow: hidden` and end on the same pixel as the card, so the room for it
has to be made inside the clip (`.heroSlide6` bottom padding, with the same
amount taken off `.feature-area2`'s top padding) — and that room has to be
changed whenever the shadow's reach changes. Getting that wrong is what put a
straight cut across the page 20px under the card.

## «همسایه» — the peeking neighbours

The template gives the hero deck `margin: 0 -36%` and runs two slides to a view,
centred, so the cards either side of the active one show past the page's
margins — 83px of pane on each side, at every width. Ours are six panes of the
same glass rather than the template's six pastels, so they can be mistaken for
a stray panel.

**They are wanted. Cutting them has been undone twice now.** The second time
cost more than the first, because the request that prompted it was about
something else entirely: the client wrote «منوی بالا اضافات داره از ۲ طرف» —
the top *menu* has extra space at both ends — and that was the 120px of dead
space inside the header island, not these panes. The giveaway was in the very
next message, «هدر بالا **هنوز** فضای اضافه داره»: *still*, because the header
had not been touched. When a complaint names a part of the page, take it to
mean that part.

If this ever genuinely is asked for, the change is `slidesPerView: 1` at the 992
and 1200 breakpoints of the hero's `data-slider-options`, the track back to the
page's width with `width: 85%` and `margin-inline: auto` above 992, and
`initialSlide: 1` so the deck still opens on the slide carrying the real
product photograph. Confirm it against the page before writing any of it.

## «قالب قبلی» — the template comes back

**Symptom, in the client's words:** «چرا الان دوباره وقتی سایت داشت آپدیت میشد
برای چند لحظه اینارو نمایش داد؟؟؟؟!!!!» — two photographs of a phone taken
during a deploy, showing this shop's own photographs and Persian laid out as
somebody else's template: a mint-green hero, red headings and buttons, the
header's logo and icons in a bare column, the five story rings printed as five
loose «٪۳۰»s.

Read literally, it is the previous session's promise broken — every trace of
the Erna template removed, and here it is again. It is not that. Nothing in the
markup went back.

**What it is.** The page is the template's stylesheet with `tweaks.css` on top
of it, and they are two files. `style.rtl.css` styles this page *completely* on
its own — that is what a ThemeForest template is — and `tweaks.css` is the
644KB that turns it into this shop. Lose the second one and the browser does
not stop: it paints the first. The mint is the template's own
`#D3FBD9` on `.heroSlide6 .swiper-slide:nth-child(3)`.

**Why a deploy is when it happens.** Liara replaces the container, and for the
seconds either side of the swap a request can be answered by nothing. The
returning visitor's other six stylesheets come off their own disk and cannot
fail. `tweaks.css` is fingerprinted with the md5 of its contents, so a deploy
that touched it changed its URL: the one file the whole appearance lives in is
also the one file guaranteed to need the network, at the one moment the network
is not there. Nothing goes red — the run is green, the HTML is right, and the
failure is entirely in which of eight requests got dropped.

**The fix — the design signs itself, and nothing paints unsigned.**

- `tweaks.css` ends with `:root { --vp-design: ok }`. It cannot be read unless
  the whole file arrived.
- The head, after the last `<link>`, asks the computed style for it. A script
  there runs only once every stylesheet above it has finished — loaded or
  failed — and before `<body>` is parsed, so the answer is decisive and nothing
  has been painted yet. Missing: hide the document, reload once 1.2s later
  (the window is seconds wide, so the reload usually lands on a served file),
  and if it is missing again say «سایت در حال به‌روزرسانی است» on a plain white
  panel that needs no stylesheet to draw.

Measured with the stylesheet aborted in Chromium: dead — hidden, one reload,
then the notice; dropped once then served — the correct page, no notice;
served — visible, one load, nothing added. `check-parity.js` still prints zero,
because a page whose CSS arrives opens the gate and never touches the document.

**Do not** append rules below the signature in `tweaks.css`, and do not move
the gate above the `<link>`s — either one leaves it vouching for a file it has
not read to the end. `DesignGateTest` holds both, plus the gate's presence in
all three shells, because every other check we have renders a page whose CSS
was served and so can never see this.

**The deeper thing this does not fix:** the template's stylesheet is still the
foundation of the page, 648KB of it, and removing it is not a tidy-up — the
markup's `th-*`, the grid, the swiper skins and the icons are all its. The gate
means it can no longer be *seen*. If the client asks again for every trace to
be gone, that is the conversation: the base layer stays, and what it costs to
change that is a rebuild of the page's CSS from nothing.
