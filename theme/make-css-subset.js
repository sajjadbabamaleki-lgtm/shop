#!/usr/bin/env node
/**
 * Cuts the three bought stylesheets down to the rules this shop can reach.
 *
 * Run: node theme/make-css-subset.js
 * Then: node theme/sync-storefront-assets.js
 *
 * ---------------------------------------------------------------------------
 * WHY
 *
 * The complaint is «سایت وحشتناک کند هستش», and the probe job in the deploy
 * workflow finally measured the live site rather than a local one. The server
 * compresses — gzip, HTTP/2, nothing in front — so the page's stylesheets
 * cross as 154KB rather than 1,047KB, and there is no server setting left to
 * find. But **every one of those bytes is render-blocking**: the browser paints
 * nothing until all of them have arrived, and this page holds the paint
 * deliberately besides (see «قالب قبلی» — the design gate). So the stylesheets
 * are the first thing a visitor waits for and the largest thing on that path.
 *
 * Measured across every page of the shop at four widths: of the base sheet's
 * 4,757 rules, **581 can ever match** — 81KB of 620KB. Of Bootstrap's 2,337,
 * 171 — 14KB of 190KB. The rest styles the ten other demos the bundle ships:
 * a coffee shop, a grocer, an electrician. `tweaks.css`, which is written by
 * hand for this shop, is 94% live, which is the control that says the method
 * is reading the right thing.
 *
 * This is the same cut `make-icon-fonts.js` already makes on the icon
 * stylesheet — 454KB to 12.5KB, verified pixel-identical — pointed at the
 * three sheets beside it.
 *
 * ---------------------------------------------------------------------------
 * THE RULE, AND WHY IT IS THE CONSERVATIVE ONE
 *
 * A selector is kept when **every class and id it names appears somewhere this
 * shop could produce it**: in a Blade template, in the generated preview page,
 * or in any script the page loads — jQuery, Swiper and GSAP included, because
 * a class this page never writes can still be *added* at runtime.
 * `.swiper-slide-active` is the example that matters: no template contains it,
 * Swiper writes it on every slide change, and a crawler that only looked at
 * rendered documents would have thrown its rules away.
 *
 * No stylesheet is a source — see `VOCABULARY_FILES` for what that cost when
 * one was.
 *
 * Asking "does this selector match a page right now" is the wrong question for
 * the same reason. It cannot see a menu that is closed, a modal that is shut,
 * a slide that is not the active one, or an element the shop only draws when
 * something is out of stock. Asking "could this class ever exist here" can.
 *
 * Two more deliberate leanings toward keeping:
 *
 *   - A selector naming **no** class or id at all — `body`, `a:hover`,
 *     `input[type=text]`, `:root` — is always kept. There is nothing to check
 *     and everything to lose.
 *   - `:not()`, `:is()`, `:where()` and `:has()` have their contents ignored
 *     rather than required. `a:not(.foo)` matches when `.foo` is *absent*, so
 *     requiring `.foo` to exist would drop a rule that does match.
 *
 * Dead selectors are dropped from inside a rule's selector list as well as
 * whole rules, which is most of the saving on a minified sheet where one rule
 * carries forty of them.
 *
 * ---------------------------------------------------------------------------
 * SAFETY
 *
 *   - **`theme/base-stylesheets/*.full.*` is the source, always.** The subset
 *     is cut from it every time, never from the last subset, so running this
 *     twice cannot narrow a sheet twice. The first run puts the current file
 *     there; `FULL_DIR` says why that directory and not this one.
 *   - `node theme/check-css-subset.js` renders every page at four widths, and
 *     the drawer, the search and the basket opened, against both the full
 *     sheets and the cut ones, and fails on a pixel above the page's own
 *     noise. **That is the real guard, and this script does not replace it.**
 *     It has already caught two faults this script had.
 *   - `CssSubsetTest` is the CI half: `subset.json` records the fingerprint of
 *     the vocabulary this cut was made from, and the test rebuilds it — so
 *     adding a class to a template and not re-running this fails the build.
 *   - `node theme/check-parity.js` must still print zero afterwards.
 */
const fs = require('fs');
const path = require('path');
const postcss = require('./node_modules/postcss');

const ROOT = path.resolve(__dirname, '..');
const CSS = path.join(ROOT, 'download-version/assets/css');

/**
 * Where the untouched originals live, and why it is not beside the files they
 * are cut from.
 *
 * `make-icon-fonts.js` keeps `fontawesome.full.min.css` next to the sheet it
 * cuts, and this deliberately does not follow it. `download-version/` is what
 * Netlify publishes, and **the base sheet's first comment names where the base
 * layer was bought** — the one thing CLAUDE.md says may never appear in
 * anything a person outside this repository reads. The published copy of
 * `style.rtl.css` has always had its comments taken out on the way; an
 * original sitting beside it would be published whole, and would stay
 * published until somebody remembered to add its name to the strip command in
 * `netlify.toml`. `theme/` is not published, so nothing has to be remembered.
 */
