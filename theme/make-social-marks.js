#!/usr/bin/env node
/**
 * The بله and روبیکا marks for the phone footer's social row.
 *
 * The row has five services. Three of them are Font Awesome brand glyphs;
 * these two are not in Font Awesome and were stand-ins — a chat bubble and the
 * letter R on a tile — with a note in tweaks.css saying they become images the
 * day the client sends the artwork. This is that day.
 *
 * **The client sent photographs of the logos, not artwork**: JPEGs on a light
 * grey ground, one 420px and one 225px, and JPEG has no transparency. A chip
 * in the footer is 42px and coloured, so pasting either one in as-is puts a
 * grey square inside a rounded tile. So the ground is removed here rather than
 * by hand in an editor, and the step is a script rather than a one-off because
 * the day a mark is re-issued, this has to be repeatable.
 *
 * **The ground is removed by flooding in from the edges, not by keying a
 * colour.** Both marks contain white *inside* them — روبیکا's centre cube is
 * white, بله's tick is white — and a colour key would punch those out and
 * leave a hole. A flood fill can only reach what touches the border, so an
 * enclosed white survives by construction. That is the whole reason for the
 * queue below.
 *
 * Same rule as the category photographs and the icon set: resize only, no
 * crop, no cut-out, nothing redrawn. These are somebody else's trademarks and
 * the shop is showing them, not adapting them.
 *
 * Run: node theme/make-social-marks.js
 */
const fs = require('fs');
const path = require('path');
const sharp = require(path.join(__dirname, 'node_modules/sharp'));

const SRC = path.join(__dirname, 'social-marks');
const OUT = path.join(__dirname, '..', 'download-version', 'assets', 'img', 'social');

/** 42px chip, drawn at 3× so it stays sharp on a phone. */
const SIZE = 126;

/**
 * How far a pixel may be from the ground and still count as ground.
 *
 * The supplied files are JPEG, so a flat grey is not flat: measured across the
 * border of both files the values run 226–233, and the thin lighter frame
 * inside روبیکا's is 240. 45 covers both and still stops dead at the marks
 * themselves, whose lightest edge pixel is white — and white, inside the
 * hexagon, is not reachable from the border.
 */
const TOLERANCE = 45;

const MARKS = [
  ['rubika-supplied.jpg', 'rubika.png'],
  ['bale-supplied.jpg', 'bale.png'],
];

/**
 * Everything connected to the border, at the border's colour, becomes clear.
 *
 * @param {Buffer} data raw RGBA
 * @param {{width: number, height: number}} info
 */
function clearGround(data, info) {
  const { width, height } = info;
  const at = (x, y) => (y * width + x) * 4;
  const ground = [data[0], data[1], data[2]];

  const isGround = (i) =>
    Math.abs(data[i] - ground[0]) <= TOLERANCE &&
    Math.abs(data[i + 1] - ground[1]) <= TOLERANCE &&
    Math.abs(data[i + 2] - ground[2]) <= TOLERANCE;

  const seen = new Uint8Array(width * height);
  const queue = [];

  for (let x = 0; x < width; x++) {
    queue.push([x, 0], [x, height - 1]);
  }
  for (let y = 0; y < height; y++) {
    queue.push([0, y], [width - 1, y]);
  }

  while (queue.length) {
    const [x, y] = queue.pop();
    if (x < 0 || y < 0 || x >= width || y >= height) continue;

    const cell = y * width + x;
    if (seen[cell]) continue;

    const i = at(x, y);
    if (!isGround(i)) continue;

    seen[cell] = 1;
    data[i + 3] = 0;

    queue.push([x + 1, y], [x - 1, y], [x, y + 1], [x, y - 1]);
  }
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  for (const [from, to] of MARKS) {
    const source = path.join(SRC, from);
    const { data, info } = await sharp(source)
      .ensureAlpha()
      .raw()
      .toBuffer({ resolveWithObject: true });

    clearGround(data, info);

    const cleared = sharp(data, { raw: { width: info.width, height: info.height, channels: 4 } });

    await cleared
      // What is left of the ground at the corners is transparent now, so this
      // trims to the mark itself rather than to the photograph's edges.
      .trim()
      .resize(SIZE, SIZE, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
      .png()
      .toFile(path.join(OUT, to));

    const written = await sharp(path.join(OUT, to)).metadata();
    console.log(`wrote assets/img/social/${to} (${written.width}×${written.height})`);
  }
})();
