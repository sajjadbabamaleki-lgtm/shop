#!/usr/bin/env node
/**
 * The واتساپ, تلگرام and اینستاگرام marks, as SVG cut from the icon font.
 *
 * **Why they stopped being font glyphs.** The row's five tiles were three
 * glyphs and two images, and the glyphs would not stay centred: a glyph sits on
 * a baseline inside a line box, and `line-height: 1` makes that box shorter
 * than the font's own metrics, so where the ink lands is left to the engine's
 * rounding. Measured on the same build, Chromium put them exactly on the tile's
 * centre and WebKit put them 1.0–1.2px above it — one number could only be
 * right on one of them, which is two rounds of «وسط نیست» and no way to end it.
 * «همون کار درستو بکن، تصویرشون کن».
 *
 * **The shapes are the ones already on the page**, not new artwork: the glyphs
 * are lifted out of `fa-brands-400.woff2`, the file the browser is already
 * drawing them from. Nothing is redrawn and nothing is fetched.
 *
 * **The viewBox is the ink's own bounding box.** That is the whole point: the
 * file's edges *are* the mark's edges, so centring the image centres the mark,
 * on every engine, with no baseline anywhere in it.
 *
 * White, because the three tiles they sit on are the services' own colours and
 * the mark is knocked out of them.
 *
 * Run: node theme/make-brand-marks.js
 */
const fs = require('fs');
const path = require('path');
const opentype = require(path.join(__dirname, 'node_modules/opentype.js'));
const wawoff2 = require(path.join(__dirname, 'node_modules/wawoff2'));

const FONT = path.join(__dirname, '..', 'download-version', 'assets', 'fonts', 'fontawesome', 'fa-brands-400.woff2');
const OUT = path.join(__dirname, '..', 'download-version', 'assets', 'img', 'social');

/** The three, by the code points fontawesome.min.css puts in `content`. */
const MARKS = [
  ['whatsapp', 0xf232],
  ['telegram', 0xf2c6],
  ['instagram', 0xf16d],
];

/** Two decimals is a twentieth of a pixel at the size these are drawn. */
const round = (n) => Math.round(n * 100) / 100;

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  // opentype.js reads sfnt, not woff2, so the file is decompressed in memory.
  // The font itself is not touched — this only ever reads it.
  const sfnt = await wawoff2.decompress(fs.readFileSync(FONT));
  const font = opentype.parse(Uint8Array.from(sfnt).buffer);

  for (const [name, code] of MARKS) {
    const glyph = font.charToGlyph(String.fromCodePoint(code));

    if (!glyph || glyph.index === 0) {
      throw new Error(`${name} (U+${code.toString(16)}) is not in ${path.basename(FONT)}.`);
    }

    // Drawn at the font's own em, y flipped, so the path is in SVG's
    // coordinates rather than the font's.
    const em = font.unitsPerEm;
    const p = glyph.getPath(0, em, em);
    const box = p.getBoundingBox();

    const width = round(box.x2 - box.x1);
    const height = round(box.y2 - box.y1);

    const svg = [
      `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${round(box.x1)} ${round(box.y1)} ${width} ${height}">`,
      `<path fill="#FFFFFF" d="${p.toPathData(2)}"/>`,
      '</svg>',
      '',
    ].join('');

    fs.writeFileSync(path.join(OUT, `${name}.svg`), svg);

    console.log(`wrote assets/img/social/${name}.svg (ink ${width}×${height} of ${em} em)`);
  }
})();
