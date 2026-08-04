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

// The middle hero slide carries a real product shot instead of the template's
// grey placeholder. Two slides share this source, both the same product.
html = html.split('assets/img/hero/hero_6_2.png').join('assets/img/hero/vikyplus-hero-1.png');

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

// The row under the hero carries the six shoe categories instead of the
// template's four service boxes: a photograph filling each square, with the
// name on a strip of glass laid over it.
// Right to left, the order the row reads in.
const CATEGORIES = [
  ['majlesi', 'مجلسی'],
  ['sneaker', 'ونس و کتونی'],
  ['college', 'کالج'],
  ['sandal', 'صندل'],
  ['boot', 'بوت و نیم‌بوت'],
  ['bag-set', 'ست کیف و کفش'],
];

// The name is real text on the tile, so it is also the link's own name and
// needs no aria-label.
const CATEGORY_ROW =
  '<div class="row vp-category-row">' +
  CATEGORIES.map(([file, name]) =>
    '\n                <div class="col-2">' +
    '\n                    <a class="vp-category" href="shop.html">' +
    `\n                        <img src="assets/img/category/${file}.jpg" alt="" loading="lazy">` +
    `\n                        <span class="vp-category-label">${name}</span>` +
    '\n                    </a>' +
    '\n                </div>'
  ).join('') +
  '\n            </div>';

html = html.replace(
  /<section class="feature-area2[^"]*">\s*<div class="container th-container">\s*<div class="row gy-4 gx-50">[\s\S]*?<\/div>\s*<\/div>\s*<\/section>/i,
  '<section class="feature-area2 positive-relative overflow-hidden">\n' +
  '        <div class="container th-container">\n' +
  '            ' + CATEGORY_ROW + '\n' +
  '        </div>\n' +
  '    </section>'
);

// The offer lockup on the hero shot, in place of the template's spinning
// discount disc: a countdown on the right, the discount in a disc in the
// middle, and the offer's name on the left. Right to left, the order it reads
// in — so that is the order it is written in, and the flow does the rest.
//
// The two wings carry the buy button's shape and fill; the disc is half the
// diameter the template's was. The countdown is filled in by the script below,
// so the markup ships a placeholder rather than a time that would be wrong the
// moment the page is served.
const DEAL =
  '<div class="vp-deal">' +
  '\n                                                <span class="vp-deal-timer" data-deal-countdown>--:--:--</span>' +
  '\n                                                <span class="vp-deal-disc">۲۶٪</span>' +
  '\n                                                <span class="vp-deal-label">تخفیف ویژه</span>' +
  '\n                                            </div>';

html = html.replace(
  /<div class="discount-wrapp style2">[\s\S]*?<\/div>\s*<\/div>/g,
  DEAL
);

// Counts down to the end of the day, so the preview always shows a live time
// rather than a build-time one that would run out. The real end date belongs to
// the campaign and comes from the store, not from here.
html = html.replace('</body>',
  '    <script>\n' +
  '        (function () {\n' +
  '            var fields = document.querySelectorAll("[data-deal-countdown]");\n' +
  '            if (!fields.length) return;\n' +
  '            var pad = function (n) {\n' +
  '                return n.toLocaleString("fa-IR", { minimumIntegerDigits: 2, useGrouping: false });\n' +
  '            };\n' +
  '            var tick = function () {\n' +
  '                var now = new Date();\n' +
  '                var end = new Date(now);\n' +
  '                end.setHours(24, 0, 0, 0);\n' +
  '                var left = Math.max(0, Math.floor((end - now) / 1000));\n' +
  '                var text = pad(Math.floor(left / 3600)) + ":" + pad(Math.floor(left / 60) % 60) + ":" + pad(left % 60);\n' +
  '                for (var i = 0; i < fields.length; i++) fields[i].textContent = text;\n' +
  '            };\n' +
  '            tick();\n' +
  '            setInterval(tick, 1000);\n' +
  '        }());\n' +
  '    </script>\n</body>');

// Three gold bars behind the hero, drawn as real elements so both ends can be
// rounded. First thing in the body, so they paint behind the header and the
// card and get frosted where they pass under either.
html = html.replace(/(<body[^>]*>)/i,
  '$1\n    <div class="vp-hero-marks" aria-hidden="true"><i class="m-fall"></i><i class="m-near"></i><i class="m-far"></i></div>');

// Swiper reads the container's own dir attribute, not the inherited one.
html = html.replace(/<div class="swiper([^"]*)"/g, '<div dir="rtl" class="swiper$1"');

// --- demo copy --------------------------------------------------------------
// Keys must match the markup's own casing, not what the page displays: the nav
// renders uppercase via text-transform while the source says "Contact Us".
// Longest-first so multi-word phrases match before their constituent words.
const DICT = {
  // Hero slide titles. The template repeats the product name as the small
  // label above it, so one mapping covers both.
  'Adidas Stan Running Spikes': 'کتونی آدیداس استن اسمیت',
  // Non-breaking spaces bind each half, so the title breaks in one place
  // only: 'کتونی جردن' over 'وان ایر'.
  'Nike Air Running Spikes': 'کتونی\u00A0جردن وان\u00A0ایر',
  'Nike Mag Sneakers Shoe': 'کفش اسنیکر نایک مگ',
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
  h1.replace(DICT['Nike Air Running Spikes'], DICT['Nike Air Running Spikes'].replace(' ', ' <br>'))
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

fs.writeFileSync(out, html);
console.log(`wrote ${path.relative(ROOT, out)} (theme: ${theme || 'none — template colours'})`);
