#!/usr/bin/env node
/**
 * Swaps the template's header and hero for the replacements in theme/partials.
 *
 * Palette and geometry can be overridden from a stylesheet, but these two are
 * markup: a three-band header and a carousel of rounded pastel cards read as a
 * stock theme no matter how they are coloured. This step replaces the markup
 * itself, which is also why the partials are written as standalone fragments —
 * they port to Blade unchanged.
 *
 * Run: node theme/restructure.js <theme> [source.html]
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const SITE = path.join(ROOT, 'download-version');

const theme = process.argv[2] || 'minimal';
const src = process.argv[3] || path.join(SITE, `shoe-shop-${theme}.html`);
const out = path.join(SITE, `shoe-shop-${theme}-v2.html`);

let html = fs.readFileSync(src, 'utf8');

const partial = (name) =>
  fs.readFileSync(path.join(__dirname, 'partials', `${name}.html`), 'utf8');

/** Replaces the region between an opening tag matching `startRe` and its matching close. */
function replaceBlock(source, startRe, tag, replacement, label) {
  const m = source.match(startRe);
  if (!m) throw new Error(`${label}: opening tag not found`);

  const open = new RegExp(`<${tag}\\b`, 'g');
  const close = new RegExp(`</${tag}>`, 'g');
  let depth = 0;
  let i = m.index;
  const end = (() => {
    while (i < source.length) {
      open.lastIndex = close.lastIndex = i;
      const o = open.exec(source);
      const c = close.exec(source);
      if (!c) return -1;
      if (o && o.index < c.index) { depth++; i = o.index + 1; continue; }
      depth--;
      i = c.index + 1;
      if (depth === 0) return c.index + `</${tag}>`.length;
    }
    return -1;
  })();

  if (end < 0) throw new Error(`${label}: matching </${tag}> not found`);
  console.log(`  ${label}: replaced ${(end - m.index).toLocaleString()} chars`);
  return source.slice(0, m.index) + replacement + source.slice(end);
}

// The stock header carries its own sticky wrapper and mega-menu panels, so the
// whole element goes rather than being edited in place.
html = replaceBlock(html, /<header\b[^>]*class="[^"]*th-header/, 'header', partial('header'), 'header');

// The hero wrapper contains the Swiper instance; removing it also removes the
// slider, which the specification did not ask for.
html = replaceBlock(html, /<div class="th-hero-wrapper[^"]*"/, 'div', partial('hero'), 'hero');

// Structure loads last so it wins over the theme's own header rules.
html = html.replace(
  /(<link rel="stylesheet" href="assets\/css\/rtl-fixes\.css">)/,
  '$1\n    <link rel="stylesheet" href="assets/css/structure.css">'
);

fs.writeFileSync(out, html);
console.log(`wrote ${path.relative(ROOT, out)}`);
