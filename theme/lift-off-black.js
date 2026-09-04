#!/usr/bin/env node
/**
 * Lifts a shoe off a solid black ground, into the cut-out `hero-src/` wants.
 *
 * Run: node theme/lift-off-black.js <in.png> <hero-src/name.png>
 *
 * **Nothing in the repository needs this today**, and it is kept because the
 * next photograph might. The hero's slides sit on the glass pane with nothing
 * behind them, so every shot in `hero-src/` has to be background-free —
 * `HeroPhotographTest` fails on a corner that is not fully transparent, which
 * is the guard that made this necessary. Most of what the client sends already
 * is. One arrived as a black On Cloudtilt **on a black background**, a black
 * rectangle on the pane being exactly the failure the override exists to
 * avoid; this cut it, and then a proper cut-out of the same shoe arrived and
 * is what ships. Asking is cheaper than cutting, and the result is better —
 * compare the two edges if this is ever in question. Reach for this when
 * asking is not an option.
 *
 * **Why a threshold alone will not do it.** The shoe is black too, so «drop
 * every dark pixel» drops the shoe. What separates the two is not the colour
 * but the *reachability*: the ground is one region touching the canvas edge,
 * and the shoe is not. So the ground is flooded from the border and only what
 * the flood reaches is cleared. A pocket of pure black inside the knit upper
 * is enclosed by lighter pixels and is never reached, which is the same
 * argument `make-sold-out-stamp.js` makes in the other direction — there the
 * enclosed transparency is the artwork's own lettering.
 *
 * The measurement that says this is safe, for the shoe it was written for:
 * the ground is 69% of the frame at exactly (0,0,0) with a 12% skirt at level
 * 1 (the encoder's noise), and the darkest points sampled inside the upper are
 * (9,8,11) and (25,27,31). A ceiling of 2 sits in the gap with room either
 * side. It is a parameter because the next photograph will have its own gap,
 * and the script prints what it cleared so an obviously wrong ceiling — a
 * cleared fraction near 100%, or near 0 — is visible rather than silent.
 *
 * The edge is feathered afterwards. A hard flood leaves the antialiased rim of
 * the shoe half-black against whatever it is laid on, which on this shop's
 * pale glass reads as a dirty outline; the alpha is taken from how far each
 * boundary pixel is above the ground instead, so the rim fades the way the
 * photograph does.
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

/** Anything at or below this, reachable from the border, is the ground. */
const GROUND = Number(process.env.VP_GROUND || 2);

/**
 * Where the rim stops being ground and starts being shoe. A pixel this far
 * above the ground is fully opaque; below it, the alpha is the fraction.
 */
const RIM = Number(process.env.VP_RIM || 24);

/**
 * How wide a bite out of the silhouette to mend. The flood walks into the
 * shoe wherever its outline is as dark as the ground, and the notches that
 * leaves are a few pixels across; anything narrower than twice this is filled.
 */
const CLOSE = Number(process.env.VP_CLOSE || 3);

/** How much of the outline to shave afterwards, to take the ringing with it. */
const TRIM = Number(process.env.VP_TRIM || 1);

/**
 * Close a mask: grow the *non*-mask by r and shrink it back.
 *
 * Written on the mask of what is being removed, so this fills notches in what
 * is being kept. Separable — a max then a min along each axis — because the
 * square kernel over 1.6 megapixels is otherwise several seconds.
 *
 * @param {Uint8Array} mask 1 where the ground is
 */
