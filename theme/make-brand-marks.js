#!/usr/bin/env node
/**
 * Prepares the brand strip's plate marks.
 *
 * The plate is white glass and the mark on it is ink, the way Nike's swoosh
 * already was. Everything below therefore ends the same way — coverage painted
 * in `#101111`, the page's own ink, the same value the tile's name is set in —
 * and differs only in where that coverage is read from, because the three
 * marks arrived in three different states:
 *
 *   jordan  `alpha`          a PNG that already carries its own cut-out. Its
 *                            alpha channel *is* the coverage; nothing has to
 *                            be separated from anything.
 *   nb      `dark-on-light`  black on white, opaque, no alpha. Coverage is how
 *                            dark each pixel is.
 *   on      `light-on-dark`  no logo file at all — the mark had to come out of
 *                            the poster the client sent for that tile, where
 *                            it is white on a rose gradient.
 *
 * The cut is a ramp between two luminances rather than a threshold, so the
 * glyph keeps the soft edge it arrived with instead of growing a staircase. On
 * is the case that needed the ramp measured: across its mark's neighbourhood
 * the poster's ground runs 130 to 184 while the glyph sits at 254, and between
 * 190 and 240 there is almost nothing but the glyph's own antialiased edge. A
 * flat file needs no such care, but the same ramp costs it nothing.
 *
 * On also needs to be *found*, which the other two do not: it is a mark inside
 * a poster rather than a file of its own. It is the only near-white thing in
 * the bottom fifth — the shoe and the RUNNING wordmark are both well above it —
 * so the search is bounded by `from` and the box within that is measured.
 *
 * Everything is trimmed to its own content at the end. The slot is a fixed
 * 36x36 `object-fit: contain` box (see `.vp-brand-plate .vp-brand-logo`), so
 * whatever margin a file arrived with would otherwise shrink the mark inside
 * it, and by a different amount for each of the four.
 *
 * Nike's mark is not in here. `brand_5_2.png` came with the template, is
 * genuinely the swoosh, and is already ink on transparent — there is nothing
 * for this to do to it.
 *
 * Run: node theme/make-brand-marks.js
 * Then `node theme/sync-storefront-assets.js`, and the paths go in
 * CatalogueSeeder's BRANDS and in make-rtl-page.js's BRANDS.
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const SRC = path.resolve(__dirname, 'brand-src');
const OUT = path.resolve(__dirname, '../download-version/assets/img/brand');

// The page's ink, as `.vp-brand-name` is set in.
const INK = { r: 0x10, g: 0x11, b: 0x11 };

// Anything at or below this is margin, not mark. The cuts leave a feathered
// edge, so a strict `> 0` would keep a rim of near-invisible pixels and the
// trim would do nothing.
const ALPHA_FLOOR = 8;

const MARKS = [
  { name: 'jordan', source: 'jordan-mark.png', cut: 'alpha' },
  { name: 'nb', source: 'nb-mark.jpg', cut: 'dark-on-light', floor: 40, ceiling: 200 },
  { name: 'on', source: 'on-running.jpg', cut: 'light-on-dark', floor: 195, ceiling: 240, from: 0.82 },
];

/**
 * Where the mark is, when it is a mark inside a photograph. Bright and
 * near-neutral: the poster's ground is bright too but strongly warm, so the
 * red-to-blue spread is what separates them, not brightness.
 */
function findLightMark(data, info, fromRow) {
  const { width, height, channels } = info;
  let minX = width, minY = height, maxX = -1, maxY = -1;

  for (let y = fromRow; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const i = (y * width + x) * channels;
      const [r, g, b] = [data[i], data[i + 1], data[i + 2]];
      if (r > 225 && g > 215 && b > 210 && r - b < 28) {
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
      }
    }
  }

  if (maxX < 0) throw new Error('no mark found in the search band');
  return { left: minX, top: minY, width: maxX - minX + 1, height: maxY - minY + 1 };
}

/** The content box of an RGBA buffer, by alpha. */
function contentBox(rgba, width, height) {
  let minX = width, minY = height, maxX = -1, maxY = -1;

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      if (rgba[(y * width + x) * 4 + 3] > ALPHA_FLOOR) {
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
      }
    }
  }

  if (maxX < 0) throw new Error('the mark came out empty');
  return { left: minX, top: minY, width: maxX - minX + 1, height: maxY - minY + 1 };
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  for (const mark of MARKS) {
    const src = path.join(SRC, mark.source);
    if (!fs.existsSync(src)) throw new Error(`missing source: ${mark.source}`);

    let pipeline = sharp(src);

    // The one mark that has to be located before it can be read.
    if (mark.cut === 'light-on-dark') {
      const whole = await sharp(src).raw().toBuffer({ resolveWithObject: true });
      pipeline = pipeline.extract(
        findLightMark(whole.data, whole.info, Math.round(whole.info.height * mark.from))
      );
    }

    const { data, info } = await pipeline.ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    const { width, height, channels } = info;

    const out = Buffer.alloc(width * height * 4);

    for (let p = 0; p < width * height; p++) {
      const i = p * channels;
      const lum = 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];

      let coverage;
      if (mark.cut === 'alpha') {
        coverage = data[i + 3] / 255;
      } else {
        const span = mark.ceiling - mark.floor;
        const t = mark.cut === 'light-on-dark'
          ? (lum - mark.floor) / span
          : (mark.ceiling - lum) / span;
        // A file that carries alpha as well as tone is honoured on both: a
        // transparent margin cannot become mark however dark its dead pixels
        // happen to be.
        coverage = Math.max(0, Math.min(1, t)) * (data[i + 3] / 255);
      }

      out[p * 4] = INK.r;
      out[p * 4 + 1] = INK.g;
      out[p * 4 + 2] = INK.b;
      out[p * 4 + 3] = Math.round(coverage * 255);
    }

    const box = contentBox(out, width, height);
    const dest = path.join(OUT, `vikyplus-${mark.name}.png`);

    await sharp(out, { raw: { width, height, channels: 4 } })
      .extract(box)
      .png()
      .toFile(dest);

    console.log(
      `  ${mark.name.padEnd(7)} ${mark.cut.padEnd(14)} ${mark.source.padEnd(17)}` +
      ` ${width}x${height} -> ${box.width}x${box.height}` +
      `  ${Math.round(fs.statSync(dest).size / 1024)}KB`
    );
  }
})();
