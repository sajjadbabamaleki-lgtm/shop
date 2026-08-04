#!/usr/bin/env node
/**
 * Generates the two raster inputs the liquid-glass lab page needs.
 *
 * A refracting lens is a displacement map plus something worth refracting, and
 * neither can be written by hand: the map has to encode a surface normal per
 * pixel, and the ground has to carry enough detail that a few pixels of warp
 * are visible at all.
 *
 * Run: node theme/make-lens-assets.js
 */
const path = require('path');
const sharp = require('./node_modules/sharp');

const OUT = path.resolve(__dirname, '../download-version/lab');

/* --- the ground ----------------------------------------------------------
   A stand-in for the reference's sky and branches: strong flat blue, a few
   crossing warm strokes, then blurred. Detail with hard direction changes is
   what makes refraction legible — a soft gradient would warp invisibly. */
function ground() {
  const W = 1240, H = 900;
  const stroke = (x, y, w, h, a, fill) =>
    `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${w / 2}" fill="${fill}" transform="rotate(${a} ${x + w / 2} ${y + h / 2})"/>`;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">
    <defs>
      <linearGradient id="sky" x1="0" y1="0" x2="0.3" y2="1">
        <stop offset="0" stop-color="#1E6FE0"/>
        <stop offset="0.55" stop-color="#2B84F0"/>
        <stop offset="1" stop-color="#1857C8"/>
      </linearGradient>
    </defs>
    <rect width="${W}" height="${H}" fill="url(#sky)"/>
    ${stroke(180, -80, 120, 700, 24, '#9E1B2F')}
    ${stroke(300, 120, 74, 760, 12, '#C22B3F')}
    ${stroke(640, -140, 150, 620, -28, '#B02034')}
    ${stroke(760, 300, 92, 700, 18, '#D9455A')}
    ${stroke(980, -60, 110, 820, -14, '#8E1526')}
    ${stroke(420, 380, 620, 96, 82, '#E0637A')}
    ${stroke(120, 620, 520, 70, 74, '#7E1120')}
    ${stroke(560, 40, 300, 54, 68, '#F0899B')}
  </svg>`;
  return sharp(Buffer.from(svg)).blur(26).png().toFile(path.join(OUT, 'ground.png'));
}

/* --- the displacement map ------------------------------------------------
   R and G carry the x and y offsets feDisplacementMap will apply, with 0.5
   meaning no shift. The offset is the shape's outward normal, scaled by how
   close the pixel is to the edge — so the middle of the pane is flat glass
   and only a band around the rim bends, which is what a thick convex edge
   does. The distance field is sampled numerically and its gradient taken by
   difference, so the same routine works for a capsule or a circle.

   `edge` is how deep the bevel runs, `falloff` how abruptly it dies out.
   Higher falloff keeps more of the interior undistorted. */
function displacementMap(name, MW, MH, sdf, edge, falloff) {
  const buf = Buffer.alloc(MW * MH * 3);
  const grad = (x, y) => {
    const h = 1;
    return [(sdf(x + h, y) - sdf(x - h, y)) / (2 * h), (sdf(x, y + h) - sdf(x, y - h)) / (2 * h)];
  };
  for (let y = 0; y < MH; y++) {
    for (let x = 0; x < MW; x++) {
      const i = (y * MW + x) * 3;
      const d = sdf(x + 0.5, y + 0.5);
      let r = 0.5, g = 0.5;
      if (d < 0) {
        const e = Math.min(1, -d / edge);
        const f = Math.pow(1 - e, falloff);
        let [nx, ny] = grad(x + 0.5, y + 0.5);
        const len = Math.hypot(nx, ny) || 1;
        nx /= len; ny /= len;
        r = 0.5 + nx * f * 0.5;
        g = 0.5 + ny * f * 0.5;
      }
      buf[i] = Math.round(Math.max(0, Math.min(1, r)) * 255);
      buf[i + 1] = Math.round(Math.max(0, Math.min(1, g)) * 255);
      buf[i + 2] = 128;
    }
  }
  return sharp(buf, { raw: { width: MW, height: MH, channels: 3 } })
    .png().toFile(path.join(OUT, name));
}

// Signed distance to a rounded rectangle centred in the map. Negative inside.
const roundedRect = (MW, MH, w, h, r) => (x, y) => {
  const px = Math.abs(x - MW / 2) - (w / 2 - r);
  const py = Math.abs(y - MH / 2) - (h / 2 - r);
  const qx = Math.max(px, 0), qy = Math.max(py, 0);
  return Math.hypot(qx, qy) + Math.min(Math.max(px, py), 0) - r;
};

// The pane is inset in its map so displacement can pull in ground from
// outside the shape; without the margin the rim would sample transparency.
const PAD = 90;

(async () => {
  await ground();
  // The bevel runs most of the way to the middle rather than sitting in a
  // narrow rim. A thin bevel does distort, but only in a band the hairline
  // and the inner shadow then cover, so nothing of it survives to be seen.
  // Carrying it deep is what makes the piece read as a thick lens.
  await displacementMap('map-pill.png', 560 + PAD * 2, 148 + PAD * 2,
    roundedRect(560 + PAD * 2, 148 + PAD * 2, 560, 148, 74), 70, 1.15);
  await displacementMap('map-dot.png', 148 + PAD * 2, 148 + PAD * 2,
    roundedRect(148 + PAD * 2, 148 + PAD * 2, 148, 148, 74), 70, 1.15);
  await displacementMap('map-card.png', 560 + PAD * 2, 300 + PAD * 2,
    roundedRect(560 + PAD * 2, 300 + PAD * 2, 560, 300, 62), 96, 1.3);
  console.log('wrote ground.png, map-pill.png, map-dot.png, map-card.png');
})();

/* --- the storefront's own lens ------------------------------------------
   The hero cards get the same treatment, with one change forced by the
   platform: backdrop-filter can only see the backdrop *inside* the element,
   so a rim that samples outward hits the element's edge and clamps, and every
   shape crossing the boundary picks up a hard step. Negating the normal makes
   the rim sample inward instead — there is always content there, so nothing
   clamps, and it reads as magnification at the edge rather than compression.

   Run: node theme/make-lens-assets.js --hero
   ------------------------------------------------------------------------ */
function heroMap() {
  // A 96px bevel on a 548px card smears whatever passes under it far enough
  // along the rim to read as a gold frame rather than as an edge. 58 keeps the
  // bend where the eye expects it.
  const W = 1227, H = 548, r = 60, edge = 58, fall = 1.45;
  const sdf = roundedRect(W, H, W, H, r);
  const buf = Buffer.alloc(W * H * 3);
  for (let y = 0; y < H; y++) {
    for (let x = 0; x < W; x++) {
      const i = (y * W + x) * 3;
      const d = sdf(x + 0.5, y + 0.5);
      let R = 0.5, G = 0.5;
      if (d < 0) {
        const e = Math.min(1, -d / edge);
        const f = Math.pow(1 - e, fall);
        let nx = (sdf(x + 1.5, y + 0.5) - sdf(x - 0.5, y + 0.5)) / 2;
        let ny = (sdf(x + 0.5, y + 1.5) - sdf(x + 0.5, y - 0.5)) / 2;
        const L = Math.hypot(nx, ny) || 1;
        R = 0.5 - (nx / L) * f * 0.5;
        G = 0.5 - (ny / L) * f * 0.5;
      }
      buf[i] = Math.round(R * 255);
      buf[i + 1] = Math.round(G * 255);
      buf[i + 2] = 128;
    }
  }
  return sharp(buf, { raw: { width: W, height: H, channels: 3 } })
    .png({ compressionLevel: 9 })
    .toFile(path.resolve(__dirname, '../download-version/assets/img/hero/vp-lens-map.png'))
    .then(() => console.log('wrote assets/img/hero/vp-lens-map.png'));
}

if (process.argv.includes('--hero')) heroMap();
