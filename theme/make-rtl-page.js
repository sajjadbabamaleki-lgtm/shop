#!/usr/bin/env node
/**
 * Generates an RTL Persian preview page from a template page.
 *
 * This exists to validate the RTL build visually before the markup is ported
 * into Blade. It is a preview artifact, not the shipping storefront: the
 * Persian strings below are a demo dictionary, not a translation layer.
 *
 * Run: node theme/make-rtl-page.js [source.html] [output.html]
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const SITE_IMG = path.join(ROOT, 'download-version/assets/img');

// Persian digits and separators. Declared with the paths rather than half way
// down, because the tables further on are evaluated where they are written and
// three of them format a price — the first to be moved up hit `Cannot access
// 'fa' before initialization`, which is the same trap in a different order.
const fa = (n) => n.toLocaleString('fa-IR');

// Usage: make-rtl-page.js [theme] [source.html]
//
// With no theme the page renders in the template's own colours and styles;
// only the Persian face and the direction corrections are added. Passing a
// theme name layers that palette on top — the variants live in the repo but
// are opt-in, not the default.
const theme = process.argv[2] && process.argv[2] !== 'none' ? process.argv[2] : null;
const src = process.argv[3] || path.join(ROOT, 'download-version/shoe-shop.html');
const out = path.join(ROOT, `download-version/shoe-shop-${theme || 'rtl'}.html`);

let html = fs.readFileSync(src, 'utf8');

// --- direction and language -------------------------------------------------
html = html.replace(/<html[^>]*>/i, '<html class="no-js" lang="fa" dir="rtl">');

// --- the head says whose shop this is ----------------------------------------
//
// «لطفا یک جستجوی کامل در کل صفحات بکن که هیچ نشونه ای از قالب آماده یا اون
// قالب ERNA وجود نداشته باشه».
//
// The template's own title, author, description and keywords rode through
// every build — «Erna - Multi-Purpose Modern & Minimal WooCommerce Template»
// was the browser tab, the search result and the share card of every page of
// this shop. It is the one trace of the template that a *visitor* could read.
html = html.replace(
  /<title>[\s\S]*?<\/title>/i,
  '<title>ویکی پلاس | فروشگاه کیف و کفش زنانه</title>'
);

html = html.replace(
  /<meta name="author"[^>]*>/i,
  '<meta name="author" content="ویکی پلاس">'
);

html = html.replace(
  /<meta name="description"[^>]*>/i,
  '<meta name="description" content="ویکی پلاس، فروشگاه اینترنتی کیف و کفش زنانه: کتانی، مجلسی، بوت، صندل و کیف، با ارسال به سراسر ایران.">'
);

html = html.replace(
  /<meta name="keywords"[^>]*>/i,
  '<meta name="keywords" content="کفش زنانه, کیف زنانه, کتانی زنانه, کفش مجلسی, بوت زنانه, ویکی پلاس">'
);

/*
 * The page does not zoom.
 *
 * «وقتی رو باکس سرچ تو فروشگاه زده میشه تصویر زوم و ناقص میشه» — tapping the
 * listing's search box on a phone zoomed the page in and cut the shot off at
 * the edge. That is not our layout: iOS Safari and Android Chrome zoom to any
 * form field whose text is under 16px, and every field on this site is 14 or
 * 15 because that is what the design was measured at. The page never zooms
 * back out on its own afterwards, so the visitor is left looking at a
 * magnified corner of a card.
 *
 * `maximum-scale=1` is what stops that particular zoom, and `user-scalable=no`
 * answers the rest of the instruction — «هیچ جا نباید تصویر زوم بشه، همه جا ui
 * و دیزاین قفل بشه». `touch-action: manipulation` in tweaks.css takes the
 * double-tap zoom with it.
 *
 * **The cost, said plainly**: this also takes away pinch-to-zoom, which is how
 * somebody with poor sight reads a page. It is WCAG 1.4.4, and it is the
 * client's decision rather than an oversight. Raising every field to 16px
 * would have fixed the reported symptom without that cost, and would have
 * changed type sizes across the shop — including the header's, which is not to
 * be touched. If the two are ever weighed again, that is the other road.
 */
html = html.replace(
  /<meta name="viewport"[^>]*>/i,
  '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">'
);

if (!html.includes('user-scalable=no')) {
  throw new Error('the viewport meta did not take — the template head has moved');
}

// --- the preloader comes off ------------------------------------------------
//
// The template covers the whole page with a white curtain and lifts it in
// main.js on the browser's `load` event:
//
//     $(window).on("load", function () { $(".preloader").fadeOut(); });
//
// `load` waits for every single subresource. One request that never finishes —
// not a 404, which resolves, but one that hangs — and the event never fires and
// the curtain never lifts. The entire shop is then held hostage by a decorative
// animation, which is exactly what happened on the first deployment: the site
// was up, serving correctly, and every visitor saw a blank white page.
//
// It buys nothing. Our page has no flash of unstyled content worth hiding, and
// the cost of being wrong about that is the whole site. So it goes, on both
// copies of the page, and the failure mode goes with it.
const PRELOADER = /[ \t]*<!--=+\s*\n\s*Preloader\s*\n\s*=+-->\s*\n[ \t]*<div class="preloader[\s\S]*?\n[ \t]*<\/div>\n/;
if (!PRELOADER.test(html)) {
  throw new Error('the preloader is not where it was — check before assuming it is gone');
}
html = html.replace(PRELOADER, '');

// --- the scroll-to-top ring becomes a WhatsApp button -----------------------
//
// «بجای این باید یه آیکون واتسپ بیاری با گوشه های کرو», with a screenshot of
// the template's gold circle and its up arrow. So the ring goes and a WhatsApp
// button takes its corner — «بجای این», not beside it, which was asked and
// answered before this was written.
//
// **The number lives here and nowhere else.** It is written into the generated
// page, which `theme/make-blade.js` then ports into the Blade partial, so both
// copies of the site carry one number from one line. The alternative — reading
// it from `config/storefront.php` — would make the Blade hand-owned and let
// the two pages drift, and the footer's landline is already hardcoded the same
// way. `wa.me` wants the international form with no plus and no leading zero:
// 09918905993 becomes 989918905993.
//
// The arrow's SVG goes with the ring. It was a scroll *progress* indicator —
// the path's dash offset was written every frame from the page's scroll — so
// it has no meaning on a button that opens a chat, and the script below sets
// `ring` to null rather than looking for a path that is not there.
//
// The rest of that script is still wanted, though. «آیکون واتسپ وقتی اولین
// اسکرول شروع میشه باید ظاهر بشه» — the show-on-scroll behaviour the ring had
// is the behaviour this button wants too, so the same handler drives it,
// pointed at `.vp-whatsapp` and toggling on the first pixel instead of the
// ring's 50th. This round reverses the one before it, which parked the button
// on screen from the top on the reasoning that a way to ask a question is most
// wanted on the first screen. The client has looked at both.
const SCROLL_TOP = /[ \t]*<!-- Scroll To Top -->\s*\n[ \t]*<div class="scroll-top">[\s\S]*?\n[ \t]*<\/div>\n/;
if (!SCROLL_TOP.test(html)) {
  throw new Error('the scroll-to-top ring is not where it was — check before replacing it');
}
const WHATSAPP = [
  '    <!-- WhatsApp -->',
  '    <a class="vp-whatsapp" href="https://wa.me/989918905993" target="_blank" rel="noopener"',
  '       aria-label="گفتگو در واتساپ">',
  '        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>',
  '    </a>',
  '',
].join('\n');
html = html.replace(SCROLL_TOP, WHATSAPP);

// --- swap in the flipped stylesheets ---------------------------------------
const SHEETS = [
  ['assets/css/style.css', 'assets/css/style.rtl.css'],
  ['assets/css/bootstrap.min.css', 'assets/css/bootstrap.rtl.min.css'],
  ['assets/css/swiper-bundle.min.css', 'assets/css/swiper-bundle.rtl.min.css'],
  ['assets/css/magnific-popup.min.css', 'assets/css/magnific-popup.rtl.min.css'],
];
for (const [from, to] of SHEETS) {
  html = html.split(`href="${from}"`).join(`href="${to}"`);
}

// Google Fonts is replaced by the self-hosted Persian face; dropping the
// request also removes a render-blocking third-party round trip.
html = html.replace(/<link[^>]*fonts\.googleapis\.com[^>]*>/gi, '');
html = html.replace(/<link[^>]*fonts\.gstatic\.com[^>]*>/gi, '');

// The Persian face and the direction corrections load after the template's own
// stylesheets. A theme, when asked for, sits between them, and hero-original
// keeps the hero out of it.
const layers = ['assets/css/fonts-fa.css'];
if (theme) layers.push(`assets/css/theme-${theme}.css`, 'assets/css/hero-original.css');
layers.push('assets/css/rtl-fixes.css');
// Requested deviations from the template, loaded last.
layers.push('assets/css/tweaks.css');

html = html.replace(
  /(<link[^>]+href="assets\/css\/style\.rtl\.css"[^>]*>)/i,
  '$1' + layers.map((h) => `\n    <link rel="stylesheet" href="${h}">`).join('')
);

// Icons carry the template's red inside the file, out of reach of the accent
// variable. theme/recolor-svg.js derives gold siblings; swap the references.
const SVG_GOLD = require('./svg-gold-map.json');
for (const [from, to] of Object.entries(SVG_GOLD)) {
  html = html.split(`"${from}"`).join(`"${to}"`);
}

// The hero deck is six slides over three photographs — the template repeats
// hero_6_1..3 twice — so these three cover the whole deck, one product each,
// in place of its grey placeholders. theme/make-hero-photos.js prepares them;
// see the note there on why they are normalised rather than dropped in as
// supplied.
//
// The product name is written per slot here rather than left to the DICT
// below, because the template puts the same label ("Nike Air Running Spikes")
// on every slide whatever product that slide actually shows. One global
// mapping cannot tell the three apart, and each slide now shows a different
// shoe.
//
// Every name is the word the three share, 'کتونی', plus the model. The
// heading sets the model on its own second line: the shared word reads as a
// lead-in, and the models start at the same place on every slide instead of
// wrapping wherever the measure happens to run out.
const HERO_TITLES = {
  hero_6_1: 'کتونی نیوبالانس ۵۳۰',
  hero_6_2: 'کتونی جردن وان ایر',
  hero_6_3: 'کتونی گلدن گوس',
};

// The model is bound with non-breaking spaces so the second line stays one
// line whatever the type size — the break belongs to the name, not to the
// measure. The label above the heading keeps the plain name on one line.
const heroHeading = (name) => {
  const [kind, ...model] = name.split(' ');
  return kind + '<br>' + model.join('\u00A0');
};

const HERO_PHOTOS = {
  hero_6_1: 'vikyplus-hero-nb530.webp',
  hero_6_2: 'vikyplus-hero-jordan.webp',
  hero_6_3: 'vikyplus-hero-goldengoose.webp',
};

// One match per slide: the label, the heading and the photograph are rewritten
// together, keyed on which of the three placeholders the slide carries. There
// is no other <img> between the heading and the shot, so the lazy span between
// them cannot run past the slide it started in.
html = html.replace(
  /(<span class="sub-title"[^>]*>)[^<]*(<\/span>\s*<h1 class="hero-title"[^>]*>)[\s\S]*?(<\/h1>[\s\S]*?<img src=")assets\/img\/hero\/(hero_6_[123])\.png(")/g,
  (_, openLabel, openTitle, betweenTitleAndImg, slot, closeSrc) => {
    const title = HERO_TITLES[slot];
    return openLabel + title + openTitle + '\n                                                ' +
      heroHeading(title) + ' ' + betweenTitleAndImg + `assets/img/hero/${HERO_PHOTOS[slot]}` + closeSrc;
  }
);

// The brand mark and name replace the template's own logo in the header band.
// Written as markup rather than a single image so the name and the line under
// it stay text — selectable, translatable, and sharp at any density.
html = html.replace(
  /<div class="header-logo">\s*<a[^>]*>\s*<img[^>]*>\s*<\/a>\s*<\/div>/i,
  '<div class="header-logo">\n' +
  '                                <a href="index.html" class="vp-logo">\n' +
  '                                    <img src="assets/img/vikyplus-appicon.png" alt="ویکی پلاس">\n' +
  '                                    <span class="vp-logo-text">\n' +
  '                                        <b>ویکی پلاس</b>\n' +
  '                                        <small>فروشگاه کیف و کفش زنانه</small>\n' +
  '                                    </span>\n' +
  '                                </a>\n' +
  '                            </div>'
);

// --- the circle that follows the mouse --------------------------------------
//
// «اون دایره گردانی که با موس حرکت میکنه». The template's cursor follower: two
// divs the size of a coin that trail the pointer across every page. On a phone
// there is no pointer to follow and it sits in a corner doing nothing; on a
// desktop it is the template's flourish and not this shop's.
//
// The markup goes and the script does not have to: main.js guards the whole
// block with `if ($('.cursor-follower').length > 0)`, so with the element gone
// it never runs.
if (!html.includes('cursor-follower')) {
  throw new Error('the cursor follower is already gone — check what replaced it');
}
// Two of them: the wrapper with its pair of divs, and a *third*
// `.cursor-follower` sitting loose in the markup underneath it. The guard
// below is what noticed the second one.
html = html.replace(
  /<div class="magic-cursor[^"]*">[\s\S]*?<\/div>\s*<\/div>\s*/,
  ''
);
html = html.replace(/<div class="cursor-follower"><\/div>\s*/g, '');
if (html.includes('cursor-follower')) {
  throw new Error('the cursor follower replacement did not match');
}

// --- the drawer's search field ----------------------------------------------
//
// Removed at the client's word, and the second time of asking: they meant the
// field, not only its button. The phone had no search at all for a while after
// that, which was said plainly here rather than worked around.
//
// It has one again, and the shape is the point: «یه آیکون مربع سفید سرچ بیاد
// کنار اون دوتا» — a square, beside the basket and the menu, not a field. The
// objection was to a text field taking a row of the drawer, and a button that
// goes to the search page is not that.

// The basket takes a filled icon rather than the template's outline SVG, to
// match the one on the sale cards and because it now sits on gold, where an
// outline reads as a hole rather than as a bag. fa-solid is already loaded —
// the sale cards use the same glyph.
// The button's only text is the count in its badge, so unnamed it announces
// itself as «۵» — a number with nothing saying what it counts. The name goes
// on the button and the badge stays visible; a name on the element wins over
// its contents, so the count no longer stands in for one.
html = html.replace(
  /<button type="button" class="icon-btn sideMenuToggler"><img[^>]*>/i,
  // The phone's search, at the right-hand end of the button group — which is
  // where the desktop's search field sits, so the two layouts order the same
  // controls the same way. `d-lg-none` because above 992 the field itself is
  // there and a second search beside it would be two ways to the same page.
  //
  // The same two-shape SVG the field's own button carries, rather than
  // FontAwesome's fa-search, whose handle is nearly as long as the glass. It
  // is sized by the CSS rather than by this markup's width/height, which the
  // viewBox makes safe to override.
  //
  // `search-product.html` is the template's search page and is mapped in
  // `config/storefront.php`, so make-blade turns this into the `search` route
  // rather than leaving a dead .html link in the Blade.
  '<a href="search-product.html" class="icon-btn vp-search-btn d-lg-none" aria-label="جستجو">' +
    '<svg class="vp-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">' +
      '<circle cx="8.5" cy="8.5" r="6" stroke="currentColor" stroke-width="2"/>' +
      '<line x1="12.9" y1="12.9" x2="15.3" y2="15.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
    '</svg>' +
  '</a>' +
  // The account, beside the basket. It lived in the dark strip above the
  // header until that strip was removed, and on a desktop the drawer — which
  // carries the other copy — never opens. `.vp-account-btn` so the two are the
  // same control at the two ends of the page and neither can be styled alone.
  '<a href="my-account.html" class="icon-btn vp-account-btn" aria-label="ورود / ثبت‌نام">' +
    '<i class="fa-solid fa-user" aria-hidden="true"></i>' +
  '</a>' +
  '<button type="button" class="icon-btn sideMenuToggler" aria-label="سبد خرید">' +
    '<i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>'
);

// --- the dark strip above the header ----------------------------------------
//
// «این هدر تیره رو حذف کن». It was the template's, entire: a German company's
// telephone number, `helloerna@mail.com`, and two select menus offering
// English/Spanish/Hindi and USD/Euro/GBP on a shop that is written in Persian
// and prices everything in Toman. The two pickers did nothing — no handler, no
// second locale, no second currency — so the strip's whole content was either
// false or inert.
//
// It carried two links that are real, and both are kept: «پیگیری سفارش» is in
// the phone drawer and is now the footer's «سفارش‌های من» as well, and
// «ورود / ثبت‌نام» has just become an icon beside the basket. Nothing else in
// the strip survives, because nothing else in it was true.
//
// Anchored on the next sibling rather than on a run of closing divs — the same
// trap the footer's contact block set, where a lazy match stopped inside the
// info-boxes and left the row one </div> heavy.
if (!html.includes('helloerna@mail.com')) {
  throw new Error('the header-top is not the template\'s any more — read it before deleting it');
}
html = html.replace(
  /<div class="header-top">[\s\S]*?(?=<div class="sticky-wrapper">)/,
  ''
);
if (html.includes('helloerna@mail.com')) {
  throw new Error('the header-top replacement did not match');
}