function close(mask, width, height, r) {
  if (r < 1) return mask;

  // The shoe, grown by r in each direction, then shrunk by r. Working on the
  // shoe rather than the ground keeps the two passes the right way round.
  const grow = (src, radius, pick) => {
    const rows = new Uint8Array(width * height);
    for (let y = 0; y < height; y++) {
      for (let x = 0; x < width; x++) {
        let v = pick === 'max' ? 0 : 1;
        for (let d = -radius; d <= radius; d++) {
          const nx = x + d;
          if (nx < 0 || nx >= width) continue;
          const s = src[y * width + nx];
          v = pick === 'max' ? Math.max(v, s) : Math.min(v, s);
        }
        rows[y * width + x] = v;
      }
    }

    const out = new Uint8Array(width * height);
    for (let x = 0; x < width; x++) {
      for (let y = 0; y < height; y++) {
        let v = pick === 'max' ? 0 : 1;
        for (let d = -radius; d <= radius; d++) {
          const ny = y + d;
          if (ny < 0 || ny >= height) continue;
          const s = rows[ny * width + x];
          v = pick === 'max' ? Math.max(v, s) : Math.min(v, s);
        }
        out[y * width + x] = v;
      }
    }

    return out;
  };

  const shoe = new Uint8Array(width * height);
  for (let i = 0; i < mask.length; i++) shoe[i] = mask[i] ? 0 : 1;

  const shrunk = grow(grow(shoe, r, 'max'), r, 'min');

  const out = new Uint8Array(width * height);
  for (let i = 0; i < out.length; i++) out[i] = shrunk[i] ? 0 : 1;

  return out;
}

/** Shave `r` pixels off everything the mask does *not* cover. */
function trim(mask, width, height, r) {
  if (r < 1) return mask;

  let out = mask;

  for (let pass = 0; pass < r; pass++) {
    const next = new Uint8Array(width * height);
    for (let y = 0; y < height; y++) {
      for (let x = 0; x < width; x++) {
        const i = y * width + x;
        next[i] = out[i]
          || (x > 0 && out[i - 1])
          || (x < width - 1 && out[i + 1])
          || (y > 0 && out[i - width])
          || (y < height - 1 && out[i + width])
          ? 1 : 0;
      }
    }
    out = next;
  }

  return out;
}

const [, , input, output] = process.argv;

if (!input || !output) {
  console.error('usage: node theme/lift-off-black.js <in.png> <hero-src/name.png>');
  process.exit(1);
}

