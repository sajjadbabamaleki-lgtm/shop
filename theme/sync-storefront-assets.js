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
const collected = [];
while (queue.length) {
  const file = queue.shift();
  collected.push(file);
  const ext = path.extname(file).toLowerCase();
  if (ext === '.css') {
    const css = fs.readFileSync(file, 'utf8');
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
if (missing.length) {
  console.log(`\n${missing.length} reference(s) point at files that are not in the bundle:`);
  for (const { rel, by } of missing) console.log(`  ? ${rel}  (named by ${by})`);
}