// FontAwesome's fa-search draws its handle almost as long as the glass
// itself, which read cramped once the header disc shrank. A two-shape inline
// SVG — a circle and a short stroke — replaces it, so the handle length is a
// number to set rather than a glyph's fixed proportions. currentColor keeps
// it on the button's own white, same as the glyph it replaces.
html = html.replace(
  /<button type="submit" class="th-btn"><i class="far fa-search"><\/i><\/button>/i,
  '<button type="submit" class="th-btn" aria-label="جستجو"><svg class="vp-search-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">' +
    '<circle cx="8.5" cy="8.5" r="6" stroke="currentColor" stroke-width="2"/>' +
    '<line x1="12.9" y1="12.9" x2="15.3" y2="15.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
  '</svg></button>'
);

// The two menu toggles carry a glyph and nothing else, so unnamed they
// announce as an empty button. They are the only way to the navigation below
// 992, which is where the page is read most.
html = html.replace(
  /<button type="button" class="th-menu-toggle d-block d-lg-none"><i class="far fa-bars"><\/i><\/button>/i,
  '<button type="button" class="th-menu-toggle d-block d-lg-none" aria-label="باز کردن منو">' +
    '<i class="far fa-bars" aria-hidden="true"></i></button>'
);
html = html.replace(
  /<button class="th-menu-toggle"><i class="fal fa-times"><\/i><\/button>/i,
  '<button class="th-menu-toggle" aria-label="بستن منو">' +
    '<i class="fal fa-times" aria-hidden="true"></i></button>'
);

// The row under the hero carries eight shoe categories instead of the
// template's four service boxes: a photograph filling each square, with the
// name on a strip of glass laid over it.
// Right to left, the order the row reads in. Eight does not divide evenly
// into Bootstrap's 12-column grid (unlike the original six, which was
// col-2), so the tiles use the bare "col" auto-layout class instead — equal
// flex division with no 12-unit quantization — to stay one row at every
// width, same as before.
const CATEGORIES = [
  ['majlesi', 'مجلسی'],
  ['sneaker', 'ونس و کتونی'],
  ['college', 'کالج'],
  ['sandal', 'صندل'],
  ['boot', 'بوت و نیم‌بوت'],
  ['bag-set', 'ست کیف و کفش'],
  ['accessory', 'اکسسوری'],
  ['sport-set', 'ست ورزشی'],
];

// The mark beside a category in the phone drawer. The same map as
// storefront/config/storefront.php's `category_icons`, and the two have to
// agree — check-parity.js compares the two pages but not the drawer, which is
// parked off-screen, so PhoneDrawerTest is what notices if they drift.
//
// All eight drawn for this, in one hand: the shop sells five kinds of footwear
// and the template ships one shoe, and the template's own icons are
// multi-coloured line art that would not have sat beside anything drawn to fill
// the gaps.
const CATEGORY_ICONS = {
  'majlesi': 'vp-cat-heel.svg',
  'sneaker': 'vp-cat-sneaker.svg',
  'college': 'vp-cat-college.svg',
  'sandal': 'vp-cat-sandal.svg',
  'boot': 'vp-cat-boot.svg',
  'bag-set': 'vp-cat-bagset.svg',
  'accessory': 'vp-cat-watch.svg',
  'sport-set': 'vp-cat-sport.svg',
};

// The name is real text on the tile, so it is also the link's own name and
// needs no aria-label.
const CATEGORY_ROW =
  '<div class="row vp-category-row">' +
  CATEGORIES.map(([file, name]) =>
    '\n                <div class="col">' +
    '\n                    <a class="vp-category" href="shop.html">' +
    `\n                        <img src="assets/img/category/${file}.jpg" alt="" loading="lazy">` +
    `\n                        <span class="vp-category-label">${name}</span>` +
    '\n                    </a>' +
    '\n                </div>'
  ).join('') +
  '\n            </div>';

// --- the drawer that opens on a phone ---------------------------------------
//
// The template's mobile menu was a directory of its own demo: «Electronics
// فروشگاه», «About Style 1», ten spellings of blog-grid, every one of them a
// page this shop does not have. Nobody saw it on a desktop, so it survived the
// whole port — and it is the *only* menu a phone visitor gets.
//
// Rebuilt from the shop's previous site, which the client asked for by name,
// in this one's materials rather than that one's: the old menu colour-coded
// its three shortcuts pink, blue and orange, and this page has one accent. So
// the tiles are the page's own quiet tint with gold marks on them, and the one
// with urgency — the sale — is lit the way the running step of the stepped
// sale is lit. Same device, same gradient, ink on the gold.
//
// Every destination here is a page that exists. The old menu's «ورود / ثبت‌نام»
// is not among them: this shop has no customer accounts — an order is found by
// its number — so the foot of the drawer is the basket instead. That swaps back
// the day accounts exist and not before.
const QUICK_LINKS = [
  ['fa-tag', 'تخفیف‌دارها', 'is-lit'],
  ['fa-clock', 'جدیدترین‌ها', ''],
  ['fa-arrow-trend-up', 'پرفروش‌ترین‌ها', ''],
];

const DRAWER_LINKS = [
  ['fa-truck-fast', 'پیگیری سفارش', 'order-tracking.html'],
  ['fa-store', 'فروشنده شوید', 'vendor-register.html'],
];

const DRAWER =
  '<div class="th-menu-wrapper">\n' +
  '        <div class="th-menu-area">\n' +
  '            <div class="vp-drawer">\n' +
  '                <div class="vp-drawer-head">\n' +
  '                    <a href="index.html" class="vp-logo vp-logo-drawer">\n' +
  '                        <img src="assets/img/vikyplus-appicon.png" alt="ویکی پلاس">\n' +
  '                        <span class="vp-logo-text">\n' +
  '                            <b>ویکی پلاس</b>\n' +
  '                            <small>فروشگاه کیف و کفش زنانه</small>\n' +
  '                        </span>\n' +
  '                    </a>\n' +
  // The class the template's plugin binds to. It is what closes the drawer,
  // so it stays whatever else changes around it.
  '                    <button type="button" class="th-menu-toggle" aria-label="بستن منو"><i class="fal fa-times" aria-hidden="true"></i></button>\n' +
  '                </div>\n' +
  '                <div class="vp-drawer-body">\n' +
  // No label over the three shortcuts. «اون دسترسی سریع و خط روبروش باید از
  // منو حذف بشن» — the heading and the gold rule that ran off the end of it
  // both. The chips say what they are; the label was naming a category the
  // menu does not otherwise have. The «فروشگاه» heading below stays: it has a
  // list under it that does need naming, and «همه محصولات» opposite it.
  '                    <div class="vp-drawer-quick">\n' +
  QUICK_LINKS.map(([icon, name, lit]) =>
    `                        <a class="vp-quick${lit ? ' ' + lit : ''}" href="shop.html">\n` +
    `                            <span class="vp-quick-mark"><i class="fa-solid ${icon}" aria-hidden="true"></i></span>\n` +
    `                            <span class="vp-quick-name">${name}</span>\n` +
    '                        </a>\n'
  ).join('') +
  '                    </div>\n' +
  '                    <div class="vp-drawer-heading">\n' +
  '                        <p class="vp-drawer-label">فروشگاه</p>\n' +
  '                        <a class="vp-drawer-all" href="shop.html">همه محصولات</a>\n' +
  '                    </div>\n' +
  '                    <ul class="vp-drawer-cats">\n' +
  CATEGORIES.map(([slug, name]) =>
    '                        <li>\n' +
    '                            <a href="shop.html">\n' +
    `                                <img class="vp-cat-icon" src="assets/img/icon/${CATEGORY_ICONS[slug]}" alt="" loading="lazy">\n` +
    `                                <span class="vp-cat-name">${name}</span>\n` +
    '                                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>\n' +
    '                            </a>\n' +
    '                        </li>\n'
  ).join('') +
  '                    </ul>\n' +
  '                    <ul class="vp-drawer-links">\n' +
  DRAWER_LINKS.map(([icon, name, page]) =>
    '                        <li>\n' +
    `                            <a href="${page}">\n` +
    `                                <i class="fa-solid ${icon}" aria-hidden="true"></i>\n` +
    `                                <span>${name}</span>\n` +
    '                            </a>\n' +
    '                        </li>\n'
  ).join('') +
  '                    </ul>\n' +
  '                </div>\n' +
  // «ورود / ثبت‌نام», as the shop's previous menu had it and as the client
  // asked for again. It was the basket for one round, on the grounds that this
  // shop had no accounts and a login button would go nowhere — so accounts were
  // built rather than the button changed back.
  '                <div class="vp-drawer-foot">\n' +
  '                    <a class="vp-drawer-cta" href="my-account.html">\n' +
  '                        <i class="fa-solid fa-user" aria-hidden="true"></i>\n' +
  '                        <span>ورود / ثبت‌نام</span>\n' +
  '                    </a>\n' +
  '                </div>\n' +
  '            </div>\n' +
  '        </div>\n' +
  '    </div>';

if (!html.includes('logo-gold.svg')) {
  throw new Error('the mobile drawer is not the template\'s any more — check what replaced it before replacing it again');
}
html = html.replace(
  /<div class="th-menu-wrapper">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>(?=<!--)/,
  DRAWER
);
if (html.includes('logo-gold.svg')) {
  throw new Error('the drawer replacement did not match');
}

// Five story circles between the header and the hero.
//
// «۵ تا حالت استوری دایره ای بزار بالای هیرو و زیر هدر ببینیم چطور میشه» — a
// look, asked for as one. It is phone-only: the strip is `display: none` above
// 991.98 so the desktop page does not gain a band it was never designed with,
// and so the desktop stays pixel-identical, which is the standing rule.
//
// The five are the catalogue's own first five sections, with the photographs
// the tiles under the hero already use and the names they already carry.
// Nothing here is invented — same rule as the trust badges. In the Blade they
// come out of `$categories` so the strip and the tiles cannot describe two
// different shops; here they are typed, the way the preview types everything
// the storefront queries.
const STORY_ROW =
  '<section class="vp-stories" aria-label="دسته‌بندی‌ها">\n' +
  '        <div class="vp-stories-row">' +
  CATEGORIES.slice(0, 5).map(([file, name]) =>
    // No caption under the circle — «نباید زیر عنوان داشته باشن استوری ها».
    // The name moves onto the link as its accessible name rather than being
    // deleted: a link whose whole content is a decorative photograph announces
    // itself as nothing at all.
    `\n            <a class="vp-story" href="shop.html" aria-label="${name}">` +
    '\n                <span class="vp-story-ring">' +
    `\n                    <img src="assets/img/category/${file}.jpg" alt="" loading="lazy">` +
    '\n                </span>' +
    '\n            </a>'
  ).join('') +
  '\n        </div>\n' +
  '    </section>\n    ';

html = html.replace(
  '<div class="th-hero-wrapper hero-6 slider-area" id="hero">',
  STORY_ROW + '<div class="th-hero-wrapper hero-6 slider-area" id="hero">'
);

// The mini basket, in place of the template's demo.
//
// What the basket button opened, on every page and at every width, was still
// the ThemeForest demo: «فروشگاهping Cart» (a half-translated title nobody
// caught), five Nike and Adidas shoes, `$39.00`, and remove links pointing at
// '#'. It survived the whole port for the same reason the phone menu did —
// nobody opens it in a desktop review, and no check can see it because the
// panel is parked off-screen.
//
// The preview draws the **empty** state, which is what a visitor with nothing
// in their basket gets, so the static page and the Blade render the same thing
// and check-parity.js compares like with like. The filled state is the Blade's
// alone — this page has no basket to fill.
//
// The classes the template's script binds to stay exactly as they are:
// `sidemenu-wrapper`, `sidemenu-content` and `sideMenuCls` are what open and
// close the panel, and renaming any of them makes the basket button do nothing.
const MINI_CART =
  '<div class="sidemenu-wrapper sidemenu-cart">\n' +
  '        <div class="sidemenu-content">\n' +
  '            <div class="vp-mini">\n' +
  '                <div class="vp-mini-head">\n' +
  '                    <h2 class="vp-mini-title">سبد خرید</h2>\n' +
  '                    <button type="button" class="closeButton sideMenuCls" aria-label="بستن سبد خرید"><i class="fal fa-times" aria-hidden="true"></i></button>\n' +
  '                </div>\n' +
  '                <div class="vp-mini-empty">\n' +
  '                    <span class="vp-mini-empty-mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M10 16 h28 l-3 22 a3 3 0 0 1 -3 3 h-16 a3 3 0 0 1 -3 -3 z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path><path d="M18 16 a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg></span>\n' +
  '                    <p class="vp-mini-say">سبد خریدت خالی است.</p>\n' +
  '                    <a class="vp-mini-out" href="shop.html">رفتن به فروشگاه</a>\n' +
  '                </div>\n' +
  '            </div>\n' +
  '        </div>\n' +
  '    </div>\n    ';

html = html.replace(
  /<div class="sidemenu-wrapper sidemenu-cart[\s\S]*?<div class="popup-search-box/,
  MINI_CART + '<div class="popup-search-box'
);

// Six trust badges under the category row: the template's own feature-card
// markup and CSS (feature-card.style2), just with gold icons in place of its
// red ones and Persian copy. row-cols-* rather than col-N, same reason as the
// category row: six is a clean fraction of twelve but five was not, and the
// row has been both.
// Solid-fill icons, not the outlined feature_2_* set — the client wants every
// icon in the same filled style as the payment shield, and said so twice.
//
// The sixth is a FontAwesome glyph rather than one of the template's SVGs, and
// that is the second time. It was `bag.svg` first, on the reasoning that it
// came from the same folder as the other five — but the template's icon set is
// almost entirely line art, and `bag.svg` is an outline drawn as a filled path,
// so it passed a "no stroke attribute" check and still read as a wire bag
// beside five solid glyphs: «اون آیکون هم باید مث اون ۵ تا توپر باشه».
//
// Every icon in that folder was rendered and looked at. The genuinely solid
// ones are the five already in use plus a credit card, three flames and some
// user silhouettes — there is no filled box or bag anywhere in it. FontAwesome
// 6 is already shipped and already used for the phone drawer's marks, its solid
// family is solid by definition, and `fa-boxes-stacked` is the subject. It is
// painted with the same `#7D6324 → #CE9E29` ramp the SVGs carry, so the row
// stays one material — see tweaks.css.
const TRUST_GLYPH = 'fa-boxes-stacked';
//
// The sixth is «خرید تکی و عمده», and it is here to make the phone's two-up
// grid come out even: «بنظرم یه آیتم تکراری بزار ۶ تایی بشه». What was asked
// for was a repeat of one of the five, which on a live shop reads as a mistake
// rather than as a sixth promise — so it is a real one instead, and its words
// are not invented either. The page already carries «تضمین کیفیت، ارسال سریع و
// امکان خرید تکی و عمده»; this is the half of that sentence the row did not
// already say. The strapline restates the claim rather than extending it,
// because a trust badge is a promise to a customer and this repository does
// not write those — see HANDOFF on copy.
const TRUST_ITEMS = [
  ['feature_card_1-gold.svg', 'ارسال سریع', 'ارسال به سراسر کشور'],
  ['feature_card_2-gold.svg', 'ضمانت بازگشت کالا', 'بازگشت و تعویض آسان'],
  ['secure-gold.svg', 'پرداخت امن', 'پرداخت آنلاین مطمئن'],
  ['check2-gold.svg', 'تضمین اصالت', 'گارانتی اصل بودن کالا'],
  ['feature_card_4-gold.svg', 'پشتیبانی آنلاین', 'پاسخگویی ۲۴ ساعته'],
  [TRUST_GLYPH, 'خرید تکی و عمده', 'امکان سفارش عمده'],
];

const TRUST_ROW =
  '<div class="row gy-4 row-cols-2 row-cols-lg-3 row-cols-xl-5 vp-trust-row">' +
  TRUST_ITEMS.map(([icon, title, text]) =>
    '\n                <div class="col">' +
    '\n                    <div class="feature-card style2">' +
    '\n                        <div class="box-icon">' +
    (icon.startsWith('fa-')
      ? `\n                            <i class="fa-solid ${icon} vp-trust-glyph" aria-hidden="true"></i>`
      : `\n                            <img src="assets/img/icon/${icon}" alt="">`) +
    '\n                        </div>' +
    '\n                        <div class="box-content">' +
    `\n                            <h3 class="box-title">${title}</h3>` +
    `\n                            <p class="box-text">${text}</p>` +
    '\n                        </div>' +
    '\n                    </div>' +
    '\n                </div>'
  ).join('') +
  '\n            </div>';

// The trust row sits outside .th-container, in its own full-bleed wrapper —
// the client wants it run out to the same 18px-from-the-edge margin as the
// header island (.th-header .menu-area), not held to the container's width
// like the category row above it.
html = html.replace(
  /<section class="feature-area2[^"]*">\s*<div class="container th-container">\s*<div class="row gy-4 gx-50">[\s\S]*?<\/div>\s*<\/div>\s*<\/section>/i,
  '<section class="feature-area2 positive-relative overflow-hidden">\n' +
  '        <div class="container th-container">\n' +
  '            ' + CATEGORY_ROW + '\n' +
  '        </div>\n' +
  '        <div class="vp-trust-row-wrap">\n' +
  '            ' + TRUST_ROW + '\n' +
  '        </div>\n' +
  '    </section>'
);