const FULL_DIR = path.join(ROOT, 'theme/base-stylesheets');
const VIEWS = path.join(ROOT, 'storefront/resources/views');
const JS = path.join(ROOT, 'download-version/assets/js');

/** live file => the untouched copy it is always cut from. */
const SHEETS = {
  'style.rtl.css': 'style.rtl.full.css',
  'bootstrap.rtl.min.css': 'bootstrap.rtl.full.min.css',
  'swiper-bundle.rtl.min.css': 'swiper-bundle.rtl.full.min.css',
};

/**
 * Everything that can put a class on an element of this shop: markup, and the
 * scripts that write markup.
 *
 * **No stylesheet is in this list, and that is the whole of getting the cut
 * right.** A stylesheet is not markup — it says what a class would look like
 * if it existed, never that it does. Reading `tweaks.css` here kept 2,483 of
 * the base sheet's rules instead of 581, and the ones it kept were
 * `.process-box`, `.team-block`, `.project-block`, `.cursor-follower`: demo
 * furniture that appears in no template, and whose names are in `tweaks.css`
 * precisely because that file is where the template's demo sections are turned
 * *off*. Every class this shop really draws is in a Blade file or in the
 * preview page, because that is where markup lives.
 */
const VOCABULARY_FILES = [
  path.join(ROOT, 'download-version/shoe-shop-rtl.html'),
  path.join(JS, 'main.js'),
  path.join(JS, 'swiper-bundle.min.js'),
  path.join(JS, 'gsap.min.js'),
  path.join(JS, 'vendor/jquery-3.7.1.min.js'),
];

function everyBladeFile(dir, out = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) everyBladeFile(p, out);
    else if (e.name.endsWith('.blade.php')) out.push(p);
  }
  return out;
}

/**
 * Every word any of those files contains, which is the set a name must be in.
 *
 * Each file is read **whole** rather than for its `class` attributes, on
 * purpose: `@php($cls = 'th-btn style3')` puts a class on an element without
 * ever writing it inside a `class=`, and a minified library's class names live
 * in its string literals. Reading everything costs precision and buys safety,
 * and precision is not the thing to spend safety on here.
 */
function vocabulary() {
  const words = new Set();
  const files = [...VOCABULARY_FILES, ...everyBladeFile(VIEWS)].filter((f) => fs.existsSync(f)).sort();

  for (const file of files) {
    const text = fs.readFileSync(file, 'utf8');
    for (const [word] of text.matchAll(WORD)) words.add(word);
  }

  return { words, files };
}

/**
 * What a name can be made of, written once because `CssSubsetTest` has to
 * read the same thing in PHP and a difference between the two would be a guard
 * that quietly guards nothing.
 */
const WORD = /[A-Za-z_][A-Za-z0-9_-]+/g;

/**
 * The fingerprint of the vocabulary, which is what the manifest records.
 *
 * **Not a fingerprint of the files.** Persian copy changes in a Blade template
 * every other day and none of it can affect a stylesheet; hashing the files
 * would fail the build on a comma. The vocabulary is the only thing the cut
 * depends on, so a new class changes this and a reworded sentence does not.
 */
function fingerprint(words) {
  return require('crypto').createHash('sha256').update([...words].sort().join('\n')).digest('hex');
}

/**
 * Prefixes a library glues onto a name it builds at runtime.
 *
 * Swiper composes every state class as `containerModifierClass + suffix`, so
 * what its bundle contains as a string is `backface-hidden` and what lands on
 * the element is `swiper-backface-hidden`. Nothing that reads files can join
 * those two halves, and the rule this cost —
 * `.swiper-backface-hidden .swiper-slide { backface-visibility: hidden }` —
 * was dropped on the first run and caught by `check-css-subset.js`.
 *
 * So a name also passes if one of these prefixes is stripped off it and what
 * remains is in the vocabulary. It stays a list of two, and it stays explicit:
 * allowing *any* leading segment to be stripped would let `.process-box` in on
 * the strength of the word "box", which is in half the files here.
 */
const RUNTIME_PREFIXES = ['swiper-', 'th-'];

/**
 * The class and id names a selector *requires*.
 *
 * The functional pseudo-classes go first, contents and all: what is inside
 * them either inverts the test or widens it, and in both cases requiring the
 * names would be wrong in the unsafe direction.
 */
