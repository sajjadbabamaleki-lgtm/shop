#!/usr/bin/env node
/**
 * Copies the assets the home page actually uses into the Laravel app's
 * public/ directory.
 *
 * `download-version/assets` is the whole ThemeForest bundle — 40MB across
 * every demo the template ships. The storefront needs the slice the RTL home
 * page reaches, which is a fraction of that, so this walks the dependency
 * graph rather than copying the tree:
 *
 *   the page's src/href/data-*-src/url() references
 *     → each stylesheet's own url() and @import
 *       → the fonts and images those name
 *
 * Run: node theme/sync-storefront-assets.js
 *
 * It is re-runnable and prints what it added, so it can be used again while
 * the preview page is still where design decisions get made. Once the Blade
 * views are the source of truth this can go, and public/assets becomes the
 * only copy.
 */
const fs = require('fs');
const path = require('path');
const { strip, verifyGate } = require('./strip-css-comments');

/**
 * Every stylesheet's comments are left in the source and kept out of the wire.
 *
 * `tweaks.css` is 776KB of which 551KB is the reasoning above each rule —
 * written for whoever edits it next, downloaded by every visitor on the one
 * file the whole appearance depends on. The source keeps every word; this is
 * the copy the app serves.
 *
 * **This used to be ours only**, on the reasoning that a third party's
 * comments are not ours to remove and some of them are licences. The licence
 * half of that is real and is handled where it belongs: `strip()` keeps every
 * `/*!` block, which is the convention for a notice, so FontAwesome's and
 * Bootstrap's survive untouched. The rest was costing the visitor 28KB of the
 * base sheet's own table of contents — and one of those paragraphs is the
 * header naming where the base layer was bought, in a file this site hands to
 * anybody who opens it. That is the one thing CLAUDE.md says may never appear
 * in anything a person outside this repository reads.
 */
const STRIP_COMMENTS = (rel) => rel.endsWith('.css');

const ROOT = path.resolve(__dirname, '..');
const FROM = path.join(ROOT, 'download-version');
const TO = path.join(ROOT, 'storefront/public');
const PAGE = path.join(FROM, 'shoe-shop-rtl.html');