// Best sellers: six cards in a fixed row rather than the template's
// filterable grid — there is nothing left to filter once the count is
// fixed at six, so the tab row goes with it. Built on the same "photo tile,
// glass strip" object the category row and the deal cards already use, not
// a fourth new one: square shot, rounded corners, a glass strip carrying
// the name, and a round glass control at the foot.
//
// The client's own instruction: photographs and copy the site already has,
// not invented ones. The row draws its six straight from CATEGORIES above
// — the first six of the eight real category photographs, in the row's own
// order — rather than mixing in the five real deal products, so the row is
// one material throughout instead of two. Each card browses its category
// (its existing label, "مشاهده دسته", and a browse arrow) rather than
// pricing a single product, since a category photograph is not a SKU and
// forcing a price onto it would be the invented-content problem this was
// asked to avoid.
// The glass strip sits under the photograph rather than lapped over its
// foot — client correction — so it is a second block inside the card, not
// an absolutely-positioned overlay: nothing left to blur once it is off
// the photo and onto the page's own ground, so it drops the backdrop-filter
// too (see .vp-best-label below, same reasoning as .vp-ladder-notes span).
// Colour swatches on hover, over the photo: the template's own Select
// Color panel (.beige-color, still on the untouched product grids further
// down the page) reused rather than invented — same five swatch colours
// already baked into style.css. Plain spans, not the template's nested
// anchors: those swatches don't link anywhere distinct from the tile's own
// link anyway (every href in the original is the same shop-details.html),
// and a real anchor can't nest inside .vp-best-shot's own anchor.
const bestColors =
  '\n                            <div class="vp-best-colors" aria-hidden="true">' +
  '\n                                <span></span><span></span><span></span><span></span><span></span>' +
  '\n                            </div>';

// Client's own words: put a shoe name and a price in the strip, and don't
// worry that it doesn't match the photograph — this is a placeholder for
// testing, not a claim about what's in the shot. Reuses the five real
// name/price pairs already on the site (DEAL_ITEMS below) rather than
// inventing new ones, cycling to cover the sixth tile. "کتونی" dropped from
// every name — client's own request, so the name is short enough to sit on
// one line with the price beside it.
// --- the discount mark ------------------------------------------------------
//
// Everything from here to the end of `dealBurst` was written beside the hero
// and the sale cards and has been lifted to the top of the file, in four
// separate goes, because each new band that draws one of these sits above
// where it used to live and `const` is not hoisted. If a fifth band needs one,
// move it here rather than copying it — and expect the same error first:
// `ReferenceError: Cannot access 'X' before initialization` at load.
//
// Declared up here rather than beside the hero it was drawn for, because three
// bands draw it now — the hero, the sale cards and the best sellers — and the
// best sellers are assembled above where it used to sit. `const` is only in
// scope after it is evaluated, so leaving it below meant `Cannot access
// 'bestBurst' before initialization` on load. That is the third constant this
// file has had to lift for the same reason; if a fourth band needs one of
// these, move it here too rather than duplicating it.
//
// The discount mark: a lobed burst in the buy button's gold, with the offer
// on it. Used full-size on the hero shot, and again, smaller and with one
// line instead of two, on each of the five deal cards below — declared here
// so both can reach it.
//
// The outline is eleven lobes — outer and inner points alternating round a
// circle at radii 72 and 61, with a Catmull-Rom spline through them turned into
// cubic segments, which is what gives the soft scalloped edge rather than a
// spiked star. Generated once and written in, since it never changes.
const BURST_PATH =
  'M 75,3 C 80.73,3 85.7,14.57 92.19,16.47 C 98.67,18.38 109.11,11.33 113.93,14.43 C 118.75,17.53 116.67,29.94 121.1,35.05 C 125.53,40.16 138.11,39.88 140.49,45.09 C 142.87,50.3 134.42,59.63 135.38,66.32 C 136.34,73.01 147.08,79.58 146.27,85.25 C 145.45,90.92 133.3,94.19 130.49,100.34 C 127.68,106.49 133.17,117.82 129.41,122.15 C 125.66,126.48 113.67,122.66 107.98,126.32 C 102.29,129.97 100.78,142.47 95.28,144.08 C 89.79,145.7 81.76,136 75,136 C 68.24,136 60.21,145.7 54.72,144.08 C 49.22,142.47 47.71,129.97 42.02,126.32 C 36.33,122.66 24.34,126.48 20.59,122.15 C 16.83,117.82 22.32,106.49 19.51,100.34 C 16.7,94.19 4.55,90.92 3.73,85.25 C 2.92,79.58 13.66,73.01 14.62,66.32 C 15.58,59.63 7.13,50.3 9.51,45.09 C 11.89,39.88 24.47,40.16 28.9,35.05 C 33.33,29.94 31.25,17.53 36.07,14.43 C 40.89,11.33 51.33,18.38 57.81,16.47 C 64.3,14.57 69.27,3 75,3 Z';

// A stud in the mouth of each outward lobe — where the lobe opens out of the
// body, on the lobe's own axis. Not in the notches between them: that is half
// a lobe round from here and is where these first went, wrongly.
//
// Taken off the client's reference by measuring it, not by reading it. The
// centre has to be the gold's centroid and not its bounding box — eleven lobes
// are not symmetric about a box, and using the box put every angle out by
// enough to land the studs a half-lobe away. From the centroid, the outline's
// own tips come out at 285.3° and 317.8° and its notches at 300.4° and 333.1°;
// the two dots sit at 285.0° and 317.3°, which is the tips.
//
// Their radius measures 0.783 and 0.751 of the outline's, so 56.4 and 54.1 of
// our 72, and 55.5 is between them. It reads as the lobe's mouth because that
// is about where the mouth is: the chord joining the two notches either side
// crosses the lobe's axis at 61·cos(180°/11) = 58.5.
const BURST_LOBES = 11;
const BURST_STUD_ORBIT = 55.5;
// 2 was where these started. The client asked for 20% on the studs as well
// as on the burst, and the burst's own 20% is taken on its box in the CSS,
// so this 20% is on top of that: 2.4 here is a stud 44% larger on the page
// than before, against a burst 20% larger.
const BURST_STUD_R = 2.4;

// The outline starts on an outer point at twelve o'clock, so the lobes' own
// axes are that angle and every 360/11 from it.
const BURST_STUDS = Array.from({ length: BURST_LOBES }, (_, i) => {
  const turn = (2 * Math.PI) / BURST_LOBES;
  const angle = -Math.PI / 2 + i * turn;
  const cx = (75 + BURST_STUD_ORBIT * Math.cos(angle)).toFixed(2);
  const cy = (75 + BURST_STUD_ORBIT * Math.sin(angle)).toFixed(2);
  return `<circle class="vp-burst-stud" cx="${cx}" cy="${cy}" r="${BURST_STUD_R}"></circle>`;
}).join('');


// The same construction as the hero's mark, verbatim — shape, studs and all —
// just drawn smaller and with one line instead of two: at the deal cards'
// size there is room for the cut and nothing else. A gradient id per card:
// SVG ids have to be unique in the document, and five cards each need their
// own.
const dealBurst = (cut, i) =>
  `<svg class="vp-deal-burst" viewBox="0 0 150 150" aria-hidden="true">` +
  `<defs><linearGradient id="vp-deal-burst-gold-${i}" x1="0" y1="0" x2="0" y2="1">` +
  '<stop offset="0%" stop-color="#C0972F"></stop><stop offset="100%" stop-color="#E3B54A"></stop>' +
  '</linearGradient></defs>' +
  '<g class="vp-burst-star">' +
  `<path fill="url(#vp-deal-burst-gold-${i})" d="${BURST_PATH}"></path>` +
  BURST_STUDS +
  '</g>' +
  // Percent first in the string, same reasoning as the ladder tiles: this
  // text renders left to right, so the sign has to come before the digits in
  // source order to land behind them for an RTL reader.
  `<text x="75" y="88">٪${fa(cut)}</text>` +
  '</svg>';

// The five shoes in the stepped sale: photograph, name, list price, and the
// stock line each one carries.
//
// Declared here rather than beside the sale's own markup further down, because
// three bands read it now — the sale, the daily-deal banner, and the best
// sellers, which take their photographs from it — and a `const` is only in
// scope after it is evaluated. It was below the best sellers and the file
// threw on load.
const LADDER_DEALS = [
  ['hero/vikyplus-hero-goldengoose.webp', 'کتونی گلدن گوس', 6480000, 'فقط سایزهای ۳۷ و ۳۹'],
  ['hero/vikyplus-deal-cloudtilt.webp', 'کتونی اون کلادتیلت', 4880000, 'فقط سایزهای ۳۸ و ۴۰'],
  ['hero/vikyplus-hero-nb530.webp', 'کتونی نیوبالانس ۵۳۰', 7980000, 'فقط ۱ عدد باقی مانده'],
  ['hero/vikyplus-deal-v2k.webp', 'کتونی نایک وی۲کی ران', 6980000, 'فقط سایزهای ۳۷ و ۳۹'],
  ['hero/vikyplus-hero-jordan.webp', 'کتونی جردن وان ایر', 8480000, 'فقط سایز ۳۸'],
];

//
// «از همون عکس های قسمت حراج پله ای استفاده کن» — so this is not a table any
// more, it is derived from LADDER_DEALS. The photograph, the name and the
// price on every tile now belong to *one* shoe instead of a category
// photograph with somebody else's name under it. The placeholder the comment
// above admits to is half retired by that: which shoe lands on which tile
// still carries no meaning, but the tile no longer contradicts itself.
//
// «کتونی» is still dropped from the front of each name, at the client's own
// request, so the name fits on one line beside the price.
//
// **The order has to match `config/storefront.php`'s
// `placeholders.best_sellers.priced_from`, or the two pages show the same six
// shoes in different places and check-parity.js fails.** It did, the first
// time this was written: the generator ran in LADDER_DEALS' order and the
// storefront in the config's, and 46,629 pixels differed at 1440. The names
// below are that config's list, spelled the way LADDER_DEALS spells them.
const BEST_ORDER = [
  'کتونی نیوبالانس ۵۳۰',
  'کتونی جردن وان ایر',
  'کتونی گلدن گوس',
  'کتونی نایک وی۲کی ران',
  'کتونی اون کلادتیلت',
];

const BEST_TEST_ITEMS = BEST_ORDER.map((wanted) => {
  const deal = LADDER_DEALS.find(([, name]) => name === wanted);
  if (!deal) {
    throw new Error(`best sellers: no shoe in LADDER_DEALS called ${wanted}`);
  }
  const [file, name, price] = deal;
  return [name.replace(/^کتونی\s+/, ''), fa(price), file];
});

// The category's own file is no longer read — the tile takes the shoe's
// photograph instead — but the parameter stays so the caller's list of
// categories still drives how many tiles there are and in what order.
const bestCard = (_category, i) => {
  const [name, price, file] = BEST_TEST_ITEMS[i % BEST_TEST_ITEMS.length];
  return (
    '\n                <div class="col">' +
    '\n                    <div class="vp-best">' +
    '\n                        <a class="vp-best-shot" href="shop.html">' +
    `\n                            <img src="assets/img/${file}" alt="" loading="lazy">` +
    bestColors +
    // «گوشه چپ کارت پر فروش ترینها باید یه مربع اندازه ی سبد خرید تو هدر بیاد
    // و روش یه قلب بزاری». Outside the <a>, because a button inside a link is
    // invalid and because a favourite is not a navigation — the same reason
    // the sale card's basket sits outside its own link.
    //
    // Outline heart, not filled: nothing is favourited, and there is no
    // wishlist behind this yet.
    // «بعضی از همون کارتها» — every other tile. Nothing in the data says which
    // of these six is discounted (none of them is, on this band: the price
    // shown is the one before the sale), so "some" had to be a rule rather
    // than a fact. Alternating is the plainest one that reads as "some".
    //
    // `dealBurst` itself, not a copy of it: «اون ستاره تخفیف فقط در هیرو باید
    // سفید بشه» settled that this badge is the sale cards' gold one, and once
    // it is, there is nothing about it that differs except the number. Inside
    // the <a>, where the sale card puts its own, so it positions against the
    // tile — an <svg> in a link is valid where the button below is not. The id
    // suffix is prefixed so it cannot collide with the sale cards' five.
    (i % 2 === 0 ? '\n                            ' + dealBurst(25, `b${i}`) : '') +
    '\n                        </a>' +
    `\n                        <button type="button" class="vp-best-fav" aria-label="افزودن ${name} به علاقه‌مندی‌ها">` +
    '<i class="fa-regular fa-heart" aria-hidden="true"></i></button>' +
    '\n                        <div class="vp-best-info">' +
    '\n                            <div class="vp-best-label">' +
    '\n                                <span class="vp-best-lines">' +
    `\n                                    <span class="vp-best-name">${name}</span>` +
    `\n                                    <span class="vp-best-cta"><strong>${price} <span>تومان</span></strong></span>` +
    '\n                                </span>' +
    '\n                            </div>' +
    `\n                            <a class="vp-best-browse" href="shop.html" aria-label="افزودن ${name} به سبد خرید"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></a>` +
    '\n                        </div>' +
    '\n                    </div>' +
    '\n                </div>'
  );
};

// row-cols rather than col-N: six is a clean twelfth either way, but this
// keeps it in the same idiom as the trust and deal rows either side of it,
// and their default 24px gutter is the "small gap" the client pointed at —
// measured on both those rows, not eyeballed.
const BEST_ROW =
  '<div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xl-6 vp-best-row">' +
  CATEGORIES.slice(0, 6).map(bestCard).join('') +
  '\n            </div>';

// The card itself now runs to the trust row's own 18px edge margin — the
// client's "same distance as those five items" applied to the panel this
// time, not the row inside it (see .vp-best-panel below) — so .th-container5
// drops out entirely; nothing else needs its width.
//
// The title moves inside the card, small, at its own top-right, with a row
// of brand filters and a "view all" link opposite it on the left — the
// template's own filter-menu job, dropped when the row went from a
// filterable grid to a fixed six, now back but scoped to what the row can
// actually promise. The six tiles are categories, not SKUs tagged by
// brand, so the filters do not drive real isotope filtering the way the
// template's did — same footing as the colour swatches on the tiles below,
// which don't switch a real variant either. The five names are real ones
// already on the site (DEAL_ITEMS below), not invented.
const BEST_FILTERS = ['همه', 'نایک', 'جردن', 'نیوبالانس', 'گلدن گوس'];

const BEST_HEAD =
  '<div class="vp-best-head">' +
  '\n                <h2 class="vp-best-title">پرفروش‌ترین‌ها</h2>' +
  '\n                <div class="vp-best-filters">' +
  BEST_FILTERS.map((label, i) =>
    `\n                    <button type="button" class="vp-best-filter${i === 0 ? ' active' : ''}">${label}</button>`
  ).join('') +
  '\n                    <a class="vp-best-all" href="shop.html">مشاهده همه محصولات</a>' +
  '\n                </div>' +
  '\n            </div>';

// vp-best-section on the <section> itself: the default .space class gives
// every section 120px top padding, which — stacked with .vp-best-panel's
// own margin-top — put 144px between the stepped sale above it and this
// card, against the 40px measured between the trust row and the stepped
// sale. .vp-best-section zeroes the top half of .space (same specificity,
// this file loads last) so .vp-best-panel's own margin-top is the entire
// gap, set to that same measured 40.
html = html.replace(
  /<section class="space overflow-hidden overflow-hidden">\s*<div class="container th-container5">\s*<div class="row justify-content-xl-between justify-content-center align-items-center">\s*<div class="col-xl-4">\s*<div class="title-area text-center text-xl-start">\s*<h2 class="sec-title sec-title2 style1">Best Seller Products<\/h2>[\s\S]*?<\/section>/,
  '<section class="space overflow-hidden overflow-hidden vp-best-section">\n' +
  '        <div class="vp-best-panel">\n' +
  '            ' + BEST_HEAD + '\n' +
  '            ' + BEST_ROW + '\n' +
  '        </div>\n' +
  '    </section>'
);

// The stepped sale, in place of the template's collections band.
//
// What the band was is worth recording, because it is why it went. It was
// three cards and three layouts: two read photograph then copy and the third
// read copy then photograph, so its title sat 451px down the section against
// the others' 53 and its button 575 against 177; its photograph was 687 tall
// against their 426; and it sat on #FFE2B5 while they sat on #F5F5F5, a colour
// found nowhere else on the page.
//
// Rebuilding it as one card three times fixed the form but not the reason: the
// three names it then carried — جدیدترین‌ها, پرفروش‌ترین‌ها, تخفیف‌دار — are
// the headings of three sections immediately below it on the same page. A band
// whose whole content is a table of contents for the next screenful is a band
// the page is better without.
//
// This is a mechanism instead, and one the page has nowhere else: a price that
// falls a step a week until the thing sells. It gives the visitor a reason to
// act now that the discount badge on the hero cannot — the choice between this
// price and a better one that may not still have their size.
//
// The reference for it came in the client's own colours, maroon on cream. It
// is drawn here in the page's: the same glass as the hero pane and the trust
// cards, the same gold for the live step as the buy button, the same 24px
// corner, the same Cairo.
const LADDER_INTRO = {
  title: 'حراج پله‌ای ویکی پلاس',
  strap: 'خرید هوشمندانه، قیمت منصفانه',
  how: 'نحوه کار',
};

