#!/usr/bin/env node
/**
 * Prepares the three hero product shots.
 *
 * The deck is six slides over three photographs — the template repeats
 * hero_6_1..3 twice — so these three cover the whole hero.
 *
 * Two things have to be normalised, and neither can be left to CSS.
 *
 * The shots arrive as cut-outs floating in transparent space, and each floats
 * by a different amount: measured, the supplied files are 48%, 26% and 40%
 * opaque. `.heroSlide6 .hero-img img` is `object-fit: contain`, which fits the
 * *canvas*, not the shoe inside it — so dropped in as supplied the same shoe
 * would render at a quarter of the size of another. Each is trimmed to its own
 * opaque bounding box first, which removes that variable entirely.
 *
 * Then they are laid on one common canvas. Same canvas for all three means the
 * CSS treats all three identically, so their relative sizes on the page are
 * exactly the ones set here rather than a side effect of how each was cropped.
 * The canvas keeps 984x696's aspect — the shot already on the page, which the
 * client has approved — so the hero's proportions do not move.
 *
 * Within the canvas each shoe is fitted by length, not by area: these are all
 * side profiles, and a high-top genuinely is taller than a low-top at the same
 * shoe length, so matching lengths is what makes them read as one set. All
 * three are wider than the canvas, so a plain contain-fit does exactly that.
 *
 * WebP rather than PNG. These are photographs that have to keep an alpha
 * channel, which is the one case PNG is worst at: measured on the Jordan,
 * full-colour PNG came to 1186KB, and quantising it to a palette to get that
 * down to 347KB cost a mean absolute error of 31 per channel — visible banding
 * across the sole. WebP at q88 is 103KB at an error of 1.98. A third of the
 * size of the quantised PNG and fifteen times closer to the source, so there
 * is no version of this where PNG is the right container.
 *
 * Run: node theme/make-hero-photos.js
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const SRC = path.resolve(__dirname, 'hero-src');
const OUT = path.resolve(__dirname, '../download-version/assets/img/hero');

// The aspect of the shot already on the page (984x696), at well over twice its
// pixel size so it still holds up on a 2x screen.
const W = 1400;
const H = 990;

// A little air inside the canvas, so no shoe touches its own edge — the sole
// and the toe are the extremes and they should not sit hard against the crop.
const INSET = 0.96;

// `cloudtilt-black` replaced `nb530` in the deck — «تو هیرو ها بیاد بجای
// نیوبالانس». The New Balance stays here and its file stays on disk: the deck
// is editable from `/admin/front-page`, so a shoe that leaves it may be put
// back without anybody re-running this.
// The first three are the hero deck's; `cloudtilt-black` replaced `nb530`
// there — «تو هیرو ها بیاد بجای نیوبالانس» — and the New Balance stays because
// the deck is editable from `/admin/front-page` and a shoe that leaves it may
// be put back.
//
// The four after it are the best sellers' row, which is six tiles the client
// supplied six pictures for: «اینام برای بخش پر فروشها، یدونه کم بهت دادم که
// از اون جردن صورتی استفاده کن». They are laid on the same canvas as the hero's
// because that row's tiles draw from the same files, so all of them read as
// one set rather than as two sets at two crops.
const SOURCES = [
  'jordan', 'goldengoose', 'nb530', 'cloudtilt-black',
  'nb530-white', 'airzoom-cream', 'goldengoose-black', 'jordan-travis',
  'jordan-chicago',
];

// Anything at or below this is background, not shoe. The cut-outs carry a
// feathered edge, so a strict `> 0` would keep a rim of near-invisible pixels
// and the trim would do nothing.
const ALPHA_FLOOR = 8;

async function contentBox(file) {
  const { data, info } = await sharp(file).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
  const { width, height, channels } = info;
  let minX = width, minY = height, maxX = -1, maxY = -1;
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      if (data[(y * width + x) * channels + 3] > ALPHA_FLOOR) {
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
      }
    }
  }
  if (maxX < 0) throw new Error(`${path.basename(file)} is fully transparent`);
  return { left: minX, top: minY, width: maxX - minX + 1, height: maxY - minY + 1 };
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  for (const name of SOURCES) {
    const src = path.join(SRC, `${name}.png`);
    if (!fs.existsSync(src)) throw new Error(`missing source: ${name}.png`);

    const box = await contentBox(src);

    // Contain: whichever of the two ratios is smaller is the one that binds.
    const scale = Math.min((W * INSET) / box.width, (H * INSET) / box.height);
    const w = Math.round(box.width * scale);
    const h = Math.round(box.height * scale);

    const shoe = await sharp(src).extract(box).resize(w, h).png().toBuffer();

    const dest = path.join(OUT, `vikyplus-hero-${name}.webp`);
    await sharp({
      create: { width: W, height: H, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } },
    })
      .composite([{ input: shoe, left: Math.round((W - w) / 2), top: Math.round((H - h) / 2) }])
      // alphaQuality 100: the cut-out's edge is the one thing that must not
      // soften, or the shoe grows a halo against the pane.
      .webp({ quality: 88, alphaQuality: 100, effort: 6 })
      .toFile(dest);

    console.log(
      `  ${name.padEnd(10)} content ${box.width}x${box.height} -> ${w}x${h} on ${W}x${H}` +
      `  ${Math.round(fs.statSync(dest).size / 1024)}KB`
    );
  }
})();
