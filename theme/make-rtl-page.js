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
const src = process.argv[2] || path.join(ROOT, 'download-version/shoe-shop.html');
const out = process.argv[3] || path.join(ROOT, 'download-version/shoe-shop-rtl.html');

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

// Theme layer loads last so its palette wins.
html = html.replace(
  /(<link[^>]+href="assets\/css\/style\.rtl\.css"[^>]*>)/i,
  '$1\n    <link rel="stylesheet" href="assets/css/theme-gold.css">'
);

// Swiper reads the container's own dir attribute, not the inherited one.
html = html.replace(/<div class="swiper([^"]*)"/g, '<div dir="rtl" class="swiper$1"');

// --- demo copy --------------------------------------------------------------
// Keys must match the markup's own casing, not what the page displays: the nav
// renders uppercase via text-transform while the source says "Contact Us".
// Longest-first so multi-word phrases match before their constituent words.
const DICT = {
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

// Currency: the demo prices are USD strings from the theme.
html = html.replace(/\$(\d+)\.(\d+)\s*USD/g, (_, d) => `${(Number(d) * 100).toLocaleString('fa-IR')} تومان`);

fs.writeFileSync(out, html);
console.log(`wrote ${path.relative(ROOT, out)}`);