// Step, its cut, the week it runs, and where it stands. Exactly one is
// 'current' — the CSS leans on that for the gold tile and the live label.
const LADDER_STEPS = [
  ['پله اول', 15, 'هفته اول', 'done'],
  ['پله دوم', 30, 'هفته دوم', 'current'],
  ['پله سوم', 45, 'هفته سوم', ''],
  ['پله چهارم', 60, 'هفته چهارم', ''],
  ['پله نهایی', 70, 'پس از هفته چهارم', ''],
];

// The standing condition first, the countdown second: in RTL the first sits on
// the right, which is the order the reference reads in.
const LADDER_NOTES = [
  'انتقال پله فقط در صورت باقی‌ماندن موجودی',
  'پله بعدی در ۲۲ روز و ۱۴ ساعت',
];

// The cut is the live step's, so the two cannot drift apart when the step
// moves — the card's badge and its price both read from here.
const LADDER_CUT = LADDER_STEPS.find(([, , , state]) => state === 'current')[1];
const LADDER_STEP_NAME = LADDER_STEPS.find(([, , , state]) => state === 'current')[0];

// Four products, all cut-outs on transparent so they sit on the card's glass
// the way the hero's do. Two are the hero's own shots and two are shots that
// were the hero's before the client replaced them — kept rather than thrown
// away, so all four carry the same treatment. Real stock photography replaces
// these; the prices are demo figures, like every other price on the page.
//
// price is what it was before the sale. What is shown is that less the live
// step's cut, worked out here rather than written down twice.
//
// The fourth column was the size/scarcity line — «فقط سایزهای ۳۷ و ۳۹» and the
// like — drawn on the tile as .vp-deal-stock. The client asked for it off the
// card. It is left in the data rather than deleted: it is the only place those
// sizes are written down, it costs nothing unused, and putting the pill back
// is then one line in the template rather than five invented strings.

// fa-IR gives Persian digits and the Arabic thousands mark, which is what a
// price should read as on this page.

// How a step stands, drawn rather than set: a tick for the one that is done, a
// loading ring for the one running now, a clock for the ones still to come.
// Drawn, because that is what the tick here always was — it needs no icon font
// and lands on the same gold as everything else — and because three marks from
// one hand read as a set where three glyphs from a font do not.
//
// 16-unit box, stroked in currentColor, so each takes the colour of the
// rectangle it sits in and scales with it.
const STEP_MARKS = {
  done:
    '<svg viewBox="0 0 16 16" aria-hidden="true">' +
    '<path d="M3.6 8.4 6.6 11.4 12.4 4.9" fill="none" stroke="currentColor" ' +
    'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
  // A ring with a bite out of it, turning: the arc is 26 of the circle's 34.5
  // circumference, so about a quarter is open, which is what reads as loading
  // rather than as a plain circle.
  current:
    '<svg viewBox="0 0 16 16" aria-hidden="true">' +
    '<circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" ' +
    'stroke-width="2" stroke-linecap="round" stroke-dasharray="26 9"></circle></svg>',
  upcoming:
    '<svg viewBox="0 0 16 16" aria-hidden="true">' +
    '<circle cx="8" cy="8" r="5.9" fill="none" stroke="currentColor" stroke-width="1.6"></circle>' +
    '<path d="M8 4.7 V8.2 L10.3 9.7" fill="none" stroke="currentColor" ' +
    'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
};

// The words the marks stand in for. They are still said, on the element, so a
// screen reader gets what the eye gets.
const STEP_MARK_LABELS = {
  done: 'تکمیل شد',
  current: 'مرحله فعلی',
  upcoming: 'هنوز نرسیده',
};

// Nothing above the tiles: the step's name used to sit there, and everything
// that says what the step is now sits under it instead, as a row of small
// rectangles — the name, the week, and a mark for how the step stands. Every
// step carries all three now, so the five read as one row rather than as two
// long ones and three short.
const LADDER_STEPS_HTML = LADDER_STEPS.map(([name, cut, when, state]) => {
  const mark = state || 'upcoming';
  return `\n                    <li class="vp-step${state ? ' is-' + state : ''}">` +
  // Each digit on its own tile, split across the middle, so the row reads as a
  // board that flips rather than as type in a box.
  //
  // The percent tile comes first, not last: this row is forced ltr (see
  // above), so the last item lands at the visual right — the first thing an
  // RTL reader's eye meets — which put the sign in front of the number
  // instead of behind it.
  '\n                        <span class="vp-step-rate">' +
  ['٪', ...fa(cut)].map((ch) => `<b>${ch}</b>`).join('') +
  '</span>' +
  '\n                        <span class="vp-step-tags">' +
  `<span class="vp-step-name">${name}</span>` +
  `<span class="vp-step-when">${when}</span>` +
  `<span class="vp-step-flag is-${mark}" role="img" aria-label="${STEP_MARK_LABELS[mark]}">` +
  STEP_MARKS[mark] + '</span>' +
  '</span>' +
  '\n                    </li>';
}
).join('');

const LADDER_TRACK_HTML = LADDER_STEPS.map(([, , , state], i) => {
  const currentAt = LADDER_STEPS.findIndex(([, , , st]) => st === 'current');
  return `\n                    <span class="vp-track-leg${i <= currentAt ? ' is-filled' : ''}"></span>`;
}).join('');

// The card is one of the eight tiles, larger: a square photograph with the
// name on a strip of the same glass laid across it, and a round button for the
// basket beside the name. The tall card it replaces carried the same facts
// stacked — name, badges, price, stock, a bar, a full-width button — and at
// four across that made the row the heaviest thing on the page.
//
// What is kept on the tile is what the sale is: the cut, the name and the two
// prices. The step's name and the countdown come off the card, because the
// ladder above already says both once for all four.
//
// The size line went the same way at the client's request. The comment on
// .vp-deal-stock argued it was the scarcity half of the offer and had earned
// its place; that was our reasoning, not theirs, and they have now said
// otherwise. The rule is still in tweaks.css, unused, next to that argument.
// «یک محصول تکراری در حراج پله ای بزار که ۶ تایی بشه». The phone shows six
// cards and the sale holds five, so the sixth is the first one again — and it
// carries `d-lg-none`, because `row-cols-xl-5` puts five on one line above 992
// and a sixth would wrap that row onto two. The Blade does the same thing from
// the catalogue's side; see home/ladder.blade.php, and change the two together.
const LADDER_DEALS_HTML = LADDER_DEALS
  .map((deal, i) => ({ deal, i, phoneOnly: false }))
  .concat(LADDER_DEALS.length < 6
    ? [{ deal: LADDER_DEALS[0], i: 0, phoneOnly: true }]
    : [])
  .map(({ deal: [file, name, price], i, phoneOnly }) => {
  const now = Math.round(price * (100 - LADDER_CUT) / 100);
  return '\n                <div class="col' + (phoneOnly ? ' d-lg-none' : '') + '">' +
    '\n                    <div class="vp-deal">' +
    `\n                        <a class="vp-deal-shot" href="shop.html">` +
    `\n                            <img src="assets/img/${file}" alt="" loading="lazy">` +
    `\n                            ${dealBurst(LADDER_CUT, i)}` +
    '\n                            <span class="vp-deal-label">' +
    '\n                                <span class="vp-deal-lines">' +
    `\n                                    <span class="vp-deal-name">${name}</span>` +
    '\n                                    <span class="vp-deal-price">' +
    `<del>${fa(price)}</del><strong>${fa(now)} <span>تومان</span></strong></span>` +
    '\n                                </span>' +
    '\n                            </span>' +
    '\n                        </a>' +
    // Its own control, outside the link, so a basket is never a navigation.
    '\n                        <button type="button" class="vp-deal-cart" aria-label="افزودن به سبد خرید">' +
    '<i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></button>' +
    '\n                    </div>' +
    '\n                </div>';
}).join('');

html = html.replace(
  /<section class="collection-area[^"]*">[\s\S]*?<\/section>/i,
  '<section class="collection-area vp-ladder-area overflow-hidden">\n' +
  // The tiles above are held by .feature-area2 .th-container, which caps at
  // 1620; the trust row is not, and runs to 18 from the page. Those two do
  // not agree with each other — measured at 1920, the tiles end at 173 from
  // the edge and the trust row at 18 — and the client wants the sale to line
  // up with the tiles, so it takes their container and their cap.
  '        <div class="container th-container vp-ladder-wrap">\n' +
  '            <div class="vp-ladder">\n' +
  '                <div class="vp-ladder-head">\n' +
  '                    <div class="vp-ladder-intro">\n' +
  `                        <h2 class="vp-ladder-title">${LADDER_INTRO.title}</h2>\n` +
  `                        <p class="vp-ladder-strap">${LADDER_INTRO.strap}</p>\n` +
  `                        <a href="#" class="vp-ladder-how">${LADDER_INTRO.how}</a>\n` +
  '                    </div>\n' +
  '                    <ol class="vp-ladder-steps">' + LADDER_STEPS_HTML + '\n' +
  '                    </ol>\n' +
  '                </div>\n' +
  '                <div class="vp-ladder-track">' + LADDER_TRACK_HTML + '\n' +
  '                </div>\n' +
  // The way out sits with the two conditions rather than on a line of its own
  // under the tiles. It is the same kind of thing they are — a standing fact
  // about the sale, not a step in it — and putting it here takes a whole row
  // off the section's height.
  //
  // First in the row, not last: the row is RTL, so the first child sits at
  // the right, which is where the client asked for the link to read.
  '                <div class="vp-ladder-notes">\n' +
  '                    <a href="shop.html" class="vp-ladder-all">مشاهده همه محصولات موجود در حراج</a>\n' +
  LADDER_NOTES.map((n) => `                    <span>${n}</span>`).join('\n') + '\n' +
  '                </div>\n' +
  '                <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xl-5 vp-ladder-deals">' + LADDER_DEALS_HTML + '\n' +
  '                </div>\n' +
  '            </div>\n' +
  '        </div>\n' +
  '    </section>'
);

// The "Today's Best Deals" template section — a generic product slider over
// an unstyled countdown scaffold, never touched — becomes a single-product
// daily-deal banner instead, built from the client's own reference photo and
// mirrored for RTL: the photo and the countdown move to the banner's own
// left, the marketing copy to the right, the opposite of the reference's
// left-to-right original. Within the white card the image is the DOM's last
// child rather than its first, so RTL's own right-to-left flow puts it at
// the card's left edge — and the card's left edge is the banner's left edge
// — instead of mirroring the reference's internal image/info order too and
// leaving the photo stranded in the middle.
//
// The featured product is New Balance 530, third in LADDER_DEALS above —
// the same real photo, name and price already used there and in the deal
// ladder, not a new figure. The stock line reuses that entry's own "فقط ۱
// عدد باقی مانده" too, and the bar reads it literally: near empty, not the
// reference's near-full one, since a real "1 left" is not a healthy stock
// level to draw as comfortable.
//
// The countdown itself is the template's own working widget
// (.timer-counter.counter-list, wired to $(".counter-list").countdown() in
// main.js) restyled into four boxes rather than a fourth timer built from
// scratch — data-offer-date is the one input it reads.
const [DAILY_SHOT, DAILY_NAME, DAILY_PRICE, DAILY_STOCK] = LADDER_DEALS[2];

const DAILY_DEAL =
  '<div class="vp-daily-deal">\n' +
  '                <div class="vp-daily-deal-copy">\n' +
  '                    <span class="vp-daily-deal-badge">پیشنهاد امروز</span>\n' +
  // Broken where the client broke it in their own message, not left to
  // wrap on its own — the copy column is 521 wide and the line fits on one
  // at every desktop width, so without the <br> it would never stack.
  '                    <h2 class="vp-daily-deal-title">قبل از<br>تمام شدن بخرش!</h2>\n' +
  '                    <p class="vp-daily-deal-sub">عجله کن؛ موجودی محدوده.</p>\n' +
  '                    <a href="shop-details.html" class="vp-daily-deal-cta">خرید کنید' +
  '<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>\n' +
  '                </div>\n' +
  '                <div class="vp-daily-deal-card">\n' +
  '                    <div class="vp-daily-deal-info">\n' +
  '                        <span class="vp-daily-deal-cat">ونس و کتونی</span>\n' +
  `                        <h3 class="vp-daily-deal-name">${DAILY_NAME}</h3>\n` +
  `                        <strong class="vp-daily-deal-price">${fa(DAILY_PRICE)} <span>تومان</span></strong>\n` +
  '                        <div class="vp-daily-deal-stock">\n' +
  `                            <span>${DAILY_STOCK}</span>\n` +
  '                            <span class="vp-daily-deal-bar"><span class="vp-daily-deal-bar-fill"></span></span>\n' +
  '                        </div>\n' +
  // .counter-list is what $(".counter-list").countdown() in main.js looks
  // for — the only class this markup needs from the template. Neither
  // .timer-counter nor .style7 comes along: both carry their own selectors
  // (.counter-list.style7 li and the giant accent-colour list further up
  // this file) more specific than a single custom class can out-cascade,
  // and .style7's own box is already gold-filled with white type — this
  // component's own boxes were fighting a design that happened to look
  // like a finished one, not building from a blank slate.
  '                        <ul class="counter-list vp-daily-deal-timer" data-offer-date="08/08/2026">\n' +
  '                            <li><div class="day count-number">00</div><span class="count-name">روز</span></li>\n' +
  '                            <li><div class="hour count-number">00</div><span class="count-name">ساعت</span></li>\n' +
  '                            <li><div class="minute count-number">00</div><span class="count-name">دقیقه</span></li>\n' +
  '                            <li><div class="seconds count-number">00</div><span class="count-name">ثانیه</span></li>\n' +
  '                        </ul>\n' +
  '                    </div>\n' +
  `                    <div class="vp-daily-deal-shot"><img src="assets/img/${DAILY_SHOT}" alt="" loading="lazy"></div>\n` +
  '                </div>\n' +
  '            </div>';

// vp-daily-deal-section on the <section> itself: .space's own padding-top
// and padding-bottom (120px each) are what actually set how much of the
// page this section takes, distinct from the banner's own internal
// proportions — see the CSS note before assuming "shorten this section"
// means the photo again.
html = html.replace(
  /<section class="space overflow-hidden overflow-hidden">\s*<div class="product-area3">[\s\S]*?<\/section>/,
  // .th-container, not the .th-container5 this started on, and narrowed to
  // the ladder sale's own 1620 by .vp-daily-deal-wrap — client asked for
  // this band to stand off the desktop's edges by the same amount that
  // section does. The two containers only differ above 1300 (1800 vs 1420),
  // which is why they already agreed at 1440 and only parted at 1920.
  '<section class="space overflow-hidden overflow-hidden vp-daily-deal-section">\n' +
  '        <div class="container th-container vp-daily-deal-wrap">\n' +
  '            ' + DAILY_DEAL + '\n' +
  '        </div>\n' +
  '    </section>'
);

const RING =
  '<svg class="vp-burst" viewBox="0 0 150 150" aria-hidden="true">' +
  '<defs><linearGradient id="vp-burst-gold" x1="0" y1="0" x2="0" y2="1">' +
  '<stop offset="0%" stop-color="#C0972F"></stop><stop offset="100%" stop-color="#E3B54A"></stop>' +
  '</linearGradient></defs>' +
  // The shape and its studs turn together, so the studs stay in the notches.
  '<g class="vp-burst-star">' +
  '<path fill="url(#vp-burst-gold)" d="' + BURST_PATH + '"></path>' +
  BURST_STUDS +
  '</g>' +
  '<text class="vp-burst-num" x="75" y="72">25%</text>' +
  '<text class="vp-burst-off" x="75" y="98">OFF</text>' +
  '</svg>';

html = html.replace(
  /<span class="discount-anime">[^<]*<\/span>/g,
  RING
);

// The template's own number goes: the burst carries its own type.
html = html.replace(/<h4 class="discount">[^<]*<\/h4>\s*/g, '');

// Three pink marks behind the hero, drawn as real elements so both ends can be
// rounded.
//
// They go inside .slider-area, immediately before the deck, for two reasons.
// They have to paint under the card so the glass frosts them, and a preceding
// sibling in the same stacking context does that. And .slider-area's box is the
// card's box vertically — measured 178..664 at 1280, 1440 and 1600 and
// 178..763 at 1920, matching the card's top and foot exactly each time — while
// staying the full width of the page, so a mark can still cross the card's
// edge and be taken apart by it.
//
// That is what lets the marks be placed in the card's coordinates rather than
// the page's. They were in the page's, as fixed pixel offsets from the body,
// which held only at 1440: the disc sat 240px off the card's centre at 1920,
// and the low bar, pinned to y 600 while the card's foot moved with the shoe,
// climbed 137px off the foot and onto the shoe. Anchored here, both hold at
// every width, and neither has to be re-measured when something above the hero
// changes height.
html = html.replace(
  /(<div class="th-hero-wrapper[^>]*>\s*<div class="slider-area">)/,
  '$1\n            <div class="vp-hero-marks" aria-hidden="true"><i class="m-fall"></i><i class="m-near"></i><i class="m-far"></i></div>');

// Swiper reads the container's own dir attribute, not the inherited one.
html = html.replace(/<div class="swiper([^"]*)"/g, '<div dir="rtl" class="swiper$1"');

