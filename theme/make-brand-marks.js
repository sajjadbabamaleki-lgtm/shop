#!/usr/bin/env node
/**
 * Lifts On's mark off the poster the client supplied, and inks it.
 *
 * Every other mark on the brand strip's plates is either the template's own
 * abstract placeholder or, in Nike's case, the real thing. On had neither: the
 * brand exists in the catalogue (it sells the daily deal) but its `logo_path`
 * was null, and the plate renders `asset(logo_path)` — which for null resolves
 * to the site root and draws a broken image. So the tile needed a mark before
 * it could go on the page at all.
 *
 * The mark is in the artwork the client sent. `on-running.jpg` carries it
 * below the shoe, and taking it from there is better than dressing the tile in
 * one of the template's abstract marks: the three photographs *on that tile*
 * show the real logo, so an unrelated shape on the plate beside them reads as
 * a mistake rather than as a placeholder.
 *
 * Two things have to happen to it and neither can be left to CSS.
 *
 * It has to be cut out. The mark is white on the poster's rose ground, which
 * is a gradient — measured across the mark's neighbourhood the ground runs 130
 * to 184 in luminance while the glyph sits at 254, and between 190 and 240
 * there is almost nothing but the glyph's own antialiased edge. So the cut is
 * a ramp across that empty band rather than a hard threshold: coverage is
 * `(luminance - 195) / 45`, clamped, which keeps the edge soft in exactly the
 * way the original is and does not pick up the ground anywhere.
 *
 * And it has to change colour. The plate is white glass and the mark on it has
 * to be ink, the way Nike's swoosh already is; a white mark on white glass is
 * an empty slot. The cut-out is coverage, not colour, so this paints #101111 —
 * the page's ink, the same value the tile's name is set in — through it.
 *
 * The slot is a fixed 36x36 `object-fit: contain` box (see `.vp-brand-logo`),
 * so the mark's own proportions are kept and only its height binds. At 2x that
 * asks for 72px of height; the mark is 104 on the poster, which is why this can
 * be a straight trim with no resampling at all.
 *
 * Run: node theme/make-brand-marks.js
 * Then `node theme/sync-storefront-assets.js`, and the path goes in
 * CatalogueSeeder's BRANDS.
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const SRC = path.resolve(__dirname, 'brand-src');
const OUT = path.resolve(__dirname, '../download-version/assets/img/brand');

// The page's ink, as `.vp-brand-name` is set in.
const INK = { r: 0x10, g: 0x11, b: 0x11 };

// Luminance floor and ceiling of the cut. Below the floor is ground, above the
// ceiling is glyph, and the ramp between them is the mark's own soft edge.
const FLOOR = 195;
const CEILING = 240;

// Where in the poster to look. The mark is the only near-white thing in the
// bottom fifth — the shoe and the wordmark are both well above it — so the
// search is bounded rather than aimed, and the exact box is measured.
const SEARCH_FROM = 0.82;

const MARKS = [{ name: 'on', source: 'on-running.jpg' }];

/** The near-white glyph's bounding box within the search band. */
function markBox(data, info, fromRow) {
  const { width, height, channels } = info;
  let minX = width, minY = height, maxX = -1, maxY = -1;

  for (let y = fromRow; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const i = (y * width + x) * channels;
      const [r, g, b] = [data[i], data[i + 1], data[i + 2]];
      // Bright and near-neutral. The ground is bright too but strongly warm,
      // so the red-to-blue spread is what separates them, not brightness.
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

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  for (const { name, source } of MARKS) {
    const src = path.join(SRC, source);
    if (!fs.existsSync(src)) throw new Error(`missing source: ${source}`);

    const whole = await sharp(src).raw().toBuffer({ resolveWithObject: true });
    const box = markBox(whole.data, whole.info, Math.round(whole.info.height * SEARCH_FROM));

    const { data, info } = await sharp(src).extract(box).raw().toBuffer({ resolveWithObject: true });
    const { width, height, channels } = info;

    const out = Buffer.alloc(width * height * 4);
    let lit = 0;

    for (let p = 0; p < width * height; p++) {
      const i = p * channels;
      const lum = 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
      const alpha = Math.max(0, Math.min(1, (lum - FLOOR) / (CEILING - FLOOR)));
      out[p * 4] = INK.r;
      out[p * 4 + 1] = INK.g;
      out[p * 4 + 2] = INK.b;
      out[p * 4 + 3] = Math.round(alpha * 255);
      if (alpha > 0.5) lit++;
    }

    const dest = path.join(OUT, `vikyplus-${name}.png`);
    await sharp(out, { raw: { width, height, channels: 4 } }).png().toFile(dest);

    console.log(
      `  ${name.padEnd(4)} from ${source} at ${box.left},${box.top} -> ${width}x${height}` +
      `  ${((lit / (width * height)) * 100).toFixed(0)}% covered  ${Math.round(fs.statSync(dest).size / 1024)}KB`
    );
  }
})();
