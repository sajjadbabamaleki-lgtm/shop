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

/*
 * **Two sources, and the difference matters.**
 *
 * `vikyplus-appicon.png` is 208px and is also the logo in the header, the
 * footer and the phone drawer, so it is left exactly as it is — changing it
 * changes the visible site. `vikyplus-appicon-1024.png` is the same mark at
 * the resolution the client later supplied, framed identically (the gem is
 * 59.62% of the canvas either way), and it is what every icon here is resized
 * from. Android wants a 512, and a 512 upscaled from 208 is a blurred 512.
 */
const SOURCE = 'download-version/assets/img/vikyplus-appicon-1024.png';
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
  const androidSizes = [36, 48, 72, 96, 144, 192, 512];

  // 512 is not decoration: Chrome will not treat a site as installable without
  // one, and without that it never offers «نصب برنامه» — it offers a bookmark,
  // or it tries to mint a throwaway APK that Google Play Protect then blocks
  // with «This app was built for an older version of Android». That is what the
  // client hit. It is written here rather than left to the loop below because
  // nothing in `DIR` was named android-icon-512x512.png to begin with.
  for (const size of [512]) {
    await sharp(SOURCE).resize(size, size, { fit: 'cover' }).png()
      .toFile(path.join(DIR, `android-icon-${size}x${size}.png`));
    written++;
  }

  fs.writeFileSync(path.join(DIR, 'manifest.json'), JSON.stringify({
    name: 'ویکی پلاس',
    short_name: 'ویکی پلاس',
    dir: 'rtl',
    lang: 'fa-IR',
    // `start_url` was missing altogether, which alone is enough for Chrome to
    // refuse to install. Root, not the manifest's own folder: the manifest
    // lives under assets/img/favicons and a relative start_url would open the
    // shop at its icon directory.
    start_url: '/',
    scope: '/',
    id: '/',
    display: 'standalone',
    orientation: 'portrait',
    background_color: '#FFFFFF',
    theme_color: '#FFFFFF',
    icons: [
      ...androidSizes.map((size) => ({
        src: `android-icon-${size}x${size}.png`,
        sizes: `${size}x${size}`,
        type: 'image/png',
        purpose: 'any',
        // Android's own baseline is mdpi, 48px — the density is the icon
        // measured against it.
        density: String(size / 48),
      })),
      // The same file again, declared maskable. Android crops a home-screen
      // icon to whatever shape the launcher uses and only guarantees the middle
      // 80%; the mark occupies 59.62% of the canvas and sits inside that, so
      // the artwork survives the crop and the white ground bleeds to the edge
      // instead of leaving the mark in a white box on a dark wallpaper.
      {
        src: 'android-icon-512x512.png',
        sizes: '512x512',
        type: 'image/png',
        purpose: 'maskable',
      },
    ],
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