// The hero deck keeps the template's two slides to a view. See «همسایه» in
// CLAUDE.md — the panes either side of the active card are wanted, and cutting
// them has now been undone twice.

// --- demo copy --------------------------------------------------------------
// Keys must match the markup's own casing, not what the page displays: the nav
// renders uppercase via text-transform while the source says "Contact Us".
// Longest-first so multi-word phrases match before their constituent words.
const DICT = {
  // The hero's own names are not here: its three slides each carry a
  // different product now and the template labels them all the same, so
  // they are written per slot up in HERO_TITLES instead.
  'Shop Grid With Left Sidebar': 'فروشگاه با سایدبار راست',
  'Shop Grid With Right Sidebar': 'فروشگاه با سایدبار چپ',
  'Order Tracking': 'پیگیری سفارش',
  'Shop Details': 'جزئیات محصول',
  'My Account': 'حساب کاربری',
  'Contact Us': 'تماس با ما',
  'Shop Grid': 'فروشگاه',
  'Shop List': 'لیست محصولات',
  'About Us': 'درباره ما',
  'Wishlist': 'علاقه‌مندی‌ها',
  'Checkout': 'تسویه حساب',
  'Home': 'خانه',
  'Shop': 'فروشگاه',
  'Blog': 'وبلاگ',
  'Pages': 'صفحات',
  'Cart': 'سبد خرید',
  'Search for a product or brand...': 'جستجوی محصول یا برند...',
  'The Shipping for orders over $120': 'ارسال رایگان سفارش بالای ۲ میلیون تومان',
  'Support online 24 hours a day': 'پشتیبانی در ساعات کاری',
  'Back guarantee in 7 days': 'ضمانت بازگشت تا ۷ روز',
  'Huge order over $150': 'تخفیف ویژه اعضا',
  'Money Back Guarantee': 'ضمانت بازگشت وجه',
  'Customers Latest Reviews': 'نظرات مشتریان',
  'Latest News & Updates': 'تازه‌ترین مطالب',
  "Women's Collections": 'کالکشن زنانه',
  "Men's Collections": 'کالکشن مردانه',
  'Best Seller Products': 'پرفروش‌ترین‌ها',
  "Today's Best Deals": 'پیشنهاد امروز',
  'Feature Products': 'محصولات منتخب',
  'Membership Offer': 'باشگاه مشتریان',
  'New Trend Edition': 'کالکشن جدید',
  'Login / Register': 'ورود / ثبت‌نام',
  'Instagram Shop': 'اینستاگرام',
  'Online Support': 'پشتیبانی',
  'Free Shipping': 'ارسال رایگان',
  'CONTACT US': 'تماس با ما',
  'Track Order': 'پیگیری سفارش',
  'ABOUT US': 'درباره ما',
  'Explore All': 'مشاهده همه',
  'Read More': 'ادامه مطلب',
  'Shop Now': 'خرید کنید',
  'PAGES': 'صفحات',
  'HOME': 'خانه',
  'SHOP': 'فروشگاه',
  'BLOG': 'وبلاگ',
};

for (const [en, fa] of Object.entries(DICT).sort((a, b) => b[0].length - a[0].length)) {
  html = html.split(en).join(fa);
}


// Currency: the theme's demo prices are USD. Scaled to a plausible Toman
// figure so the grid can be judged at realistic string lengths, which is what
// actually stresses the layout.
html = html.replace(
  /\$(\d+)\.(\d+)\s*USD/g,
  (_, d) => `${(Number(d) * 100000).toLocaleString('fa-IR')} تومان`
);

// Discount chips read "15%-" once the bidi algorithm moves the sign; write
// them as Persian percent instead.
html = html.replace(/-(\d+)%/g, (_, n) => `${Number(n).toLocaleString('fa-IR')}٪ تخفیف`);

/* --------------------------------------------------------------------------
   «نحوه کار» — the how-it-works dialog.

   Opened from the link in the sale's own head. It carries the offer's terms in
   full, and above them a short motion piece that says the same thing in about
   ten seconds for anyone who will not read the rest.

   Everything here is injected at </body> rather than written into the section,
   because the prose passes above run over the whole document — the dictionary,
   the currency scaling and the discount-chip rewrite — and this copy is
   already Persian and already carries percent signs. Injected earlier it would
   be rewritten. The note on the entrance-animation script at the end of this
   file makes the same point about code.
   -------------------------------------------------------------------------- */

/* --------------------------------------------------------------------------
   «نحوه کار» — the how-it-works dialog.

   Opened from the link in the sale's own head, and laid out as the client's
   reference is: one wide landscape board, not a column. The title sits between
   two asides, the five steps run across the middle as podiums, the rules are a
   single strip under them, and the prose that is left goes in columns beside
   each other rather than stacked.

   Everything here is injected at </body> rather than written into the section,
   because the prose passes above run over the whole document — the dictionary,
   the currency scaling and the discount-chip rewrite — and this copy is
   already Persian and already carries percent signs. Injected earlier it would
   be rewritten. The note on the entrance-animation script at the end of this
   file makes the same point about code.

   The reference came in purple on cream. Nothing of that palette is here, for
   the same reason the ladder itself is not maroon: the page is gold and ink,
   and the live step takes the buy button's gold exactly as the ladder's does.
   -------------------------------------------------------------------------- */

// A calendar, drawn rather than set, so it needs no icon font — the same
// reason the ladder's own step marks are drawn.
const HOW_CAL =
  '<svg viewBox="0 0 16 16" aria-hidden="true">' +
  '<rect x="2" y="3.4" width="12" height="10.6" rx="2.4" fill="none" stroke="currentColor" stroke-width="1.4"></rect>' +
  '<path d="M2 6.8h12M5.4 2v2.6M10.6 2v2.6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"></path>' +
  '<circle cx="5.6" cy="9.6" r="0.9" fill="currentColor"></circle>' +
  '<circle cx="8" cy="9.6" r="0.9" fill="currentColor"></circle>' +
  '<circle cx="10.4" cy="9.6" r="0.9" fill="currentColor"></circle>' +
  '</svg>';

// A drawn sneaker rather than a photograph: this is a diagram of how the offer
// works, not a shop window, and a cut-out of a real shoe in a 58px box reads as
// a thumbnail of something for sale. One outline, in currentColor, so it takes
// the step's own state the way the rest of the card does.
// The template's own shoe icon, inlined rather than loaded through <img> so it
// can take the step's colour: the file paints itself the template's red, and
// an <img> cannot inherit currentColor. The original asset is left alone —
// this reads it at build time and swaps the fill, the same bargain
// theme/recolor-svg.js strikes for the icons that do go in as files.
const HOW_SHOE = fs
  .readFileSync(path.join(SITE_IMG, 'icon/shoe.svg'), 'utf8')
  .replace(/<\?xml[^>]*\?>/g, '')
  .replace(/\s(?:width|height)="[^"]*"/g, '')
  .replace(/fill="#E42E3B"/gi, 'fill="currentColor"')
  .replace('<svg', '<svg aria-hidden="true"')
  .replace(/\n\s*/g, '')
  .trim();

// The steps are the page's own, not a second set written out here: the dialog
// explains the ladder that is actually running, so it reads from the same
// array the ladder itself is built from.
//
// The percent sign is written before the digits, not after: the row is forced
// ltr so the scale reads as a number line, and in an ltr box the last thing in
// the string lands on the right — which is the first thing an RTL reader's eye
// meets. The ladder in the section is written the same way, for the same
// reason.
const HOW_STEPS = LADDER_STEPS.map(([name, cut, when]) =>
  // No step starts lit in the markup any more — the modal's own script
  // lights the first step when it opens and carries the light along from
  // there (see the script below), so a static is-lit here would just be
  // the state the script immediately overwrites.
  `\n                        <li class="vp-how-step">` +
  `\n                            <span class="vp-how-step-no">${name}</span>` +
  '\n                            <div class="vp-how-card">' +
  `\n                                <span class="vp-how-shot">${HOW_SHOE}</span>` +
  `\n                                <span class="vp-how-off"><b>\u066a${fa(cut)}</b><small>تخفیف</small></span>` +
  '\n                            </div>' +
  '\n                            <span class="vp-how-podium"></span>' +
  // The week goes under the podium, where it reads as when that step runs
  // rather than as another fact on the card.
  `\n                            <span class="vp-how-when">${HOW_CAL}${when}</span>` +
  '\n                        </li>'
).join('');

// The six standing conditions, as the reference runs them: one strip under the
// steps, an icon and two lines each.
const HOW_RULES = [
  ['tag', 'تخفیف‌ها خودکار و', 'زمان‌بندی‌شده هستند'],
  ['cal', 'انتقال به پله بعد فقط', 'با باقی‌ماندن موجودی'],
  ['box', 'کالاهای حراج پله‌ای', 'موجودی محدود دارند'],
  ['pct', 'امکان اتمام موجودی', 'در هر مرحله هست'],
  ['lock', 'پس از اتمام موجودی', 'رزرو قیمت ممکن نیست'],
  ['user', 'اولویت با کسانی است', 'که زودتر ثبت می‌کنند'],
];

const HOW_RULE_ICONS = {
  tag: '<path d="M3 8.4V3.4a.9.9 0 0 1 .9-.9h5l7.6 7.6a1.2 1.2 0 0 1 0 1.7l-4.3 4.3a1.2 1.2 0 0 1-1.7 0Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path><circle cx="6.4" cy="6" r="1.2" fill="currentColor"></circle>',
  cal: '<rect x="2.6" y="4" width="12.8" height="11.4" rx="2.6" fill="none" stroke="currentColor" stroke-width="1.5"></rect><path d="M2.6 7.6h12.8M6.4 2.4v3M11.6 2.4v3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>',
  box: '<path d="M9 2.4 15.6 6v6L9 15.6 2.4 12V6Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path><path d="M2.4 6 9 9.6 15.6 6M9 9.6v6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path>',
  pct: '<circle cx="9" cy="9" r="6.6" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="M6.6 11.4 11.4 6.6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path><circle cx="6.9" cy="6.9" r="1.1" fill="currentColor"></circle><circle cx="11.1" cy="11.1" r="1.1" fill="currentColor"></circle>',
  lock: '<rect x="3.6" y="7.8" width="10.8" height="7.6" rx="2.4" fill="none" stroke="currentColor" stroke-width="1.5"></rect><path d="M6 7.8V6a3 3 0 0 1 6 0v1.8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>',
  user: '<circle cx="9" cy="6.4" r="2.8" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="M3.6 15.2a5.4 5.4 0 0 1 10.8 0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>',
};

const HOW_RULES_HTML = HOW_RULES.map(([icon, a, b]) =>
  '\n                        <li class="vp-how-rule">' +
  `<span class="vp-how-rule-icon"><svg viewBox="0 0 18 18" aria-hidden="true">${HOW_RULE_ICONS[icon]}</svg></span>` +
  `<span class="vp-how-rule-text">${a}<br>${b}</span></li>`
).join('');


const HOW_HTML =
  '    <div class="vp-how-modal" id="vp-how" hidden>\n' +
  '        <div class="vp-how-veil" data-vp-how-close></div>\n' +
  '        <div class="vp-how-panel" role="dialog" aria-modal="true" aria-labelledby="vp-how-title">\n' +
  '            <button type="button" class="vp-how-close" data-vp-how-close aria-label="بستن">\n' +
  '                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4 12 12 M12 4 4 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>\n' +
  '            </button>\n' +
  '            <div class="vp-how-scroll">\n' +
  // The head: the title in the middle with an aside on each side, as the
  // reference has it. They are one row, not three stacked blocks.
  '                <header class="vp-how-head">\n' +
  '                    <p class="vp-how-aside">اگر در هر مرحله فروش نرود، به پله بعدی منتقل شده و با تخفیف بیشتر عرضه می‌شود.</p>\n' +
  '                    <div class="vp-how-titles">\n' +
  `                        <h2 class="vp-how-title" id="vp-how-title">${LADDER_INTRO.title}</h2>\n` +
  `                        <p class="vp-how-strap"><span>${LADDER_INTRO.strap}</span></p>\n` +
  '                    </div>\n' +
  '                    <p class="vp-how-aside is-warn"><b>فرصت را از دست ندهید!</b> ممکن است قبل از رسیدن به تخفیف بیشتر فروخته شود.</p>\n' +
  '                </header>\n' +
  '                <div class="vp-how-board">\n' +
  '                    <ol class="vp-how-steps">' + HOW_STEPS + '\n' +
  '                    </ol>\n' +
  '                </div>\n' +
  '                <ul class="vp-how-rules">' + HOW_RULES_HTML + '\n' +
  '                </ul>\n' +
  // What is left of the copy, in columns beside each other.
  '                <div class="vp-how-cols">\n' +
  '                    <section>\n' +
  '                        <h3>حراج پله‌ای چیست؟</h3>\n' +
  '                        <p>در ویکی پلاس، برای اولین بار مدل فروش حراج پله‌ای را طراحی کرده‌ایم؛ روشی شفاف، زمان‌بندی‌شده و منصفانه که به شما امکان می‌دهد کالاهای منتخب را با تخفیف‌های مرحله‌ای و واقعی بخرید.</p>\n' +
  '                        <p>این طرح مخصوص محصولاتی است که موجودی محدودی دارند یا پس از فروش اولیه، تنها برخی سایزها یا رنگ‌های آن‌ها باقی مانده است.</p>\n' +
  '                    </section>\n' +
  '                    <section>\n' +
  '                        <h3>چگونه کار می‌کند؟</h3>\n' +
  '                        <ol class="vp-how-list">\n' +
  '                            <li>هر محصول با یک تخفیف اولیه وارد پله اول می‌شود.</li>\n' +
  '                            <li>اگر تا پایان آن مرحله فروش نرود، به پله بعدی منتقل می‌شود.</li>\n' +
  '                            <li>در هر پله، درصد تخفیف بیشتر از مرحله قبل خواهد بود.</li>\n' +
  '                            <li>تنها موجودی باقی‌مانده وارد مرحله بعد می‌شود.</li>\n' +
  '                            <li>این روند تا فروش کامل کالا یا پایان آخرین پله ادامه دارد.</li>\n' +
  '                        </ol>\n' +
  '                    </section>\n' +
  '                    <section>\n' +
  '                        <h3>یک مثال</h3>\n' +
  '                        <p>فرض کنید از یک مدل کفش فقط سایزهای ۳۷ و ۴۰ باقی مانده باشد.</p>\n' +
  '                        <p>اگر این سایزها در پله اول به فروش نرسند، همان موجودی وارد پله دوم می‌شود و با تخفیف بیشتری عرضه خواهد شد. بنابراین ممکن است پیش از رسیدن به تخفیف بالاتر، سایز موردنظر شما را شخص دیگری بخرد.</p>\n' +
  '                    </section>\n' +
  '                    <section>\n' +
  '                        <h3>چرا حراج پله‌ای؟</h3>\n' +
  '                        <ul class="vp-how-list is-ticked">\n' +
  '                            <li>قیمت‌گذاری شفاف و بدون تخفیف ساختگی</li>\n' +
  '                            <li>خرید هوشمندانه متناسب با بودجه شما</li>\n' +
  '                            <li>مدیریت عادلانه کالاهای با موجودی محدود</li>\n' +
  '                            <li>تجربه‌ای نو در خرید آنلاین کفش و کیف</li>\n' +
  '                        </ul>\n' +
  '                    </section>\n' +
  '                </div>\n' +
  '                <p class="vp-how-note">انتخاب با شماست؛ خرید زودتر با اطمینان بیشتر، یا صبر برای تخفیف بالاتر با ریسک از دست رفتن کالا.</p>\n' +
  '            </div>\n' +
  '        </div>\n' +
  '    </div>\n';

html = html.replace('</body>', HOW_HTML + '</body>');


