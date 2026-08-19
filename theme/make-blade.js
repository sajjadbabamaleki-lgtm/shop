#!/usr/bin/env node
/**
 * Ports the rendered RTL home page into the Laravel app's Blade views.
 *
 * The static page in `download-version/` is where the look was settled — every
 * number in HANDOFF.md was measured off it. This carries that page into
 * `storefront/resources/views/` without retyping it: the body is cut at its
 * section boundaries into one partial per region, the head becomes the layout,
 * and only two things are rewritten —
 *
 *   assets/...      →  {{ asset('assets/...') }}
 *   somepage.html   →  {{ page_url('somepage.html') }}
 *
 * so the markup that reaches the browser is the markup that was measured.
 *
 * Run: node theme/make-blade.js
 *
 * It overwrites the generated partials every run and leaves everything else in
 * resources/views alone. That is deliberate for the length of the port: the
 * static page is still the surface the client reviews, so the two have to be
 * able to stay in step. Once data is being wired into these views, Blade
 * becomes the source of truth and this script should be deleted rather than
 * run again — see HANDOFF.md.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PAGE = path.join(ROOT, 'download-version/shoe-shop-rtl.html');
const VIEWS = path.join(ROOT, 'storefront/resources/views');

const html = fs.readFileSync(PAGE, 'utf8');

// --- the regions, in the order they appear -----------------------------------
//
// Each is named by a string that occurs exactly once in the page, and a region
// runs from its own anchor to the next one's. Slicing that way rather than
// matching closing tags means nothing between two regions can be dropped by
// accident: the slices are guaranteed to reassemble into the original body.
//
// `into` says which file the region is written to. Several regions share a
// file where the page interleaves them — the modal explaining the ladder is
// emitted next to the ladder, not left among the script tags where the
// template's markup happens to put it.
//
// `owned` marks the regions that have since been rewritten by hand to render
// from the catalogue. Those are no longer copies of anything and this script
// must not overwrite them — it still slices them, so the boundary checks below
// keep working and the regions after them stay in the right place, but it
// leaves the files alone. See HANDOFF.md.
const REGIONS = [
  // Was `<div class="magic-cursor` until the cursor follower was taken out of
  // the page — the anchor threw rather than silently swallowing the region,
  // which is the whole reason it insists the string exists exactly once.
  { name: 'chrome', anchor: '<div class="slider-drag-cursor">', into: 'partials/chrome.blade.php' },
  // The mini basket, sliced out of chrome so it can render the real one. Same
  // reasoning as the stories strip: without a region of its own the chrome
  // region runs on through it and the demo markup comes back on every build.
  { name: 'mini-cart', anchor: '<div class="sidemenu-wrapper sidemenu-cart">', into: 'partials/mini-cart.blade.php', owned: true },
  { name: 'mobile-menu', anchor: '<div class="th-menu-wrapper">', into: 'partials/mobile-menu.blade.php', owned: true },
  { name: 'header', anchor: '<header class="th-header', into: 'partials/header.blade.php' },
  // The story strip sits between the header and the hero, so it needs a region
  // of its own or the header's runs on into it: without this line the five
  // circles were written into partials/header.blade.php *and* rendered again
  // from home/stories.blade.php, twice on the page. `owned` because the Blade
  // reads its five out of $categories rather than having them typed in.
  { name: 'stories', anchor: '<section class="vp-stories"', into: 'home/stories.blade.php', owned: true },
  { name: 'hero', anchor: '<div class="th-hero-wrapper', into: 'home/hero.blade.php', owned: true },
  { name: 'categories', anchor: '<section class="feature-area2', into: 'home/categories.blade.php', owned: true },
  { name: 'ladder', anchor: '<section class="collection-area vp-ladder-area', into: 'home/ladder.blade.php', owned: true },
  { name: 'best-sellers', anchor: '<section class="space overflow-hidden overflow-hidden vp-best-section"', into: 'home/best-sellers.blade.php', owned: true },
  { name: 'offer-banner', anchor: '<section class="overflow-hidden">', into: 'home/offer-banner.blade.php' },
  { name: 'daily-deal', anchor: '<section class="space overflow-hidden overflow-hidden vp-daily-deal-section"', into: 'home/daily-deal.blade.php', owned: true },
  { name: 'brands', anchor: '<section class="vp-brands-section', into: 'home/brands.blade.php', owned: true },
  { name: 'footer', anchor: '<footer class="footer-wrapper', into: 'partials/footer.blade.php' },
  { name: 'page-end', anchor: '<!-- WhatsApp -->', into: 'partials/scripts.blade.php' },
];

const BODY_OPEN = html.indexOf('<body');
const BODY_END = html.lastIndexOf('</body>');
if (BODY_OPEN < 0 || BODY_END < 0) throw new Error('the page has no <body>');

/** Index of an anchor, insisting it is unambiguous. */
function at(anchor) {
  const first = html.indexOf(anchor);
  if (first < 0) throw new Error(`anchor never appears: ${anchor}`);
  if (html.indexOf(anchor, first + 1) >= 0) throw new Error(`anchor is not unique: ${anchor}`);
  return first;
}

