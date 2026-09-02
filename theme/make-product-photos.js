#!/usr/bin/env node
/**
 * The client's own product photographs, cut to a width the site can draw.
 *
 * Run: node theme/make-product-photos.js
 *
 * **Why.** The studio files arrive at whatever the camera and the retoucher
 * left them at — the woven mule came as four 2560x2560 JPEGs, 0.6 to 1.1MB
 * each, 3.4MB for one shoe. The card draws a photograph at 177 CSS pixels and
 * the product page's gallery at about 600; a 2x phone can therefore show 1200,
 * and 1400 covers that with room. Everything past it is downloaded, decoded and
 * thrown away — and a JPEG is already compressed, so gzip on the server does
 * nothing for it, exactly as with the icon fonts and the hero shots.
 *
 * Measured on the four: **3.4MB becomes 331KB**, and nothing on any screen this
 * shop is drawn on can tell.
 *
 * **Resize only** — the same rule the category tiles, the app icon and
 * `make-photo-sizes.js` are under. No crop, no re-framing: a shoe leaving its
 * frame is the fault the client reported in the first place, and it was the
 * supplier's file that did it. `sharp().resize(1400)` and nothing else, and
 * only ever downwards.
 *
 * **It rewrites in place and keeps no original.** That is deliberate and it is
 * the opposite of what `make-icon-fonts.js` does: a font is re-subset every
 * time an icon is added, so it needs the full one beside it, while a
 * photograph is cut once and the pixels above 1400 are of no use to any page
 * this shop serves. What guards against a bad run is that these are committed
 * files — `git checkout` puts them back — and that the width is a floor, so
 * running it twice cannot narrow a photograph twice.
 *
 * These live under `storefront/public/`, not in `download-version/`, because
 * only `storefront/` is deployed and the static preview has no catalogue.
 */
const fs = require('fs');
const path = require('path');
const sharp = require('./node_modules/sharp');

const ROOT = path.resolve(__dirname, '..');
const PHOTOS = path.join(ROOT, 'storefront/public/assets/img/product');

/**
 * The widest the site can use.
 *
 * The same 1400 `make-hero-photos.js` chose, for the same reason: a 2x screen
 * drawing the largest box this shop has for a photograph. `make-photo-sizes.js`
 * then offers a 700 beside it for phones; these are not in that manifest yet,
 * so 1400 is what a phone gets yet.
 */
const WIDTH = 1400;

/** Below this a file is already the right size and is left alone. */
const FLOOR = WIDTH * 1.05;

async function main() {
  let was = 0;
  let now = 0;
  let cut = 0;

  for (const set of fs.readdirSync(PHOTOS).sort()) {
    const dir = path.join(PHOTOS, set);
    if (!fs.statSync(dir).isDirectory()) continue;

    for (const name of fs.readdirSync(dir).sort()) {
      if (!/\.jpe?g$/i.test(name)) continue;

      const file = path.join(dir, name);
      const before = fs.statSync(file).size;
      const meta = await sharp(file).metadata();

      if (meta.width < FLOOR) continue;

      // withoutEnlargement as well as the floor above: two ways of saying the
      // same thing, and the one that survives somebody changing WIDTH.
      const buffer = await sharp(file)
        .resize({ width: WIDTH, withoutEnlargement: true })
        .jpeg({ quality: 88, mozjpeg: true })
        .toBuffer();

      fs.writeFileSync(file, buffer);

      was += before;
      now += buffer.length;
      cut++;

      console.log(
        `  ${(set + '/' + name).padEnd(34)} ${String(Math.round(before / 1024)).padStart(4)}KB @${meta.width}` +
        ` -> ${String(Math.round(buffer.length / 1024)).padStart(3)}KB @${WIDTH}`,
      );
    }
  }

  if (cut === 0) {
    console.log('Every product photograph is already at or under ' + WIDTH + ' wide.');
    return;
  }

  console.log(`\n${cut} photographs: ${(was / 1024).toFixed(0)}KB is ${(now / 1024).toFixed(0)}KB at ${WIDTH} wide.`);
  console.log('The hashes in TheShopsOwnPhotographsTest name files by content — re-read them if a wired set was cut.');
}

main();
