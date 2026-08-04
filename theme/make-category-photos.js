#!/usr/bin/env node
/**
 * Prepares the six category cut-outs.
 *
 * Every tile shows a subject on nothing — no photographic background — so each
 * source is reduced to its subject and written as a transparent square. Three
 * routes get there, chosen per source:
 *
 *   alpha    the source is already a cut-out; trim it and square it up.
 *   flood    the source is a photograph on a plain, even ground; the ground is
 *            removed by flooding inward from the border, which only takes
 *            pixels connected to the edge and so cannot eat into the subject
 *            the way a plain colour-distance threshold does.
 *   crop     take part of a cut-out before squaring it, where one source holds
 *            two subjects.
 *
 * Four of the six are the right subject for their category. Boot and sandal
 * are not: there is no boot and no sandal anywhere in the template's library,
 * so those two carry clean cut-outs of other footwear until real photographs
 * arrive. They are marked STAND-IN below.
 *
 * Run: node theme/make-category-photos.js
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const IMG = path.resolve(__dirname, '../download-version/assets/img');
const OUT = path.join(IMG, 'category');
const SIZE = 560;

const SOURCES = {
  // The whole frame: court shoes and a bag together, which is the category.
  'bag-set': { src: 'collection/collection_1_1.png', mode: 'alpha' },
  // The same frame, left portion only — the shoes without the bag. 0.52 left a
  // sliver of the bag's edge in frame; 0.44 clears it.
  majlesi:   { src: 'collection/collection_1_1.png', mode: 'crop', keep: [0, 0.44] },
  sneaker:   { src: 'collection/collection_2_3.png', mode: 'alpha' },
  // Derbies, worn, on an even pale-blue ground.
  college:   { src: 'collection/collection_2_1.png', mode: 'flood', tolerance: 46 },
  boot:      { src: 'product/product_13_1.png',      mode: 'alpha' }, // STAND-IN
  sandal:    { src: 'product/product_12_112.png',    mode: 'alpha' }, // STAND-IN
};

/**
 * Clears the ground by flooding inward from the border. Only pixels reachable
 * from the edge within `tolerance` of the border's own colour are cleared, so
 * a light patch inside the subject stays put.
 */
async function flood(file, tolerance) {
  const { data, info } = await sharp(file).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
  const { width: W, height: H, channels: C } = info;
  const at = (x, y) => (y * W + x) * C;

  // The ground colour is the median of the border, not one corner pixel.
  const edge = [];
  for (let x = 0; x < W; x += 2) { edge.push(at(x, 0)); edge.push(at(x, H - 1)); }
  for (let y = 0; y < H; y += 2) { edge.push(at(0, y)); edge.push(at(W - 1, y)); }
  const med = (k) => {
    const v = edge.map((i) => data[i + k]).sort((a, b) => a - b);
    return v[Math.floor(v.length / 2)];
  };
  const bg = [med(0), med(1), med(2)];

  const near = (i) =>
    Math.abs(data[i] - bg[0]) + Math.abs(data[i + 1] - bg[1]) + Math.abs(data[i + 2] - bg[2]) < tolerance * 3;

  const seen = new Uint8Array(W * H);
  const stack = [];
  for (let x = 0; x < W; x++) { stack.push([x, 0], [x, H - 1]); }
  for (let y = 0; y < H; y++) { stack.push([0, y], [W - 1, y]); }

  while (stack.length) {
    const [x, y] = stack.pop();
    if (x < 0 || y < 0 || x >= W || y >= H) continue;
    const p = y * W + x;
    if (seen[p]) continue;
    const i = p * C;
    if (!near(i)) continue;
    seen[p] = 1;
    data[i + 3] = 0;
    stack.push([x + 1, y], [x - 1, y], [x, y + 1], [x, y - 1]);
  }

  // One pass of feathering, so the cut edge is not a staircase.
  return sharp(data, { raw: { width: W, height: H, channels: C } })
    .png().toBuffer();
}

async function square(buf) {
  const trimmed = await sharp(buf).trim({ threshold: 6 }).toBuffer();
  const m = await sharp(trimmed).metadata();
  const scale = Math.min((SIZE * 0.86) / m.width, (SIZE * 0.86) / m.height);
  const fitted = await sharp(trimmed)
    .resize(Math.round(m.width * scale), Math.round(m.height * scale))
    .toBuffer();
  return sharp({
    create: { width: SIZE, height: SIZE, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } },
  }).composite([{ input: fitted, gravity: 'center' }]).png({ compressionLevel: 9 }).toBuffer();
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  for (const [name, spec] of Object.entries(SOURCES)) {
    const src = path.join(IMG, spec.src);
    if (!fs.existsSync(src)) throw new Error(`missing source: ${spec.src}`);

    let buf;
    if (spec.mode === 'flood') {
      buf = await flood(src, spec.tolerance);
    } else if (spec.mode === 'crop') {
      const m = await sharp(src).metadata();
      buf = await sharp(src).ensureAlpha().extract({
        left: Math.round(m.width * spec.keep[0]),
        top: 0,
        width: Math.round(m.width * (spec.keep[1] - spec.keep[0])),
        height: m.height,
      }).png().toBuffer();
    } else {
      buf = await sharp(src).ensureAlpha().png().toBuffer();
    }

    const dest = path.join(OUT, `${name}.png`);
    await sharp(await square(buf)).toFile(dest);
    console.log(`  ${name.padEnd(9)} ${spec.mode.padEnd(5)} <- ${spec.src}  ${Math.round(fs.statSync(dest).size / 1024)}KB`);
  }
})();
