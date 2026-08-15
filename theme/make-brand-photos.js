#!/usr/bin/env node
/**
 * Prepares the brand strip's own photographs.
 *
 * Until recently every tile in «برندهای موجود» borrowed the eight category
 * photographs from the top of the page, because we held one product shot per
 * brand and each tile wants three. The client has now sent a set of three for
 * each of the four, so nothing on that block borrows any more. A brand that
 * ever needs a set again gets three entries here and a `photos` key in the two
 * places listed at the bottom.
 *
 * Same rule as the category photographs: **resize only, no crop.** Each file
 * goes in as supplied and the framing stays where it belongs, in the CSS —
 * `.vp-brand-cell img` is `object-fit: cover`, so the cell decides what is
 * shown and a build that also cropped would be cropping twice.
 *
 * The size is computed rather than typed, because the two cells are different
 * shapes and the sources are not all the same shape either. A square shot in
 * the tall lead cell is bound by its *height*; a 4:5 poster in the same cell
 * is bound by its width. So each file is scaled to the smallest size that
 * still covers its cell — the same arithmetic `object-fit: cover` does — and
 * everything else falls out of that:
 *
 *     scale = max(cell width / source width, cell height / source height)
 *
 * The cell sizes below are measured off the page at 1920, which is the widest
 * anything in this repo checks (`check-parity.js` and `check-overflow.js` both
 * stop there), and doubled so they still hold up on a 2x screen. The tiles do
 * go on growing past 1920 — the row is fluid and has no max width — so this is
 * a stated ceiling rather than a guarantee; at 2560 a lead cell is 344 wide and
 * a 2x screen there would be reading these at about 1.5x.
 *
 * WebP rather than JPEG, and this one is measured rather than inherited. The
 * photographs are dark saturated gradients over most of their area, which is
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

// What each cell measures at 1920, doubled. `lead` is the tall cell that spans
// both rows; `small` is one of the stacked pair.
const CELL = {
  lead: { width: 520, height: 680 },
  small: { width: 384, height: 336 },
};

// Named for what is in them, not for where they sit, so a re-ordering is a
// change to the lists that name them and not to a filename. The order within
// each brand is the order on the tile: lead first, then the stacked pair.
const SOURCES = [
  { name: 'nike-vomero', cell: 'lead' },
  { name: 'nike-kit', cell: 'small' },
  { name: 'nike-athlete', cell: 'small' },
  { name: 'jordan-one', cell: 'lead' },
  { name: 'jordan-kit', cell: 'small' },
  { name: 'jordan-athlete', cell: 'small' },
  { name: 'nb-530', cell: 'lead' },
  { name: 'nb-kit', cell: 'small' },
  { name: 'nb-athlete', cell: 'small' },
  // On's poster is the one source that is not a PNG — it arrived as a JPEG,
  // and re-encoding it to PNG first would only have made a larger file of the
  // same pixels. It is also where On's mark comes from; see make-brand-marks.js.
  { name: 'on-running', cell: 'lead', file: 'on-running.jpg' },
  { name: 'on-kit', cell: 'small' },
  { name: 'on-athlete', cell: 'small' },
];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  for (const { name, cell, file } of SOURCES) {
    const source = file ?? `${name}.png`;
    const src = path.join(SRC, source);
    if (!fs.existsSync(src)) throw new Error(`missing source: ${source}`);

    const m = await sharp(src).metadata();
    const box = CELL[cell];
    const scale = Math.max(box.width / m.width, box.height / m.height);

    // Enlarging a photograph past its own resolution is not a resize, it is an
    // invention — so it stops rather than shipping something soft.
    if (scale > 1) {
      throw new Error(
        `${name}.png is ${m.width}x${m.height}; covering the ${cell} cell at 2x wants ${box.width}x${box.height}`
      );
    }

    const width = Math.round(m.width * scale);

    const dest = path.join(OUT, `vikyplus-${name}.webp`);
    const info = await sharp(src).resize(width).webp({ quality: 88, effort: 6 }).toFile(dest);

    console.log(
      `  ${name.padEnd(15)} ${cell.padEnd(5)} ${m.width}x${m.height} -> ${info.width}x${info.height}` +
      `  ${Math.round(fs.statSync(dest).size / 1024)}KB`
    );
  }
})();