// Every way this page names a file. `data-mask-src` and `data-bg-src` are the
// template's lazy background attributes, read by main.js at runtime rather
// than by the browser. `content` is deliberately not here — most of it is meta
// prose; the one meta that names a file is picked up separately below.
const ATTRS = /(?:src|href|data-bg-src|data-mask-src|poster)\s*=\s*"([^"]+)"/g;
// `srcset` names several files in one attribute, each followed by its width:
// `a-700.webp 700w, a.webp 1400w`. Without this the small copies of the
// photographs are the one thing on the page that never gets copied.
const SRCSET = /srcset\s*=\s*"([^"]+)"/g;
const TILE = /<meta[^>]+msapplication-TileImage[^>]+content\s*=\s*"([^"]+)"/gi;
const URLS = /url\(\s*['"]?([^'")]+)['"]?\s*\)/g;
const IMPORTS = /@import\s+(?:url\()?\s*['"]([^'"]+)['"]/g;

/** Everything an HTML or CSS file points at, as paths relative to `dir`. */
function refsIn(text, patterns) {
  const found = new Set();
  for (const re of patterns) {
    for (const [, ref] of text.matchAll(re)) found.add(ref);
  }
  return found;
}

/**
 * Keep only same-origin references to files we ship. The template's markup is
 * full of links to its other demo pages; those are not assets and the port
 * rewrites them separately.
 */
function isLocalFile(ref) {
  if (/^(?:[a-z]+:|\/\/|#|\?)/i.test(ref) || ref.trim() === '') return false;
  return !/\.html?(?:[#?]|$)/i.test(ref);
}

const queue = [];
const seen = new Set();

/** Resolve `ref` against the file that named it and queue it if it exists. */
function enqueue(ref, fromFile) {
  if (!isLocalFile(ref)) return;
  const clean = ref.split('#')[0].split('?')[0];
  const abs = path.resolve(path.dirname(fromFile), clean);
  // Refuse anything that resolves outside the bundle.
  if (!abs.startsWith(FROM + path.sep)) return;
  const rel = path.relative(FROM, abs);
  if (seen.has(rel)) return;
  seen.add(rel);
  if (!fs.existsSync(abs) || !fs.statSync(abs).isFile()) {
    missing.push({ rel, by: path.relative(FROM, fromFile) });
    return;
  }
  queue.push(abs);
}

const missing = [];

const page = fs.readFileSync(PAGE, 'utf8');
for (const ref of refsIn(page, [ATTRS, URLS, TILE])) enqueue(ref, PAGE);
for (const list of refsIn(page, [SRCSET])) {
  for (const candidate of list.split(',')) enqueue(candidate.trim().split(/\s+/)[0], PAGE);
}

// The manifest names the icons it lists, and nothing in the markup does.
/**
 * A stylesheet that does not parse the way it reads, refused before it ships.
 *
 * `tweaks.css` is two thirds prose: every deviation from the template carries
 * the reasoning above it, and a round of edits usually means editing one of
 * those comments. Twice now an edit has left a `*​/` in the middle of a block —
 * the rest of the paragraph then sits *outside* the comment as garbage, and a
 * browser recovering from garbage throws away the declaration block that
 * follows it. Silently. Both times the rule that vanished was the one the
 * round was about, and both times it was found by measuring the page and
 * disbelieving the number.
 *
 * So the sync will not copy a stylesheet with a stray closer or unbalanced
 * braces. It is the gate between the source stylesheet and the app, it already
 * reads every CSS file it copies, and there is no point shipping a file whose
 * rules the browser is going to drop.
 *
 * @param {string} css
 * @param {string} file
 */
function refuseIfUnparseable(css, file) {
  const faults = [];
  let inComment = false;
  let braces = 0;
  let line = 1;

  for (let i = 0; i < css.length; i++) {
    if (css[i] === '\n') line++;

    if (!inComment && css[i] === '/' && css[i + 1] === '*') {
      inComment = true;
      i++;
    } else if (inComment && css[i] === '*' && css[i + 1] === '/') {
      inComment = false;
      i++;
    } else if (!inComment && css[i] === '*' && css[i + 1] === '/') {
      faults.push(`line ${line}: a */ with no comment open — the rule after it is dropped`);
      i++;
    } else if (!inComment) {
      if (css[i] === '{') braces++;
      if (css[i] === '}') braces--;
    }
  }

  if (inComment) faults.push('the file ends inside a comment');
  if (braces !== 0) faults.push(`braces are ${braces > 0 ? braces + ' short of closing' : -braces + ' too many'}`);

  if (faults.length) {
    console.error(`\n${path.relative(ROOT, file)} would not parse as written:`);
    for (const fault of faults) console.error('  -', fault);
    console.error('\nNothing copied. Fix the stylesheet and run this again.\n');
    process.exit(1);
  }
}

const collected = [];
while (queue.length) {
  const file = queue.shift();
  collected.push(file);
  const ext = path.extname(file).toLowerCase();
  if (ext === '.css') {
    const css = fs.readFileSync(file, 'utf8');
    refuseIfUnparseable(css, file);
    for (const ref of refsIn(css, [URLS, IMPORTS])) enqueue(ref, file);
  } else if (path.basename(file) === 'manifest.json') {
    const manifest = JSON.parse(fs.readFileSync(file, 'utf8'));
    for (const icon of manifest.icons || []) enqueue(icon.src, file);
  }
}

let copied = 0;
let bytes = 0;
let saved = 0;
for (const file of collected) {
  const rel = path.relative(FROM, file);
  const dest = path.join(TO, rel);
  let src = fs.readFileSync(file);
  bytes += src.length;

  if (STRIP_COMMENTS(rel.split(path.sep).join('/'))) {
    const stripped = strip(src.toString('utf8'));
    // The gate's signature has to survive the trip, or the shop paints its
    // update notice instead of itself. Checked on the bytes that ship.
    if (rel.endsWith('tweaks.css')) verifyGate(stripped, rel);
    saved += src.length - Buffer.byteLength(stripped);
    src = Buffer.from(stripped);
  }

  if (fs.existsSync(dest) && fs.readFileSync(dest).equals(src)) continue;
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.writeFileSync(dest, src);
  copied++;
  console.log('  +', rel);
}

console.log(
  `${collected.length} files reachable from the page (${(bytes / 1e6).toFixed(1)}MB), ${copied} written.`
);
if (saved) console.log(`  ${(saved / 1024).toFixed(0)}KB of comment left in the source, out of what the browser downloads.`);

// One file nothing links to and every browser asks for. `/favicon.ico` is
// requested before any markup has been read, and Laravel ships a zero-byte one
// at the public root — so the tab was blank on the first paint of every page,
// no matter what the <link> tags in the head said. It is not reachable by the
// crawl above precisely because it is a convention rather than a reference, so
// it is copied by name.
// It goes to the public root, where the browser looks, and to the icon set it
// belongs to, so the set on the server is the set on disk rather than the
// twenty of it that happen to be linked.
// The photograph manifest, for the same reason: nothing links to it. It is
// what `photo_srcset()` reads to offer a phone the 700-wide copy of a picture,
// and only `storefront/` is deployed — a manifest left in `download-version/`
// would be present in every test and absent on the server.
{
  const rel = 'assets/img/photo-sizes.json';
  const src = fs.readFileSync(path.join(FROM, rel));
  const dest = path.join(TO, rel);
  if (!fs.existsSync(dest) || !fs.readFileSync(dest).equals(src)) {
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.writeFileSync(dest, src);
    console.log('  +', rel, ' (read by photo_srcset(), named by no page)');
  }
}

// **Every photograph `config/storefront.php` names, for the third time the
// same reason.** The crawl above follows the *page*, and these are chosen in a
// config file: `hero.photos` and `placeholders.best_sellers.photos` are the
// cut-outs a slide or a tile draws instead of the product's own catalogue
// shot, keyed by a slug that exists only in the live shop. Nothing in either
// copy of the home page references them here, so the crawl cannot see them —
// and only `storefront/` is deployed, so the file would be present in this
// repository and a broken image on the site. That failure is silent in every
// direction: the tests pass, `check-parity.js` prints zero (neither page draws
// it), and the first symptom is the client photographing a grey box.
//
// The phone-sized sibling goes with it, read off the manifest rather than
// guessed at, so a picture the crawl never saw is still offered at two sizes.
{
  const config = fs.readFileSync(path.resolve(__dirname, '../storefront/config/storefront.php'), 'utf8');
  const manifest = JSON.parse(fs.readFileSync(path.join(FROM, 'assets/img/photo-sizes.json'), 'utf8')).photos;
  const named = new Set();

  for (const [, rel] of config.matchAll(/'(assets\/img\/[^']+\.(?:webp|png|jpe?g|svg))'/g)) {
    named.add(rel);
    if (manifest[rel]?.small) named.add(manifest[rel].small);
  }

  for (const rel of named) {
    const from = path.join(FROM, rel);
    if (!fs.existsSync(from)) {
      // Some of these never passed through the preview at all — the story
      // strip's photographs were made straight into the app, because the
      // preview has no catalogue to open a story on. A file already sitting
      // where it is served is not missing; one that is in neither place is.
      if (!fs.existsSync(path.join(TO, rel))) {
        missing.push({ rel, by: 'storefront/config/storefront.php' });
      }
      continue;
    }
    const src = fs.readFileSync(from);
    const dest = path.join(TO, rel);
    if (fs.existsSync(dest) && fs.readFileSync(dest).equals(src)) continue;
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.writeFileSync(dest, src);
    console.log('  +', rel, ' (named in config/storefront.php, by no page)');
  }
}

const ico = fs.readFileSync(path.join(FROM, 'assets/img/favicons/favicon.ico'));
for (const rel of ['favicon.ico', 'assets/img/favicons/favicon.ico']) {
  const dest = path.join(TO, rel);
  if (fs.existsSync(dest) && fs.readFileSync(dest).equals(ico)) continue;
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.writeFileSync(dest, ico);
  console.log('  +', rel, ' (asked for by convention, so no link names it)');
}
if (missing.length) {
  console.log(`\n${missing.length} reference(s) point at files that are not in the bundle:`);
  for (const { rel, by } of missing) console.log(`  ? ${rel}  (named by ${by})`);
}
