#!/usr/bin/env node
/**
 * Squares up the six category photographs.
 *
 * The tiles are square, so the sources are cropped square rather than letting
 * object-fit do it at render time: cropping once, here, means the browser
 * downloads what it displays instead of a wider frame it will throw away.
 *
 * Four of the six are real matches from the template's own photography. Two —
 * boot and sandal — have no matching shot anywhere in the asset library, and
 * are marked below as standing in until real ones arrive.
 *
 * Run: node theme/make-category-photos.js
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const IMG = path.resolve(__dirname, '../download-version/assets/img');
const OUT = path.join(IMG, 'category');

const SOURCES = {
  majlesi: 'collection/collection_1_1.png',   // court shoes and a bag
  sneaker: 'collection/collection_2_2.png',   // trainers, worn
  college: 'collection/collection_2_1.png',   // derbies, worn
  sandal:  'collection/collection_2_3.png',   // STAND-IN: no sandal in the library
  boot:    'collection/collection_3_1.jpg',   // STAND-IN: no boot in the library
  'bag-set': 'collection/collection_5_2.jpg', // quilted bag
};

const SIZE = 640;

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  for (const [name, rel] of Object.entries(SOURCES)) {
    const src = path.join(IMG, rel);
    if (!fs.existsSync(src)) throw new Error(`missing source: ${rel}`);
    const dest = path.join(OUT, `${name}.jpg`);
    await sharp(src)
      .flatten({ background: '#ffffff' })
      .resize(SIZE, SIZE, { fit: 'cover', position: 'attention' })
      .jpeg({ quality: 82, mozjpeg: true })
      .toFile(dest);
    console.log(`  ${name.padEnd(9)} <- ${rel}  ${Math.round(fs.statSync(dest).size / 1024)}KB`);
  }
})();
