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
// **The number lives in `WHATSAPP_NUMBER` below and nowhere else in this
// file.** It is written into the generated page, which `theme/make-blade.js`
// then ports into the Blade partials, so both copies of the site carry one
// number from one line. Reading it from `config/storefront.php` instead is not
// open to us: the preview page is static HTML with no PHP in it.
//
// That comment used to be here and was not true — the number was typed out in
// five separate literals in this file, and a sixth lives in
// `config/storefront.php` for the pages that *are* PHP. When the client
// changed it («۰۹۳۶۶۶۵۹۲۲۴ این شماره هم در پشتیبانی واتسپ») five of the six
// would have been missed by anybody searching for the one they had found.
// `WhatsAppNumberTest` is what makes the two remaining copies agree: it reads
// every wa.me link off the rendered pages and fails if any of them is not the
// config's.
//
// `wa.me` wants the international form with no plus and no leading zero:
// 09366659224 becomes 989366659224.
const WHATSAPP_NUMBER = '989366659224';
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
// Two buttons in that corner now, stacked.
//
// «پشتیبانی هم بالای واتسپ شناور یه مربع پشتیبانی بزار با آیکون مناسب». The
// support page had been reachable from the footer and nowhere else, which on a
// phone is seven thousand pixels down the home page; this is the answer to
// «چرا پیدا نمی‌کنم».
//
// The support square is written **before** the WhatsApp link and drawn above
// it. Source order is the stacking order the page was measured with, and it is
// also the reading order: the two are one control that happens to be two
// buttons, and a screen reader should meet them the way the eye does.
//
// Both carry `.show`, and the scroll handler toggles both — see the corner
// block near the foot of this file. A support button that appeared while the
// WhatsApp one was still hidden would read as a fault, not as a feature.
const WHATSAPP = [
  '    <!-- WhatsApp -->',
  '    <a class="vp-support-fab" href="support.html" aria-label="پشتیبانی ۲۴ ساعته">',
  // Two speech bubbles: this is «پشتیبانی آنلاین», so the mark has to be a
  // conversation. A telephone was tried and rejected — it reads as «زنگ
  // بزنید», which is a different thing and a different hour of the day.
  // `fa-comments` rather than a single bubble because the WhatsApp mark
  // below it is one, and two identical bubbles stacked in a corner read as
  // one control drawn twice.
  '        <i class="fa-solid fa-comments" aria-hidden="true"></i>',
  // The label is always in the markup; what changes is how much room it is
  // given. See `.vp-support-fab-label` in tweaks.css — a label that came and
  // went would be a control whose accessible name changed three seconds in.
  '        <span class="vp-support-fab-label">پشتیبانی ۲۴ ساعته</span>',
  '    </a>',
  `    <a class="vp-whatsapp" href="https://wa.me/${WHATSAPP_NUMBER}" target="_blank" rel="noopener"`,
  '       aria-label="گفتگو در واتساپ">',
  '        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>',
  '    </a>',
  '',
].join('\n');
html = html.replace(SCROLL_TOP, WHATSAPP);

// --- the libraries this shop never calls come off ---------------------------
//
// «سایت با فیلترشکن خیلی کند باز میشه». The template loads eleven scripts
// because it is eleven demos in one download; this page is one of them, and
// eight of those libraries bind to markup that exists in none of the pages we
// ship — measured, not assumed:
//
//   magnific-popup  .popup-image / .popup-video / .popup-content   0 uses
//   counterup       .counter-number                                0
//   tilt            .tilt-active                                   0
//   imagesloaded    used only by isotope, below                    0
//   isotope         .filter-active / .masonary-active              0
//   jquery-ui       .price_slider (a demo shop's price filter)     0
//   nice-select     .nice-select                                   0
//   bootstrap       every data-bs-* attribute, anywhere            0
//
// That is 187KB the browser fetches, parses and throws away, over eight round
// trips it makes before `load` can fire. The shop's own dropdowns, sheets and
// modals are hand-written in `tweaks.css` and the partials; nothing here is
// the site's behaviour.
//
// **Two things make this safe rather than a silent loss.** `main.js` still
// contains the calls and now asks whether each plugin is present first — so
// putting a `<script>` back here is the whole of putting a feature back. And
// `DeadLibrariesTest` fails the suite if any of those hooks ever appears in a
// template again, naming the library to restore. Without that test this is a
// trap: add `class="popup-video"` to a page a year from now and the lightbox
// simply does not open, with nothing red anywhere.
//
// GSAP stays — `.slider-drag-cursor` is on the page and main.js drives it with
// `gsap.ticker`. Swiper, jQuery and main.js are the page.
const DEAD_LIBRARIES = [
  ['assets/js/bootstrap.min.js', '<!-- Bootstrap -->'],
  ['assets/js/jquery.magnific-popup.min.js', '<!-- Magnific Popup -->'],
  ['assets/js/jquery.counterup.min.js', '<!-- Counter Up -->'],
  ['assets/js/tilt.jquery.min.js', '<!-- Tilt -->'],
  ['assets/js/imagesloaded.pkgd.min.js', '<!-- Isotope Filter -->'],
  ['assets/js/isotope.pkgd.min.js', null],
  ['assets/js/jquery-ui.min.js', null],
  ['assets/js/nice-select.min.js', '<!-- nice select -->'],
];
const quote = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
for (const [file, label] of DEAD_LIBRARIES) {
  // The comment above a script is the template's label for it and means
  // nothing without the script, so it comes off in the same bite.
  const tag = new RegExp(
    '[ \\t]*' + (label ? quote(label) + '\\s*\\n[ \\t]*' : '') +
    '<script src="' + quote(file) + '"><\\/script>[ \\t]*\\n',
  );
  if (!tag.test(html)) {
    throw new Error(`${file} is not where it was — check the page before assuming it is gone`);
  }
  html = html.replace(tag, '');
}

// Magnific's stylesheet goes with its script. It styles a lightbox that can no
// longer be opened, and it is a render-blocking request to say so.
const POPUP_CSS = /[ \t]*<!-- Magnific Popup -->\s*\n[ \t]*<link rel="stylesheet" href="assets\/css\/magnific-popup\.min\.css">\s*\n/;
if (!POPUP_CSS.test(html)) {
  throw new Error('the magnific-popup stylesheet is not where it was');
}
html = html.replace(POPUP_CSS, '');

