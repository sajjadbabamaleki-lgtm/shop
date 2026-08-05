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
// Non-breaking spaces bind each half of the Jordan's name, so it breaks in one
// place only: 'کتونی جردن' over 'وان ایر'. See the <br> pass further down.
const HERO_TITLES = {
  hero_6_1: 'کتونی نایک وی۲کی ران',
  hero_6_2: 'کتونی\u00A0جردن وان\u00A0ایر',
  hero_6_3: 'کتونی اون کلادتیلت',
};

const HERO_PHOTOS = {
  hero_6_1: 'vikyplus-hero-v2k.webp',
  hero_6_2: 'vikyplus-hero-jordan.webp',
  hero_6_3: 'vikyplus-hero-cloudtilt.webp',
};

// One match per slide: the label, the heading and the photograph are rewritten
// together, keyed on which of the three placeholders the slide carries. There
// is no other <img> between the heading and the shot, so the lazy span between
// them cannot run past the slide it started in.
html = html.replace(
  /(<span class="sub-title"[^>]*>)[^<]*(<\/span>\s*<h1 class="hero-title"[^>]*>)[\s\S]*?(<\/h1>[\s\S]*?<img src=")assets\/img\/hero\/(hero_6_[123])\.png(")/g,
  (_, openLabel, openTitle, betweenTitleAndImg, slot, closeSrc) => {
    const title = HERO_TITLES[slot];
    // The label is the same name without the binding spaces: it is one line by
    // design and has no break to protect.
    const label = title.replace(/\u00A0/g, ' ');
    return openLabel + label + openTitle + '\n                                                ' +
      title + ' ' + betweenTitleAndImg + `assets/img/hero/${HERO_PHOTOS[slot]}` + closeSrc;
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

// Five trust badges under the category row: the template's own feature-card
// markup and CSS (feature-card.style2), just with gold icons in place of its
// red ones and Persian copy. row-cols-* rather than col-N, same reason as the
// category row: five is not a clean fraction of Bootstrap's 12 columns.
// Solid-fill icons (feature_card_* and check2), not the outlined feature_2_*
// set — the client wants every icon in the same filled style as the payment
// shield.
const TRUST_ITEMS = [
  ['feature_card_1-gold.svg', 'ارسال سریع', 'ارسال به سراسر کشور'],
  ['feature_card_2-gold.svg', 'ضمانت بازگشت کالا', 'بازگشت و تعویض آسان'],
  ['secure-gold.svg', 'پرداخت امن', 'پرداخت آنلاین مطمئن'],
  ['check2-gold.svg', 'تضمین اصالت', 'گارانتی اصل بودن کالا'],
  ['feature_card_4-gold.svg', 'پشتیبانی آنلاین', 'پاسخگویی ۲۴ ساعته'],
];

const TRUST_ROW =
  '<div class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-5 vp-trust-row">' +
  TRUST_ITEMS.map(([icon, title, text]) =>
    '\n                <div class="col">' +
    '\n                    <div class="feature-card style2">' +
    '\n                        <div class="box-icon">' +
    `\n                            <img src="assets/img/icon/${icon}" alt="">` +
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

// The discount mark on the shot: a lobed burst in the buy button's gold, with
// the offer on it.
//
// The outline is eleven lobes — outer and inner points alternating round a
// circle at radii 72 and 61, with a Catmull-Rom spline through them turned into
// cubic segments, which is what gives the soft scalloped edge rather than a
// spiked star. Generated once and written in, since it never changes.
//
// The type lives in the same SVG as the shape, so both scale together and the
// lines stay centred on the burst whatever size it is drawn at. The two
// baselines are 26 apart and sit where they do because the block is centred on
// its own ink, not on its line boxes: rendered and measured, the ink's centre
// and the burst's are the same point to within a fifth of a unit.
const BURST_PATH =
  'M 75,3 C 80.73,3 85.7,14.57 92.19,16.47 C 98.67,18.38 109.11,11.33 113.93,14.43 C 118.75,17.53 116.67,29.94 121.1,35.05 C 125.53,40.16 138.11,39.88 140.49,45.09 C 142.87,50.3 134.42,59.63 135.38,66.32 C 136.34,73.01 147.08,79.58 146.27,85.25 C 145.45,90.92 133.3,94.19 130.49,100.34 C 127.68,106.49 133.17,117.82 129.41,122.15 C 125.66,126.48 113.67,122.66 107.98,126.32 C 102.29,129.97 100.78,142.47 95.28,144.08 C 89.79,145.7 81.76,136 75,136 C 68.24,136 60.21,145.7 54.72,144.08 C 49.22,142.47 47.71,129.97 42.02,126.32 C 36.33,122.66 24.34,126.48 20.59,122.15 C 16.83,117.82 22.32,106.49 19.51,100.34 C 16.7,94.19 4.55,90.92 3.73,85.25 C 2.92,79.58 13.66,73.01 14.62,66.32 C 15.58,59.63 7.13,50.3 9.51,45.09 C 11.89,39.88 24.47,40.16 28.9,35.05 C 33.33,29.94 31.25,17.53 36.07,14.43 C 40.89,11.33 51.33,18.38 57.81,16.47 C 64.3,14.57 69.27,3 75,3 Z';

const RING =
  '<svg class="vp-burst" viewBox="0 0 150 150" aria-hidden="true">' +
  '<defs><linearGradient id="vp-burst-gold" x1="0" y1="0" x2="0" y2="1">' +
  '<stop offset="0%" stop-color="#C0972F"></stop><stop offset="100%" stop-color="#E3B54A"></stop>' +
  '</linearGradient></defs>' +
  '<path fill="url(#vp-burst-gold)" d="' + BURST_PATH + '"></path>' +
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

// The hero headline reads on two lines, 'کتونی جردن' over 'وان ایر'. The
// non-breaking spaces above leave that as the only place it can break, but
// whether it does depends on the type size — at a smaller size the whole name
// fits on one line and the break is lost. The break is where the name divides,
// not a consequence of the measure, so it is written in. Only in the heading:
// the template repeats the same name as the small label above it, which is one
// line by design.
html = html.replace(/<h1 class="hero-title"[^>]*>[\s\S]*?<\/h1>/g, (h1) =>
  h1.replace(HERO_TITLES.hero_6_2, HERO_TITLES.hero_6_2.replace(' ', ' <br>'))
);

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
  '            var items = document.querySelectorAll(".vp-category, .th-product, .collection-category, .blog-card, .sec-title");\n' +
  '            if (!items.length || !("IntersectionObserver" in window)) return;\n' +
  '            items.forEach(function (el) {\n' +
  '                el.classList.add("vp-enter");\n' +
  '                var row = el.closest(".row") || el.parentElement;\n' +
  '                var peers = row ? row.children : [];\n' +
  '                var i = 0;\n' +
  '                for (var n = 0; n < peers.length; n++) {\n' +
  '                    if (peers[n] === el || peers[n].contains(el)) { i = n; break; }\n' +
  '                }\n' +
  '                el.style.setProperty("--enter-delay", Math.min(i, 5) * 60 + "ms");\n' +
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