function namesRequiredBy(selector) {
  const bare = selector.replace(/:(?:not|is|where|has|matches|any)\([^()]*(?:\([^()]*\)[^()]*)*\)/g, ' ');

  return [...bare.matchAll(/[.#](-?[A-Za-z_][A-Za-z0-9_-]*)/g)].map((m) => m[1]);
}

/** Is this a name the shop can put on an element? */
function known(name, words) {
  if (words.has(name)) return true;

  return RUNTIME_PREFIXES.some((p) => name.startsWith(p) && words.has(name.slice(p.length)));
}

/** The copy this sheet is always cut from; created from the live file once. */
function fullSheet(live, full) {
  const livePath = path.join(CSS, live);
  const fullPath = path.join(FULL_DIR, full);

  if (!fs.existsSync(fullPath)) {
    if (!fs.existsSync(livePath)) return null;
    fs.mkdirSync(FULL_DIR, { recursive: true });
    fs.copyFileSync(livePath, fullPath);
    console.log(`  kept the original as theme/base-stylesheets/${full}`);
  }

  return fs.readFileSync(fullPath, 'utf8');
}

function subset(css, words) {
  const root = postcss.parse(css);

  // Pass one: the selectors.
  root.walkRules((rule) => {
    // A keyframe's "selectors" are 0% and `from`. They are not selectors.
    if (rule.parent && rule.parent.type === 'atrule' && /keyframes$/i.test(rule.parent.name)) return;

    const live = rule.selectors.filter((s) => namesRequiredBy(s).every((n) => known(n, words)));

    if (live.length === 0) rule.remove();
    else if (live.length !== rule.selectors.length) rule.selectors = live;
  });

  // Pass two: the animations nothing asks for any more. Read after the
  // selectors, because a @keyframes is alive exactly when a surviving
  // declaration names it.
  //
  // **Every declaration, not just `animation`.** This page names its
  // animations through a custom property — `--animation-name: slideinrighthero`
  // on the active slide, read by a shorthand somewhere else entirely — so
  // reading only `animation` and `animation-name` threw away the keyframes
  // that move the hero's photograph and its price badge onto the slide, and
  // left them at the `opacity: 0` the template starts them on. The hero was
  // blank. Values are cheap to read and keyframes are small; read them all.
  const asked = new Set();
  root.walkDecls((decl) => {
    for (const [word] of decl.value.matchAll(/[A-Za-z_][A-Za-z0-9_-]*/g)) asked.add(word);
  });
  root.walkAtRules(/keyframes$/i, (at) => {
    if (!asked.has(at.params.trim())) at.remove();
  });

  // Whatever is left holding nothing. Repeatedly, because emptying a @media
  // inside a @supports empties the @supports.
  for (let pass = 0; pass < 4; pass++) {
    let removed = 0;
    root.walkAtRules((at) => {
      if (/^(font-face|import|charset|namespace|page|property|counter-style|font-feature-values)$/i.test(at.name)) return;
      if (at.nodes && at.nodes.length === 0) { at.remove(); removed++; }
    });
    if (!removed) break;
  }

  // The comments go with them. `sync-storefront-assets.js` strips these on the
  // way to the browser anyway; taking them out here means the file in the
  // repository is the file that is served, and the base sheet's own header —
  // the one thing CLAUDE.md says may never appear in anything a person outside
  // this repository reads — is not in the shipped copy at all.
  root.walkComments((c) => c.remove());

  return root.toString();
}

const kb = (n) => `${Math.round(n / 1024)}KB`;

const { words, files } = vocabulary();
console.log(`vocabulary: ${words.size} words from ${files.length} files\n`);

let before = 0;
let after = 0;
const record = {};

for (const [live, full] of Object.entries(SHEETS)) {
  const css = fullSheet(live, full);
  if (css === null) {
    console.error(`  ! ${live} is not here and neither is ${full} — nothing written.`);
    process.exitCode = 1;
    continue;
  }

  const cut = subset(css, words);

  // A sheet that came out empty, or barely smaller, means the parse or the
  // vocabulary went wrong; either way writing it would be worse than not.
  if (cut.length < 200 || cut.length > css.length) {
    console.error(`  ! ${live} came out at ${cut.length} bytes from ${css.length} — refusing to write it.`);
    process.exitCode = 1;
    continue;
  }

  fs.writeFileSync(path.join(CSS, live), cut);
  before += css.length;
  after += cut.length;
  record[live] = { from: full, was: css.length, is: cut.length };
  console.log(`  ${live.padEnd(30)} ${kb(css.length).padStart(6)} -> ${kb(cut.length).padStart(6)}`);
}

/**
 * What was cut and what it was cut against.
 *
 * `CssSubsetTest` reads this, rebuilds the vocabulary from the same file list
 * in PHP, and fails when the fingerprint no longer matches — which is what
 * happens the moment somebody writes a class into a template and does not
 * re-run this script. That is the failure this whole arrangement exists to
 * make loud: a cut stylesheet does not throw, it just stops styling something.
 */
fs.writeFileSync(path.join(FULL_DIR, 'subset.json'), `${JSON.stringify({
  note: 'Written by theme/make-css-subset.js. Do not edit — re-run the script.',
  vocabulary: fingerprint(words),
  words: words.size,
  read: files.map((f) => path.relative(ROOT, f)),
  sheets: record,
}, null, 2)}\n`);

console.log(`\n  ${'total'.padEnd(30)} ${kb(before).padStart(6)} -> ${kb(after).padStart(6)}`);
console.log('\nNow: node theme/check-css-subset.js   (pixels, against the full sheets)');
console.log('     node theme/sync-storefront-assets.js');
