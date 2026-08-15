#!/usr/bin/env node
/**
 * Prepares the brand strip's own photographs.
 *
 * Until now every tile in «برندهای موجود» borrowed the eight category
 * photographs from the top of the page, because we held one product shot per
 * brand and each tile wants three. Nike's three arrived, so Nike's tile stops
 * borrowing; the other three still do, and the moment their photographs arrive
 * they get an entry here and a `photos` key in the two places listed below.
 *
 * Same rule as the category photographs: **resize only, no crop.** Each file
 * goes in as supplied and the framing stays where it belongs, in the CSS —
 * `.vp-brand-cell img` is `object-fit: cover`, so the cell decides what is
 * shown and a build that also cropped would be cropping twice.
 *
 * The widths are set per cell rather than one size for all three, because the
 * mosaic's cells are not the same size or the same shape. Measured at 1440,
 * where a tile is 324x243: the lead is 183x243 and each small cell is
 * 135x119. Widest they ever get is the one-column layout under 576, where a
 * tile is about 500 across — lead 282, small 210. These are set at well over
 * twice that, so both still hold up on a 2x screen.
 *
 * WebP rather than JPEG, and this one is measured rather than inherited. The
 * three photographs are dark teal gradients over most of their area, which is
 * what JPEG is worst at, so the encoder was compared against the resized
 * source at the sizes actually shipped (mean absolute error per channel):
 *
 *              jpeg q86          webp q88
 *   vomero     34KB  MAE 1.51    28KB  MAE 1.37
 *   kit        28KB  MAE 2.02    25KB  MAE 1.75
 *   athlete    21KB  MAE 1.56    17KB  MAE 1.49
 *
 * WebP is smaller *and* closer to the source on all three, so there is nothing
 * to trade off. The hero shots are already WebP, so this adds no new format to
 * the page.
 *
 * The sources live in theme/brand-src rather than under the site's assets
 * because they are build input, not anything the site serves.
 *
 * Run: node theme/make-brand-photos.js
 *
 * After it, the page and the app have to be told the files exist:
 *   node theme/make-rtl-page.js          (BRANDS, `photos`)
 *   node theme/sync-storefront-assets.js (copies what the page names)
 * and `storefront/config/storefront.php`'s `placeholders.brand_strip` carries
 * the same paths for the Laravel side.
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const SRC = path.resolve(__dirname, 'brand-src');
const OUT = path.resolve(__dirname, '../download-version/assets/img/brand');

// `lead` is the tall cell that spans both rows; `small` is one of the stacked
// pair. Height is never given — resizing by width alone is what keeps this a
// straight resize.
const WIDTH = { lead: 600, small: 480 };

// Named for what is in them, not for where they sit, so a re-ordering is a
// change to the two lists that name them and not to a filename.
const SOURCES = [
  { name: 'nike-vomero', cell: 'lead' },
  { name: 'nike-kit', cell: 'small' },
  { name: 'nike-athlete', cell: 'small' },
];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  for (const { name, cell } of SOURCES) {
    const src = path.join(SRC, `${name}.png`);
    if (!fs.existsSync(src)) throw new Error(`missing source: ${name}.png`);

    const m = await sharp(src).metadata();
    const width = WIDTH[cell];

    // Enlarging a photograph past its own resolution is not a resize, it is an
    // invention — so it stops rather than shipping something soft.
    if (m.width < width) {
      throw new Error(`${name}.png is ${m.width}px wide; the ${cell} cell wants ${width}`);
    }

    const dest = path.join(OUT, `vikyplus-${name}.webp`);
    const info = await sharp(src).resize(width).webp({ quality: 88, effort: 6 }).toFile(dest);

    console.log(
      `  ${name.padEnd(13)} ${cell.padEnd(5)} ${m.width}x${m.height} -> ${info.width}x${info.height}` +
      `  ${Math.round(fs.statSync(dest).size / 1024)}KB`
    );
  }
})();
