#!/usr/bin/env node
/**
 * Generates the RTL stylesheets and copies the Persian webfont into the
 * template's asset tree.
 *
 * The Erna template ships LTR-only CSS with several thousand directional
 * declarations (float, margin-left, text-align, transform...), so the RTL
 * build is produced mechanically with rtlcss rather than by hand. The source
 * files under download-version/assets/css are never modified; every output is
 * written alongside them with a .rtl. infix.
 *
 * Run: node theme/build.js
 */
const fs = require('fs');
const path = require('path');
const rtlcss = require('rtlcss');

const ROOT = path.resolve(__dirname, '..');
const CSS_DIR = path.join(ROOT, 'download-version/assets/css');
const FONT_DIR = path.join(ROOT, 'download-version/assets/fonts/vazirmatn');

// Only the stylesheets that carry layout. Font and icon sheets are direction
// agnostic, and flipping them would rewrite glyph metrics for no benefit.
const SHEETS = [
  ['style.css', 'style.rtl.css'],
  ['bootstrap.min.css', 'bootstrap.rtl.min.css'],
  ['swiper-bundle.min.css', 'swiper-bundle.rtl.min.css'],
  ['magnific-popup.min.css', 'magnific-popup.rtl.min.css'],
];

function flip() {
  for (const [src, out] of SHEETS) {
    const from = path.join(CSS_DIR, src);
    if (!fs.existsSync(from)) {
      console.warn(`  skip ${src} (not found)`);
      continue;
    }
    const css = fs.readFileSync(from, 'utf8');
    const flipped = rtlcss.process(css, { autoRename: false, clean: false });
    fs.writeFileSync(path.join(CSS_DIR, out), flipped);
    const kb = (s) => `${Math.round(s / 1024)}KB`;
    console.log(`  ${src} -> ${out}  (${kb(css.length)} -> ${kb(flipped.length)})`);
  }
}

function copyFont() {
  fs.mkdirSync(FONT_DIR, { recursive: true });
  // The variable font covers every weight in one file, which keeps the
  // performance budget in section 9.1 intact.
  const src = path.join(__dirname, 'node_modules/vazirmatn/fonts/webfonts/Vazirmatn[wght].woff2');
  const dest = path.join(FONT_DIR, 'Vazirmatn[wght].woff2');
  fs.copyFileSync(src, dest);
  console.log(`  Vazirmatn variable -> assets/fonts/vazirmatn/ (${Math.round(fs.statSync(dest).size / 1024)}KB)`);
}

console.log('Building RTL stylesheets...');
flip();
console.log('Copying webfont...');
copyFont();
console.log('Done.');
