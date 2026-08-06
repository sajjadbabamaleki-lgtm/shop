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
const LADDER_DEALS = [
  ['hero/vikyplus-hero-goldengoose.webp', 'کتونی گلدن گوس', 6480000, 'فقط سایزهای ۳۷ و ۳۹'],
  ['hero/vikyplus-deal-cloudtilt.webp', 'کتونی اون کلادتیلت', 4880000, 'فقط سایزهای ۳۸ و ۴۰'],
  ['hero/vikyplus-hero-nb530.webp', 'کتونی نیوبالانس ۵۳۰', 7980000, 'فقط ۱ عدد باقی مانده'],
  ['hero/vikyplus-deal-v2k.webp', 'کتونی نایک وی۲کی ران', 6980000, 'فقط سایزهای ۳۷ و ۳۹'],
  ['hero/vikyplus-hero-jordan.webp', 'کتونی جردن وان ایر', 8480000, 'فقط سایز ۳۸'],
];

// fa-IR gives Persian digits and the Arabic thousands mark, which is what a
// price should read as on this page.
const fa = (n) => n.toLocaleString('fa-IR');

const LADDER_STEPS_HTML = LADDER_STEPS.map(([name, cut, when, state]) =>
  `\n                    <li class="vp-step${state ? ' is-' + state : ''}">` +
  `\n                        <span class="vp-step-name">${name}</span>` +
  // Each digit on its own tile, split across the middle, so the row reads as a
  // board that flips rather than as type in a box.
  '\n                        <span class="vp-step-rate">' +
  [...fa(cut), '٪'].map((ch) => `<b>${ch}</b>`).join('') +
  '</span>' +
  `\n                        <span class="vp-step-when">${when}</span>` +
  (state === 'done' ? '\n                        <span class="vp-step-flag is-done" aria-label="گذشته"></span>' : '') +
  (state === 'current' ? '\n                        <span class="vp-step-flag">مرحله فعلی</span>' : '') +
  '\n                    </li>'
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
// What is kept on the tile is what the sale is: the cut, the name, the two
// prices, and how much is left. The step's name and the countdown come off the
// card, because the ladder above already says both once for all four.
const LADDER_DEALS_HTML = LADDER_DEALS.map(([file, name, price, stock]) => {
  const now = Math.round(price * (100 - LADDER_CUT) / 100);
  return '\n                <div class="col">' +
    '\n                    <div class="vp-deal">' +
    `\n                        <a class="vp-deal-shot" href="shop.html">` +
    `\n                            <img src="assets/img/${file}" alt="" loading="lazy">` +
    `\n                            <span class="vp-deal-cut">${fa(LADDER_CUT)}٪</span>` +
    `\n                            <span class="vp-deal-stock">${stock}</span>` +
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
    '<i class="fa-light fa-bag-shopping" aria-hidden="true"></i></button>' +
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
  '                <div class="vp-ladder-notes">\n' +
  LADDER_NOTES.map((n) => `                    <span>${n}</span>`).join('\n') + '\n' +
  '                    <a href="shop.html" class="vp-ladder-all">مشاهده همه محصولات</a>\n' +
  '                </div>\n' +
  '                <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xl-5 vp-ladder-deals">' + LADDER_DEALS_HTML + '\n' +
  '                </div>\n' +
  '            </div>\n' +
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
  '            var items = document.querySelectorAll(".vp-category, .vp-trust-row .feature-card, .th-product, .vp-deal, .blog-card, .sec-title");\n' +
  '            if (!items.length || !("IntersectionObserver" in window)) return;\n' +
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
