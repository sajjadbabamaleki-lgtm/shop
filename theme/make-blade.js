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
const REGIONS = [
  { name: 'chrome', anchor: '<div class="magic-cursor', into: 'partials/chrome.blade.php' },
  { name: 'mobile-menu', anchor: '<div class="th-menu-wrapper">', into: 'partials/mobile-menu.blade.php' },
  { name: 'header', anchor: '<header class="th-header', into: 'partials/header.blade.php' },
  { name: 'hero', anchor: '<div class="th-hero-wrapper', into: 'home/hero.blade.php' },
  { name: 'categories', anchor: '<section class="feature-area2', into: 'home/categories.blade.php' },
  { name: 'ladder', anchor: '<section class="collection-area vp-ladder-area', into: 'home/ladder.blade.php' },
  { name: 'best-sellers', anchor: '<section class="space overflow-hidden overflow-hidden vp-best-section"', into: 'home/best-sellers.blade.php' },
  { name: 'offer-banner', anchor: '<section class="overflow-hidden">', into: 'home/offer-banner.blade.php' },
  { name: 'daily-deal', anchor: '<section class="space overflow-hidden overflow-hidden vp-daily-deal-section"', into: 'home/daily-deal.blade.php' },
  { name: 'brands', anchor: '<section class="vp-brands-section', into: 'home/brands.blade.php' },
  { name: 'footer', anchor: '<footer class="footer-wrapper', into: 'partials/footer.blade.php' },
  { name: 'page-end', anchor: '<!-- Scroll To Top -->', into: 'partials/scripts.blade.php' },
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
// Four of them run after main.js. Two are page-wide and stay with the script
// tags; two drive one section each and move to that section's partial, pushed
// onto a stack the layout empties in the same place the tags used to sit. All
// four are independent IIFEs, so their order relative to each other does not
// matter — only that they still run after main.js, which the stack preserves.
const SECTION_SCRIPTS = [
  { owns: '.vp-hero-marks', region: 'hero' },
  { owns: 'vp-ladder-how', region: 'ladder' },
];

const INLINE = /[ \t]*<script>\n[\s\S]*?<\/script>\n?/g;
const inlineScripts = pageEnd.text.match(INLINE) || [];
if (inlineScripts.length !== 4) {
  throw new Error(`expected 4 inline scripts after main.js, found ${inlineScripts.length}`);
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

// The scroll-to-top ring is markup, not script; it is only in this region
// because the template puts it between the footer and the script tags. Keeping
// it there — rather than folding it into the chrome at the top of the body —
// keeps the DOM order, and with it the stacking order, exactly as measured.
const SCROLL_TOP_END = '</div>\n';
const scrollTopEnd = pageEnd.text.indexOf(SCROLL_TOP_END) + SCROLL_TOP_END.length;
const scrollTop = pageEnd.text.slice(0, scrollTopEnd);
if (!scrollTop.includes('class="scroll-top"')) {
  throw new Error('the scroll-to-top ring is not at the head of the tail region');
}
pageEnd.text = pageEnd.text.slice(scrollTopEnd);
marks.push({ name: 'scroll-top', into: 'partials/scroll-top.blade.php', text: scrollTop });

// --- write -------------------------------------------------------------------

const written = [];
function write(rel, body) {
  const dest = path.join(VIEWS, rel);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  const header = `{{-- Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js. --}}\n`;
  fs.writeFileSync(dest, header + body);
  written.push(`${rel} (${body.split('\n').length} lines)`);
}

for (const region of marks) {
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
write('partials/head.blade.php', tidy(toBlade(head)));

console.log('Wrote:');
for (const line of written) console.log('  ' + line);