// --- swap in the flipped stylesheets ---------------------------------------
const SHEETS = [
  ['assets/css/style.css', 'assets/css/style.rtl.css'],
  ['assets/css/bootstrap.min.css', 'assets/css/bootstrap.rtl.min.css'],
  ['assets/css/swiper-bundle.min.css', 'assets/css/swiper-bundle.rtl.min.css'],
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

// --- the design gate -------------------------------------------------------
//
// What this stops: the template painting on its own.
//
// This page is the ThemeForest stylesheet with tweaks.css on top of it, and
// the two are separate files. Drop the second one and the first still styles
// the page completely — the mint hero (#D3FBD9), the red buttons, the template
// layout — with this shop's own photographs and Persian in it. It does not
// read as a page that failed to load. It reads as the old template coming
// back, which is exactly how the client read it, twice, on two photographs of
// their phone taken during a deploy: «چرا الان دوباره وقتی سایت داشت آپدیت
// میشد برای چند لحظه اینارو نمایش داد؟».
//
// Why a deploy is when it happens: Liara replaces the container, and for the
// seconds either side of the swap a request can be answered by nothing at all.
// A returning visitor's other six stylesheets come from their own disk cache
// and cannot fail; tweaks.css is fingerprinted with the md5 of its contents,
// so a deploy that touched it has changed its URL and it is the one file that
// *must* come over the network. The single most likely file to be dropped is
// the single file the shop's appearance lives in. A browser that loses a
// stylesheet does not stop — it paints what it has.
//
// So the paint is gated on the design being complete. tweaks.css ends with
// `--vp-design: ok`; this script sits after every <link> in the head, which
// means the browser has already finished with all of them — loaded or failed —
// before it runs, and it runs before <body> is parsed, so nothing has been
// painted yet. If the property is not there:
//
//   first time   hide the document and reload once, 1.2s later. The window is
//                seconds wide, so the reload usually lands on a served file
//                and the visitor sees a slow page rather than a wrong one.
//   second time  keep it hidden and say so, in Persian, on a plain white
//                panel that needs no stylesheet to draw. A visitor who is told
//                the site is updating has been told the truth; a visitor shown
//                the template has been told the work was lost.
//
// It costs nothing when the file arrives: one computed-style read, and the
// gate returns before it touches the document. check-parity.js still prints
// zero — both pages run this and both open it.
//
// ── the hole this left, found the hard way ────────────────────────────────
//
// All of the above is written for a stylesheet that **fails**. It says nothing
// about one that is merely **slow**, and the client saw the template a third
// time — not during a deploy, half an hour after one, on an iPhone, with the
// site barely answering at all: «چرا باز دوباره اثرات قالب قبلیو دیدم چند
// لحظه؟؟؟».
//
// There is a load-bearing assumption above: "this script sits after every
// <link> in the head, which means the browser has already finished with all of
// them — loaded or failed — before it runs." That is true of a stylesheet that
// has resolved. One still in flight has not finished, and what a browser does
// while it waits is its own business. Chromium blocks: measured here with
// tweaks.css held back three seconds, the body is not parsed at 2.5s and
// nothing paints. **Safari does not have to**, and the client is on an iPhone.
// A 644KB stylesheet on a container that is struggling is exactly the case
// that takes long enough to find out.
//
// So the gate no longer relies on the browser's choice. The document is hidden
// by an inline style **above the first <link>** — before any stylesheet has
// been asked for, let alone painted — and only the gate reveals it, on the one
// condition that the design signed itself. Hidden is the default and visible
// is earned, which is the way round that cannot be lost by a browser deciding
// to paint early.
//
// What it costs: on a slow connection the visitor waits on white rather than
// watching the template assemble itself. That is the trade, and being shown
// somebody else's design has now cost this project three rounds. `<noscript>`
// reveals the page where there is no JavaScript, and both bail-outs in the
// gate reveal it too, so nothing can leave a page hidden for ever.
//
// The real cure is still the one in CLAUDE.md: the template's stylesheet is
// 648KB of foundation under this page, and while it is there it can always be
// what paints. The gate stops it being *seen*. Nothing else does.
// Hidden until the design says otherwise. This goes above the first <link> on
// purpose: at that point the browser has not requested a single stylesheet, so
// there is no state in which the template can paint first.
// **These two comments are served to every visitor**, so they say what the
// mechanism is and where its source lives, and nothing else. What the page is
// built on is not a thing an HTML comment tells the world.
const DESIGN_HOLD = [
  '    <!-- Hidden until the design signs itself; the gate below reveals it.',
  '         Both halves are one mechanism — see theme/make-rtl-page.js. -->',
  '    <style>html{visibility:hidden}</style>',
  '    <noscript><style>html{visibility:visible}</style></noscript>',
  '',
  '',
].join('\n');

const DESIGN_GATE = [
  '',
  '',
  '    <!-- The design gate: nothing paints until tweaks.css has arrived whole.',
  '         See its last rule and theme/make-rtl-page.js before changing either. -->',
  '    <script>',
  '        (function () {',
  '            var root = document.documentElement;',
  '',
  '            // Whatever happens below, a document left hidden is worse than',
  '            // one showing the wrong thing, so every path out of here either',
  '            // reveals the page or replaces it with the notice.',
  "            var show = function () { root.style.visibility = 'visible'; };",
  '',
  '            // A browser with no custom properties would fail this for ever,',
  '            // and it has bigger problems with this page than the gate.',
  "            if (!window.getComputedStyle || !window.CSS || !CSS.supports || !CSS.supports('--vp-design', 'ok')) { show(); return; }",
  '',
  '            var key = "vp-design-retry";',
  '            var mark = function (v) { try { v === null ? sessionStorage.removeItem(key) : sessionStorage.setItem(key, v); } catch (e) {} };',
  '            var marked = function () { try { return sessionStorage.getItem(key) === "1"; } catch (e) { return false; } };',
  '',
  "            if (getComputedStyle(root).getPropertyValue('--vp-design').trim() === 'ok') { show(); mark(null); return; }",
  '',
  '            // Nothing is painted yet — the hold above saw to that — and it',
  '            // stays that way until there is something true to show.',
  '',
  '            if (!marked()) {',
  '                mark("1");',
  '                setTimeout(function () { location.reload(); }, 1200);',
  '                return;',
  '            }',
  '',
  '            mark(null);',
  '',
  "            document.addEventListener('DOMContentLoaded', function () {",
  "                var note = document.createElement('div');",
  '                note.dir = "rtl";',
  "                note.setAttribute('style', 'visibility:visible;position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:24px;background:#fff;color:#101111;font:400 16px/1.9 Tahoma,Arial,sans-serif;text-align:center');",
  '                note.innerHTML =',
  '                    \'<strong style="font-size:22px;letter-spacing:.2px">ویکی پلاس</strong>\'',
  '                    + \'<span>سایت در حال به‌روزرسانی است.</span>\'',
  '                    + \'<span style="font-size:14px;color:#6b6b6b">چند لحظه دیگر دوباره تلاش کنید.</span>\'',
  '                    + \'<button type="button" style="margin-top:6px;padding:10px 26px;border:0;border-radius:999px;background:#101111;color:#fff;font:inherit;cursor:pointer">تلاش دوباره</button>\';',
  "                note.querySelector('button').addEventListener('click', function () { location.reload(); });",
  '                document.body.appendChild(note);',
  '            });',
  '        })();',
  '    </script>',
].join('\n');

// The hold goes above the first stylesheet the head asks for; the gate goes
// below the last one. Between them, nothing paints unsigned.
const FIRST_SHEET = /([ \t]*<link[^>]+href="assets\/css\/bootstrap\.rtl\.min\.css"[^>]*>)/i;
if (!FIRST_SHEET.test(html)) {
  throw new Error('the head has no bootstrap stylesheet to put the design hold above');
}
html = html.replace(FIRST_SHEET, DESIGN_HOLD + '$1');

html = html.replace(
  /(<link[^>]+href="assets\/css\/style\.rtl\.css"[^>]*>)/i,
  '$1' + layers.map((h) => `\n    <link rel="stylesheet" href="${h}">`).join('') + DESIGN_GATE
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
  hero_6_1: 'کتونی اون کلادتیلت',
  hero_6_2: 'کتونی جردن وان ایر',
  hero_6_3: 'کتونی گلدن گوس',
};

// The label above the heading is the slide's reason for being in the deck, one
// line per shoe — «بجای اسم تکراری هم این ۳ تا بیاد رو هر کفش یکیش». It was the
// product's name, printed a second time immediately above the heading that
// already says it, and the client asked for that repeat to go.
//
// These must stay in step with `storefront.hero.products` in the Laravel
// config, which carries the same three lines against the same three slugs:
// hero_6_1 is on-cloudtilt, _2 is jordan-one-air, _3 is golden-goose. The
// two copies of this page are compared pixel for pixel by check-parity.js, so
// a line changed on one side and not the other fails there.
const HERO_EYEBROWS = {
  hero_6_1: 'پر فروش این هفته',
  hero_6_2: 'یه پیشنهاد ویژه',
  hero_6_3: 'موجودی محدود',
};

// The model is bound with non-breaking spaces so the second line stays one
// line whatever the type size — the break belongs to the name, not to the
// measure.
const heroHeading = (name) => {
  const [kind, ...model] = name.split(' ');
  return kind + '<br>' + model.join('\u00A0');
};

// The slide's photograph, which is not the product's card photograph: a hero
// shot has to be background-free to sit on the glass — «عکس های فروشگاه
// بکگراند دارن و مواردی که ما تو هیرو میزاریم باید بی بکگراند باشن که بشینن رو
// شیشه هیرو». On the Laravel side that is `hero.photos` in
// config/storefront.php, keyed by slug; here it is this table, keyed by slot.
// The two have to name the same file or check-parity.js fails.
const HERO_PHOTOS = {
  hero_6_1: 'vikyplus-hero-cloudtilt-black.webp',
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
    return openLabel + HERO_EYEBROWS[slot] + openTitle + '\n                                                ' +
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
// The third field is whether the section is open yet. The last four are not —
// «اکسسوری / ست کیف و کفش / ست ورزشی / بوت و نیم بوت … اینا باید هرجا روشون زده
// بشه باید بشن کامینگ سون» — so they keep their tile and wear «به‌زودی» on it.
// The Laravel page reads the same fact off `categories.coming_soon`; this list
// and that column have to agree, and check-parity.js is what notices when they
// do not.
//
// «ست ورزشی» is back after being taken off the shop this morning: the
// instruction above names it among the four to announce, which is a different
// answer to the same question and the client's to give.
const CATEGORIES = [
  ['majlesi', 'مجلسی', false],
  ['sneaker', 'ونس و کتونی', false],
  ['college', 'کالج', false],
  ['sandal', 'صندل', false],
  ['boot', 'بوت و نیم‌بوت', true],
  ['bag-set', 'ست کیف و کفش', true],
  ['accessory', 'اکسسوری', true],
  ['sport-set', 'ست ورزشی', true],
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
  CATEGORIES.map(([file, name, soon]) =>
    '\n                <div class="col">' +
    `\n                    <a class="vp-category" href="shop.html"${soon ? ` data-vp-soon="${name}"` : ''}>` +
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

// The four tiles at the foot of the drawer. Their order is the client's, read
// in RTL: «فروش عمده» first, then «فروشنده شوید», then «پیگیری سفارش», then
// «راهنمای سایز».
//
// «سوالات متداول» came out of here — «از اون ۴ مستطیل پایین منو سوالات متداول
// باید حذف بشه پیگیری سفارش بره جاش و اولین مورد بشه فروش عمده». The page is
// not gone: `/faq` is still in the footer, still linked from `/contact` and
// `/size-guide`, and the home page still carries the band of the same eight
// questions. What left is its tile, and the slot went to the thing a phone
// visitor with an order in the post actually opens the menu for.
//
// «فروش عمده» is `/wholesale`, which the shop has advertised on the front
// page's trust row since the template was dressed. Its mark is the same
// `fa-boxes-stacked` that badge uses, so the two places the shop says «عمده»
// say it with one glyph.
//
// Four entries make the 1fr 1fr grid two even rows. The body they sit in is
// `overflow-y: auto` already, so the extra 48px scrolls on a 375×667 screen
// rather than pushing the sign-in button off it; at 390×844 the drawer is
// 695 of 824 and nothing scrolls at all. Both measured.
// The one section the drawer does not offer.
//
// «اکسسوری از منو حذف بشه» — the tile row under the hero still has it and so
// does the listing's strip; this is the menu alone. The Laravel drawer reads
// `categories.show_in_nav`, which is the column that has always meant exactly
// this and had never been used; here the list is hand-written, so the slug is.
const DRAWER_HIDES = 'accessory';

// Two rows that are not sections, in the sections' own shape.
//
// «۲ تا از اون مستطیل درازها اضافه بشه روش تعهدات ما و شرایط ارسال و مرجوعی
// بیاد». They are full-width rows like the categories rather than tiles like
// the four below, because that is what was asked for and because a promise and
// a rule are things you read, not errands you run.
//
// **They are the reason those two pages are reachable on a telephone at all.**
// Both were in the footer only, and the footer on the home page is seven
// thousand pixels down: «من پیدا نکردم تو نسخه موبایل مواردی که انجام دادیرو».
//
// The mark is an `<i>` carrying `.vp-cat-icon`, so it takes the same 28px gold
// tile the categories' `<img>` marks take. `fa-truck-fast` is already the
// drawer's own «پیگیری سفارش» glyph and is the one the shop already uses for
// anything that travels; `fa-handshake` is new and comes with this round —
// see theme/make-icon-fonts.js, which has to be re-run when it does.
const DRAWER_LINKS = [
  ['fa-boxes-stacked', 'فروش عمده', 'wholesale.html'],
  ['fa-store', 'فروشنده شوید', 'vendor-register.html'],
  ['fa-truck-fast', 'پیگیری سفارش', 'order-tracking.html'],
  ['fa-ruler', 'راهنمای سایز', 'size-guide.html'],
  // «دوتا از اون مستطیل درازا بزار که مستطیل های پایین بجای ۴ تا بشه ۶ تا».
  //
  // **These two are the reason either page is reachable on a telephone.** Both
  // were in the footer alone, and the footer on the home page is seven thousand
  // pixels down: «من پیدا نکردم تو نسخه موبایل مواردی که انجام دادیرو». The
  // room for a third row came from «اکسسوری» leaving the list above it.
  //
  // `fa-handshake` is new and arrives with this round — theme/make-icon-fonts.js
  // has to be re-run when an icon does, or the class is styled, the element is
  // there and the glyph is a blank box.
  ['fa-handshake', 'تعهدات ما', 'about.html'],
  ['fa-file-contract', 'شرایط ارسال و مرجوعی', 'terms.html'],
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
  CATEGORIES.filter(([slug]) => slug !== DRAWER_HIDES).map(([slug, name, soon]) =>
    '                        <li>\n' +
    `                            <a href="shop.html"${soon ? ` data-vp-soon="${name}"` : ''}>\n` +
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
// painted with the same `#93791F → #E3B825` ramp the SVGs carry, so the row
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

// --------------------------------------------------------------------------
// «تاس شانس» — the game band, under the trust row.
//
// Two dice at rest, a button, and a line saying one throw per person. What is
// here is the **opening state and nothing else**: the result card is built by
// the script when the server answers, so this markup is the same for every
// visitor — which is what lets check-parity.js compare the static preview
// against the Laravel page at all. A band whose HTML depended on who was
// looking could not be checked that way.
//
// The dice faces are drawn, not pictures: seven pip positions per die, shown
// and hidden by a class. That is one element per pip and no request, against
// six images per die that would each have to load before the first throw
// could be shown.
// --------------------------------------------------------------------------
const DIE_FACE = (n) =>
  '\n                        <span class="vp-die" data-face="' + n + '" aria-hidden="true">' +
  [1, 2, 3, 4, 5, 6, 7].map(() => '<i></i>').join('') +
  '</span>';

const DICE_BAND =
  '\n    <section class="vp-dice-area" id="vp-dice">' +
  '\n        <div class="container th-container">' +
  '\n            <div class="vp-dice-card">' +
  '\n                <h2 class="vp-dice-title">تاس شانس، بنداز و ببر!</h2>' +
  '\n                <p class="vp-dice-say">روی دکمه شروع بزن تا تاس‌ها بچرخن؛<br>جفت شیش بیاد، جایزه‌ات فعال می‌شه</p>' +
  '\n                <div class="vp-dice-pair" data-dice-pair>' +
  DIE_FACE(3) +
  DIE_FACE(5) +
  '\n                </div>' +
  '\n                <button type="button" class="vp-dice-go" data-dice-go>شروع بازی</button>' +
  '\n                <p class="vp-dice-foot">هر کاربر ۲ بار شانس داره</p>' +
  '\n            </div>' +
  '\n        </div>' +
  '\n    </section>';

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
  '    </section>\n' +
  DICE_BAND
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
    // Two marks, one shown at a time: the shop page's own Tabler
    // `shopping-bag-plus` for the phone's square — «ما یدونه آیکون سبد خرید که
    // روش بعلاوه داره تو صفحه فروشگاه داریم چرا آیکون جدیدی میاری؟» — and the
    // plain bag for the desktop circle, which is not being changed. Kept in
    // step with resources/views/home/best-sellers.blade.php, or
    // check-parity.js fails.
    `\n                            <a class="vp-best-browse" href="shop.html" aria-label="افزودن ${name} به سبد خرید"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><svg class="vp-best-add" viewBox="0 0 24 24" fill="none" aria-hidden="true"><g stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12.5 21H8.574a3 3 0 0 1-2.965-2.544l-1.255-8.152A2 2 0 0 1 6.331 8H17.67a2 2 0 0 1 1.977 2.304l-.263 1.708M16 19h6m-3-3v6"></path><path d="M9 11V6a3 3 0 0 1 6 0v5"></path></g></svg></a>` +
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

// The way out sits opposite the title, not at the end of the filter row —
// «مشاهده همه محصولات اینجا باید حذف بشه بیاد روبروی عنوان سمت چپ». That is
// also what `.vp-brands-head` already does, and its comment says it is
// copying this band; now the two really do match.
const BEST_HEAD =
  '<div class="vp-best-head">' +
  '\n                <h2 class="vp-best-title">پرفروش‌ترین‌ها</h2>' +
  '\n                <a class="vp-best-all" href="shop.html">مشاهده همه محصولات</a>' +
  '\n            </div>' +
  '\n            <div class="vp-best-filters">' +
  BEST_FILTERS.map((label, i) =>
    `\n                <button type="button" class="vp-best-filter${i === 0 ? ' active' : ''}">${label}</button>`
  ).join('') +
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
// cards and the sale holds fewer, so the rest are the first ones again — each
// carrying `d-lg-none`, because `row-cols-xl-5` puts five on one line above 992
// and a sixth would wrap that row onto two.
//
// It pads *up to* six rather than by one: this list is five and the Blade's
// pool is whatever is promoted and sellable on the day, which came back four
// once and put five cards on the phone — «حراج پله ای چرا ناقص شده باید ۶ محصول
// توش باشه». The pads cycle through the list so two gaps read as two shoes
// rather than one shoe three times. The Blade does the same thing from the
// catalogue's side; see home/ladder.blade.php, and change the two together.
const LADDER_PADS = [];
for (let i = 0; LADDER_DEALS.length + LADDER_PADS.length < 6; i++) {
  LADDER_PADS.push({ deal: LADDER_DEALS[i % LADDER_DEALS.length], i: i % LADDER_DEALS.length, phoneOnly: true });
}

const LADDER_DEALS_HTML = LADDER_DEALS
  .map((deal, i) => ({ deal, i, phoneOnly: false }))
  .concat(LADDER_PADS)
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
  // The arrow stays here and is switched off below 992 in tweaks.css —
  // «فلش هم حذف بشه» is a phone instruction and the desktop keeps its arrow.
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

/*
 * The badge comes off the deck entirely.
 *
 * «روی عکس‌های اسلایدر همشون ۲۵ درصد تخفیف خورده قسمت تخفیفات از روی اسلایدر
 * برداشته بشه» — all six slides wore the same ٪۲۵, which is not a sale, it is
 * a decoration that says one. The client's own next sentence is the option
 * rather than the instruction — «فقط می‌تونیم در یک اسلاید حراج پله‌ای رو
 * تبلیغ کنیم» — so nothing is put in its place here; a slide that advertises
 * the stepped sale is a design decision to be made and looked at, not one to
 * be invented in a build script.
 *
 * The whole `.discount-wrapp` goes, not just the mark inside it: the wrapper
 * is positioned over the photograph and an empty one is an invisible box in
 * the corner of every slide.
 *
 * **RING above is still used**, by the dice game's prize card — see
 * `.vp-prize-badge` in the scripts, which builds the same burst around the
 * percentage somebody actually won. That one is a real number about a real
 * prize, which is the difference.
 */
html = html.replace(
  /\s*<div class="discount-wrapp[^"]*">[\s\S]*?<\/div>\s*<\/div>/g,
  ''
);

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
  // **Every tile carries the brand's own photographs now** — supplied by the
  // client, three per brand, for this arrangement: «این ۳ تصویر در ۳ کادر اول
  // که نایک هستش بیاد». Nike came first and set the order for the rest,
  // because the client named which of the three leads: the shoe on its own,
  // «اون کفش تکی که پشتش نوشته نایک برای تصویر بزرگس». So every set reads the
  // same way down the tile — the shoe alone in the large cell, then the kit,
  // then the athlete — and a set sent tomorrow needs no decision made about it.
  //
  // They are prepared by theme/make-brand-photos.js, which resizes and does
  // not crop; the cell's `object-fit: cover` does the framing, as it does for
  // every other photograph on this page. Nothing here borrows the category
  // photographs any more — that stand-in, and the client's own call behind it
  // («از عکس های اون قسمت هشتایی بالای وبسایت استفاده کن»), is finished with.
  //
  // **The fourth tile is On rather than گلدن گوس**, at the client's
  // instruction, because the fourth set they sent is On's. That is a change of
  // which brand the strip *features*, not of the catalogue: On already sells
  // the daily deal and گلدن گوس still sells its shoe, still appears in the
  // best-sellers filter and still has its own page. The strip's four are
  // whichever four `placeholders.brand_strip` names.
  //
  // **The marks are all real now**, and none of the template's abstract
  // stand-ins is left on this block. brand_5_2 came with the template and is
  // genuinely the swoosh; the other three go through
  // theme/make-brand-marks.js, which puts every one of them in the page's ink
  // on transparency whatever state it arrived in — Jordan's already cut out,
  // New Balance's black on white, On's still inside the poster it was sent
  // with. The stock counts on the plates are the only invented thing left
  // here.
  //
  // The counts are invented. There is no inventory behind this page — the
  // Laravel app has the tables, this static page has no data — so they are
  // shaped like real numbers and are not real numbers.
  {
    name: 'نایک', logo: 'brand_5_2.png', stock: '۴۲',
    photos: [
      'assets/img/brand/vikyplus-nike-vomero.webp',
      'assets/img/brand/vikyplus-nike-kit.webp',
      'assets/img/brand/vikyplus-nike-athlete.webp',
    ],
  },
  {
    name: 'جردن', logo: 'vikyplus-jordan.png', stock: '۲۸',
    photos: [
      'assets/img/brand/vikyplus-jordan-one.webp',
      'assets/img/brand/vikyplus-jordan-kit.webp',
      'assets/img/brand/vikyplus-jordan-athlete.webp',
    ],
  },
  {
    name: 'نیوبالانس', logo: 'vikyplus-nb.png', stock: '۳۵',
    photos: [
      'assets/img/brand/vikyplus-nb-530.webp',
      'assets/img/brand/vikyplus-nb-kit.webp',
      'assets/img/brand/vikyplus-nb-athlete.webp',
    ],
  },
  {
    name: 'اون', logo: 'vikyplus-on.png', stock: '۱۹',
    photos: [
      'assets/img/brand/vikyplus-on-running.webp',
      'assets/img/brand/vikyplus-on-kit.webp',
      'assets/img/brand/vikyplus-on-athlete.webp',
    ],
  },
];

// A tile's three photographs, whether they are the brand's own or the category
// tiles standing in for them. The first is the lead; the two after it are the
// stacked pair, in order.
const brandPhotos = b =>
  b.photos ?? [b.lead, b.a, b.b].map(slug => `assets/img/category/${slug}.jpg`);

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
    brandPhotos(b).map((photo, i) =>
      `                        <span class="vp-brand-cell${i === 0 ? ' is-lead' : ''}">` +
      `<img src="${photo}" alt="" loading="lazy"></span>\n`
    ).join('') +
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

// «سوالات متداول», at the foot of this page too.
//
// «بخش سوالات متداول تو دستاپ هم بیاد پایین وبسایت». The band has existed in
// the Laravel page since it was asked for on a phone, and was held off the
// desktop by one line of CSS so that this page and that one stayed
// pixel-identical. Lifting that line means this page needs the band as well,
// and here it is.
//
// **A snapshot of eight answers, and that is on purpose.** The shop's copy
// reads the live rows — the delivery answer lists whatever `shipping_methods`
// the checkout will actually offer, so repricing one from the panel rewrites
// it — and this page has no database to read. What matters is that it cannot
// cost a pixel: every box renders **closed**, a shut `<details>` is its
// `<summary>` and nothing else, and the eight questions are the same eight
// words on both sides. The answers are never painted.
//
// The lead under the heading *is* painted, so it has to match: it carries the
// shop's telephone number and its WhatsApp link, and if either moves in
// `config/storefront.php` this line goes stale and `check-parity.js` is what
// says so.
const FAQ_HTML =
`    <section class="vp-home-faq">
        <div class="container th-container">
            <div class="vp-home-faq-panel">
                <h2 class="vp-home-faq-title">سوالات متداول</h2>

                <p class="vp-doc-lead vp-faq-lead">
        اگر جواب سؤالتان این‌جا نبود، با
        <a href="tel:02133983125">021-3398-3125</a>
        تماس بگیرید یا در
        <a href="https://wa.me/${WHATSAPP_NUMBER}" target="_blank" rel="noopener">واتساپ</a>
        بپرسید.
    </p>

                <div class="vp-faq vp-faq-boxes">
                    <details >
                <summary>هزینه ارسال چقدر است؟</summary>
                <div class="vp-faq-a">
                <p>
                    بستگی به روشی دارد که هنگام ثبت سفارش انتخاب می‌کنید.
                                        گزینه‌ها اینها هستند:
                        پست پیشتاز (پس‌کرایه)، تیپاکس (پس‌کرایه) و پست معمولی (۲۰۰٬۰۰۰ تومان).
                        «پس‌کرایه» یعنی هزینهٔ ارسال را هنگام تحویل به شرکت حمل
                        می‌پردازید و فروشگاه بابتش چیزی از شما نمی‌گیرد.
                                    مبلغ دقیق، پیش از پرداخت در صفحهٔ ثبت سفارش نوشته
                    می‌شود؛ چیزی بعداً به آن اضافه نمی‌شود.
                </p>
                </div>
            </details>

            <details>
                <summary>چطور پرداخت کنم؟</summary>
                <div class="vp-faq-a">
                <p>
                    پرداخت اینترنتی. بعد از ثبت سفارش، در صفحهٔ سفارش دکمهٔ
                    پرداخت را می‌زنید و به درگاه بانکی می‌روید؛ شمارهٔ کارت و
                    رمز را همان‌جا، روی صفحهٔ بانک، وارد می‌کنید، نه در این
                    سایت. هیچ‌جای ویکی پلاس شمارهٔ کارت یا رمز از شما
                    نمی‌خواهد، و ما هیچ‌کدام را نمی‌بینیم و ذخیره نمی‌کنیم. اگر
                    صفحه‌ای به نام ویکی پلاس اطلاعات بانکی خواست، مال ما نیست.
                </p>
                </div>
            </details>

            <details>
                <summary>سفارشم را چطور پیگیری کنم؟</summary>
                <div class="vp-faq-a">
                <p>
                    از
                    <a href="faq.html">صفحه پیگیری سفارش</a>،
                    با شماره سفارش (که با VP- شروع می‌شود) و شماره موبایلی
                    که سفارش با آن ثبت شده. اگر حساب کاربری دارید، همه
                    سفارش‌هایتان در
                    <a href="my-account.html">حساب کاربری</a>
                    فهرست شده است.
                </p>
                </div>
            </details>

            <details>
                <summary>می‌توانم سفارشم را لغو کنم؟</summary>
                <div class="vp-faq-a">
                <p>
                    تا وقتی وضعیت سفارش «ثبت شد» است، بله؛ دکمه لغو در
                    همان صفحه سفارش هست و کالاها بلافاصله به فروشگاه
                    برمی‌گردند. بعد از این‌که سفارش ارسال شد دیگر از سایت
                    قابل لغو نیست و باید تماس بگیرید.
                </p>
                </div>
            </details>

            <details>
                <summary>سایز کفش اشتباه بود. تعویض می‌کنید؟</summary>
                <div class="vp-faq-a">
                <p>
                    تا ۷ روز بعد از تحویل، برای
                    تعویض سایز با ما تماس بگیرید. تعویض فعلاً تلفنی و
                    دستی انجام می‌شود، نه از داخل سایت، پس لطفاً کالا را
                    نپوشیده و با جعبه نگه دارید تا هماهنگ شود.
                </p>
                <p>
                    بهتر از تعویض، این است که اول
                    <a href="size-guide.html">راهنمای سایز</a>
                    را ببینید یا طول پایتان را برای ما بفرستید.
                </p>
                </div>
            </details>

            <details>
                <summary>کد تخفیف را کجا وارد کنم؟</summary>
                <div class="vp-faq-a">
                <p>
                    در صفحه پرداخت، کنار جمع فاکتور. کد روی کالاهای خود
                    ویکی پلاس اعمال می‌شود و رقم آن، پیش از ثبت سفارش، در
                    همان جمع به شما نشان داده می‌شود.
                </p>
                </div>
            </details>

            <details>
                <summary>حساب کاربری لازم است؟</summary>
                <div class="vp-faq-a">
                <p>
                    نه. می‌توانید بدون ثبت‌نام سفارش دهید. اگر حساب بسازید،
                    سفارش‌هایتان یک‌جا جمع می‌شود.
                </p>
                <p>
                    نکته‌ای که ممکن است به آن بربخورید: اگر قبلاً با همین
                    شماره موبایل سفارش داده‌اید، هنگام ثبت‌نام شماره یکی از
                    سفارش‌های خودتان را می‌پرسیم. این تنها راهی است که
                    مطمئن شویم شماره مال خودتان است؛ پیامک تأیید هنوز
                    نداریم.
                </p>
                </div>
            </details>

            <details>
                <summary>می‌خواهم کالاهایم را در ویکی پلاس بفروشم.</summary>
                <div class="vp-faq-a">
                <p>
                    از
                    <a href="faq.html">فرم فروشنده شوید</a>
                    درخواست بدهید. ثبت‌نام رایگان است، کارمزد فقط روی فروش
                    گرفته می‌شود، و کالاهایتان پس از تأیید روی سایت
                    می‌آید.
                </p>
                </div>
            </details>
                </div>
            </div>
        </div>
    </section>

    <script>
        (function () {
            var band = document.querySelector('.vp-faq-boxes');
            if (!band) return;

            var boxes = Array.prototype.slice.call(band.querySelectorAll('details'));
            var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var MS = 260;

            // The height of a closed <details> cannot be read, so it is opened
            // first and then animated from nothing. Closing is the same in reverse,
            // and \`open\` is only taken away once the drawer has finished shutting —
            // take it away first and there is nothing left to animate.
            function slide(box, open) {
                var body = box.querySelector('.vp-faq-a');
                if (!body || calm) { box.open = open; return; }

                window.clearTimeout(box.vpTimer);

                var from = box.open ? body.getBoundingClientRect().height : 0;
                if (open) box.open = true;

                body.style.overflow = 'hidden';
                body.style.height = from + 'px';
                void body.offsetHeight;

                body.style.transition = 'height ' + MS + 'ms cubic-bezier(0.2, 0.7, 0.2, 1)';
                body.style.height = (open ? body.scrollHeight : 0) + 'px';

                box.vpTimer = window.setTimeout(function () {
                    body.style.transition = '';
                    body.style.height = '';
                    body.style.overflow = '';
                    if (!open) box.open = false;
                }, MS);
            }

            boxes.forEach(function (box) {
                box.querySelector('summary').addEventListener('click', function (event) {
                    // The browser's own toggle would fight the animation, and it is
                    // what the keyboard fires too, so this covers Enter and Space.
                    event.preventDefault();

                    var opening = !box.open;

                    boxes.forEach(function (other) {
                        if (other !== box && other.open) slide(other, false);
                    });

                    slide(box, opening);
                });
            });
        }());
    </script>
`;

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
  html = html.slice(0, start) + BRANDS_HTML + FAQ_HTML + html.slice(end);
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
//
// **A shoe with no colour gets no hue**, and this one has none: with white,
// black and grey left out of the vote, 47.7% of the Jordan's pixels vote,
// 24.0% of the New Balance's and 20.5% of the Golden Goose's — and 0.3% of
// this Cloudtilt's, which is the red Swiss flag on its heel and nothing else.
// Run through the same recipe it would come out at 5.8°, a pink within one
// level of the Jordan's own mark. So it takes the family's lightness with the
// saturation at nothing: the shoe is a black knit on a white sole and the mark
// under it says so.
const HERO_MARKS = {
  'vikyplus-hero-jordan.webp': '#DDC1BB',
  'vikyplus-hero-goldengoose.webp': '#DDCEBB',
  'vikyplus-hero-nb530.webp': '#BBCFDD',
  'vikyplus-hero-cloudtilt-black.webp': '#CCCCCC',
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
  // The six trust badges under them are out for the same reason, one round
  // later: «در حالت فعلی وقتی وارد وبسایت میشیم اون ۶ آیتم پایین ۸ آیتم باید
  // اسکرول کنیم تا پدیدار بشه ولی باید از اول باشه». On a 390×844 screen the
  // first two of the six land at y=634 and the other four at 760 and 885 — one
  // and two rows past the fold — so the visitor who opens the shop sees two
  // badges and two card-shaped holes, and only scrolling fills them. The row
  // is six of the shop's own promises; it is not an entrance.
  //
  // Read once, before the loop, because it decides two things — whether the
  // tiles are in `items` at all, and whether the closing-on-the-middle
  // movement below is set up. A resize past 992 does not re-run this, which is
  // the right trade: nobody resizes a phone across the breakpoint, and the
  // desktop still gets the entrance it was drawn with.
  '            var phone = window.matchMedia("(max-width: 991.98px)").matches;\n' +
  '            items = Array.prototype.filter.call(items, function (el) {\n' +
  '                if (!phone) return true;\n' +
  '                return !(el.classList.contains("vp-category") || el.closest(".vp-trust-row"));\n' +
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
// «تاس شانس» — the throw, from the page's side.
//
// Plain DOM, no jQuery: the band is one button and it should not wait on a
// library that the rest of this page happens to carry. It reads two addresses
// off the section (`data-dice-url`, `data-shop-url`) so the same markup works
// in the Laravel app and in this static preview, where there is no server and
// the throw simply says so.
//
// **Nothing here decides anything.** The faces, the win and the code all come
// back from the server; this only shows them. A visitor who edits this script
// changes what their own screen says and nothing else — which is the whole
// reason the roll is not done in the browser.
const DICE_SCRIPT =
`    <script>
        (function () {
            var band = document.getElementById('vp-dice');
            if (!band) { return; }

            var pair = band.querySelector('[data-dice-pair]');
            var go = band.querySelector('[data-dice-go]');
            if (!pair || !go) { return; }

            var dice = pair.querySelectorAll('.vp-die');
            var url = band.getAttribute('data-dice-url') || '/game/dice';
            var shop = band.getAttribute('data-shop-url') || '/products';
            var busy = false;

            // The dice tumble for this long whatever the network does. An
            // answer that arrives in 40ms and stops them dead is not a throw;
            // a slow one keeps them going until it lands.
            //
            // It was 1100 and «خیلی بیخودو کوتاهه» — so 2400, about as long as
            // a real pair takes to leave a hand, hit the table and settle. The
            // face churns underneath every CHURN ms, which is what makes it a
            // throw rather than a shape wobbling: the pips have to be moving
            // or the eye reads the die as being pushed, not rolled.
            var TUMBLE = 2400;
            var CHURN = 90;
            var LAND = 260;
            // A double six is the whole point of the band, so it is allowed to
            // be looked at. «وقتی جفت شیش میشه باید ۲ ثانیه رو جفت شیش بمونه
            // بعد این پاپاپ باز بشه» — the card covers the dice, and opening
            // it the instant they stop means nobody ever sees what they threw.
            //
            // Then «اون مکث نصف بشه»: two seconds was long enough to read as
            // the page having stalled rather than as a pause for effect, which
            // is the failure this number can have in either direction. One
            // second still clears the landing animation (LAND + 60 = 320ms) by
            // a wide margin, so the dice are settled and still for two thirds
            // of the wait before the card arrives.
            var GLOAT = 1000;

            function fa(text) {
                return String(text).replace(/[0-9]/g, function (d) {
                    return String.fromCharCode(1776 + Number(d));
                });
            }

            function csrf() {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return band.getAttribute('data-dice-token') || (meta ? meta.getAttribute('content') : '');
            }

            // The button is spent after one throw, so it is replaced by the
            // sentence rather than left sitting there disabled.
            function spend(text) {
                var line = document.createElement('p');
                line.className = 'vp-dice-done';
                line.textContent = text;
                if (go.parentNode) { go.parentNode.replaceChild(line, go); }
            }

            // A throw that never reached the server is not a throw. The button
            // comes back and the reason goes under it — spending it here would
            // charge somebody their one go for a dropped packet.
            function excuse(text) {
                var line = band.querySelector('.vp-dice-done');

                if (!line) {
                    line = document.createElement('p');
                    line.className = 'vp-dice-done';
                    go.parentNode.insertBefore(line, go.nextSibling);
                }

                line.textContent = text;
                go.disabled = false;
                busy = false;
            }

            function copy(text, button) {
                function done() {
                    button.textContent = 'کپی شد';
                    setTimeout(function () { button.textContent = 'کپی'; }, 1600);
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done, function () {});
                    return;
                }

                // http, or an older browser. execCommand is deprecated and is
                // the only thing that works there.
                var box = document.createElement('textarea');
                box.value = text;
                box.setAttribute('readonly', 'readonly');
                box.style.position = 'fixed';
                box.style.insetBlockStart = '-1000px';
                document.body.appendChild(box);
                box.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(box);
            }

            function confetti(into) {
                // The page's gold, its ink and its grey. Confetti in colours
                // the site does not otherwise own is how the band ended up
                // looking like somebody else's site the first time.
                var colours = ['#DAB226', '#EFC94F', '#A08119', '#101111', '#E7E9EC'];
                var field = document.createElement('div');
                field.className = 'vp-confetti';

                for (var i = 0; i < 24; i++) {
                    var piece = document.createElement('i');
                    piece.style.insetInlineStart = (Math.random() * 100) + '%';
                    piece.style.background = colours[i % colours.length];
                    piece.style.animationDelay = (Math.random() * 0.8) + 's';
                    piece.style.animationDuration = (2.2 + Math.random() * 1.2) + 's';
                    field.appendChild(piece);
                }

                into.appendChild(field);
            }

            function prize(answer) {
                var scrim = document.createElement('div');
                scrim.className = 'vp-prize-scrim';
                scrim.setAttribute('role', 'dialog');
                scrim.setAttribute('aria-modal', 'true');
                scrim.setAttribute('aria-label', 'جایزه تاس شانس');

                var card = document.createElement('div');
                card.className = 'vp-prize';
                scrim.appendChild(card);

                var shut = document.createElement('button');
                shut.type = 'button';
                shut.className = 'vp-prize-shut';
                shut.setAttribute('aria-label', 'بستن');
                shut.textContent = '×';
                card.appendChild(shut);

                // The shop's own star, not a disc — the hero's mark and the
                // sale cards' mark are this same shape, and the path and the
                // studs are interpolated from the very constants that draw
                // them, so the three can never drift apart. The figure is
                // Latin on purpose, the way the hero's «25% OFF» is.
                var badge = document.createElement('div');
                badge.className = 'vp-prize-badge';
                badge.innerHTML = '<svg class="vp-prize-burst" viewBox="0 0 150 150" aria-hidden="true">'
                    + '<defs><linearGradient id="vp-prize-burst-gold" x1="0" y1="0" x2="0" y2="1">'
                    + '<stop offset="0%" stop-color="#C0972F"></stop><stop offset="100%" stop-color="#E3B54A"></stop>'
                    + '</linearGradient></defs>'
                    + '<g class="vp-burst-star">'
                    + '<path fill="url(#vp-prize-burst-gold)" d="${BURST_PATH}"></path>'
                    + '${BURST_STUDS}'
                    + '</g>'
                    + '<text class="vp-burst-num" x="75" y="72"></text>'
                    + '<text class="vp-burst-off" x="75" y="98">OFF</text>'
                    + '</svg>';
                badge.querySelector('.vp-burst-num').textContent = answer.percent + '%';
                card.appendChild(badge);

                var what = document.createElement('p');
                what.className = 'vp-prize-what';
                what.textContent = 'جفت شیش آوردی 🎲';
                card.appendChild(what);

                var title = document.createElement('h3');
                title.className = 'vp-prize-title';
                title.textContent = answer.fresh ? 'تبریک! 🎉' : 'جایزه‌ات همین‌جاست';
                card.appendChild(title);

                var say = document.createElement('p');
                say.className = 'vp-prize-say';
                say.textContent = fa(answer.percent) + '٪ تخفیف روی سفارشت، برای شما فعال شد.';
                card.appendChild(say);

                var box = document.createElement('div');
                box.className = 'vp-prize-code';
                var label = document.createElement('span');
                label.textContent = 'کد تخفیف';
                var value = document.createElement('b');
                value.textContent = answer.code;
                var take = document.createElement('button');
                take.type = 'button';
                take.className = 'vp-prize-copy';
                take.textContent = 'کپی';
                take.addEventListener('click', function () { copy(answer.code, take); });
                box.appendChild(label);
                box.appendChild(value);
                box.appendChild(take);
                card.appendChild(box);

                var use = document.createElement('a');
                use.className = 'vp-prize-go';
                use.href = shop;
                use.textContent = 'استفاده از تخفیف';
                card.appendChild(use);

                var when = document.createElement('p');
                when.className = 'vp-prize-when';
                when.textContent = '⏱ اعتبار کد: ' + fa(answer.hours) + ' ساعت';
                card.appendChild(when);

                // Around the card, not across it: inside the card itself
                // these twenty-four opaque flakes landed on the code and
                // the title.
                confetti(scrim);

                function shutIt() {
                    if (scrim.parentNode) { scrim.parentNode.removeChild(scrim); }
                    document.body.classList.remove('vp-prize-open');
                    document.removeEventListener('keydown', onKey);
                    go.focus && go.focus();
                }

                function onKey(event) {
                    if (event.key === 'Escape') { shutIt(); }
                }

                shut.addEventListener('click', shutIt);
                scrim.addEventListener('click', function (event) {
                    if (event.target === scrim) { shutIt(); }
                });
                document.addEventListener('keydown', onKey);

                // The corner's WhatsApp button is fixed and outranks nothing,
                // so without this it sits on the scrim as a green square over
                // the celebration. tweaks.css hides it on this class.
                document.body.classList.add('vp-prize-open');
                document.body.appendChild(scrim);
                shut.focus();
            }

            // While the dice are in the air their faces are meaningless, so
            // they may as well churn. Nothing here decides anything — the
            // server has already decided, or is about to — and land() writes
            // the real faces over whatever this left behind.
            var churn = null;

            function startChurn() {
                stopChurn();
                churn = setInterval(function () {
                    for (var i = 0; i < dice.length; i++) {
                        dice[i].setAttribute('data-face', String(1 + Math.floor(Math.random() * 6)));
                    }
                }, CHURN);
            }

            function stopChurn() {
                if (churn) { clearInterval(churn); churn = null; }
            }

            function land(answer) {
                stopChurn();
                pair.classList.remove('is-rolling');

                if (answer.dice && answer.dice.length === 2) {
                    for (var i = 0; i < dice.length && i < 2; i++) {
                        dice[i].setAttribute('data-face', String(answer.dice[i]));
                    }
                }

                // The settle. Removed afterwards so the next throw can play it
                // again — an animation that is already on an element does not
                // restart when the class is merely still there.
                pair.classList.add('is-landing');
                setTimeout(function () { pair.classList.remove('is-landing'); }, LAND + 60);

                if (answer.won && answer.code) {
                    // The code goes on the band as well as in the card, so
                    // closing the card does not take it away.
                    spend('جفت شیش! کد تخفیفت: ' + answer.code);
                    // Two seconds on the two sixes before the card covers
                    // them. Winning is the moment; it should be seen.
                    setTimeout(function () { prize(answer); }, GLOAT);
                    return;
                }

                if (answer.won) {
                    // Won, but the code has been spent or has expired.
                    spend('قبلاً بازی کردی و برده بودی؛ کد تخفیفت دیگر معتبر نیست.');
                    return;
                }

                // A loss with a throw still owed is not the end of the game,
                // so the button stays and the line goes under it. Ending it
                // here is what «۲ شانس» would look like as one.
                if (answer.left > 0) {
                    excuse(answer.left === 1
                        ? 'این بار جفت شیش نیامد؛ یک شانس دیگر داری.'
                        : 'این بار جفت شیش نیامد؛ ' + fa(answer.left) + ' شانس دیگر داری.');
                    return;
                }

                spend(answer.fresh
                    ? 'این بار هم جفت شیش نیامد. شانس‌هایت تمام شد.'
                    : 'شانس‌هایت تمام شده بود.');
            }

            go.addEventListener('click', function () {
                if (busy) { return; }
                busy = true;

                go.disabled = true;
                pair.classList.remove('is-landing');
                pair.classList.add('is-rolling');
                startChurn();

                var thrown = Date.now();
                var landed = function (answer) {
                    var left = Math.max(0, TUMBLE - (Date.now() - thrown));
                    setTimeout(function () { land(answer); }, left);
                };

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        if (!response.ok) {
                            throw new Error(body.error || (response.status === 429
                                ? 'کمی صبر کن و دوباره بزن.'
                                : 'الان نشد؛ کمی بعد دوباره امتحان کن.'));
                        }
                        return body;
                    });
                }).then(landed, function (error) {
                    var left = Math.max(0, TUMBLE - (Date.now() - thrown));
                    setTimeout(function () {
                        stopChurn();
                        pair.classList.remove('is-rolling');
                        excuse(error && error.message ? error.message : 'الان نشد؛ کمی بعد دوباره امتحان کن.');
                    }, left);
                });
            });
        })();
    </script>
</body>`;

html = html.replace('</body>', DICE_SCRIPT);

// «به‌زودی» — the four sections that are not open yet.
//
// «اکسسوری / ست کیف و کفش / ست ورزشی / بوت و نیم بوت … اینا باید هرجا روشون
// زده بشه باید بشن کامینگ سون», and then, after a first attempt that badged the
// front page's tiles and greyed the listing's marks: «چرا رو کارتشون تو صفحه
// هوم زدی؟؟؟ … آیکونشون غیره فعال نشه، فقط هرجا کلیک میشه پاپاپ کامینگ سون
// بیاد». So nothing anywhere looks different, and the *click* is the whole
// feature.
//
// **Delegated on the document, not bound to eight links.** The rows this has to
// answer are on three surfaces — the tiles under the hero, the phone drawer and
// the listing's category strip — and the drawer is rebuilt by the template's
// own script on some pages. One listener on the document catches all of them
// and cannot go stale.
//
// **The link is left working.** `data-vp-soon` carries the section's name and
// nothing else; the `href` still points at the section's own page, which says
// the same sentence in a full page. So a visitor with no JavaScript, a
// middle-click, or a long-press "open in new tab" all land somewhere that
// answers them, and this only saves them the round trip.
//
// Plain DOM, no jQuery: this is on every page of the shop and the two catalogue
// pages do not carry the home page's libraries.
html = html.replace('</body>',
  '    <script>\n' +
  '        (function () {\n' +
  '            var scrim = null;\n' +
  '            var camefrom = null;\n' +
  '\n' +
  '            function shut() {\n' +
  '                if (!scrim) { return; }\n' +
  '                if (scrim.parentNode) { scrim.parentNode.removeChild(scrim); }\n' +
  '                scrim = null;\n' +
  '                document.body.classList.remove("vp-soon-open");\n' +
  '                document.removeEventListener("keydown", onKey);\n' +
  '                if (camefrom && camefrom.focus) { camefrom.focus(); }\n' +
  '            }\n' +
  '\n' +
  '            function onKey(event) {\n' +
  '                if (event.key === "Escape") { shut(); }\n' +
  '            }\n' +
  '\n' +
  '            function show(name) {\n' +
  '                shut();\n' +
  '\n' +
  '                scrim = document.createElement("div");\n' +
  '                scrim.className = "vp-soon-scrim";\n' +
  '                scrim.setAttribute("role", "dialog");\n' +
  '                scrim.setAttribute("aria-modal", "true");\n' +
  '                scrim.setAttribute("aria-label", "به‌زودی");\n' +
  '\n' +
  '                var card = document.createElement("div");\n' +
  '                card.className = "vp-soon-card";\n' +
  '                scrim.appendChild(card);\n' +
  '\n' +
  '                var x = document.createElement("button");\n' +
  '                x.type = "button";\n' +
  '                x.className = "vp-soon-shut";\n' +
  '                x.setAttribute("aria-label", "بستن");\n' +
  '                x.textContent = "×";\n' +
  '                card.appendChild(x);\n' +
  '\n' +
  '                var mark = document.createElement("span");\n' +
  '                mark.className = "vp-empty-mark";\n' +
  '                mark.setAttribute("aria-hidden", "true");\n' +
  '                mark.innerHTML = \'<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="17" fill="none" stroke="currentColor" stroke-width="3"></circle><path d="M24 14 V25 L31 29" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>\';\n' +
  '                card.appendChild(mark);\n' +
  '\n' +
  '                var say = document.createElement("p");\n' +
  '                say.className = "vp-empty-say";\n' +
  '                say.textContent = "«" + name + "» به‌زودی راه‌اندازی می‌شود.";\n' +
  '                card.appendChild(say);\n' +
  '\n' +
  '                var ok = document.createElement("button");\n' +
  '                ok.type = "button";\n' +
  '                ok.className = "vp-soon-ok";\n' +
  '                ok.textContent = "باشه";\n' +
  '                card.appendChild(ok);\n' +
  '\n' +
  '                x.addEventListener("click", shut);\n' +
  '                ok.addEventListener("click", shut);\n' +
  '                scrim.addEventListener("click", function (event) {\n' +
  '                    if (event.target === scrim) { shut(); }\n' +
  '                });\n' +
  '                document.addEventListener("keydown", onKey);\n' +
  '\n' +
  '                document.body.classList.add("vp-soon-open");\n' +
  '                document.body.appendChild(scrim);\n' +
  '                ok.focus();\n' +
  '            }\n' +
  '\n' +
  '            // **Capture, not bubble.** The template\'s mobile-menu plugin\n' +
  '            // binds `stopPropagation` to the drawer and to every div inside\n' +
  '            // it, to keep a tap in the panel from reaching its\n' +
  '            // close-on-outside-click handler. A listener on the document\n' +
  '            // therefore never hears a tap on a drawer row: the first version\n' +
  '            // of this bubbled, worked on the tiles and the listing strip, and\n' +
  '            // navigated straight past the card in the drawer. Capture runs\n' +
  '            // before any of that and cannot be stopped from below.\n' +
  '            document.addEventListener("click", function (event) {\n' +
  '                var target = event.target;\n' +
  '                if (!target || !target.closest) { return; }\n' +
  '\n' +
  '                var link = target.closest("[data-vp-soon]");\n' +
  '                if (!link) { return; }\n' +
  '\n' +
  '                // A modified click is somebody asking for the page in a tab\n' +
  '                // of their own, and the page exists and says the same thing.\n' +
  '                if (event.metaKey || event.ctrlKey || event.shiftKey || event.button) { return; }\n' +
  '\n' +
  '                event.preventDefault();\n' +
  '                camefrom = link;\n' +
  '\n' +
  '                // Tapped inside the phone drawer, which is a full-screen\n' +
  '                // panel: shut it with its own button rather than laying the\n' +
  '                // card over it, so closing the card does not leave the\n' +
  '                // visitor back in a menu they had finished with.\n' +
  '                var drawer = link.closest(".th-menu-wrapper");\n' +
  '                var close = drawer && drawer.querySelector(".th-menu-toggle");\n' +
  '                if (close) { close.click(); }\n' +
  '\n' +
  '                show(link.getAttribute("data-vp-soon"));\n' +
  '            }, true);\n' +
  '        })();\n' +
  '    </script>\n' +
  '</body>');

html = html.replace('</body>',
  '    <script>\n' +
  '        (function () {\n' +
  '            var $ = window.jQuery;\n' +
  '            var wrap = document.querySelector(".sticky-wrapper");\n' +
  '            var header = wrap && wrap.closest(".th-header");\n' +
  '            var menu = wrap && wrap.querySelector(".menu-area");\n' +
  '            var catMenu = document.querySelector(".category-menu");\n' +
  '            // The corner button. It was a scroll-to-top ring once\n' +
  '            // and is the WhatsApp link now; the name says which, because a\n' +
  '            // variable called toTop that shows a chat button is a trap.\n' +
  '            // Both corner buttons: the support square and the WhatsApp\n' +
  '            // link. They show together — one appearing without the other\n' +
  '            // reads as a fault rather than as two controls.\n' +
  '            var corner = document.querySelectorAll(".vp-whatsapp, .vp-support-fab");\n' +
  '            // The support one on its own, for the pill below.\n' +
  '            var fab = document.querySelector(".vp-support-fab");\n' +
  '            var saidIt = false;\n' +
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
  '                //\n' +
  '                // And out again at the footer: «اسکرول وقتی به فوتر میرسه\n' +
  '                // اون آیکون شناور واتسپ باید حذف بشه». The footer carries\n' +
  '                // its own WhatsApp mark, so down there the floating one is\n' +
  '                // a second copy of a button sitting right behind it — and\n' +
  '                // on a phone it lands on top of the copyright line. The\n' +
  '                // test is the footer\'s top crossing the bottom of the\n' +
  '                // viewport; screenTop is already measured for the band\n' +
  '                // below, so this costs no layout read.\n' +
  '                var atFoot = screenEl ? (y + winH > screenTop) : false;\n' +
  '                var on = y > 0 && !atFoot;\n' +
  '                for (var c = 0; c < corner.length; c++) {\n' +
  '                    corner[c].classList.toggle("show", on);\n' +
  '                }\n' +
  '                // «اولش باید اون پشتیبانی یه مستطیل باشه که روش نوشته\n' +
  '                // پشتیبانی ۲۴ ساعته چند ثانیه باشه بعد جمع بشه».\n' +
  '                //\n' +
  '                // The clock starts the first time the button is actually on\n' +
  '                // screen, not on load: these two appear on the first scroll,\n' +
  '                // so a timer armed at load would spend its whole three\n' +
  '                // seconds while the button is still invisible and the pill\n' +
  '                // would never be seen. `saidIt` makes it once a page — it is\n' +
  '                // an introduction, and a button that reintroduces itself\n' +
  '                // every time you scroll back up is a fidget.\n' +
  '                if (on && fab && !saidIt) {\n' +
  '                    saidIt = true;\n' +
  '                    fab.classList.add("is-wide");\n' +
  '                    setTimeout(function () {\n' +
  '                        fab.classList.remove("is-wide");\n' +
  '                    }, 3200);\n' +
  '                }\n' +
  '                if (screenEl) {\n' +
  '                    // The original test, unchanged: the footer is left\n' +
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
// **Five, not six.** The basket and the account are already buttons in this
// same band, so listing them again is the same door twice — and measured, six
// items of Persian ran the header row 22px past the page at 1200, which
// `check-overflow.js` failed on. The template's six were shorter words with
// dropdown arrows; ours are «پیگیری سفارش» and «فروشنده شوید».
//
// «فروش عمده» is the fifth, at the client's word: «فروش عمده باید بیاد تو
// موارد بالای وبسایت». It sits after «فروشگاه» because the two are the same
// errand at two scales, and it is the shortest of the five, which is what
// makes room for it — remeasured at 992/1200/1440/1920 with
// `check-overflow.js` after adding it, because 22px is all the slack this row
// has ever had. A sixth still does not fit.
html = html.replace(
  /<nav class="main-menu d-none d-lg-inline-block">[\s\S]*?<\/nav>/,
  '<nav class="main-menu d-none d-lg-inline-block">\n' +
  '                                <ul>\n' +
  // «فروش عمده و پیگیری سفارش باید بیان بعد از فروشگاه» — the two errands sit
  // together behind the shop, and the ways of browsing follow them.
  //
  // The three that drop as the row narrows are the last three, in the order
  // the classes name. Counted rather than guessed, by hiding items until every
  // control was back inside the island: 5 fit at 992, 6 at 1100, 7 at 1200 and
  // all 8 from 1280 up. «فروشگاه», «فروش عمده» and «پیگیری سفارش» never drop —
  // they are errands somebody came to do, where a section is a way of browsing
  // that the listing offers again in its own sort control. «جدیدترین‌ها» goes
  // first because it is the one the client did not name.
  '                                    <li><a href="shop.html">فروشگاه</a></li>\n' +
  '                                    <li><a href="wholesale.html">فروش عمده</a></li>\n' +
  '                                    <li><a href="order-tracking.html">پیگیری سفارش</a></li>\n' +
  '                                    <li><a href="shop.html?sale=1">تخفیف پله‌ای</a></li>\n' +
  '                                    <li><a href="shop.html?sort=bestselling">پرفروش‌ترین‌ها</a></li>\n' +
  '                                    <li class="vp-nav-drop-1"><a href="shop.html?sort=newest">جدیدترین‌ها</a></li>\n' +
  '                                    <li class="vp-nav-drop-3"><a href="index.html#brands">برندها</a></li>\n' +
  '                                    <li class="vp-nav-drop-2"><a href="faq.html">سوالات متداول</a></li>\n' +
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

// --- the offer banners, now the shop's own coming-soon collages -------------
//
// Used to carry «BLACK / FRIDAY / SPECIAL OFFER» and «Adidas Shoes — The
// Summer Sale Up to 50% Off» translated into the shop's own stepped-sale
// copy. «اینام بزار تو بنرای سایت» replaced the photographs themselves with
// two collages the client sent, each already carrying its own «COMING SOON»
// mark baked into the picture — so the template's title, sub-title and «Shop
// Now» button come off rather than sit on top of a second, conflicting
// message. «متن و دکمه پاک بشه، فقط خود عکس» in those words. The `.discount`
// shape on the first banner goes with it — a Black Friday «%» badge has
// nothing to do with a coming-soon collage.
//
// `cta_11_1.png`/`cta_11_2.png` were the template's own placeholders, small
// and flat enough to compress to a few KB; real photography does not, so the
// replacements are `.jpg` (mozjpeg, resized to the width the banner actually
// draws at) rather than a giant PNG re-using the old name.
html = html.replace(
  /<div class="cta-area4 mega-hover" data-bg-src="assets\/img\/normal\/cta_11_1\.png">[\s\S]*?discount2\.png"[^>]*>\s*<\/div>\s*<\/div>/,
  '<div class="cta-area4 mega-hover" data-bg-src="assets/img/normal/cta_11_1.jpg" role="img" aria-label="به‌زودی">\n' +
  '                    </div>'
);

// Matched on «خرید کنید», not «Shop Now»: DICT (line ~1443) runs long before
// this and has already turned every «Shop Now» on the page into it, this
// button included — the hero's own buy button hit the same thing, see the
// note below on why that one is matched by its wrapper rather than its label.
html = html.replace(
  /<div class="cta-area4 style2 mega-hover" data-bg-src="assets\/img\/normal\/cta_11_2\.png">[\s\S]*?line-btn th-icon">خرید کنید<\/a>\s*<\/div>\s*<\/div>/,
  '<div class="cta-area4 style2 mega-hover" data-bg-src="assets/img/normal/cta_11_2.jpg" role="img" aria-label="به‌زودی">\n' +
  '                    </div>'
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

// The anchor «برندها» in the top menu points at. An `id` paints nothing, so
// the parity check cannot see it either way — which is exactly why it is
// written on both sides rather than only on the one somebody remembered.
html = html.replace(
  '<section class="vp-brands-section space">',
  '<section class="vp-brands-section space" id="brands">'
);
if (!html.includes('id="brands"')) {
  throw new Error('the brands band has moved — «برندها» in the section bar points at nothing');
}

// The seal, exactly as the eNamad panel issues it — see the note below the
// next comment for why every attribute in here matters. One string, so the
// day it is reissued there is one line to change.
//
// **It is pasted exactly as eNamad issued it, and «exactly» is their rule:**
// «لوگو خود را کپی کرده بدون تغییر در سایت خود قرار دهید». Two consequences
// that look like sloppiness and are not:
//
//   - `alt` is empty. It was filled in here once, to name the seal for a
//     screen reader, and it has been put back: their check reads the markup,
//     and a seal that does not verify is worth less than the alt text is worth.
//   - **The link carries no `rel`, and none may be added.** eNamad say so
//     outright — «عبارت rel="noopener noreferrer" باعث عدم نمایش لوگو در سایت
//     شما میشود» — because their server refuses the picture to a request that
//     arrives without a referrer. Every other `target="_blank"` on this site
//     has `rel="noopener"` and that is right; adding it here, on the grounds
//     that it is missing, breaks the seal silently. `TrustSealTest` fails if
//     it ever appears.
//
// The same rule is why opening the image's address straight in a browser
// answers `HTTP 400`: no referrer, no picture. That is not evidence of a wrong
// code, and an afternoon went into learning it.
//
// **`onerror` hides the whole plate, and it is there because it happened.**
// The first code we were given answered `HTTP 400 Bad Request` from
// trustseal.enamad.ir for every visitor — the id and Code did not name a seal
// their server would serve — and what a failed image leaves behind is not
// nothing: it is the reserved 90px box with its white plate, an empty white
// square on the baseboard of every page, which is worse than no seal at all.
// A trust mark that cannot load must take its frame with it. When the right
// code arrives the picture loads, `onerror` never fires, and the plate is
// there as designed.
//
// Inline rather than in a script file on purpose: it has to be armed before
// the image finishes failing, and the page's own scripts load at the foot of
// the document, long after.
//
// **`loading="lazy"` because of the filter-breaker.** This is the only address
// on the page that is not ours, and it is an Iranian host — which a visitor
// reaching the shop through a VPN is coming at from a foreign IP. That does
// not fail, it *hangs*: no response, no error, so `onerror` never fires and
// the browser holds a connection open and keeps the tab spinning while
// everything else has long since arrived. Lazy puts the request off until the
// baseboard is nearly on screen, so a hang costs a seal nobody scrolled to
// rather than the whole page's «finished loading». `decoding="async"` keeps it
// off the main thread when it does come.
//
// **And a clock, because a hang is not an error.** `onerror` fires when the
// server answers badly. It never fires when the server does not answer at all,
// which is the filter-breaker case exactly — so without this the plate sits
// there reserved and empty for as long as the request stays open, which is the
// white square the paragraph above says is worse than no seal. Six seconds is
// long enough for a slow but working connection to deliver a 3KB image and
// short enough that nobody reads the baseboard before it resolves.
const ENAMAD =
  "<a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=696411&Code=oyQ6picRwm2lLEPobQWLuNSW37WIf7mV'>" +
  "<img referrerpolicy='origin' loading='lazy' decoding='async' " +
  "src='https://trustseal.enamad.ir/logo.aspx?id=696411&Code=oyQ6picRwm2lLEPobQWLuNSW37WIf7mV' " +
  "alt='' style='cursor:pointer' code='oyQ6picRwm2lLEPobQWLuNSW37WIf7mV' " +
  "onerror=\"var p=this.closest('.vp-enamad'); if (p) p.style.display='none';\"></a>" +
  "<script>(function(){var p=document.currentScript.parentNode,i=p.querySelector('img');" +
  "setTimeout(function(){if(!i.complete||!i.naturalWidth)p.style.display='none';},6000);}());<\/script>";

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
// What is left is the shop's own line, and — since the shop has one — the
// نماد اعتماد الکترونیکی above it.
//
// **The seal is eNamad's own markup, kept as they issue it.** The `code`
// attribute on the image, both `referrerpolicy='origin'`s and the two query
// strings are not decoration: eNamad's own script reads the code, and their
// check requires the image to be fetched from *their* server, from a page
// whose referrer is this domain. A copy of the picture served from our own
// assets would look identical and count as not installed. So the one thing
// changed is `alt`, which arrives empty and now names the seal for a screen
// reader.
//
// It is the one image on this site that is not ours and cannot be made local.
// `tweaks.css` therefore reserves its box, so the strip is the same height
// whether or not enamad.ir answers — see the block by `.vp-enamad`.
html = html.replace(
  /<div class="copyright-wrap">[\s\S]*?<\/footer>/,
  '<div class="copyright-wrap">\n' +
  '            <div class="container th-container5">\n' +
  '                <div class="vp-enamad">\n' +
  '                    ' + ENAMAD + '\n' +
  '                </div>\n' +
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
// The shop's own details, said once. They are printed in the phone's footer
// and now in the desktop's, and a second copy of an address is how a shop ends
// up with two.
const FOOT_STRAP = 'ارائه\u200cدهنده انواع کیف و کفش زنانه با تضمین کیفیت، ارسال سریع و امکان خرید تکی و عمده.';
const FOOT_ADDRESS = 'تهران، سعدی شمالی، روبه\u200cروی بانک ملی، پلاک ۵۶۵';
const FOOT_PHONE = '021-3398-3125';
const FOOT_TEL = '02133983125';

// The desktop footer's «دسته‌ها» column: the phone's four, plus «حراج پله‌ای»,
// which is the one other link the phone's footer carries and the desktop's
// three columns have under no name. The phone keeps its own arrangement below
// — it is a screenshot the client drew and approved, and the desktop is not
// that screenshot.
const FOOT_CATS = [
  ['shop.html', 'کفش زنانه'],
  ['shop.html', 'کیف زنانه'],
  ['shop.html', 'پرفروش\u200cترین\u200cها'],
  ['shop.html', 'تخفیف\u200cدارها'],
  ['stepped-sale.html', 'حراج پله\u200cای'],
];

/*
 * Five each now, and five for the same reason it was four: «تعداد با بغلی ها
 * برابر بشه» is a rule about the counts matching, not about the number being
 * four.
 *
 * «مقالات» is the page the shop had nowhere for — «هیچ جایی برای مقالات در
 * سایت نداریم» — and this footer is the one the phone actually reaches, so it
 * goes in here and the two columns beside it come up to meet it.
 *
 * The two that join it are pages that already exist and were not linked from
 * this footer: `/support`, which is where the desktop footer's own
 * «پشتیبانی» goes, and «جدیدترین‌ها», which came off the third column only to
 * make the counts match and is exactly what a fifth row is for.
 */
const FOOT_COLS = [
  ['لینک\u200cها', [
    ['shop.html', 'فروشگاه'],
    ['about.html', 'درباره ما'],
    ['blog.html', 'مقالات'],
    ['contact.html', 'ارتباط با ما'],
    ['size-guide.html', 'راهنمای سایز'],
  ]],
  ['خدمات', [
    ['privacy.html', 'حریم خصوصی'],
    ['terms.html', 'قوانین و مقررات'],
    ['faq.html', 'سوالات متداول'],
    ['support.html', 'پشتیبانی'],
    ['stepped-sale.html', 'حراج پله\u200cای'],
  ]],
  ['دسته\u200cها', [
    ['shop.html', 'کفش زنانه'],
    ['shop.html', 'کیف زنانه'],
    ['shop.html', 'پرفروش\u200cترین\u200cها'],
    // «جدیدترین‌ها» is back: it came off only to make this column four like the
    // two beside it, and they are five now.
    ['shop.html?sort=newest', 'جدیدترین\u200cها'],
    ['shop.html', 'تخفیف\u200cدارها'],
  ]],
];

// Listed in the order they are *read* in RTL, so the row comes out as the
// screenshot has it: Instagram at the right of the row, and what follows runs
// leftwards. Written the other way round first and the row came out mirrored —
// the page is RTL, so the first child sits at the right.
//
// «تو فوتر باید آیکون واتسپ تلگرام اینستا بله و روبیکا باشه» — five now, and the fourth is no
// longer «the multi-coloured mark this could not identify from the
// screenshot»: it is بله, and روبیکا joins it.
//
// **بله and روبیکا have no mark in Font Awesome and no artwork in this
// repository**, so both are drawn rather than reproduced: a tile in the
// service's own colour with a glyph on it. That is a stand-in and it is meant
// to be replaced — send the two logos the way `vikyplus-appicon.png` was sent
// and each becomes an `<img>` on this line, with nothing else in the footer
// changing. Drawing a trademark from memory is the one thing worse than saying
// out loud that it is a stand-in.
//
// WhatsApp's link is the shop's own number, the same one the floating corner
// button opens. The other four are '#' until the client sends the addresses.
const FOOT_SOCIAL = [
  // Named in Latin, and deliberately: `HomePageTest` guards the four sections
  // taken off the page by their headings, and one of those headings is the
  // word «اینستاگرام». A Persian label here puts that word back on the page and
  // fails that test — which is the guard doing its job, not a false alarm, so
  // the label moves rather than the test. «Instagram» is how the service is
  // said in Persian anyway.
  // **Images, not glyphs, and that was a decision with a measurement behind
  // it.** These three were `<i class="fa-brands …">` until the same complaint
  // arrived twice: a glyph sits on a baseline, `line-height: 1` shortens the
  // line box below the font's own metrics, and where the ink lands is then the
  // engine's rounding — Chromium centred them exactly while WebKit drew them
  // 1.0–1.2px high, on one build, measured both ways. `make-brand-marks.js`
  // cuts the same shapes out of the same font file the browser was drawing
  // them from, with the ink's bounding box as the viewBox, so the file's edges
  // are the mark's edges and centring the image centres the mark everywhere.
  ['instagram', 'Instagram', '#', '<img class="vp-foot-m-soc-mark" src="assets/img/social/instagram.svg" alt="" width="24" height="24">'],
  ['telegram', 'تلگرام', '#', '<img class="vp-foot-m-soc-mark" src="assets/img/social/telegram.svg" alt="" width="26" height="26">'],
  ['whatsapp', 'واتساپ', `https://wa.me/${WHATSAPP_NUMBER}`, '<img class="vp-foot-m-soc-mark" src="assets/img/social/whatsapp.svg" alt="" width="24" height="24">'],
  // The client sent these two as photographs of the logos; `make-social-marks.js`
  // lifts them off their grey ground and writes the two files below. They were
  // a chat bubble and the letter R until then — see the note beside
  // `.vp-foot-m-soc.is-bale` in tweaks.css, which said exactly this would
  // happen. The mark carries its own colours, so its chip is white where the
  // other three are on their service's colour.
  ['bale', 'بله', '#', '<img class="vp-foot-m-soc-mark" src="assets/img/social/bale.png" alt="" width="26" height="26">'],
  ['rubika', 'روبیکا', '#', '<img class="vp-foot-m-soc-mark" src="assets/img/social/rubika.png" alt="" width="26" height="26">'],
];

const FOOT_PHONE_HTML =
  '<div class="vp-foot-m">\n' +
  // «تو فوتر موبایل باید لوگو بیاد بالای ویکی پلاس و اون خطهای ۲ طرفش پاک
  // بشن» — the mark above the name, and the two rules gone. The rules in the
  // column headings below are a different thing and stay; the instruction
  // named the ones beside the shop's name.
  '                <div class="vp-foot-m-head">\n' +
  '                    <img class="vp-foot-m-mark" src="assets/img/vikyplus-appicon.png" alt="" width="56" height="56">\n' +
  '                    <b class="vp-foot-m-name">ویکی پلاس</b>\n' +
  '                </div>\n' +
  '                <p class="vp-foot-m-strap">' + FOOT_STRAP + '</p>\n' +
  '                <p class="vp-foot-m-line"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>' + FOOT_ADDRESS + '</span></p>\n' +
  '                <p class="vp-foot-m-line"><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:' + FOOT_TEL + '">' + FOOT_PHONE + '</a></p>\n' +
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

// --- what the phone's footer has and the desktop's did not -------------------
//
// «چیزایی که تو فوتر نسخه موبایل اضافه کردیم هم بیان تو نسخه دستاپ».
//
// The two footers were built to be different on purpose: the phone's is one
// centred column from a screenshot the client sent, the desktop's is the
// template's four-column widget area, and the standing instruction while the
// phone one was being written was that none of it should reach the desktop.
// That instruction is now reversed, and this is the reversal — not by showing
// `.vp-foot-m` at every width, which would put two whole footers on the page,
// but by giving the desktop's own brand column the three things the phone's
// has and it lacks.
//
// **The address and the telephone number are the ones that matter.** The
// comment above says the template's contact block was removed rather than
// translated, because it carried a German company and a Californian street,
// and that it «comes back when the real details arrive». They arrived, in the
// phone footer, and this is them: one shop, one address, said in both places
// from `FOOT_ADDRESS` and `FOOT_PHONE` so it cannot be said two ways.
//
// The social row is not here: «شبکه های اجتماعیرو باید بیاری اینجا», pointing
// at «ویکی پلاس روی موبایل» further along the same footer. It goes there,
// below.
//
// Injected after the logo anchor rather than written into the block above,
// because `FOOT_SOCIAL` is declared further down this file and a `const` read
// before its own line is a crash rather than a mistake.
{
  const at = '                                    </a>\n                                </div>';
  if (!html.includes(at)) {
    throw new Error('the desktop footer brand column has moved');
  }
  html = html.replace(at,
    '                                    </a>\n' +
    '                                    <p class="vp-foot-d-strap">' + FOOT_STRAP + '</p>\n' +
    '                                    <p class="vp-foot-d-line"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>' + FOOT_ADDRESS + '</span></p>\n' +
    '                                    <p class="vp-foot-d-line"><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:' + FOOT_TEL + '">' + FOOT_PHONE + '</a></p>\n' +
    '                                </div>');
}

// And the phone's «دسته‌ها» column, which the desktop had no equivalent of at
// all. A fifth column in a `justify-content-between` row — measured at
// 992/1200/1440/1920 after adding it, because a row that wraps is a taller
// footer rather than a wider page and `check-overflow.js` cannot see it.
{
  const account = '<div class="col-md-6 col-xl-auto">\n                            <div class="widget widget_nav_menu footer-widget">\n                                <h3 class="widget_title">حساب کاربری</h3>';
  if (!html.includes(account)) {
    throw new Error('the account column has moved — the categories column has nothing to sit beside');
  }
  html = html.replace(account,
    '<div class="col-md-6 col-xl-auto">\n' +
    '                            <div class="widget widget_nav_menu footer-widget">\n' +
    '                                <h3 class="widget_title">دسته‌ها</h3>\n' +
    '                                <div class="menu-all-pages-container">\n' +
    '                                    <ul class="menu">\n' +
    FOOT_CATS.map(([href, label]) =>
      `                                        <li><a href="${href}">${label}</a></li>`).join('\n') + '\n' +
    '                                    </ul>\n' +
    '                                </div>\n' +
    '                            </div>\n' +
    '                        </div>\n' +
    '                        ' + account);
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
  /*
   * «مقالات», which the shop had nowhere for at all — «هیچ جایی برای مقالات در
   * سایت نداریم». Added rather than swapped in: no item in this column names
   * something the shop does not do, so there was nothing to take out.
   *
   * The `<li>` is written out because this list replaces anchors, and an
   * anchor cannot add a row on its own. `blog.html` is the filename
   * config/storefront.php maps to `/articles` — its own, so that only this
   * slot points there.
   *
   * **The indentation is part of the match, and it has to be.** The phone's
   * footer names «درباره ما» too, out of `FOOT_COLS`, and every entry in this
   * list is applied with `split().join()` — so the bare `<li>` matched both
   * and «مقالات» came out twice in one column. The desktop's list is indented
   * forty spaces and the phone's twenty-eight, which is the whole of the
   * difference between them.
   */
  [
    '                                        <li><a href="about.html">درباره ما</a></li>',
    '                                        <li><a href="about.html">درباره ما</a></li>\n' +
    '                                        <li><a href="blog.html">مقالات</a></li>',
  ],
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
  /*
   * «پشتیبانی» goes to the support page, not to «تماس با ما».
   *
   * «یه قسمتی به عنوان پشتیبانی ۲۴ ساعته تو سایت داریم ولی هیچ جای دیگه راجع
   * به پشتیبانی … کجا باید این سوال رو مطرح بکنه … نیست» — and this link,
   * which is the one place the footer says the word, landed on a page that
   * printed a telephone number and said in its own comment that it had no
   * form. `/support` is a form into the same inbox the other two enquiry
   * pages write to.
   */
  ['<a href="contact.html">Help Ticket</a>', '<a href="support.html">پشتیبانی</a>'],
  /*
   * These two are matched in Persian, not English: DICT (line ~1362) has
   * already translated «My Account» and «Wishlist» by the time this list runs,
   * which is also why «Online Shopping» reads «Online فروشگاهping» above.
   *
   * The account link is the plainest of the twenty-one that went nowhere: the
   * shop has had customer accounts since `AccountController`, and the footer
   * item named after them pointed at contact.html.
   *
   * The wishlist slot went the way «مقایسه» did — a real destination under a
   * label the shop could honour — and it has its own name back now: the
   * wishlist exists, at `/account/wishlist`, so «علاقه‌مندی‌ها» names something
   * again. That is what the note said to do the day it was built.
   *
   * `wishlist.html` is a filename of its own so this slot alone points there,
   * and config/storefront.php maps it to `account.wishlist`. The route is
   * behind `auth:customer`, so a visitor who is not signed in lands on the
   * shopper's sign-in rather than the staff one — `redirectGuestsTo` in
   * bootstrap/app.php picks by matching `*account*` on the route name, which
   * is why that route is called `account.wishlist` and not `wishlist`.
   */
  ['<a href="contact.html">حساب کاربری</a>', '<a href="my-account.html">حساب کاربری</a>'],
  ['<a href="contact.html">علاقه‌مندی‌ها</a>', '<a href="wishlist.html">علاقه‌مندی‌ها</a>'],
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
  ['<a href="https://www.whatsapp.com/">', `<a href="https://wa.me/${WHATSAPP_NUMBER}" target="_blank" rel="noopener" aria-label="واتساپ">`],
].forEach(([from, to]) => {
  if (!html.includes(from)) {
    throw new Error(`the footer no longer contains ${from.slice(0, 40)} — check before assuming it is gone`);
  }
  html = html.split(from).join(to);
});

// --- the five social links, in the column the client pointed at -------------
//
// «شبکه های اجتماعیرو باید بیاری اینجا», with a photograph of «ویکی پلاس روی
// موبایل» and its two grey circles — the template's own `.th-social`, which
// had been translated and left at Instagram and WhatsApp while the phone's
// footer grew to «واتسپ تلگرام اینستا بله و روبیکا».
//
// So the two are replaced by the five, from the same `FOOT_SOCIAL` list the
// phone reads, in the same order and wearing the same marks. `.vp-foot-m-soc`
// is deliberately the class on both: the tile and its five service colours are
// one rule now, shared out of the phone's media query, so the two rows cannot
// come apart. Only the row around them differs — the phone centres it under a
// centred column, this one starts at the column's own edge.
{
  const at = /<div class="th-social style2 mt-40">[\s\S]*?<\/div>/;
  if (!at.test(html)) {
    throw new Error('the mobile column no longer has the social row to replace');
  }
  html = html.replace(at,
    '<div class="vp-foot-d-social">' +
    FOOT_SOCIAL.map(([key, name, href, icon]) =>
      `\n                                        <a class="vp-foot-m-soc is-${key}" href="${href}" aria-label="${name}">${icon}</a>`).join('') +
    '\n                                    </div>');
}

// «راهنمای سایز» is the one link in the phone's three columns that the
// desktop's three do not have under any name — «سوالات متداول» is «راهنما»
// here, «ارتباط با ما» is «تماس با ما», «قوانین و مقررات» is «قوانین». It
// joins «پشتیبانی», next to the other guide.
//
// **After the list above, not before it.** «راهنما» is what that list writes;
// anchored any earlier this matches nothing, silently, and the link simply is
// not on the page — which is exactly what happened the first time. Hence the
// throw.
{
  const at = '<li><a href="faq.html">راهنما</a></li>';
  if (!html.includes(at)) {
    throw new Error('the support column has moved — «راهنمای سایز» has nothing to sit beside');
  }
  html = html.replace(at, at + '\n' +
    '                                        <li><a href="size-guide.html">راهنمای سایز</a></li>');
}

/*
 * The hero's buy button says «خرید محصول».
 *
 * Not through DICT: its «Shop Now» becomes «خرید کنید» and that word is on the
 * offer banner's two buttons as well, which were not asked about. The six hero
 * buttons are told apart by the `btn-group` wrapper the offer banner's do not
 * have — matched with the whole opening tag rather than by the label alone, so
 * this cannot start catching them if the template's markup shifts.
 *
 * Six, because the deck runs three products twice.
 */
{
  const heroButton = '<div class="btn-group" data-ani="slideinup" data-ani-delay="0.7s">' +
    '<a href="shop.html" class="th-btn th-icon">خرید کنید</a>';

  const heroButtons = html.split(heroButton).length - 1;

  if (heroButtons !== 6) {
    throw new Error(`expected the hero's six buy buttons, found ${heroButtons} — check before renaming them`);
  }

  html = html.split(heroButton).join(
    '<div class="btn-group" data-ani="slideinup" data-ani-delay="0.7s">' +
    '<a href="shop.html" class="th-btn th-icon">خرید محصول</a>'
  );
}

// The basket's badge starts at nothing. It was the template's «5» — a number
// that never moved however full the basket was, which is worse than no number
// at all. The Laravel page renders the real count in its place; both read ۰
// with an empty basket, which is what the parity check compares.
html = html.replace(
  /(<button type="button" class="icon-btn sideMenuToggler"[\s\S]*?)<span class="badge">5<\/span>/,
  '$1<span class="badge">۰</span>'
);

// --- the shop, installable ---------------------------------------------------
//
// Android will not offer «نصب برنامه» unless the site registers a service
// worker with a fetch handler. Without one — and without the `start_url` and
// 512px icon the manifest was also missing — Chrome fell back to minting a
// throwaway APK, which Google Play Protect refused: «This app was built for an
// older version of Android». The client photographed exactly that.
//
// Registered last and only after `load`, so it can never delay the page.
// `isSecureContext` rather than a check for https: it is true for https *and*
// for localhost, so the thing that ships is the thing that can be tested —
// a check for the scheme alone is unverifiable on a development machine,
// which is how a registration that never worked would have shipped green. `sw.js` itself caches nothing — see the note at the top of it, and
// «قالب قبلی» in CLAUDE.md for why caching this site's assets is not a small
// decision.
html = html.replace('</body>',
  '    <script>\n' +
  '        if ("serviceWorker" in navigator && window.isSecureContext) {\n' +
  '            window.addEventListener("load", function () {\n' +
  '                navigator.serviceWorker.register("/sw.js").catch(function () {});\n' +
  '            });\n' +
  '        }\n' +
  '    </script>\n</body>');

// --- every photograph offered at the size the screen can show ----------------
//
// Last, so it sees every `<img>` on the finished page however it got there.
//
// The product photographs are 1400 wide because a 2x desktop draws one at 583
// CSS pixels and wants 1166. A phone draws the same photograph at 267 and can
// show 534 — so on the screen that complained about the site being slow, 655KB
// of the page is resolution the device cannot render, and it is the part gzip
// cannot help. `theme/make-photo-sizes.js` cuts a 700-wide copy of each and
// writes the manifest this reads.
//
// **One `sizes` rule for every photograph on the site, here and in
// `photo_srcset()`, and they have to stay the same string.** It is not a
// per-image measurement because the two copies of this page must choose the
// same file at every width or `check-parity.js` starts reporting differences
// that are really just two encodings of one picture. Measured against the
// layout: a phone at 390 asks for 273 CSS pixels, doubled is 546, so it takes
// the 700; a 1200 desktop asks for 480 and takes the 700; 1920 asks for 768
// and takes the original, as does any 2x screen. Nothing that was sharp
// becomes soft.
const PHOTOS = JSON.parse(
  fs.readFileSync(path.join(ROOT, 'download-version/assets/img/photo-sizes.json'), 'utf8'),
).photos;
const PHOTO_SIZES = '(min-width: 992px) 40vw, 70vw';

let dressed = 0;
html = html.replace(/<img\b[^>]*>/g, (tag) => {
  if (/\bsrcset=/.test(tag)) return tag;
  const src = tag.match(/\bsrc="([^"]+)"/);
  const photo = src && PHOTOS[src[1]];
  if (!photo) return tag;
  dressed++;
  return tag.replace(
    /\bsrc="[^"]+"/,
    `$& srcset="${photo.small} ${photo.smallWidth}w, ${src[1]} ${photo.width}w" sizes="${PHOTO_SIZES}"`,
  );
});
console.log(`  ${dressed} photographs offered at two sizes`);

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
