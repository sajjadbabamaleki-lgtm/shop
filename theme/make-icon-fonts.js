#!/usr/bin/env node
/**
 * Cut the icon fonts down to the icons this shop actually draws.
 *
 * Run: node theme/make-icon-fonts.js
 * Needs: pip install fonttools brotlicffi   (pyftsubset on PATH)
 *
 * **Why this exists.** Measured on the home page at 390: the page is 4.41MB
 * over 62 requests, and 1,123KB of that is four icon fonts. The stylesheets
 * around them are large too, but they gzip eight to one — 1,505KB of CSS goes
 * over the wire as 215KB. A woff2 is already compressed, so gzip takes it from
 * 379KB to 379KB. **The icon fonts are both the largest thing on the wire and
 * the only large thing compression cannot help**, which is why they are what
 * this script touches and the CSS is not.
 *
 * The worst of them was being paid for nothing. `--icon-font` is
 * "Font Awesome 6 Pro", and that family's default weight is 300 — so every
 * `::before` in the base layer that did not name a weight pulled
 * `fa-light-300.woff2`, all 379KB of it, and the browser painted **one glyph**
 * out of it: the ✕ at U+F00D, which U+F00D in the 900 weight also draws.
 * Measured across nine pages at two widths, the whole site paints 27 glyphs.
 *
 * After: 1,123KB → 16KB. The four subsets carry 52 codepoints each, which is
 * every glyph the markup or either stylesheet can ask for — not the 27 that
 * happened to be on screen when it was measured. See «the keep set» below.
 *
 * **The originals stay** as `*.full.woff2`. If an icon ever goes missing, the
 * one-line diagnosis is to point the @font-face back at the full file; and
 * `IconFontTest` fails the build before it can get that far.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const FONTS = path.join(ROOT, 'download-version/assets/fonts/fontawesome');
const FA_CSS = path.join(ROOT, 'download-version/assets/css/fontawesome.min.css');
const FACES = ['fa-light-300', 'fa-regular-400', 'fa-solid-900', 'fa-brands-400'];

/** Class names that style an icon rather than name one. */
const NOT_GLYPHS = new Set([
  'solid', 'regular', 'light', 'brands', 'thin', 'duotone', 'sharp',
  'fw', '2x', '3x', '4x', '5x', 'lg', 'sm', 'xs', 'xl',
  'spin', 'spin-pulse', 'spin-reverse', 'beat', 'fade', 'flip', 'shake', 'bounce',
  'border', 'stack', 'stack-1x', 'stack-2x', 'inverse', 'pull-left', 'pull-right',
  'rotate-90', 'rotate-180', 'rotate-270', 'flip-horizontal', 'flip-vertical',
]);

/** Every file a person can put an icon in. */
function sources() {
  const out = [path.join(ROOT, 'download-version/shoe-shop-rtl.html')];
  const walk = (dir) => {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.name.endsWith('.blade.php')) out.push(p);
    }
  };
  walk(path.join(ROOT, 'storefront/resources/views'));
  return out;
}

/**
 * The keep set — derived from the source, never from a render.
 *
 * Measuring what a browser painted is how the *waste* was found, but it is the
 * wrong thing to build from: an icon on a page nobody thought to load would be
 * dropped and nothing would say so. So this is the union of everything that
 * *can* be asked for — every `fa-*` class written in a template or in the
 * preview page, and every `content: "\f…"` in either stylesheet, which is how
 * the base layer draws its carets and its radio dots without a class.
 */
