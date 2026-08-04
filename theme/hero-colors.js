#!/usr/bin/env node
/**
 * Reads the colour out of each hero product shot and writes the two balls'
 * colours as CSS custom properties, one rule per image.
 *
 * The balls behind the hero card are meant to pick up the product on the card.
 * Doing that by hand means re-tuning two hex values every time a photograph
 * changes, so it is measured from the file instead: this runs at build time,
 * the page ships plain CSS, and nothing is computed in the browser.
 *
 * Method, per image:
 *
 *   1. Scale to 96px on the long side — enough to survive JPEG noise, small
 *      enough to be instant.
 *   2. Drop every pixel that cannot carry the colour: transparent (alpha
 *      under 200), washed out (saturation under 0.22), and the two ends of
 *      the lightness range, which is where a product shot's white ground and
 *      its shadows live.
 *   3. Bucket what is left by hue, 15 degrees a bucket, and take the biggest
 *      bucket. A single dominant hue beats an average, which on a two-colour
 *      shoe would return mud.
 *   4. Take that bucket's median hue and saturation, then set two lightnesses
 *      from it — the large ball light, the small one deeper — matching how
 *      the gold pair reads.
 *
 * An image with no chromatic pixels at all — the template's grey placeholders
 * — gets no rule, and the page falls back to the brand gold.
 *
 * Run: node theme/hero-colors.js
 */
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const ROOT = path.resolve(__dirname, '..');
const SITE = path.join(ROOT, 'download-version');
const OUT = path.join(SITE, 'assets/css/hero-colors.css');

// The large ball and the small one. Lightness is fixed at the gold pair's own
// two values, so every product yields a pair with the same weight as the gold
// already approved on the page; only the hue and, within limits, the
// saturation come from the photograph. Left to the product alone the pair
// would be as washed out as a pastel shoe or as loud as a red one.
const SAT = { min: 0.42, max: 0.74 };
const BALL_A = { l: 0.62, s: 1.00 };
const BALL_B = { l: 0.50, s: 0.82 };

const rgbToHsl = (r, g, b) => {
  r /= 255; g /= 255; b /= 255;
  const mx = Math.max(r, g, b), mn = Math.min(r, g, b), d = mx - mn;
  let h = 0;
  if (d) {
    if (mx === r) h = ((g - b) / d) % 6;
    else if (mx === g) h = (b - r) / d + 2;
    else h = (r - g) / d + 4;
    h *= 60;
    if (h < 0) h += 360;
  }
  const l = (mx + mn) / 2;
  return [h, d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1)), l];
};

const hslToHex = (h, s, l) => {
  const c = (1 - Math.abs(2 * l - 1)) * s;
  const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
  const m = l - c / 2;
  const [r, g, b] =
    h < 60 ? [c, x, 0] : h < 120 ? [x, c, 0] : h < 180 ? [0, c, x] :
    h < 240 ? [0, x, c] : h < 300 ? [x, 0, c] : [c, 0, x];
  return '#' + [r, g, b].map((v) => Math.round((v + m) * 255).toString(16).padStart(2, '0')).join('').toUpperCase();
};

const median = (xs) => xs.slice().sort((a, b) => a - b)[Math.floor(xs.length / 2)];

async function dominant(file) {
  const { data, info } = await sharp(file)
    .resize(96, 96, { fit: 'inside' })
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  const buckets = new Map();
  for (let i = 0; i < data.length; i += info.channels) {
    if (data[i + 3] < 200) continue;
    const [h, s, l] = rgbToHsl(data[i], data[i + 1], data[i + 2]);
    if (s < 0.22 || l < 0.18 || l > 0.88) continue;
    const key = Math.floor(h / 15);
    if (!buckets.has(key)) buckets.set(key, []);
    buckets.get(key).push([h, s]);
  }
  if (!buckets.size) return null;

  const [, best] = [...buckets.entries()].sort((a, b) => b[1].length - a[1].length)[0];
  const total = [...buckets.values()].reduce((n, v) => n + v.length, 0);
  return {
    hue: median(best.map((p) => p[0])),
    sat: median(best.map((p) => p[1])),
    share: best.length / total,
    pixels: best.length,
  };
}

(async () => {
  const page = fs.readFileSync(path.join(SITE, 'shoe-shop-rtl.html'), 'utf8');
  const refs = [...new Set(
    [...page.matchAll(/src="(assets\/img\/hero\/[^"]+)"/g)].map((m) => m[1])
  )].sort();

  const rules = [];
  for (const rel of refs) {
    const abs = path.join(SITE, rel);
    if (!fs.existsSync(abs)) continue;
    const key = path.basename(rel).replace(/\.[a-z0-9]+$/i, '');
    const d = await dominant(abs);
    if (!d) {
      console.log(`  ${key.padEnd(22)} no chromatic pixels — falls back to the gold`);
      continue;
    }
    const sat = Math.min(SAT.max, Math.max(SAT.min, d.sat));
    const a = hslToHex(d.hue, sat * BALL_A.s, BALL_A.l);
    const b = hslToHex(d.hue, sat * BALL_B.s, BALL_B.l);
    console.log(
      `  ${key.padEnd(22)} hue ${d.hue.toFixed(0).padStart(3)}°  sat ${(d.sat * 100).toFixed(0)}%` +
      ` -> ${(sat * 100).toFixed(0)}%  ${(d.share * 100).toFixed(0).padStart(2)}% dominant  ->  ${a} / ${b}`
    );
    rules.push(
      `/* ${key}: hue ${d.hue.toFixed(0)}°, the dominant ${(d.share * 100).toFixed(0)}% of its ` +
      `chromatic pixels */\nbody[data-hero="${key}"] {\n  --ball-a: ${a};\n  --ball-b: ${b};\n}`
    );
  }

  const header = `/* ==========================================================================
   Hero ball colours — generated by theme/hero-colors.js, do not edit.

   One rule per product shot, keyed on the file's own name. A small script in
   the page puts the active slide's key on the body, so the two balls behind
   the card follow whatever is on it.

   Images with no colour of their own get no rule here and fall through to the
   brand gold in tweaks.css.
   ========================================================================== */\n\n`;

  fs.writeFileSync(OUT, header + rules.join('\n\n') + '\n');
  console.log(`\nwrote ${path.relative(ROOT, OUT)} (${rules.length} of ${refs.length} images have a colour)`);
})();