// --- the brand strip, rebuilt as four tiles ----------------------------------
//
// Was the template's: a three-up carousel of «The Official Store of the
// Amazing Brand» with Adidas cards and stock products. The client asked for
// the shape in the reference they sent — a photo mosaic per tile with a glass
// plate floating in the middle of it — four of them, on one white card running
// the width of the page.
//
// Three things in here are placeholders and are marked as such below: the
// logos, the photographs and the stock counts. The client chose each of those
// substitutions rather than wait for the real assets, so the layout can be
// settled now and the content dropped in later.
const BRANDS = [
  // The mosaic photographs are the eight category tiles from the top of the
  // page — the client's own call («از عکس های اون قسمت هشتایی بالای وبسایت
  // استفاده کن») when it turned out we hold one product photograph per brand
  // and this shape wants three. Twelve slots against eight photographs means
  // four repeat; what does not repeat is the lead photograph of each tile, so
  // no two tiles open on the same image.
  //
  // The logos are the template's own. brand_5_2 is genuinely the Nike swoosh
  // and is used where it belongs; the other three are the template's abstract
  // marks from one family, standing in until real ones arrive. Swap the file
  // and nothing else has to change.
  //
  // The counts are invented. There is no inventory behind this page — the
  // Laravel app has the tables, this static page has no data — so they are
  // shaped like real numbers and are not real numbers.
  { name: 'نایک',       logo: 'brand_5_2.png', stock: '۴۲', lead: 'sneaker', a: 'sport-set', b: 'sandal' },
  { name: 'جردن',       logo: 'brand_1_6.svg', stock: '۲۸', lead: 'boot',    a: 'college',   b: 'accessory' },
  { name: 'نیوبالانس',  logo: 'brand_1_5.svg', stock: '۳۵', lead: 'college', a: 'bag-set',   b: 'sneaker' },
  { name: 'گلدن گوس',   logo: 'brand_1_3.svg', stock: '۱۹', lead: 'majlesi', a: 'accessory', b: 'sport-set' },
];

const BRANDS_HTML =
  '    <section class="vp-brands-section space">\n' +
  '        <div class="vp-brands-panel">\n' +
  '            <div class="vp-brands-head">\n' +
  '                <h2 class="vp-brands-title">برندهای موجود</h2>\n' +
  '                <a href="shop.html" class="vp-brands-all">مشاهده همه برندها</a>\n' +
  '            </div>\n' +
  '            <div class="vp-brands-row">\n' +
  BRANDS.map(b =>
    '                <a class="vp-brand" href="shop.html">\n' +
    // The mosaic is decoration: the tile already says the brand's name and
    // what it holds in text, so the photographs carry nothing a reader would
    // otherwise lose and are hidden rather than given invented alt copy.
    '                    <span class="vp-brand-mosaic" aria-hidden="true">\n' +
    `                        <span class="vp-brand-cell is-lead"><img src="assets/img/category/${b.lead}.jpg" alt="" loading="lazy"></span>\n` +
    `                        <span class="vp-brand-cell"><img src="assets/img/category/${b.a}.jpg" alt="" loading="lazy"></span>\n` +
    `                        <span class="vp-brand-cell"><img src="assets/img/category/${b.b}.jpg" alt="" loading="lazy"></span>\n` +
    '                    </span>\n' +
    '                    <span class="vp-brand-plate">\n' +
    `                        <img class="vp-brand-logo" src="assets/img/brand/${b.logo}" alt="" loading="lazy">\n` +
    '                        <span class="vp-brand-lines">\n' +
    `                            <span class="vp-brand-name">${b.name}</span>\n` +
    `                            <span class="vp-brand-stock">${b.stock} کالا موجود</span>\n` +
    '                        </span>\n' +
    '                    </span>\n' +
    '                </a>\n'
  ).join('') +
  '            </div>\n' +
  '        </div>\n' +
  '    </section>';

// Keyed on the class rather than the heading: this block has a real one
// (.brand-area6) and the heading is English the dictionary may yet catch.
{
  const start = html.indexOf('\n    <div class="brand-area6') + 1;
  if (start === 0) throw new Error('brand strip: .brand-area6 is not on the page');
  const tags = /<div\b|<\/div>/g;
  tags.lastIndex = start;
  let depth = 0, end = -1, m;
  while ((m = tags.exec(html))) {
    depth += m[0][1] === '/' ? -1 : 1;
    if (depth === 0) { end = m.index + m[0].length; break; }
  }
  if (end < 0) throw new Error('brand strip: .brand-area6 never closes');
  html = html.slice(0, start) + BRANDS_HTML + html.slice(end);
}


// --- sections the client took off the home page ------------------------------
//
// «نظرات مشتریان», «محصولات منتخب», «تازه‌ترین مطالب» and «اینستاگرام» come off.
// All four were still the template's own — template faces, template products,
// template posts, template photographs — and HANDOFF.md carried them under
// "Not finished" waiting for real content.
//
// Each is cut by its heading rather than by a class, an id or a position.
// Three of the four wrappers carry nothing to aim at — `<section class="">`,
// `<section class="space overflow-hidden overflow-hidden">`, a bare
// `.gallery-area6` — and line numbers move whenever anything above them
// changes. The heading is the only thing about these blocks that is about
// them. From it the cut walks out to the enclosing top-level block, matches
// tags forward to its close, and takes the template's banner comment above it
// too, which names the section and would otherwise point at nothing.
//
// This runs after the dictionary, so the headings are Persian by now. It
// throws rather than quietly doing nothing when one stops matching: a removal
// that no-ops silently puts the section back on the page, and nobody would
// find out before the client did.
function dropSection(html, heading) {
  const at = html.indexOf('>' + heading + '<');
  if (at < 0) throw new Error(`dropSection: no heading «${heading}» on the page`);

  // the nearest top-level block opening above the heading
  const openers = /\n {4}<(section|div)\b/g;
  let start = -1, tag = null, m;
  while ((m = openers.exec(html)) && m.index < at) { start = m.index + 1; tag = m[1]; }
  if (start < 0) throw new Error(`dropSection: «${heading}» sits inside no top-level block`);

  const tags = new RegExp(`<${tag}\\b|</${tag}>`, 'g');
  tags.lastIndex = start;
  let depth = 0, end = -1;
  while ((m = tags.exec(html))) {
    depth += m[0][1] === '/' ? -1 : 1;
    if (depth === 0) { end = m.index + m[0].length; break; }
  }
  if (end < 0) throw new Error(`dropSection: «${heading}»'s <${tag}> never closes`);

  // The template writes a banner comment naming each section immediately
  // above it, tucked onto the end of the previous section's closing line.
  // It belongs to the block being removed, so it goes with it.
  const banner = html.slice(0, start).match(/<!--=+\r?\n[^\n]*\r?\n=+-->\s*$/);
  const from = banner ? start - banner[0].length : start;

  return html.slice(0, from) + html.slice(end);
}

for (const heading of [
  'نظرات مشتریان',
  'محصولات منتخب',
  'تازه‌ترین مطالب',
  'اینستاگرام',
]) {
  html = dropSection(html, heading);
}


// The marks behind the hero take the colour of the shoe on the card.
//
// Each hue is measured from the photograph itself — its opaque, coloured
// pixels, weighted by how much colour they carry, with white, black and grey
// left out of the vote — and then set at the mark's own saturation and
// lightness so the three read as a family. See the note on .vp-hero-marks i.
//
// The active slide is found by the class Swiper puts on it rather than through
// Swiper's own API: the deck is initialised by the template's script, and
// waiting for that to exist is a race this does not need. A MutationObserver
// on the wrapper's classes catches every change, including the ones the deck
// makes on its own.
const HERO_MARKS = {
  'vikyplus-hero-jordan.webp': '#DDC1BB',
  'vikyplus-hero-goldengoose.webp': '#DDCEBB',
  'vikyplus-hero-nb530.webp': '#BBCFDD',
};

html = html.replace('</body>',
  '    <script>\n' +
  '        (function () {\n' +
  '            var deck = document.querySelector("#heroSlide6");\n' +
  '            var marks = document.querySelector(".vp-hero-marks");\n' +
  '            if (!deck || !marks) return;\n' +
  '            var tones = ' + JSON.stringify(HERO_MARKS) + ';\n' +
  '            function paint() {\n' +
  '                var shot = deck.querySelector(".swiper-slide-active .hero-img img");\n' +
  '                if (!shot) return;\n' +
  '                var tone = tones[shot.getAttribute("src").split("/").pop()];\n' +
  '                if (tone) marks.style.setProperty("--vp-mark", tone);\n' +
  '            }\n' +
  '            paint();\n' +
  '            var wrapper = deck.querySelector(".swiper-wrapper");\n' +
  '            if (wrapper && "MutationObserver" in window) {\n' +
  '                new MutationObserver(paint).observe(wrapper, {\n' +
  '                    subtree: true, attributes: true, attributeFilter: ["class"]\n' +
  '                });\n' +
  '            }\n' +
  '        }());\n' +
  '    </script>\n</body>');

// A soft entrance for the page's items as they come into view.
//
// The template has nothing of the kind: its data-ani attributes only fire on
// the active swiper slide, so everything below the hero simply appears. This
// watches the items with an IntersectionObserver — no library, and nothing runs
// for anything already on screen after it has arrived — and hands the movement
// itself to CSS.
//
// Items in the same row are staggered by their position in it, so a row arrives
// as a row rather than all at once. The stagger is capped: a row of eight would
// otherwise still be arriving long after the eye had moved on.
//
// Anyone who has asked their system for less motion gets none — the CSS holds
// that, so it cannot be missed here.
//
// This goes in after the demo copy has been substituted, and has to: those
// substitutions run over the whole document, and one of them rewrites a minus
// followed by a percentage into a Persian discount chip. Injected before them,
// the observer's own rootMargin of -8% was rewritten into '۸٪ تخفیف' and the
// constructor threw. It is written in pixels now as well, but the ordering is
// the actual guard — anything injected as code belongs after the prose pass.
html = html.replace('</body>',
  '    <script>\n' +
  '        (function () {\n' +
  '            var items = document.querySelectorAll(".vp-category, .vp-trust-row .feature-card, .th-product, .vp-deal, .vp-best, .blog-card, .sec-title");\n' +
  '            if (!items.length || !("IntersectionObserver" in window)) return;\n' +
  // «اون ۸ آیتم باید از اول که صفحه هوم لود میشه باشن و منتظر اسکرول نباشن».
  // Below 992 the eight tiles are a horizontal strip, and a strip that is
  // waiting for the page to be scrolled is a strip the reader may never see
  // move: it sits one screen down, and the reveal is `opacity: 0` until then.
  // So on a phone they are simply there, and the observer never touches them.
  //
  // Read once, before the loop, because it decides two things — whether the
  // tiles are in `items` at all, and whether the closing-on-the-middle
  // movement below is set up. A resize past 992 does not re-run this, which is
  // the right trade: nobody resizes a phone across the breakpoint, and the
  // desktop still gets the entrance it was drawn with.
  '            var phone = window.matchMedia("(max-width: 991.98px)").matches;\n' +
  '            items = Array.prototype.filter.call(items, function (el) {\n' +
  '                return !(phone && el.classList.contains("vp-category"));\n' +
  '            });\n' +
  '            if (!items.length) return;\n' +
  '            items.forEach(function (el) {\n' +
  '                el.classList.add("vp-enter");\n' +
  '                var row = el.closest(".row") || el.parentElement;\n' +
  '                var peers = row ? row.children : [];\n' +
  '                var i = 0;\n' +
  '                for (var n = 0; n < peers.length; n++) {\n' +
  '                    if (peers[n] === el || peers[n].contains(el)) { i = n; break; }\n' +
  '                }\n' +
  '                var count = peers.length;\n' +
  '                var delay = Math.min(i, 5) * 60;\n' +
  '                if (el.classList.contains("vp-category")) {\n' +
  // The row is rtl, so the first half of it is the right-hand half. Each tile
  // starts on the side it belongs to and closes on the middle, and the two
  // halves run together: the outermost pair leaves first, the innermost last.
  '                    var half = count / 2;\n' +
  '                    var fromRight = i < half;\n' +
  '                    el.style.setProperty("--enter-x", (fromRight ? 56 : -56) + "px");\n' +
  '                    delay = (fromRight ? i : count - 1 - i) * 70;\n' +
  '                }\n' +
  '                el.style.setProperty("--enter-delay", delay + "ms");\n' +
  '            });\n' +
  '            var seen = new IntersectionObserver(function (entries) {\n' +
  '                entries.forEach(function (entry) {\n' +
  '                    if (!entry.isIntersecting) return;\n' +
  '                    entry.target.classList.add("vp-entered");\n' +
  '                    seen.unobserve(entry.target);\n' +
  '                });\n' +
  '            }, { rootMargin: "0px 0px -80px 0px", threshold: 0.12 });\n' +
  '            items.forEach(function (el) { seen.observe(el); });\n' +
  '        }());\n' +
  '    </script>\n</body>');

// The category strip carries itself along, and stops while it is being used.
//
// «۸ آیتم باید مث هیرو خود اسکرول هم باشن و قابل اسکرول دستی هم باشن» — like
// the hero, which means the hero's own numbers: main.js gives every .th-slider
// `delay: 6000` and `speed: 1000` with `disableOnInteraction: false`, so this
// waits six seconds between moves, takes about a second over each one, and
// comes back after a hand has been on it rather than giving up for good.
//
// It moves by one tile — the tile's width plus the strip's gap, read off the
// first two tiles rather than assumed — so it always lands on a snap point and
// never leaves a tile half in view.
//
// Nothing here decides whether the strip exists. Above 992 the row is an
// ordinary Bootstrap row with no overflow, so `scrollWidth` and `clientWidth`
// agree and every tick returns early; below it they do not. That is one test
// instead of a media query kept in step with the stylesheet, and it follows a
// resize for free.
//
// RTL: `scrollLeft` runs from 0 at the right-hand end down to a negative
// number at the left, so a step forward is a *subtraction*, and the end of the
// strip is the most negative value. Getting that sign wrong does nothing
// visible on the first tick — 0 is already the start — which is exactly the
// kind of bug that reaches a client.
html = html.replace('</body>',
  '    <script>\n' +
  '        (function () {\n' +
  '            var strip = document.querySelector(".vp-category-row");\n' +
  '            if (!strip) return;\n' +
  '            if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;\n' +
  '            var tiles = strip.querySelectorAll(".col");\n' +
  '            if (tiles.length < 2) return;\n' +
  '            var touched = 0;\n' +
  '            ["pointerdown", "touchstart", "wheel", "keydown"].forEach(function (ev) {\n' +
  '                strip.addEventListener(ev, function () { touched = Date.now(); }, { passive: true });\n' +
  '            });\n' +
  '            function step() {\n' +
  '                var room = strip.scrollWidth - strip.clientWidth;\n' +
  '                if (room < 4) return;\n' +
  '                if (Date.now() - touched < 6000) return;\n' +
  '                if (document.hidden) return;\n' +
  '                var a = tiles[0].getBoundingClientRect();\n' +
  '                var b = tiles[1].getBoundingClientRect();\n' +
  '                var pitch = Math.abs(a.left - b.left);\n' +
  '                if (!pitch) return;\n' +
  // One tile of tolerance: the end is -room, and a strip that is within a
  // tile of it has nothing left to show, so it rewinds instead of nudging.
  '                var atEnd = strip.scrollLeft <= -(room - pitch / 2);\n' +
  '                strip.scrollTo({ left: atEnd ? 0 : strip.scrollLeft - pitch, behavior: "smooth" });\n' +
  '            }\n' +
  '            setInterval(step, 6000);\n' +
  '        }());\n' +
  '    </script>\n</body>');

// Opening and closing the how-it-works dialog.
//
// Delegated off the document rather than bound to the link, so it does not
// matter whether this script or the dialog's own markup is written into the
// page first — both are injected at </body> and the order between them is an
// implementation detail of the replaces above, not something to depend on.
//
// The scroll lock is the page's own overflow rather than a fixed body: fixing
// the body loses the scroll position and this dialog is opened from the middle
// of the page. The animation is paused while closed so a hidden dialog is not
// running a ten-second loop for the life of the session.
html = html.replace('</body>',
  '    <script>\n' +
  '        (function () {\n' +
  '            var opener = ".vp-ladder-how";\n' +
  '            function modal() { return document.getElementById("vp-how"); }\n' +
  '            var lastFocus = null;\n' +
  '            var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;\n' +
  '            var lightTimer = null;\n' +
  '            // 2s a step, starting from the first — client asked for the board to\n' +
  '            // walk itself through instead of opening on one fixed step. Runs the\n' +
  '            // full five twice: the first pass ends at the last step and wraps back\n' +
  '            // to the first rather than stopping, the second pass ends at the last\n' +
  '            // step and stays there. Skipped entirely under reduced motion, where\n' +
  '            // the CSS fallback (see tweaks.css) lights the live step as a still\n' +
  '            // instead.\n' +
  '            function stepEls(m) { return m.querySelectorAll(".vp-how-step"); }\n' +
  '            function stopLights() {\n' +
  '                if (lightTimer) { clearInterval(lightTimer); lightTimer = null; }\n' +
  '            }\n' +
  '            function startLights(m) {\n' +
  '                var els = stepEls(m);\n' +
  '                if (!els.length) return;\n' +
  '                var i = 0;\n' +
  '                var pass = 1;\n' +
  '                els[i].classList.add("is-lit");\n' +
  '                lightTimer = setInterval(function () {\n' +
  '                    if (i === els.length - 1) {\n' +
  '                        if (pass >= 2) { stopLights(); return; }\n' +
  '                        pass += 1;\n' +
  '                        for (var j = 0; j < els.length; j++) els[j].classList.remove("is-lit", "is-done");\n' +
  '                        i = 0;\n' +
  '                        els[i].classList.add("is-lit");\n' +
  '                        return;\n' +
  '                    }\n' +
  '                    els[i].classList.remove("is-lit");\n' +
  '                    els[i].classList.add("is-done");\n' +
  '                    i += 1;\n' +
  '                    els[i].classList.add("is-lit");\n' +
  '                }, 2000);\n' +
  '            }\n' +
  '            function open(e) {\n' +
  '                var m = modal();\n' +
  '                if (!m) return;\n' +
  '                if (e) e.preventDefault();\n' +
  '                lastFocus = document.activeElement;\n' +
  '                m.hidden = false;\n' +
  '                document.documentElement.style.overflow = "hidden";\n' +
  '                var close = m.querySelector(".vp-how-close");\n' +
  '                if (close) close.focus();\n' +
  '                if (!reduceMotion) {\n' +
  '                    var els = stepEls(m);\n' +
  '                    for (var j = 0; j < els.length; j++) els[j].classList.remove("is-lit", "is-done");\n' +
  '                    startLights(m);\n' +
  '                }\n' +
  '            }\n' +
  '            function close() {\n' +
  '                var m = modal();\n' +
  '                if (!m || m.hidden) return;\n' +
  '                m.hidden = true;\n' +
  '                document.documentElement.style.overflow = "";\n' +
  '                if (lastFocus && lastFocus.focus) lastFocus.focus();\n' +
  '                stopLights();\n' +
  '            }\n' +
  '            document.addEventListener("click", function (e) {\n' +
  '                if (e.target.closest && e.target.closest(opener)) { open(e); return; }\n' +
  '                if (e.target.closest && e.target.closest("[data-vp-how-close]")) { close(); }\n' +
  '            });\n' +
  '            document.addEventListener("keydown", function (e) {\n' +
  '                if (e.key === "Escape") close();\n' +
  '            });\n' +
  '        }());\n' +
  '    </script>\n</body>');