const marks = REGIONS.map((r) => ({ ...r, start: at(r.anchor) }));
for (let i = 1; i < marks.length; i++) {
  if (marks[i].start <= marks[i - 1].start) {
    throw new Error(`${marks[i].name} appears before ${marks[i - 1].name}; the region order is wrong`);
  }
}

for (let i = 0; i < marks.length; i++) {
  marks[i].end = i + 1 < marks.length ? marks[i + 1].start : BODY_END;
  marks[i].text = html.slice(marks[i].start, marks[i].end);
}

// Anything between <body> and the first region is the template's "code starts
// here" banner and its <!--[if lte IE 9]> upgrade notice. Both are comments —
// the IE one is downlevel-hidden, so no browser in service renders it — and
// neither is carried over. Assert that, so a real element appearing here
// stops the port rather than vanishing from it.
const preamble = html
  .slice(html.indexOf('>', BODY_OPEN) + 1, marks[0].start)
  .replace(/<!--[\s\S]*?-->/g, '');
if (/<[a-z]/i.test(preamble)) {
  throw new Error('there is markup before the first region that would be dropped');
}

// --- rewrites ----------------------------------------------------------------

// The template's banner comments name the region above or below them. The file
// name says that now, so they go; every other comment stays.
const BANNER = /[ \t]*<!--=+\s*\n?[\s\S]*?=+\s*-->[ \t]*\n?/g;

