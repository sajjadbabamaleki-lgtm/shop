#!/usr/bin/env node
/**
 * The «فروش رفت» stamp, with its words filled in.
 *
 * Run: node theme/make-sold-out-stamp.js
 *
 * The client's artwork is a gold stamp on transparency: a ring reading SOLD
 * OUT twice, and a slab across it with «فروش رفت» **knocked out of the gold**
 * rather than drawn on it. On paper that is how a rubber stamp works — the
 * words are the paper showing through. On a card whose photograph has been
 * blurred behind it, the words are the blurred photograph showing through, and
 * they read as a smear rather than as words.
 *
 * «فروش رفت هم سفید بشه فقط دو کلمه فروش رفت» — so the holes get filled, and
 * only the holes.
 *
 * **How the holes are told apart from the background.** Both are transparent
 * and nothing about a pixel says which it is; what separates them is whether
 * you can walk to the edge of the canvas without crossing gold. So the
 * transparent pixels reachable from the border are flooded first, and every
 * transparent pixel left over is enclosed by the mark — the counters of the
 * two words, and the insides of the O and the D in SOLD OUT, which are white
 * on a white card and invisible either way.
 *
 * That leaves the gold exactly as supplied: nothing is recoloured, drawn or
 * moved. Re-run it if the artwork is ever replaced.
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const SRC = path.resolve(__dirname, 'stamp-src/sold-out.png');
const OUT = path.resolve(__dirname, '../download-version/assets/img/badge/vikyplus-sold-out.webp');

/**
 * The width the stamp is written at.
 *
 * It is drawn at most at the width of a sale card — 180px on a 390 phone, 232
 * on a 1440 desktop — so 400 covers the widest of those on a 2x screen with
 * room over it.
 */
const WIDTH = 400;

/** Anything at or under this is background; the artwork has a feathered edge. */
const ALPHA_FLOOR = 8;

(async () => {
  const { data, info } = await sharp(SRC).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
  const { width, height, channels } = info;

  // Flood the transparency that touches the canvas edge. What it cannot reach
  // is a hole inside the mark.
  const outside = new Uint8Array(width * height);
  const queue = [];

  const push = (x, y) => {
    if (x < 0 || y < 0 || x >= width || y >= height) return;
    const i = y * width + x;
    if (outside[i] || data[i * channels + 3] > ALPHA_FLOOR) return;
    outside[i] = 1;
    queue.push(i);
  };

  for (let x = 0; x < width; x++) { push(x, 0); push(x, height - 1); }
  for (let y = 0; y < height; y++) { push(0, y); push(width - 1, y); }

  for (let head = 0; head < queue.length; head++) {
    const i = queue[head];
    const x = i % width;
    const y = (i - x) / width;
    push(x - 1, y); push(x + 1, y); push(x, y - 1); push(x, y + 1);
  }

  let filled = 0;

  for (let i = 0; i < width * height; i++) {
    if (outside[i] || data[i * channels + 3] > ALPHA_FLOOR) continue;
    data[i * channels] = 255;
    data[i * channels + 1] = 255;
    data[i * channels + 2] = 255;
    data[i * channels + 3] = 255;
    filled++;
  }

  fs.mkdirSync(path.dirname(OUT), { recursive: true });

  const buffer = await sharp(data, { raw: { width, height, channels } })
    .resize({ width: WIDTH })
    .webp({ quality: 90, alphaQuality: 100, effort: 6 })
    .toBuffer();

  fs.writeFileSync(OUT, buffer);

  console.log(`  ${filled} enclosed pixels filled white (${(100 * filled / (width * height)).toFixed(1)}% of the canvas)`);
  console.log(`  ${path.relative(path.resolve(__dirname, '..'), OUT)}  ${Math.round(buffer.length / 1024)}KB @${WIDTH}`);
  console.log('Run node theme/sync-storefront-assets.js to carry it into the app.');
})();