// The page's scroll work, done once a frame.
//
// The client reported the page catching and stepping as it scrolls. Measured
// at 1440 over a full-page wheel scroll, Chromium spent 1487ms of task time,
// of which 252ms was style recalculation across 281 recalcs and 75ms was
// layout across 213 layouts — for a scroll that should cost almost nothing.
//
// main.js binds four separate jQuery handlers to window.scroll, none passive,
// and each of them reads layout on every event:
//
//   04. Sticky fix      reads scrollTop, toggles .sticky on the wrapper
//   05. Scroll To Top    reads $(document).height() and $(window).height()
//                        every event, then writes strokeDashoffset — through
//                        a `stroke-dashoffset 10ms linear` transition, so a
//                        transition starts and finishes on every frame of
//                        every scroll for a move nobody can see
//   05. (again)          a second handler for the button's .show class
//   footer animation     reads $('.th-screen').offset().top and .height()
//                        every event — and .th-screen is transitioning its
//                        own `width` over 350ms while it does, so layout is
//                        dirty and each of those reads forces it again
//
// Reading layout from a scroll handler is the classic way to lose frames: the
// handler runs before the frame's layout, so a read that finds the tree dirty
// forces the whole document to be laid out synchronously, inside the event.
// A forced layout of this page measures 40.6ms on its own — two and a half
// frames, spent before anything has been drawn.
//
// So: take those four off, and do the same four jobs here, once per frame in
// a requestAnimationFrame, from a passive listener. The scroll path itself
// reads nothing — every measurement it needs is taken in remeasure() and kept
// here, refreshed by a ResizeObserver, whose callback runs after layout has
// already been computed and so pays for nothing. What is left in the frame is
// four writes.
//
// off("scroll") is blunt — it takes every jQuery-bound window scroll handler,
// not just these. That is deliberate and it is checked: the page binds exactly
// these four (counted through jQuery._data(window,'events')), and all four are
// reimplemented below, behaviour for behaviour. main.js is a plain IIFE, not a
// ready callback, so it has already bound by the time this script parses.
//
// The one thing this adds rather than preserves is the reserve: the height the
// island gives up when it leaves the flow, measured and handed to tweaks.css,
// which is what stops the page stepping at the threshold. See «پریدگی» there.
html = html.replace('</body>',
  '    <script>\n' +
  '        (function () {\n' +
  '            var $ = window.jQuery;\n' +
  '            var wrap = document.querySelector(".sticky-wrapper");\n' +
  '            var header = wrap && wrap.closest(".th-header");\n' +
  '            var menu = wrap && wrap.querySelector(".menu-area");\n' +
  '            var catMenu = document.querySelector(".category-menu");\n' +
  '            // The corner button. It was the template\'s scroll-to-top ring\n' +
  '            // and is the WhatsApp link now; the name says which, because a\n' +
  '            // variable called toTop that shows a chat button is a trap.\n' +
  '            var corner = document.querySelector(".vp-whatsapp");\n' +
  '            var ring = null;\n' +
  '            var screenEl = document.querySelector(".th-screen");\n' +
  '            if (!$ || !wrap || !header) return;\n' +
  '\n' +
  '            $(window).off("scroll");\n' +
  '\n' +
  '            var ringLen = 0;\n' +
  '            if (ring) {\n' +
  '                ringLen = ring.getTotalLength();\n' +
  '                ring.style.strokeDasharray = ringLen + " " + ringLen;\n' +
  '                // The ring is written once a frame; a transition on it only\n' +
  '                // means every one of those writes is also a transition.\n' +
  '                ring.style.transition = ring.style.WebkitTransition = "none";\n' +
  '            }\n' +
  '\n' +
  '            // Everything the frame needs to know about the page\'s size.\n' +
  '            // Taken here so the scroll path never reads layout.\n' +
  '            var docH = 0, winH = 0, screenTop = 0, screenH = 0, reserve = 0;\n' +
  '            function remeasure() {\n' +
  '                winH = window.innerHeight;\n' +
  '                docH = document.documentElement.scrollHeight;\n' +
  '                if (screenEl) {\n' +
  '                    var r = screenEl.getBoundingClientRect();\n' +
  '                    screenTop = r.top + window.pageYOffset;\n' +
  '                    screenH = r.height;\n' +
  '                }\n' +
  '                // The island\'s flow height: its own box plus the top margin\n' +
  '                // that collapses through the wrapper. Only meaningful while\n' +
  '                // it is still in the flow.\n' +
  '                if (menu && !stuck) {\n' +
  '                    reserve = menu.offsetHeight +\n' +
  '                        (parseFloat(getComputedStyle(menu).marginTop) || 0);\n' +
  '                }\n' +
  '            }\n' +
  '\n' +
  '            var stuck = false;\n' +
  '            function apply(y) {\n' +
  '                var nowStuck = y > 500;\n' +
  '                if (nowStuck !== stuck) {\n' +
  '                    stuck = nowStuck;\n' +
  '                    wrap.classList.toggle("sticky", stuck);\n' +
  '                    if (catMenu) catMenu.classList.toggle("close-category", stuck);\n' +
  '                    header.style.setProperty("--vp-sticky-reserve",\n' +
  '                        (stuck ? reserve : 0) + "px");\n' +
  '                }\n' +
  '                if (ring) {\n' +
  '                    var run = docH - winH;\n' +
  '                    ring.style.strokeDashoffset =\n' +
  '                        run > 0 ? ringLen - (y * ringLen / run) : ringLen;\n' +
  '                }\n' +
  '                // «آیکون واتسپ وقتی اولین اسکرول شروع میشه باید ظاهر بشه»,\n' +
  '                // so the threshold is the first pixel rather than the\n' +
  '                // ring\'s old 50. The button fades in on its own transition,\n' +
  '                // so "the first scroll" is where the fade starts, not where\n' +
  '                // it finishes.\n' +
  '                if (corner) corner.classList.toggle("show", y > 0);\n' +
  '                if (screenEl) {\n' +
  '                    // The template\'s own test, unchanged: the footer is left\n' +
  '                    // alone while it sits whole in the viewport, allowing 200.\n' +
  '                    var whole = screenTop + screenH - 200 <= y + winH && screenTop >= y;\n' +
  '                    screenEl.classList.toggle("th-visible", !whole);\n' +
  '                }\n' +
  '            }\n' +
  '\n' +
  '            var queued = false;\n' +
  '            window.addEventListener("scroll", function () {\n' +
  '                if (queued) return;\n' +
  '                queued = true;\n' +
  '                requestAnimationFrame(function () {\n' +
  '                    queued = false;\n' +
  '                    apply(window.pageYOffset);\n' +
  '                });\n' +
  '            }, { passive: true });\n' +
  '\n' +
  '            // The page keeps growing after load — images arrive, the footer\n' +
  '            // animates its own width. A ResizeObserver is told about that\n' +
  '            // after layout has already run, so keeping the cache fresh this\n' +
  '            // way costs nothing; polling it from the scroll path would not.\n' +
  '            window.addEventListener("resize", remeasure);\n' +
  '            if ("ResizeObserver" in window) {\n' +
  '                var ro = new ResizeObserver(remeasure);\n' +
  '                ro.observe(document.body);\n' +
  '                if (screenEl) ro.observe(screenEl);\n' +
  '            }\n' +
  '            window.addEventListener("load", remeasure);\n' +
  '\n' +
  '            remeasure();\n' +
  '            apply(window.pageYOffset);\n' +
  '\n' +
  '            // The shop\'s price slider writes into the «تا» box as it moves,\n' +
  '            // so a drag and a typed number are the same filter arriving by\n' +
  '            // different hands. It sits here, after the scroll handler and\n' +
  '            // inside the same guard-free tail, rather than in the middle of\n' +
  '            // that handler — the first attempt spliced it into the frame\n' +
  '            // function and left the footer\'s own block inside an\n' +
  '            // `if (false)`, which nothing would have reported.\n' +
  '            //\n' +
  '            // The range carries no name and submits nothing on its own — a\n' +
  '            // named one would post a maximum on every apply, including one\n' +
  '            // nobody dragged. So this handler is not decoration: without it\n' +
  '            // the slider does nothing at all and the two boxes are the whole\n' +
  '            // filter. It also repaints the track, which is gold to the left\n' +
  '            // of the thumb and white to its right.\n' +
  '            var priceRange = document.querySelector("[data-vp-price-range]");\n' +
  '            var priceMax = document.querySelector("[data-vp-price-max]");\n' +
  '            // The shop\'s filter sheets close on their scrim and on their\n' +
  '            // own X. The tab that opened one is behind the scrim, so without\n' +
  '            // this there is no way back out except the browser\'s.\n' +
  '            document.addEventListener("click", function (e) {\n' +
  '                var hit = e.target.closest && e.target.closest("[data-vp-sheet-close]");\n' +
  '                if (!hit) return;\n' +
  '                var sheet = hit.closest("details");\n' +
  '                if (sheet) { e.preventDefault(); sheet.open = false; }\n' +
  '            });\n' +
  '\n' +
  '            if (priceRange && priceMax) {\n' +
  '                priceRange.addEventListener("input", function () {\n' +
  '                    priceMax.value = Number(priceRange.value).toLocaleString("fa-IR");\n' +
  '                    var lo = Number(priceRange.min), hi = Number(priceRange.max);\n' +
  '                    var pct = hi > lo ? (Number(priceRange.value) - lo) / (hi - lo) * 100 : 100;\n' +
  '                    priceRange.style.setProperty("--vp-fill", pct + "%");\n' +
  '                });\n' +
  '            }\n' +
  '        }());\n' +
  '    </script>\n</body>');

// --- the desktop menu, rebuilt from the pages this shop has -------------------
//
// «لطفا یک جستجوی کامل در کل صفحات بکن که هیچ نشونه ای از قالب آماده یا اون
// قالب ERNA وجود نداشته باشه».
//
// The band above the page was still the template's demo menu, in full: a
// mega-menu of screenshots of the template's own demo sites with «View Demo»
// buttons on them, «About Style 1/2/3», «Contact Style 1/2/3», eleven blog
// layouts, «Search Result for Product», «فروشگاه Full Width». It is the
// loudest trace of the template anywhere on this site, it is the first thing
// on every desktop page, and half of it links to pages this shop does not
// have — `page_url()` sends every unmapped filename to '#', so those were dead
// links as well as somebody else's product's furniture.
//
// The phone's drawer was rebuilt for exactly this reason once already; this is
// the same round for the desktop, and CLAUDE.md's note about that drawer is
// what said to look here.
//
// **Only pages that exist.** Every item below is a filename in
// `config/storefront.php`'s `pages` map, which is what turns it into a real
// route in Blade. Nothing here can go to '#'. The categories are not in it —
// they are not filenames, and the drawer, the listing's own strip and its
// sidebar all offer them; a menu item that cannot be written without a route
// helper does not belong in a file that also has to render as a static
// preview.
//
// **Four, not six.** The basket and the account are already buttons in this
// same band, so listing them again is the same door twice — and measured, six
// items of Persian ran the header row 22px past the page at 1200, which
// `check-overflow.js` failed on. The template's six were shorter words with
// dropdown arrows; ours are «پیگیری سفارش» and «فروشنده شوید».
html = html.replace(
  /<nav class="main-menu d-none d-lg-inline-block">[\s\S]*?<\/nav>/,
  '<nav class="main-menu d-none d-lg-inline-block">\n' +
  '                                <ul>\n' +
  '                                    <li><a href="index.html">خانه</a></li>\n' +
  '                                    <li><a href="shop.html">فروشگاه</a></li>\n' +
  '                                    <li><a href="order-tracking.html">پیگیری سفارش</a></li>\n' +
  '                                    <li><a href="vendor-register.html">فروشنده شوید</a></li>\n' +
  '                                </ul>\n' +
  '                            </nav>'
);

// --- two blocks of the template's demo goods, out ----------------------------
//
// Same round, same instruction: «هیچ نشونه ای از قالب آماده ... وجود نداشته
// باشه».
//
// **The desktop search's suggestion panel.** `main.js` opens it the moment the
// header's field is focused, and what it showed was five of the template's own
// products — «Nike Renew», «Adidas Plastic», «Nike Flex Run», «Nike Air Max» —
// with the template's photographs and prices, each linking to a product page
// this shop does not have. A shop that suggests four shoes it does not sell,
// on every desktop page, the first time anybody clicks search. The panel goes;
// the field stays and still submits.
//
// jQuery makes that safe: the handler does `$(this).children('.search-
// suggestions')` and then `.css()` on it, and both are no-ops on an empty set.
//
// **The QuickView modal.** A hidden dialog carrying «Women's fashion Bag» at
// $120.85, «Rated 5.00 out of 5 ... 4 customer reviews», «SKU: Fashion-1254»,
// «Category: Bag, Fashion Hand Bag, Uncategorized» and a paragraph about 1960s
// hippie fashion. Nothing on this site opens it — no `href="#QuickView"`
// anywhere — so it is dead markup that ships in the source of every page,
// with an invented rating in it. Out.
html = html.replace(/<div class="search-suggestions">[\s\S]*?<!-- \/\.box-suggestions -->\s*<\/div>/, '');
// Anchored on the sidemenu comment that follows it rather than on a run of
// closing divs — the same trap the footer replacement above documents.
html = html.replace(
  /<div id="QuickView"[\s\S]*?(?=<!--==============================\s*\n?\s*Sidemenu)/,
  ''
);

// --- the offer banners, in the shop's own words -------------------------------
//
// Still «BLACK / FRIDAY / SPECIAL OFFER» and «Adidas Shoes — The Summer Sale
// Up to 50% Off», in English, on the home page of a Persian shop that sells
// neither Adidas nor a Black Friday. The last of the template's copy anybody
// could read.
//
// What replaces it is the shop's own sale, which is the one promotion this
// site actually runs — the stepped sale the board further up the page
// explains. No number in it: the live step is `config('storefront.ladder')`
// and moves, and a banner with a percentage baked into the markup goes stale
// the week it moves without anybody noticing.
html = html.replace(
  /<span class="box-title">BLACK<\/span>\s*<h4 class="box-title style1">FRIDAY<\/h4>\s*<h3 class="sec-title style1">SPECIAL OFFER<\/h3>/,
  '<span class="box-title">فروش ویژه</span>\n' +
  '                                <h4 class="box-title style1">حراج پله‌ای</h4>\n' +
  '                                <h3 class="sec-title style1">هر هفته یک پله ارزان‌تر</h3>'
);

html = html.replace(
  /<span class="sub-title2">Adidas Shoes<\/span>\s*<h3 class="sec-title style1">The Summer Sale Up\s*to <span class="text-theme">50%<\/span>Off<\/h3>/,
  '<span class="sub-title2">کیف و کفش زنانه</span>\n' +
  '                                <h3 class="sec-title style1">تازه‌های این هفته را\n' +
  '                                    <span class="text-theme">ببینید</span></h3>'
);

