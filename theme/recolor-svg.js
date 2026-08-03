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

// The template's two reds and its gradient orange, mapped onto the gold
// sampled from the brand's chart bars. Fills take the bar's midpoint; the
// orange, which only ever appears as a gradient stop, takes the light end.
const MAP = {
  '#FF0004': '#A47F25',
  '#E42E3B': '#A47F25',
  '#FD8900': '#CA9A24',
};

const page = fs.readFileSync(path.join(SITE, 'shoe-shop.html'), 'utf8');
const referenced = new Set(
  [...page.matchAll(/(?:src|href)="(assets\/img\/[^"]+\.svg)"/g)].map((m) => m[1])
);

const rewritten = [];
for (const rel of [...referenced].sort()) {
  const abs = path.join(SITE, rel);
  if (!fs.existsSync(abs)) continue;

  const svg = fs.readFileSync(abs, 'utf8');
  let out = svg;
  for (const [from, to] of Object.entries(MAP)) {
    out = out.replace(new RegExp(from, 'gi'), to);
  }
  if (out === svg) continue;

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
