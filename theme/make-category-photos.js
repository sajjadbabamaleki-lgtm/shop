#!/usr/bin/env node
/**
 * Prepares the eight category photographs.
 *
 * Nothing is removed and nothing is cut: each photograph goes in exactly as
 * supplied, background and all. Every source is already square, so scaling to
 * the tile's size is a straight resize with no crop — no part of any frame is
 * lost. The rounded corners belong to the tile and are applied in CSS at
 * render time, so the files stay plain rectangles.
 *
 * The sources live in theme/category-src rather than under the site's assets
 * because they are build input, not anything the site serves.
 *
 * Run: node theme/make-category-photos.js
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const SRC = path.resolve(__dirname, 'category-src');
const OUT = path.resolve(__dirname, '../download-version/assets/img/category');

// Well over twice the 150px the tile is displayed at, so the photograph still
// holds up on a 2x screen.
const SIZE = 420;

const SOURCES = {
  majlesi: 'majlesi.png',
  sneaker: 'sneaker.png',
  college: 'college.png',
  sandal: 'sandal.png',
  boot: 'boot.png',
  'bag-set': 'bag-set.png',
  accessory: 'accessory.png',
  'sport-set': 'sport-set.png',
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  for (const [name, file] of Object.entries(SOURCES)) {
    const src = path.join(SRC, file);
    if (!fs.existsSync(src)) throw new Error(`missing source: ${file}`);

    // A non-square source could only be fitted by cropping it, which is the
    // one thing this must not do — so it stops rather than guessing.
    const m = await sharp(src).metadata();
    if (m.width !== m.height) {
      throw new Error(`${file} is ${m.width}x${m.height}; fitting it square would mean cropping`);
    }

    const dest = path.join(OUT, `${name}.jpg`);
    await sharp(src).resize(SIZE, SIZE).jpeg({ quality: 86, mozjpeg: true }).toFile(dest);
    console.log(`  ${name.padEnd(9)} ${m.width}x${m.height} -> ${SIZE}x${SIZE}  ${Math.round(fs.statSync(dest).size / 1024)}KB`);
  }
})();
