#!/usr/bin/env node
/**
 * Derives gold copies of the template's red SVG icons.
 *
 * The accent variable reaches everything the template drew in CSS, but not the
 * icons: those carry the red inside the file. Rather than editing the
 * template's own assets, each affected icon gets a `-gold` sibling and the page
 * builder rewrites the reference — so the originals stay diffable against the
 * shipped template.
 *
 * Run: node theme/recolor-svg.js
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const SITE = path.join(ROOT, 'download-version');

// The template's two reds and its gradient orange all become the bar gradient
// itself: a `linearGradient` def is injected per file and the fills point at
// it. Each icon is loaded through <img>, so the def is scoped to its own
// document and the fixed id cannot collide.
const GRADIENT_ID = 'vp-gold';
const DEF = `<defs><linearGradient id="${GRADIENT_ID}" x1="0" y1="0" x2="0" y2="1">` +
  `<stop offset="0" stop-color="#7D6324"/><stop offset="1" stop-color="#CE9E29"/>` +
  `</linearGradient></defs>`;

const REDS = /#(?:FF0004|E42E3B|FD8900)/gi;
const FILL = `url(#${GRADIENT_ID})`;

const page = fs.readFileSync(path.join(SITE, 'shoe-shop.html'), 'utf8');
const referenced = new Set(
  [...page.matchAll(/(?:src|href)="(assets\/img\/[^"]+\.svg)"/g)].map((m) => m[1])
);

const rewritten = [];
for (const rel of [...referenced].sort()) {
  const abs = path.join(SITE, rel);
  if (!fs.existsSync(abs)) continue;

  const svg = fs.readFileSync(abs, 'utf8');
  if (!REDS.test(svg)) continue;
  REDS.lastIndex = 0;

  // The gradient is in user-space-on-use terms once x1/y1/x2/y2 are fractions
  // of the object's bounding box, which is the SVG default — so each shape
  // gets the full run of the bar regardless of the icon's viewBox.
  const out = svg
    .replace(REDS, FILL)
    .replace(/(<svg\b[^>]*>)/, `$1${DEF}`);

  const goldRel = rel.replace(/\.svg$/, '-gold.svg');
  fs.writeFileSync(path.join(SITE, goldRel), out);
  rewritten.push([rel, goldRel]);
}

fs.writeFileSync(
  path.join(__dirname, 'svg-gold-map.json'),
  JSON.stringify(Object.fromEntries(rewritten), null, 2) + '\n'
);

for (const [from, to] of rewritten) console.log(`  ${from} -> ${to}`);
console.log(`${rewritten.length} icons recoloured`);
