#!/usr/bin/env node
/**
 * Bundles the three theme variants into one self-contained preview page.
 *
 * The Artifact host enforces a strict CSP: no external stylesheets, scripts,
 * fonts or images. Everything therefore has to travel inside the file, which
 * is the whole reason this script exists rather than a plain copy.
 *
 * Weight is the binding constraint, so three things are done about it:
 *   - only the Font Awesome weights the page actually uses are embedded, and
 *     the four `fal` icons are remapped onto `far` to avoid a 380KB face
 *   - the handful of real photographs are re-encoded as WebP; the rest of the
 *     imagery is placeholder art that is already tiny
 *   - the preloader GIF is dropped, since a preview has nothing to preload
 *
 * Run: node theme/build-preview.js
 */
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const ROOT = path.resolve(__dirname, '..');
const SITE = path.join(ROOT, 'download-version');
const OUT = path.join(ROOT, 'theme/preview.html');

const THEMES = [
  { id: 'minimal', label: 'مینیمال', note: 'گوشه تیز · بدون سایه · فاصله باز' },
  { id: 'boutique', label: 'بوتیک', note: 'زمینه کرم · گوشه نرم · سایه ملایم' },
  { id: 'contrast', label: 'کنتراست', note: 'کادر مشکی · تایپ درشت · فشرده' },
];

const read = (p) => fs.readFileSync(path.join(SITE, p), 'utf8');
const bytes = (p) => fs.readFileSync(path.join(SITE, p));
const mime = (f) =>
  ({ '.woff2': 'font/woff2', '.woff': 'font/woff', '.ttf': 'font/ttf',
     '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
     '.gif': 'image/gif', '.svg': 'image/svg+xml', '.webp': 'image/webp' })[path.extname(f).toLowerCase()]
  || 'application/octet-stream';

const dataUri = (buf, type) => `data:${type};base64,${buf.toString('base64')}`;

/** Resolves url(...) references inside a stylesheet to data URIs. */
function inlineCssUrls(css, cssDir, { skipFonts = [] } = {}) {
  return css.replace(/url\((['"]?)([^'")]+)\1\)/g, (whole, _q, ref) => {
        if (/^(data:|https?:|#)/.test(ref)) return whole;
    const clean = ref.split('?')[0].split('#')[0];
    const abs = path.resolve(path.join(SITE, cssDir), clean);

    if (skipFonts.some((name) => abs.includes(name))) return 'url()';
    if (!fs.existsSync(abs)) return 'url()';
    // Only woff2 is embedded; the ttf/woff fallbacks would double the weight
    // for browsers that have not needed them in years.
    if (/\.(ttf|woff|eot)$/i.test(abs)) return 'url()';

    return `url(${dataUri(fs.readFileSync(abs), mime(abs))})`;
  });
}

(async () => {
  let html = read('shoe-shop-minimal.html');

  // ---- stylesheets -------------------------------------------------------
  // Font Awesome ships every weight; the page uses far and fab only once fal
  // is remapped, so the rest are refused at inline time.
  const UNUSED_FA = ['fa-thin-100', 'fa-duotone-900', 'fa-light-300', 'fa-solid-900'];

  const base = ['bootstrap.rtl.min.css', 'fontawesome.min.css', 'magnific-popup.rtl.min.css',
                'swiper-bundle.rtl.min.css', 'style.rtl.css']
    .map((f) => inlineCssUrls(read(`assets/css/${f}`), 'assets/css', { skipFonts: UNUSED_FA }))
    .join('\n');

  const fixes = inlineCssUrls(read('assets/css/rtl-fixes.css'), 'assets/css');

  const themeBlocks = THEMES.map(({ id }) => {
    const css = inlineCssUrls(read(`assets/css/theme-${id}.css`), 'assets/css');
    return `<style data-theme="${id}">\n${css}\n</style>`;
  }).join('\n');

  // ---- scripts -----------------------------------------------------------
  const scripts = [...html.matchAll(/<script src="([^"]+)"><\/script>/g)].map((m) => m[1]);
  const js = scripts.map((s) => read(s)).join('\n;\n');

  // ---- images ------------------------------------------------------------
  const refs = [...new Set([
    ...[...html.matchAll(/src="(assets\/img\/[^"]+)"/g)].map((m) => m[1]),
    ...[...html.matchAll(/url\((assets\/img\/[^)]+)\)/g)].map((m) => m[1]),
  ])];

  const map = new Map();
  let saved = 0;
  for (const ref of refs) {
    const abs = path.join(SITE, ref);
    if (!fs.existsSync(abs)) continue;

    // A preview has nothing to preload.
    if (ref.includes('shopping-loader')) { map.set(ref, ''); continue; }

    const raw = fs.readFileSync(abs);
    if (raw.length > 40000 && /\.(png|jpe?g)$/i.test(ref)) {
      const webp = await sharp(raw).resize({ width: 900, withoutEnlargement: true })
        .webp({ quality: 72 }).toBuffer();
      saved += raw.length - webp.length;
      map.set(ref, dataUri(webp, 'image/webp'));
    } else {
      map.set(ref, dataUri(raw, mime(ref)));
    }
  }

  // ---- assemble ----------------------------------------------------------
  // The Artifact wrapper owns <html>/<head>/<body>, so strip the template's
  // own document shell and keep what lived inside body.
  html = html.replace(/[\s\S]*?<body[^>]*>/i, '').replace(/<\/body>[\s\S]*$/i, '');
  html = html.replace(/<script src="[^"]+"><\/script>/g, '');
  html = html.replace(/<link[^>]*>/gi, '');

  // fal is remapped so its 380KB face never has to ship.
  html = html.replace(/class="fal /g, 'class="far ');

  for (const [ref, uri] of map) {
    html = html.split(`"${ref}"`).join(`"${uri}"`).split(`(${ref})`).join(`(${uri})`);
  }

  const page = `<title>ویکی‌پلاس — پیش‌نمایش سه طرح</title>
<style>${base}</style>
${themeBlocks}
<style>${fixes}</style>
<style>${fs.readFileSync(path.join(__dirname, 'preview-chrome.css'), 'utf8')}</style>

${fs.readFileSync(path.join(__dirname, 'preview-chrome.html'), 'utf8')}

<div id="site">${html}</div>

<script>${js}</script>
<script>${fs.readFileSync(path.join(__dirname, 'preview-chrome.js'), 'utf8')}</script>
`;

  fs.writeFileSync(OUT, page);
  console.log(`preview.html  ${(page.length / 1048576).toFixed(2)}MB`);
  console.log(`  images re-encoded, saved ${(saved / 1024).toFixed(0)}KB`);
  console.log(`  scripts inlined: ${scripts.length}`);
})();
