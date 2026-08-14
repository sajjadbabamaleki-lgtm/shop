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

const ROOT = path.resolve(__dirname, '..');
const FROM = path.join(ROOT, 'download-version');
const TO = path.join(ROOT, 'storefront/public');
const PAGE = path.join(FROM, 'shoe-shop-rtl.html');

// Every way this page names a file. `data-mask-src` and `data-bg-src` are the
// template's lazy background attributes, read by main.js at runtime rather
// than by the browser. `content` is deliberately not here — most of it is meta
// prose; the one meta that names a file is picked up separately below.
const ATTRS = /(?:src|href|data-bg-src|data-mask-src|poster)\s*=\s*"([^"]+)"/g;
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
for (const file of collected) {
  const rel = path.relative(FROM, file);
  const dest = path.join(TO, rel);
  const src = fs.readFileSync(file);
  bytes += src.length;
  if (fs.existsSync(dest) && fs.readFileSync(dest).equals(src)) continue;
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.writeFileSync(dest, src);
  copied++;
  console.log('  +', rel);
}

console.log(
  `${collected.length} files reachable from the page (${(bytes / 1e6).toFixed(1)}MB), ${copied} written.`
);

// One file nothing links to and every browser asks for. `/favicon.ico` is
// requested before any markup has been read, and Laravel ships a zero-byte one
// at the public root — so the tab was blank on the first paint of every page,
// no matter what the <link> tags in the head said. It is not reachable by the
// crawl above precisely because it is a convention rather than a reference, so
// it is copied by name.
// It goes to the public root, where the browser looks, and to the icon set it
// belongs to, so the set on the server is the set on disk rather than the
// twenty of it that happen to be linked.
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
