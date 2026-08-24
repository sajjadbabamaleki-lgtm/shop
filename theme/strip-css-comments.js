#!/usr/bin/env node
/**
 * Takes the comments out of a stylesheet on its way to the browser.
 *
 * `tweaks.css` is 776KB and **551KB of it — 71% — is comment**: every
 * deviation carries its reasoning and its measurements above it, which is what
 * makes the file editable a month later. None of that is for a visitor, and a
 * visitor downloads all of it today, on the one file this site's whole
 * appearance depends on and the one file that cannot be served from cache
 * after a deploy (see the design gate).
 *
 * So the notes stay in the source and the browser gets the rules. The source
 * file in `download-version/` is never rewritten by this: `sync-storefront-assets.js`
 * strips on the way into `storefront/public`, and the Netlify build strips its
 * own checkout. Nothing in git loses a word.
 *
 * **Why this is written by hand rather than by a minifier.** A minifier also
 * renames, reorders and collapses, and this stylesheet is read by tests that
 * match on its text (`.vp-card-name {` followed by `font-size: 13.86px`) and by
 * the next person to open it. Removing comments is the only transformation
 * that provably cannot change what the browser paints; everything else in a
 * minifier is a change to the shipped rules and would have to be argued
 * separately.
 *
 * **The rules that make it safe:**
 *
 *  - A `/*` inside a string or a url() is not a comment. `content: "/*"` is
 *    legal CSS and eating it would break the declaration.
 *  - `/*!` is kept. That is the convention for a licence notice, and dropping
 *    somebody's licence to save bytes is not a saving.
 *  - A comment between two tokens becomes one space, never nothing: `a/*x* /b`
 *    is two selectors, `ab` is one.
 *  - The last declaration in the file has to stay the last declaration in the
 *    file — `--vp-design: ok` is what the design gate asks for, and a file that
 *    lost it paints nothing at all. Comments are not declarations, so removing
 *    them cannot move it; `verifyGate()` below checks anyway.
 *
 * Run on a file:  node theme/strip-css-comments.js <file> [--in-place]
 * Or require it:  const { strip } = require('./strip-css-comments');
 */

'use strict';

const fs = require('fs');
const path = require('path');

/**
 * @param {string} css
 * @returns {string} the same stylesheet with its comments gone
 */
function strip(css) {
  let out = '';
  let i = 0;
  let quote = null;   // the ' or " we are inside, if any
  let inUrl = false;  // inside an unquoted url(...)

  while (i < css.length) {
    const c = css[i];
    const next = css[i + 1];

    if (quote) {
      out += c;
      if (c === '\\') { out += css[i + 1] ?? ''; i += 2; continue; }
      if (c === quote) quote = null;
      i++;
      continue;
    }

    if (c === '"' || c === "'") { quote = c; out += c; i++; continue; }

    if (inUrl) {
      out += c;
      if (c === ')') inUrl = false;
      i++;
      continue;
    }

    if ((c === 'u' || c === 'U') && /^url\(/i.test(css.slice(i, i + 4))) {
      out += css.slice(i, i + 4);
      i += 4;
      // A quoted url() is handled by the quote branch above.
      if (css[i] !== '"' && css[i] !== "'") inUrl = true;
      continue;
    }

    if (c === '/' && next === '*') {
      const end = css.indexOf('*/', i + 2);
      const stop = end === -1 ? css.length : end + 2;

      // A licence notice stays.
      if (css[i + 2] === '!') { out += css.slice(i, stop); i = stop; continue; }

      // One space where the comment stood, so two tokens cannot fuse.
      out += ' ';
      i = stop;
      continue;
    }

    out += c;
    i++;
  }

  return tidy(out);
}

/**
 * The comments were most of the file's lines, so removing them leaves runs of
 * whitespace where paragraphs used to be. Whitespace between rules means
 * nothing to a browser and everything to anybody who opens the file, so: no
 * trailing spaces, no line that is only whitespace, and one blank line kept
 * where two or more used to be, which is where the sections were.
 */
function tidy(css) {
  return css
    .split('\n')
    .map((line) => line.replace(/[ \t]+$/, ''))
    .join('\n')
    .replace(/\n{3,}/g, '\n\n')
    .replace(/^\n+/, '')
    .replace(/\n+$/, '\n');
}

/**
 * The gate's own promise, checked on the file that is about to ship: the design
 * signs itself in the last declaration of the last rule, and the head refuses
 * to paint without it. Throws rather than returns, because a stylesheet that
 * fails this is one that leaves the shop showing its «سایت در حال
 * به‌روزرسانی است» notice to everybody.
 *
 * @param {string} css
 * @param {string} label
 */
function verifyGate(css, label) {
  const signature = css.lastIndexOf('--vp-design');
  if (signature === -1) {
    throw new Error(`${label}: the design signature (--vp-design) is not in the stylesheet.`);
  }
  const after = css.slice(signature);
  if (!/^--vp-design\s*:\s*ok\s*;?\s*\}\s*$/.test(after.trim())) {
    throw new Error(
      `${label}: --vp-design is no longer the last declaration in the file. ` +
      'The gate reads it to know the whole file arrived; anything after it is a rule the gate cannot vouch for.'
    );
  }
}

module.exports = { strip, verifyGate };

if (require.main === module) {
  const args = process.argv.slice(2);
  const inPlace = args.includes('--in-place');
  const files = args.filter((a) => !a.startsWith('--'));

  if (!files.length) {
    console.error('usage: node theme/strip-css-comments.js <file.css> [--in-place]');
    process.exit(1);
  }

  for (const file of files) {
    const css = fs.readFileSync(file, 'utf8');
    const stripped = strip(css);
    if (/tweaks\.css$/.test(file)) verifyGate(stripped, path.basename(file));

    if (inPlace) {
      fs.writeFileSync(file, stripped);
      const saved = ((1 - stripped.length / css.length) * 100).toFixed(0);
      console.log(
        `${file}: ${(css.length / 1024).toFixed(0)}KB → ${(stripped.length / 1024).toFixed(0)}KB (${saved}% was comment)`
      );
    } else {
      process.stdout.write(stripped);
    }
  }
}
