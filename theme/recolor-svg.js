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

// Icons this page uses in gold that the sweep below cannot find.
//
// That sweep reads the *template's* own shoe-shop.html and recolours whatever
// it references in one of the three template reds. The trust row's badges are
// neither: they were picked out of the template's wider icon set rather than
// off that page, and they carry #FD5B44 and #0077FF. Their gold siblings were
// made once, by hand, and for a long time nothing could rebuild them — five
// files in the repo that no command produced, which is the state this list
// exists to end. (The sixth badge's mark is a FontAwesome glyph, not a file,
// so it is not here — see the trust row in theme/make-rtl-page.js.)
//
// Each entry is a source icon and the one colour it is drawn in. The colour is
// named rather than detected because these are single-colour glyphs and a
// blanket "recolour every hex" would take the whites and the ink out of icons
// that need them.
//
// Re-running this rewrites the five that were already here byte for byte —
// that is the check that this list describes what was done by hand rather than
// something near it.
const EXTRAS = [
  ['assets/img/icon/feature_card_1.svg', '#FD5B44'],
  ['assets/img/icon/feature_card_2.svg', '#FD5B44'],
  ['assets/img/icon/feature_card_4.svg', '#FD5B44'],
  ['assets/img/icon/secure.svg', '#FD5B44'],
  ['assets/img/icon/check2.svg', '#0077FF'],
];

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

// The trust row's files, by name and by their own colour. Same gradient, same
// injected def, same `-gold` sibling — only the colour being replaced differs.
for (const [rel, colour] of EXTRAS) {
  const abs = path.join(SITE, rel);
  if (!fs.existsSync(abs)) {
    console.error(`  MISSING ${rel}`);
    process.exitCode = 1;
    continue;
  }

  const svg = fs.readFileSync(abs, 'utf8');
  const source = new RegExp(colour.replace('#', '#'), 'gi');
  if (!source.test(svg)) {
    console.error(`  ${rel} does not carry ${colour} — the list is out of date`);
    process.exitCode = 1;
    continue;
  }
  source.lastIndex = 0;

  const out = svg
    .replace(source, FILL)
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
