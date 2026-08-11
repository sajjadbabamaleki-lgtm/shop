/*
 * The favicons, from the mark the client supplied.
 *
 * Every icon in assets/img/favicons is still the template's — the browser tab,
 * the phone home screen and the Windows tile all say ERNA on a site that does
 * not. The app icon is a square PNG with its own ground, so each size is a
 * straight resize: no crop, no padding, no cut-out, the same rule the category
 * photographs follow.
 */
const fs = require('fs');
const path = require('path');
const sharp = require('/home/user/shop/theme/node_modules/sharp');

const SOURCE = 'download-version/assets/img/vikyplus-appicon.png';
const DIR = 'download-version/assets/img/favicons';

(async () => {
  const files = fs.readdirSync(DIR).filter((f) => f.endsWith('.png'));
  let written = 0;

  for (const file of files) {
    const match = file.match(/(\d+)x(\d+)/);
    // apple-icon.png and apple-icon-precomposed.png carry no size in the name;
    // they are 180 by convention.
    const size = match ? Number(match[1]) : 180;

    await sharp(SOURCE).resize(size, size, { fit: 'cover' }).png().toFile(path.join(DIR, file + '.tmp'));
    fs.renameSync(path.join(DIR, file + '.tmp'), path.join(DIR, file));
    written++;
  }

  // favicon.ico is what a browser asks for before it has read any markup, so
  // it is the one icon that cannot be left as somebody else's. An ICO may
  // carry a PNG whole — six bytes of header, sixteen of directory, then the
  // image — which is the only reason this does not need an encoder.
  const png = await sharp(SOURCE).resize(32, 32).png().toBuffer();

  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0);          // reserved
  header.writeUInt16LE(1, 2);          // 1 = icon
  header.writeUInt16LE(1, 4);          // one image in the file

  const entry = Buffer.alloc(16);
  entry.writeUInt8(32, 0);             // width
  entry.writeUInt8(32, 1);             // height
  entry.writeUInt8(0, 2);              // colours in the palette: none, it is true colour
  entry.writeUInt8(0, 3);              // reserved
  entry.writeUInt16LE(1, 4);           // colour planes
  entry.writeUInt16LE(32, 6);          // bits per pixel
  entry.writeUInt32LE(png.length, 8);
  entry.writeUInt32LE(header.length + entry.length, 12);

  fs.writeFileSync(path.join(DIR, 'favicon.ico'), Buffer.concat([header, entry, png]));
  written++;

  // The two manifests that come with a favicon set and that nobody ever opens.
  // The template's said `"name": "App"`, and pointed every icon at the domain
  // root — `/android-icon-36x36.png`, which is not where any of them are. So a
  // phone adding this to a home screen got the shop's name wrong and no icon at
  // all. Paths are relative here, which resolves against the manifest's own URL
  // and therefore stays correct wherever the site is mounted.
  const androidSizes = [36, 48, 72, 96, 144, 192];

  fs.writeFileSync(path.join(DIR, 'manifest.json'), JSON.stringify({
    name: 'ویکی پلاس',
    short_name: 'ویکی پلاس',
    dir: 'rtl',
    lang: 'fa-IR',
    display: 'standalone',
    background_color: '#FFFFFF',
    theme_color: '#FFFFFF',
    icons: androidSizes.map((size) => ({
      src: `android-icon-${size}x${size}.png`,
      sizes: `${size}x${size}`,
      type: 'image/png',
      // Android's own baseline is mdpi, 48px — the density is the icon
      // measured against it.
      density: String(size / 48),
    })),
  }, null, 2) + '\n');

  fs.writeFileSync(path.join(DIR, 'browserconfig.xml'),
    '<?xml version="1.0" encoding="utf-8"?>\n' +
    '<browserconfig><msapplication><tile>' +
    '<square70x70logo src="ms-icon-70x70.png"/>' +
    '<square150x150logo src="ms-icon-150x150.png"/>' +
    '<square310x310logo src="ms-icon-310x310.png"/>' +
    // The mark's own ground is #FEFEFE, so the tile behind it is white.
    '<TileColor>#ffffff</TileColor>' +
    '</tile></msapplication></browserconfig>\n');

  console.log(`${written} icons rewritten from ${path.basename(SOURCE)}, manifest.json and browserconfig.xml with them`);
})();