function keepSet() {
  const css = fs.readFileSync(FA_CSS, 'utf8');

  // FontAwesome writes its aliases as one grouped selector, so read the group.
  const codepoint = new Map();
  for (const m of css.matchAll(/((?:\.fa-[a-z0-9-]+:before,?)+)\{content:"\\([0-9a-f]{1,6})"\}/g)) {
    for (const c of m[1].matchAll(/\.fa-([a-z0-9-]+):before/g)) {
      if (!codepoint.has(c[1])) codepoint.set(c[1], m[2]);
    }
  }

  const text = sources().map((p) => fs.readFileSync(p, 'utf8')).join('\n');
  const classes = new Set(
    [...text.matchAll(/\bfa-([a-z0-9-]+)/g)].map((m) => m[1]).filter((c) => !NOT_GLYPHS.has(c)),
  );

  const keep = new Set();
  const missing = [];
  for (const c of [...classes].sort()) {
    if (codepoint.has(c)) keep.add(codepoint.get(c));
    else missing.push(c);
  }

  // Loud, because a class with no codepoint is an icon that will not draw —
  // and it draws nothing today too, so this is a typo report as much as a
  // subsetting one.
  if (missing.length) {
    console.error(`  ! no codepoint for: ${missing.join(', ')}`);
    console.error('    Fix the class name, or add it to NOT_GLYPHS if it is a modifier.');
    process.exit(1);
  }

  for (const sheet of ['style.rtl.css', 'tweaks.css']) {
    const s = fs.readFileSync(path.join(ROOT, 'download-version/assets/css', sheet), 'utf8');
    for (const m of s.matchAll(/content:\s*["']\\([0-9a-fA-F]{1,6})/g)) keep.add(m[1].toLowerCase());
  }

  return { keep: [...keep].sort(), classes: classes.size };
}

function main() {
  const { keep, classes } = keepSet();
  console.log(`${classes} icon classes in the templates, ${keep.length} codepoints to keep.`);

  const unicodes = keep.map((c) => `U+${c}`).join(',');
  let before = 0;
  let after = 0;

  for (const face of FACES) {
    const live = path.join(FONTS, `${face}.woff2`);
    const full = path.join(FONTS, `${face}.full.woff2`);

    // Subset the original every time, not the last subset — running this twice
    // must not narrow the font twice.
    if (!fs.existsSync(full)) fs.copyFileSync(live, full);

    const was = fs.statSync(full).size;

    try {
      execFileSync('pyftsubset', [
        full,
        `--unicodes=${unicodes}`,
        '--flavor=woff2',
        '--layout-features=',
        `--output-file=${live}`,
      ], { stdio: ['ignore', 'ignore', 'pipe'] });
    } catch (e) {
      console.error('  ! pyftsubset failed. pip install fonttools brotlicffi');
      console.error(String(e.stderr || e.message).trim().split('\n').slice(-3).join('\n'));
      process.exit(1);
    }

    // **Put `head.flags` back the way the original had it.** The woff2 encoder
    // sets bit 11 («lossless transform applied»), which reads as metadata and
    // is not — Chromium rasterises a glyph a pixel differently with it on.
    // Found by diffing renders, not by reading the spec: with the bit restored
    // the home page went from differing to identical.
    execFileSync('python3', ['-c', `
import sys
from fontTools.ttLib import TTFont
full, live = sys.argv[1], sys.argv[2]
a, b = TTFont(full), TTFont(live)
if a['head'].flags != b['head'].flags:
    b['head'].flags = a['head'].flags
    b.flavor = 'woff2'
    b.save(live)
`, full, live], { stdio: ['ignore', 'ignore', 'pipe'] });

    const now = fs.statSync(live).size;
    before += was;
    after += now;
    console.log(`  ${face.padEnd(16)} ${(was / 1024).toFixed(0).padStart(4)}KB -> ${(now / 1024).toFixed(1).padStart(6)}KB`);
  }

  // **`block` is three seconds of invisible icons.** FontAwesome ships
  // `font-display: block`, which tells the browser to paint nothing where an
  // icon goes until its font arrives — the right call for 379KB on a fast
  // line, the wrong one for a shop reached through a filter-breaker, where
  // that wait is what «کند» looks like. At 4KB a face there is nothing left to
  // hide: swap paints immediately and corrects when the font lands, a
  // handful of milliseconds later.
  const css = fs.readFileSync(FA_CSS, 'utf8');
  const swapped = css.replace(/font-display:\s*block/g, 'font-display:swap');
  if (swapped !== css) {
    fs.writeFileSync(FA_CSS, swapped);
    console.log('  font-display: block -> swap');
  }

  // **The manifest is what stops an icon disappearing quietly.** A subset
  // font fails silently: add `fa-cart-plus` to a template without re-running
  // this, and the class is styled, the element is there, and the glyph is a
  // blank box. `IconFontTest` reads this file and fails the suite instead —
  // it cannot read a woff2, and it does not need to, because this is written
  // from the same cmap the subsetter was given.
  fs.writeFileSync(
    path.join(FONTS, 'subset.json'),
    JSON.stringify({ note: 'Generated by theme/make-icon-fonts.js. Do not edit.', codepoints: keep }, null, 2) + '\n',
  );

  console.log(`\n${(before / 1024).toFixed(0)}KB of icon font is now ${(after / 1024).toFixed(0)}KB.`);
  console.log('Run node theme/sync-storefront-assets.js to carry it into the app.');
}

main();