(async () => {
  const { data, info } = await sharp(input).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  const { width, height, channels } = info;

  const value = (i) => Math.max(data[i * channels], data[i * channels + 1], data[i * channels + 2]);

  const ground = new Uint8Array(width * height);
  const queue = [];

  const push = (x, y) => {
    if (x < 0 || y < 0 || x >= width || y >= height) return;
    const i = y * width + x;
    if (ground[i] || value(i) > GROUND) return;
    ground[i] = 1;
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

  // **What the flood leaves behind is not all shoe.** A photograph delivered
  // as a JPEG has ringing around a hard edge, so the ground around the
  // silhouette carries a scatter of pixels a few levels above the ceiling.
  // They survive the flood, and at hero size they read as a crunchy black
  // fringe all round the shoe — visible in the first cut of this one, and not
  // fixable by moving the ceiling: raising it from 2 to 10 changed the cleared
  // fraction by 0.3% and left the fringe exactly where it was.
  //
  // What separates them from the shoe is the same thing that separated the
  // ground: the shoe is one region, and a speck of ringing is not part of it.
  // So the opaque pixels are labelled and only the largest island is kept.
  const island = new Int32Array(width * height).fill(-1);
  const sizes = [];

  for (let seed = 0; seed < width * height; seed++) {
    if (ground[seed] || island[seed] !== -1) continue;

    const id = sizes.length;
    const stack = [seed];
    island[seed] = id;
    let size = 0;

    while (stack.length) {
      const i = stack.pop();
      size++;
      const x = i % width;
      const y = (i - x) / width;
      const step = (j, ok) => {
        if (!ok || ground[j] || island[j] !== -1) return;
        island[j] = id;
        stack.push(j);
      };
      step(i - 1, x > 0);
      step(i + 1, x < width - 1);
      step(i - width, y > 0);
      step(i + width, y < height - 1);
    }

    sizes.push(size);
  }

  let shoe = 0;
  for (let id = 1; id < sizes.length; id++) if (sizes[id] > sizes[shoe]) shoe = id;

  let specks = 0;
  for (let i = 0; i < width * height; i++) {
    if (!ground[i] && island[i] !== shoe) {
      ground[i] = 1;
      specks++;
    }
  }

  // **And the flood bites into the shoe.** Where the shoe's own outline is as
  // dark as the ground — a black knit against black is most of this outline —
  // the flood walks straight through it, and what comes back is a silhouette
  // with notches chewed out of it. Zoomed to 3x on the first cut they are
  // unmistakable: white wedges along the collar and the heel, two to four
  // pixels across.
  //
  // Lowering the ceiling does not help, because the pixels being eaten are
  // genuinely the same value as the ground. What tells them apart is their
  // *shape*: a notch is a narrow intrusion into a solid region, and the ground
  // proper is not. So the mask is closed — grown by CLOSE and shrunk back by
  // the same — which fills any intrusion narrower than twice CLOSE and returns
  // every straight edge to where it was. The sole's own cut-outs are far wider
  // than that and stay open, which is the thing to check when this is re-run
  // on a different shoe.
  // Then a pixel off the outline. What is left hugging the silhouette after
  // the islands and the closing is ringing that *touches* the shoe, so
  // neither pass can reach it — at hero size it reads as a dirty stipple all
  // round the edge. It is one pixel deep, and the shoe is drawn 1344 wide, so
  // taking a pixel off the silhouette costs nothing anybody can see and takes
  // the stipple with it.
  const closed = trim(close(ground, width, height, CLOSE), width, height, TRIM);
  let mended = 0;
  for (let i = 0; i < width * height; i++) {
    if (ground[i] && !closed[i]) mended++;
  }
  ground.set(closed);

  // The ground is transparent; everything else keeps its colour, and the rim
  // takes an alpha from how far it stands above the ground so the silhouette
  // does not come out with a hard black hem.
  const out = Buffer.alloc(width * height * 4);
  let cleared = 0;
  let feathered = 0;

  for (let i = 0; i < width * height; i++) {
    const v = value(i);

    let alpha = 255;
    if (ground[i]) {
      alpha = 0;
      cleared++;
    } else if (v < RIM) {
      // Only where it borders the ground: an interior pocket of black is the
      // shoe's own shadow and stays solid.
      const x = i % width;
      const y = (i - x) / width;
      const touches = (x > 0 && ground[i - 1]) || (x < width - 1 && ground[i + 1])
        || (y > 0 && ground[i - width]) || (y < height - 1 && ground[i + width]);
      if (touches) {
        alpha = Math.round((v / RIM) * 255);
        feathered++;
      }
    }

    out[i * 4] = data[i * channels];
    out[i * 4 + 1] = data[i * channels + 1];
    out[i * 4 + 2] = data[i * channels + 2];
    out[i * 4 + 3] = alpha;
  }

  fs.mkdirSync(path.dirname(output), { recursive: true });
  await sharp(out, { raw: { width, height, channels: 4 } }).png().toFile(output);

  const percent = (n) => (100 * n / (width * height)).toFixed(1);

  console.log(`  ${width}x${height}: ${percent(cleared)}% cleared as ground, ${percent(feathered)}% feathered at the rim`);
  console.log(`  ${sizes.length - 1} islands of ringing dropped (${percent(specks)}% of the frame); the shoe is ${percent(sizes[shoe])}%`);
  console.log(`  ${mended} pixels mended where the flood had bitten into the silhouette`);
  console.log(`  ${path.relative(path.resolve(__dirname, '..'), output)}`);

  if (cleared === 0 || cleared > width * height * 0.97) {
    console.log('  ** that fraction is wrong. Raise or lower VP_GROUND and look again. **');
  }
})();