// --- the footer, in Persian -------------------------------------------------
//
// The template's footer arrived in English and was left that way through every
// round of work on the page above it, so the shop ends in another language and
// somebody else's company. The words are generic shop labels, so translating
// them invents nothing.
//
// The contact block is *removed* rather than translated. It carried a German
// company, a Californian street and a +00 telephone number — the template's
// own fiction. A footer with no address is ordinary; a footer with a false one
// is a lie the shop tells on every page. It comes back when the real details
// arrive; see HANDOFF.md.
// Anchored on the *next* column rather than on a run of closing divs: the
// info-boxes inside this one end in three of them too, so a lazy match stops
// in the middle and leaves the row one </div> heavy — which is exactly what
// happened, and the page grew 434px at 992 before the parity check caught it.
html = html.replace(
  /<div class="col-md-6 col-xl-3">\s*<div class="widget footer-widget">\s*<div class="th-widget-about">[\s\S]*?(?=<div class="col-md-6 col-xl-auto">)/,
  '<div class="col-md-6 col-xl-3">\n' +
  '                            <div class="widget footer-widget">\n' +
  '                                <div class="th-widget-about">\n' +
  // The header's own mark and name, verbatim — the same object at the foot of
  // the page as at the head of it. The template's ERNA wordmark is gone with
  // the rest of its company. `.vp-logo-text` already carries the strapline, so
  // the paragraph that used to repeat it is gone too.
  '                                    <a href="index.html" class="vp-logo vp-logo-foot">\n' +
  '                                        <img src="assets/img/vikyplus-appicon.png" alt="ویکی پلاس">\n' +
  '                                        <span class="vp-logo-text">\n' +
  '                                            <b>ویکی پلاس</b>\n' +
  '                                            <small>فروشگاه کیف و کفش زنانه</small>\n' +
  '                                        </span>\n' +
  '                                    </a>\n' +
  '                                </div>\n' +
  '                            </div>\n' +
  '                        </div>\n' +
  '                        '
);

// --- the strip under the footer ----------------------------------------------
//
// «پایین فوتر اون کارتها باید کامل حذف بشن و کپی رایت هم به فارسی نوشته بشه
// متعلق به ویکی پلاس است».
//
// Two things went out of that strip. The card row — «We Are Acepting» over a
// picture of Apple Pay, Visa, Discover, Mastercard and a «Secure Payment»
// badge — is the template's, and every one of those marks is a claim this shop
// has not made: an Iranian storefront settles through a shaparak gateway and
// takes none of them. A row of card logos that cannot be paid with is worse
// than no row.
//
// And the notice itself was «Copyright © 2025 Erna. All Rights Reserved», with
// the word Erna linked to the template's own demo page — the shop's own footer
// crediting somebody else's product, in English, on a Persian site.
//
// What is left is one line in the shop's own language, centred because it is
// now the only thing on the strip.
html = html.replace(
  /<div class="copyright-wrap">[\s\S]*?<\/footer>/,
  '<div class="copyright-wrap">\n' +
  '            <div class="container th-container5">\n' +
  '                <p class="copyright-text">تمامی حقوق این وب‌سایت متعلق به ویکی پلاس است.</p>\n' +
  '            </div>\n' +
  '        </div>\n' +
  '    </footer>'
);

// --- the footer on a phone --------------------------------------------------
//
// «فرم چیدمان پایین وبسایت باید این شکلی باشه با همین مشخصات» — the client sent
// a screenshot of the arrangement they want: the mark centred with a rule
// either side, a sentence about the shop, the address and the telephone each
// on a line with its own icon, four social marks, and three columns of links
// side by side. «سفید باشه» — light, not the dark ground the screenshot used.
//
// **It is a second footer, not a rearrangement of the one below it.** The
// standing instruction is that this whole run is the phone and nothing is to
// reach the desktop, and the columns in the screenshot are not the columns the
// desktop footer carries — different headings, different items, three instead
// of four. Rewriting the shared markup would have changed both. So this block
// is `d-lg-none`, the existing widget area is hidden below 992, and the
// desktop footer is untouched — measured, 0 pixels differ at 992, 1200, 1440
// and 1920.
//
// **The address and the telephone are the client's own**, off that screenshot.
// They matter because of what the comment above says: the template's contact
// block was deleted rather than translated, on the grounds that a footer with
// a false address is a lie the shop tells on every page, and that it comes
// back when the real details arrive. These are those details arriving. If they
// were ever a placeholder, this is the block to correct.
//
// The four social marks are the four in the screenshot, in its order and its
// colours. Three are certain — WhatsApp, Telegram, Instagram. The second is a
// multi-coloured mark this cannot identify with confidence; it is drawn as a
// neutral one and its link is `#` until the client says which service it is.
//
// Four of the twelve links below named `course.html` or `contact.html` — the
// template's filenames for pages this shop did not have — and so resolved to
// '#' through page_url(). The content pages exist now, and these name them.
const FOOT_COLS = [
  ['لینک\u200cها', [
    ['shop.html', 'فروشگاه'],
    ['about.html', 'درباره ما'],
    ['contact.html', 'ارتباط با ما'],
    ['size-guide.html', 'راهنمای سایز'],
  ]],
  ['خدمات', [
    ['privacy.html', 'حریم خصوصی'],
    ['terms.html', 'قوانین و مقررات'],
    ['faq.html', 'سوالات متداول'],
    ['shop.html', 'حراج پله\u200cای'],
  ]],
  ['دسته\u200cها', [
    ['shop.html', 'کفش زنانه'],
    ['shop.html', 'کیف زنانه'],
    ['shop.html', 'پرفروش\u200cترین\u200cها'],
    // «جدیدترین‌ها» came off so this column is four items like the two beside
    // it — «تعداد با بغلی ها برابر بشه».
    ['shop.html', 'تخفیف\u200cدارها'],
  ]],
];

// Listed in the order they are *read* in RTL, so the row comes out as the
// screenshot has it: WhatsApp at the left of the row, Instagram at the right.
// Written the other way round first and the row came out mirrored — the page
// is RTL, so the first child sits at the right.
const FOOT_SOCIAL = [
  // Named in Latin, and deliberately: `HomePageTest` guards the four sections
  // taken off the page by their headings, and one of those headings is the
  // word «اینستاگرام». A Persian label here puts that word back on the page and
  // fails that test — which is the guard doing its job, not a false alarm, so
  // the label moves rather than the test. «Instagram» is how the service is
  // said in Persian anyway.
  ['instagram', 'Instagram', '#', '<i class="fa-brands fa-instagram" aria-hidden="true"></i>'],
  ['telegram', 'تلگرام', '#', '<i class="fa-brands fa-telegram" aria-hidden="true"></i>'],
  ['bale', 'پیام\u200cرسان', '#', '<i class="fa-solid fa-comment-dots" aria-hidden="true"></i>'],
  ['whatsapp', 'واتساپ', '#', '<i class="fa-brands fa-whatsapp" aria-hidden="true"></i>'],
];

const FOOT_PHONE_HTML =
  '<div class="vp-foot-m">\n' +
  '                <div class="vp-foot-m-head">\n' +
  '                    <span class="vp-foot-m-rule" aria-hidden="true"></span>\n' +
  '                    <b class="vp-foot-m-name">ویکی پلاس</b>\n' +
  '                    <span class="vp-foot-m-rule" aria-hidden="true"></span>\n' +
  '                </div>\n' +
  '                <p class="vp-foot-m-strap">ارائه\u200cدهنده انواع کیف و کفش زنانه با تضمین کیفیت، ارسال سریع و امکان خرید تکی و عمده.</p>\n' +
  '                <p class="vp-foot-m-line"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>تهران، سعدی شمالی، روبه\u200cروی بانک ملی، پلاک ۵۶۵</span></p>\n' +
  '                <p class="vp-foot-m-line"><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:02133983125">021-3398-3125</a></p>\n' +
  '                <div class="vp-foot-m-social">' +
  FOOT_SOCIAL.map(([key, name, href, icon]) =>
    `\n                    <a class="vp-foot-m-soc is-${key}" href="${href}" aria-label="${name}">${icon}</a>`).join('') +
  '\n                </div>\n' +
  '                <div class="vp-foot-m-cols">' +
  FOOT_COLS.map(([title, items]) =>
    '\n                    <div class="vp-foot-m-col">' +
    '\n                        <h3 class="vp-foot-m-col-title">' +
    '<span class="vp-foot-m-rule" aria-hidden="true"></span>' + title +
    '<span class="vp-foot-m-rule" aria-hidden="true"></span></h3>' +
    '\n                        <ul>' +
    items.map(([href, label]) => `\n                            <li><a href="${href}">${label}</a></li>`).join('') +
    '\n                        </ul>' +
    '\n                    </div>').join('') +
  '\n                </div>\n' +
  '            </div>\n            ';

html = html.replace(
  '<div class="widget-area">\n                <div class="container th-container5">',
  '<div class="widget-area">\n                ' + FOOT_PHONE_HTML + '<div class="container th-container5">'
);
if (!html.includes('vp-foot-m-head')) {
  throw new Error('the phone footer did not land — the widget-area markup has moved');
}

// The four menu columns, replaced with their whole tag rather than by word:
// a bare "Menu" also appears inside `sideMenuToggler`, and a loose
// find-and-replace across a page is how a class name quietly becomes Persian.
//
// «فروشنده شوید» gets a filename of its own so that config/storefront.php can
// point it at the application form — every other item shares contact.html and
// still resolves to '#'.
[
  ['<h3 class="widget_title">Menu</h3>', '<h3 class="widget_title">ویکی پلاس</h3>'],
  ['<h3 class="widget_title">Customer Support</h3>', '<h3 class="widget_title">پشتیبانی</h3>'],
  ['<h3 class="widget_title">فروشگاه on The Go</h3>', '<h3 class="widget_title">ویکی پلاس روی موبایل</h3>'],
  ['<a href="contact.html">Become a Vendor</a>', '<a href="vendor-register.html">فروشنده شوید</a>'],
  /*
   * «همکاری در فروش» named an affiliate programme this shop does not have and
   * pointed at contact.html. The slot goes to «فروش عمده», which the shop
   * *does* offer — the front page's trust row has advertised «خرید تکی و
   * عمده» since the template was dressed, and until now there was no page
   * behind it anywhere on the site.
   */
  ['<a href="contact.html">Affiliate Program</a>', '<a href="wholesale.html">فروش عمده</a>'],
  ['<a href="course.html">Privacy Policy</a>', '<a href="privacy.html">حریم خصوصی</a>'],
  /*
   * «تأمین‌کنندگان» was pointed at «فروشنده شوید» — near enough to look right
   * and not the page anybody was after. The slot goes to «اخذ نمایندگی»,
   * which had no link anywhere on the site: the branch network is the largest
   * thing in this application and a prospective franchisee had the telephone
   * number and nothing else.
   */
  ['<a href="course.html">Our Suppliers</a>', '<a href="franchise.html">اخذ نمایندگی</a>'],
  // After-sales, help and buying online are all questions the FAQ answers —
  // the exchange window, the payment method, the delivery charge. They shared
  // contact.html because until now there was nowhere else for them to go.
  ['<a href="contact.html">Extended Plan</a>', '<a href="faq.html">خدمات پس از فروش</a>'],
  ['<a href="contact.html">Community</a>', '<a href="about.html">درباره ما</a>'],
  ['<a href="contact.html">Help Center</a>', '<a href="faq.html">راهنما</a>'],
  ['<a href="contact.html">Report Abuse</a>', '<a href="contact.html">گزارش تخلف</a>'],
  ['<a href="contact.html">Submit and Dispute</a>', '<a href="contact.html">ثبت شکایت</a>'],
  ['<a href="contact.html">Policies & Rules</a>', '<a href="terms.html">قوانین</a>'],
  ['<a href="contact.html">Online فروشگاهping</a>', '<a href="faq.html">خرید اینترنتی</a>'],
  // The real tracking page, not contact.html. The top bar carried the only
  // other link to it and the top bar is gone.
  ['<a href="contact.html">Order History</a>', '<a href="order-tracking.html">سفارش‌های من</a>'],
  ['<a href="course.html">فروشگاهing سبد خرید</a>', '<a href="cart.html">سبد خرید</a>'],
  /*
   * «مقایسه» and «علاقه‌مندی‌ها» named two features this shop does not have —
   * no compare, no wishlist, no table behind either — and both went to '#'.
   * Pointing them at a page that exists would be worse than the dead link:
   * a footer item called «مقایسه» that lands on the size guide is a wrong
   * answer rather than no answer.
   *
   * So the two slots keep their place in the column and say something the
   * shop can actually do. Put the old labels back the day the features are
   * built — the slots are here, and `wishlist.html` is a filename
   * config/storefront.php can be given in one line.
   */
  ['<a href="course.html">Compare</a>', '<a href="faq.html">تعویض و مرجوعی</a>'],
  ['<a href="contact.html">Help Ticket</a>', '<a href="contact.html">پشتیبانی</a>'],
  /*
   * These two are matched in Persian, not English: DICT (line ~1362) has
   * already translated «My Account» and «Wishlist» by the time this list runs,
   * which is also why «Online Shopping» reads «Online فروشگاهping» above.
   *
   * The account link is the plainest of the twenty-one that went nowhere: the
   * shop has had customer accounts since `AccountController`, and the footer
   * item named after them pointed at contact.html.
   *
   * The wishlist slot goes the way «مقایسه» did — a real destination under a
   * label the shop can honour, until there is a wishlist to point it at.
   */
  ['<a href="contact.html">حساب کاربری</a>', '<a href="my-account.html">حساب کاربری</a>'],
  ['<a href="contact.html">علاقه‌مندی‌ها</a>', '<a href="size-guide.html">راهنمای سایز</a>'],
  /*
   * «ویکی پلاس روی موبایل», rewritten to describe something that exists.
   *
   * There is no application. The line promised one on Cafe Bazaar and the App
   * Store, under two store badges linking to apple.com and play.google.com —
   * the template's own, pointing at the shops' front doors rather than at
   * anything of ours. «بجای کافه بازار روش اد هوم اسکرینو بزن»: the answer is
   * Add to Home Screen, which this site can actually do today. `head.blade.php`
   * links a `manifest.json` (theme/make-favicons.js writes it) with the shop's
   * mark and name in it, so a phone that adds this page gets the icon and the
   * title rather than a screenshot of a browser tab.
   *
   * **The two store badges go with the sentence.** They are one message, and
   * leaving them under a line that says "add it from your browser" would say
   * both things at once — which is the state the footer was just taken out of.
   * They are one entry to put back if the shop ever ships an application.
   */
  ['From App Store or Google Play App is available. Get it now', 'برای دسترسی سریع‌تر، ویکی پلاس را از منوی مرورگرتان به صفحه اصلی گوشی اضافه کنید.'],
  [
    '<div class="download-btn-wrap style2 d-flex">\n' +
    '                                        <div class="">\n' +
    '                                            <a target="_blank" href="https://www.apple.com/store" class="download-btn">\n' +
    '                                                <img src="assets/img/normal/app.png" alt="">\n' +
    '                                            </a>\n' +
    '                                        </div>\n' +
    '                                        <div>\n' +
    '                                            <a target="_blank" href="https://play.google.com/" class="download-btn">\n' +
    '                                                <img src="assets/img/normal/play.png" alt="">\n' +
    '                                            </a>\n' +
    '                                        </div>\n' +
    '                                    </div>\n' +
    '                                    ',
    '',
  ],
  /*
   * The social row, down to the two the shop is on.
   *
   * «این موارد اضافی شبکه های اجتماعی حذف بشه» — Facebook, Twitter and
   * LinkedIn come off. All five were the template's, and all five pointed at
   * the *service's* home page rather than at an account of ours, which is why
   * three of them could sit there for the whole port without anybody noticing
   * they were not links to anything.
   *
   * WhatsApp is repointed while it is being kept: the shop's own number, the
   * one behind the floating button on every page and on the contact page.
   * Instagram is left where the template put it — we still have no handle for
   * it, and that is the one thing here still waiting on the client.
   */
  ['<a href="https://www.facebook.com/"><i class="fab fa-facebook-f"></i></a>\n                                        ', ''],
  ['<a href="https://www.twitter.com/"><i class="fab fa-twitter"></i></a>\n                                        ', ''],
  ['<a href="https://www.linkedin.com/"><i class="fab fa-linkedin-in"></i></a>\n                                        ', ''],
  ['<a href="https://www.whatsapp.com/">', '<a href="https://wa.me/989918905993" target="_blank" rel="noopener" aria-label="واتساپ">'],
].forEach(([from, to]) => {
  if (!html.includes(from)) {
    throw new Error(`the footer no longer contains ${from.slice(0, 40)} — check before assuming it is gone`);
  }
  html = html.split(from).join(to);
});

// The basket's badge starts at nothing. It was the template's «5» — a number
// that never moved however full the basket was, which is worse than no number
// at all. The Laravel page renders the real count in its place; both read ۰
// with an empty basket, which is what the parity check compares.
html = html.replace(
  /(<button type="button" class="icon-btn sideMenuToggler"[\s\S]*?)<span class="badge">5<\/span>/,
  '$1<span class="badge">۰</span>'
);

fs.writeFileSync(out, html);
console.log(`wrote ${path.relative(ROOT, out)} (theme: ${theme || 'none — template colours'})`);

// The storefront's home page is this page, so write it to index.html as well:
// any static host serves index.html at the root, and the template's own «خانه»
// links point at index.html too, so both the address and the menu land here
// without a redirect rule to carry them. The template's index.html was a copy
// of electronics-shop.html, which is still there and still reachable.
//
// Only the default build claims index.html — a themed variant is a study and
// must not take over the home page.
if (!theme) {
  const home = path.join(ROOT, 'download-version/index.html');
  fs.writeFileSync(home, html);
  console.log(`wrote ${path.relative(ROOT, home)} (same page, as the site's home)`);
}