/** The two path rewrites, applied to every attribute that carries a path. */
function toBlade(text) {
  return text
    .replace(/(\s(?:src|href|data-bg-src|data-mask-src|poster)=)"(assets\/[^"]*)"/g,
      (_, attr, file) => `${attr}"{{ asset('${file}') }}"`)
    // The one <meta> that names a file rather than prose.
    .replace(/(msapplication-TileImage"\s+content=)"(assets\/[^"]*)"/g,
      (_, attr, file) => `${attr}"{{ asset('${file}') }}"`)
    .replace(/(\shref=)"([A-Za-z0-9._-]+\.html)"/g,
      (_, attr, page) => `${attr}"{{ page_url('${page}') }}"`);
}

/** Strip the banner comments and normalise the blank lines they leave behind. */
function tidy(text) {
  return text.replace(BANNER, '').replace(/\n{3,}/g, '\n\n').replace(/\s+$/, '') + '\n';
}

// --- the numbers that are not the page's ------------------------------------
//
// A handful of strings in the static page are figures, and in Laravel they come
// from somewhere. Rewriting them here rather than by hand after every run: the
// basket badge was re-typed into the generated header four separate times,
// because a regeneration is exactly the moment nobody remembers there was a
// hand edit to put back.
//
// Each entry is asserted, so a template change that moves one of these stops
// the port instead of silently going back to the printed number.
const LIVE = [
  {
    region: 'header',
    // The bag's count. `??` rather than a bare variable so a page that never
    // composed one — an error page, say — still renders its header.
    find: '<span class="badge">۰</span>',
    put: '<span class="badge">{{ fa_number($basketCount ?? 0) }}</span>',
  },
  {
    region: 'header',
    // The account icon beside the basket, which has to say the same thing the
    // drawer's own button says: offering «ثبت‌نام» to somebody who is signed in
    // is how a page tells them it has not noticed them. It is the button's
    // accessible name — the control has no text — so this is the only place a
    // screen reader learns which of the two it is.
    //
    // This was the dark strip's text link until the strip was removed, and the
    // guard below is what said so rather than letting the rewrite go quiet.
    find: 'aria-label="ورود / ثبت‌نام"',
    // An expression rather than `@auth … @else … @endauth`: a Blade directive
    // needs whitespace after it and that space is emitted, which inside an
    // attribute value would become part of the name.
    put: 'aria-label="{{ auth(\'customer\')->check() ? \'حساب من\' : \'ورود / ثبت‌نام\' }}"',
  },
];

for (const { region, find, put } of LIVE) {
  const target = marks.find((m) => m.name === region);
  if (!target) throw new Error(`no region named ${region}`);
  if (!target.text.includes(find)) {
    throw new Error(`the ${region} region no longer contains ${find} — it cannot be made live`);
  }
  target.text = target.text.split(find).join(put);
}

// --- the how-it-works modal --------------------------------------------------
//
// The template drops it after the script tags. It belongs with the section
// whose button opens it, so it is cut out of the tail region and moved.
const MODAL_OPEN = '<div class="vp-how-modal"';
const pageEnd = marks[marks.length - 1];
const modalStart = pageEnd.text.indexOf(MODAL_OPEN);
if (modalStart < 0) throw new Error('the how-it-works modal is not where it was');
const modalEnd = pageEnd.text.indexOf('\n    </div>', modalStart) + '\n    </div>'.length;
const modal = pageEnd.text.slice(modalStart, modalEnd);
pageEnd.text = pageEnd.text.slice(0, modalStart) + pageEnd.text.slice(modalEnd);

const ladder = marks.find((m) => m.name === 'ladder');
ladder.text += '\n' + modal + '\n';

// --- the inline scripts ------------------------------------------------------
//
// Seven of them run after main.js. Five are page-wide and stay with the script
// tags; two drive one section each and move to that section's partial, pushed
// onto a stack the layout empties in the same place the tags used to sit. All
// seven are independent IIFEs, so their order relative to each other does not
// matter — only that they still run after main.js, which the stack preserves.
//
// The count is asserted rather than assumed, and it earns its keep: adding the
// service-worker registration — the last of the seven, and what makes the shop
// installable on Android — stopped this script dead instead of silently
// dropping a script from the Laravel page while the preview kept it.
//
// Two of the page-wide four belong to a section and stay here anyway: the
// category strip's auto-scroll and the dice game's throw. Not by preference —
// `partials/categories` and `home/dice` are two of the hand-owned Blades this
// script deliberately does not write, so there is nowhere to push them to.
// Both find their section by selector, which the hand-written Blade renders
// under the same class and id.
const SECTION_SCRIPTS = [
  { owns: '.vp-hero-marks', region: 'hero' },
  { owns: 'vp-ladder-how', region: 'ladder' },
];

const INLINE = /[ \t]*<script>\n[\s\S]*?<\/script>\n?/g;
const inlineScripts = pageEnd.text.match(INLINE) || [];
if (inlineScripts.length !== 7) {
  throw new Error(`expected 7 inline scripts after main.js, found ${inlineScripts.length}`);
}

for (const { owns, region } of SECTION_SCRIPTS) {
  const script = inlineScripts.find((s) => s.includes(owns));
  if (!script) throw new Error(`no inline script mentions ${owns}`);
  pageEnd.text = pageEnd.text.replace(script, '');
  const target = marks.find((m) => m.name === region);
  target.text += `\n@push('scripts')\n${script.replace(/\s+$/, '')}\n@endpush\n`;
}

// Where those two used to be.
pageEnd.text = pageEnd.text.replace(
  /(<script src="assets\/js\/main\.js"><\/script>\n)/,
  "$1\n    @stack('scripts')\n"
);

// The floating corner button is markup, not script; it is only in this region
// because the template puts it between the footer and the script tags. Keeping
// it there — rather than folding it into the chrome at the top of the body —
// keeps the DOM order, and with it the stacking order, exactly as measured.
//
// It was the template's scroll-to-top ring and is a WhatsApp link now, so the
// closing tag to slice on is an anchor's rather than a div's, and the partial
// is named for what it is. `theme/make-rtl-page.js` is where the swap happens
// and where the number lives.
const CORNER_END = '</a>\n';
const cornerEnd = pageEnd.text.indexOf(CORNER_END) + CORNER_END.length;
const corner = pageEnd.text.slice(0, cornerEnd);
if (!corner.includes('class="vp-whatsapp"')) {
  throw new Error('the WhatsApp button is not at the head of the tail region');
}
pageEnd.text = pageEnd.text.slice(cornerEnd);
marks.push({ name: 'whatsapp', into: 'partials/whatsapp.blade.php', text: corner });

// --- write -------------------------------------------------------------------

const written = [];
function write(rel, body) {
  const dest = path.join(VIEWS, rel);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  const header = `{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}\n`;
  fs.writeFileSync(dest, header + body);
  written.push(`${rel} (${body.split('\n').length} lines)`);
}

const skipped = [];
for (const region of marks) {
  if (region.owned) {
    skipped.push(region.into);
    continue;
  }
  write(region.into, tidy(toBlade(region.text)));
}

// The head is the layout's. The <title> is the one thing in it that cannot
// stay as it was: it is per-page, and the template's is the name of the
// template. Every other string in here — author, description, keywords — is
// still the template's copy, and replacing copy is not this port's job.
let head = html.slice(html.indexOf('<head>') + '<head>'.length, html.indexOf('</head>'));
head = head.replace(/<title>[\s\S]*?<\/title>/,
  "<title>@yield('title', config('app.name'))</title>");
if (!head.includes('@yield')) throw new Error('the head has no <title> to make per-page');

// tweaks.css is fingerprinted, and this belongs here rather than in the file
// this writes.
//
// It was a hand-edit to partials/head.blade.php once, and this script silently
// deleted it the next time it ran — a generated file cannot hold a hand
// correction, which is the whole reason make-blade.js prints what it leaves
// alone. So the transformation lives in the generator and survives.
//
// What it is for: tweaks.css is the one stylesheet on this site that changes,
// and it was served at a plain URL. A returning visitor therefore got the new
// HTML — a response, never cached — against their browser's cached copy of the
// old CSS. Not a subtle failure: the client opened a rebuilt product card and
// saw the new markup with none of its rules on it, an unstyled button below
// the photograph where a white circle should have been on it, and reported the
// build as broken. It was not broken; it was half of it.
//
// The hash is of the file's contents, so the URL changes when the file does
// and not otherwise. `file_exists` because a missing stylesheet should render
// a page without styles rather than a 500.
// Applied after `toBlade`, not before: the link is still a plain relative path
// at this point and only becomes an `asset()` call in there, so a regex
// written against the finished form finds nothing and — worse — finds it
// silently.
let headOut = tidy(toBlade(head));

const TWEAKS = /<link rel="stylesheet" href="\{\{ asset\('assets\/css\/tweaks\.css'\) \}\}">/;
if (!TWEAKS.test(headOut)) {
  throw new Error('the head has no tweaks.css link to fingerprint — check before assuming it is already done');
}
headOut = headOut.replace(TWEAKS,
  "@php($tweaks = public_path('assets/css/tweaks.css'))\n"
  + "    <link rel=\"stylesheet\" href=\"{{ asset('assets/css/tweaks.css') }}"
  + "{{ file_exists($tweaks) ? '?v='.substr(md5_file($tweaks), 0, 8) : '' }}\">");

write('partials/head.blade.php', headOut);

console.log('Wrote:');
for (const line of written) console.log('  ' + line);

if (skipped.length) {
  console.log('\nLeft alone — these render from the catalogue and are hand-owned now:');
  for (const file of skipped) console.log('  ' + file);
  console.log('\nA design change to one of those has to be made in the Blade by hand.');
  console.log('node theme/check-parity.js says whether the two pages still agree.');
}
