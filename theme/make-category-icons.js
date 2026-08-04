#!/usr/bin/env node
/**
 * Lifts the six category illustrations out of a screenshot of the old site.
 *
 * The originals live on vikyplus.ir, which this environment's network policy
 * will not reach, so the source here is the screenshot the drawings were
 * supplied in. The card boundaries are not guessed: a row of pixels above the
 * drawings is scanned for runs of pure white, which is where each card is and
 * the grey section ground is not.
 *
 * Each drawing is cut from above the card's gold rule — everything below it is
 * the label, which is not wanted — then trimmed to its own ink.
 *
 * Run: node theme/make-category-icons.js
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const SRC = process.argv[2] ||
  '/root/.claude/uploads/bc96d05d-d379-570e-b102-3f3db421053f/0fa00d1b-Image_04082026_at_2.02PM.png';
const OUT = path.resolve(__dirname, '../download-version/assets/img/category');

// Right to left, the order the row reads in.
const NAMES = ['majlesi', 'sneaker', 'college', 'sandal', 'boot', 'bag-set'];

// The band between the top of the cards and the gold rule under each drawing.
const TOP = 1960;
const BOTTOM = 2315;
const PROBE = 1975;

async function cards() {
  const { data, info } = await sharp(SRC)
    .extract({ left: 0, top: PROBE, width: 3840, height: 1 })
    .raw().toBuffer({ resolveWithObject: true });
  const runs = [];
  let start = null;
  for (let x = 0; x < info.width; x++) {
    const i = x * info.channels;
    const white = data[i] >= 250 && data[i + 1] >= 250 && data[i + 2] >= 250;
    if (white && start === null) start = x;
    if (!white && start !== null) {
      if (x - start > 200) runs.push([start, x - 1]);
      start = null;
    }
  }
  if (start !== null && info.width - start > 200) runs.push([start, info.width - 1]);
  // The page's own left and right margins are white too; the cards are the
  // runs between them, and they are all the same width.
  const w = runs.map(([a, b]) => b - a);
  const card = w.slice().sort((a, b) => a - b)[Math.floor(w.length / 2)];
  return runs.filter(([a, b]) => Math.abs(b - a - card) < 12);
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const found = await cards();
  if (found.length !== 6) throw new Error(`expected 6 cards, found ${found.length}`);

  // Right to left: the rightmost card is the first item in the row.
  const ordered = found.slice().sort((a, b) => b[0] - a[0]);

  for (let i = 0; i < ordered.length; i++) {
    const [x0, x1] = ordered[i];
    const dest = path.join(OUT, `${NAMES[i]}.png`);
    // The old card's ground is a shade off white, and against a pure white
    // card that shade reads as a grey box around the drawing. Anything this
    // close to white is snapped to white so only the ink survives.
    const cut = await sharp(SRC)
      .extract({ left: x0 + 6, top: TOP, width: x1 - x0 - 12, height: BOTTOM - TOP })
      .resize({ height: 260, fit: 'inside', withoutEnlargement: true })
      .flatten({ background: '#ffffff' })
      .raw().toBuffer({ resolveWithObject: true });
    const { data, info } = cut;
    for (let i = 0; i < data.length; i += info.channels) {
      if (Math.min(data[i], data[i + 1], data[i + 2]) > 242) {
        data[i] = data[i + 1] = data[i + 2] = 255;
      }
    }
    await sharp(data, { raw: { width: info.width, height: info.height, channels: info.channels } })
      .png({ compressionLevel: 9 })
      .toFile(dest);
    const m = await sharp(dest).metadata();
    console.log(`  ${NAMES[i].padEnd(9)} ${m.width}x${m.height}  ${Math.round(fs.statSync(dest).size / 1024)}KB`);
  }
})();
