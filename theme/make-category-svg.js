#!/usr/bin/env node
/**
 * Draws the six category icons.
 *
 * Not traced from anything: each is built from a small number of straight runs
 * and arcs at one stroke weight, so the set reads as one family and stays
 * legible at the ~64px it is actually used at.
 *
 * The stroke colour is written into the files rather than left as
 * currentColor: these load through <img>, and an <img> renders the SVG as its
 * own document, which inherits nothing from the page.
 *
 * Run: node theme/make-category-svg.js
 */
const fs = require('fs');
const path = require('path');

const OUT = path.resolve(__dirname, '../download-version/assets/img/category');

const ICONS = {
  // Handbag: trapezoid body, arc handle, and a clasp so it cannot read as a
  // plain bucket.
  'bag-set': `
    <path d="M11 18h26l-2.4 19.5a2.5 2.5 0 0 1-2.5 2.2H15.9a2.5 2.5 0 0 1-2.5-2.2Z"/>
    <path d="M18.5 18v-3.5a5.5 5.5 0 0 1 11 0V18"/>
    <path d="M24 25.5v3.5"/>`,

  // Ankle boot: straight shaft, instep, and a small heel block under the back.
  boot: `
    <path d="M18 9h8.5v15.5c0 2.3 1.3 3.4 3.6 4.4l4.2 1.8a3 3 0 0 1 1.8 2.8v2.2a2 2 0 0 1-2 2H18a3 3 0 0 1-3-3V12a3 3 0 0 1 3-3Z"/>
    <path d="M15 31.5h6"/>`,

  // Sandal: flat sole, a V of straps rising from one point on it, and the band
  // across the instep. The first attempt crossed two straps in an X and read
  // as a scribble — one origin point is what makes it a sandal.
  sandal: `
    <path d="M10 30h28a4.5 4.5 0 0 1 0 9H10a4.5 4.5 0 0 1 0-9Z"/>
    <path d="m22.5 30-7.5-11"/>
    <path d="m22.5 30 7.5-11"/>
    <path d="M15 19c3 2.4 12 2.4 15 0"/>`,

  // Loafer: low side profile with the saddle band that names it.
  college: `
    <path d="M10 32.5v-8.5c0-1.2 1-2.2 2.2-2.2h3.6c1 0 1.9.6 2.2 1.6l1 2.8c.5 1.4 1.7 2.4 3.2 2.7l10.6 2.1c2.4.5 4.2 2.6 4.2 5.1v1.4a2 2 0 0 1-2 2H13a3 3 0 0 1-3-3Z"/>
    <path d="M18.5 26.5c2.6 1.6 6.4 1.9 9.5.6"/>`,

  // Sneaker: the same profile with a thicker sole, a toe cap and laces.
  sneaker: `
    <path d="M9 31v-7.5c0-1.2 1-2.2 2.2-2.2h3.3c1 0 1.9.6 2.3 1.5l1.4 3.2c.6 1.4 1.9 2.4 3.4 2.7l11.4 2.2c2.5.5 4.3 2.7 4.3 5.3v1.1a2 2 0 0 1-2 2H12a3 3 0 0 1-3-3Z"/>
    <path d="M9 34.5h29.3"/>
    <path d="M17 24.5 21 28"/>
    <path d="M20 22.5 24.5 26.5"/>`,

  // Court shoe: pointed toe, deep throat, and the thin heel that carries it.
  // Drawn larger than the first pass, which sat small and thin next to the
  // rest of the set.
  majlesi: `
    <path d="M7 38.5c8-.6 14.2-3.2 18.6-7.7 3.6-3.7 7.4-8.4 13.4-9.3v7.2c0 3-2 5.3-5 6.2"/>
    <path d="M7 38.5h12.5"/>
    <path d="M33.5 34.6c-.6 3.2.5 3.9 3.4 3.9H39"/>`,
};

const SVG = (body) =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" ` +
  `stroke="#A47F25" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">` +
  body.trim().replace(/\n\s*/g, '') +
  `</svg>\n`;

fs.mkdirSync(OUT, { recursive: true });
for (const [name, body] of Object.entries(ICONS)) {
  const file = path.join(OUT, `${name}.svg`);
  fs.writeFileSync(file, SVG(body));
  console.log(`  ${name}.svg  ${fs.statSync(file).size}B`);
}
